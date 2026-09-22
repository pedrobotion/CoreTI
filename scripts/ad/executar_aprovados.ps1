<#
.SYNOPSIS
    Busca no CoreTI as contas aprovadas para exclusao e as remove do Active
    Directory, sem nenhuma confirmacao adicional.

.DESCRIPTION
    Este e o unico script do conjunto que apaga contas.

    A aprovacao acontece uma unica vez, na tela do CoreTI. Aqui nao ha prompt,
    nao ha "digite EXCLUIR", nao ha ConfirmImpact: quem aprovou ja decidiu, e
    nao ha ninguem na frente deste servidor para responder de novo.

    Fluxo: pergunta ao CoreTI se ha lote aprovado -> reconfere cada conta
    localmente -> exclui -> devolve o resultado -> repete ate o lote acabar.
    Sem lote aprovado, encerra em silencio (o caso normal na maioria das
    execucoes).

    A lista vinda do CoreTI NAO e tratada como autorizacao suficiente. Toda
    conta passa de novo pela protecao local antes de ser apagada:

      - se uma conta protegida aparecer na lista aprovada, a execucao INTEIRA e
        abortada. Isso nao deveria acontecer nunca; se aconteceu, ou ha defeito
        na filtragem ou a lista foi adulterada, e nos dois casos o certo e
        parar, nao pular o item;
      - conta reativada no AD desde a aprovacao e pulada e reportada;
      - o CoreTI entrega em partes e este script pausa entre elas, para a
        replicacao entre os controladores acompanhar.

    Sem -Apply, apenas mostra o que faria.

.PARAMETER Apply
    Executa a exclusao. Sem este parametro, e simulacao.

.PARAMETER MaxCiclos
    Quantas partes buscar numa execucao. Protege contra laco infinito se o
    CoreTI devolver sempre a mesma lista por algum defeito.

.PARAMETER PausaSegundos
    Espera entre uma parte e a seguinte.

.EXAMPLE
    # Ver o que seria excluido
    .\executar_aprovados.ps1

.EXAMPLE
    # Uso da tarefa agendada
    .\executar_aprovados.ps1 -Apply
#>
# SupportsShouldProcess sem ConfirmImpact='High': mantem o -WhatIf, mas nao
# dispara prompt. Com ConfirmImpact='High' o ShouldProcess pediria confirmacao
# a cada conta - numa tarefa agendada nao ha console e o script travaria.
[CmdletBinding(SupportsShouldProcess = $true)]
param(
    [switch]$Apply,
    [string]$ConfigPath,
    [string]$Log,

    # Continua buscando partes ate o CoreTI nao devolver mais nada.
    [int]$MaxCiclos = 20,

    # Pausa entre partes, para a replicacao entre os controladores acompanhar.
    [int]$PausaSegundos = 30
)

$ErrorActionPreference = 'Stop'

if (-not (Get-Module -ListAvailable -Name ActiveDirectory)) {
    throw 'O modulo ActiveDirectory nao esta disponivel nesta maquina.'
}
Import-Module ActiveDirectory

$RaizScripts = $PSScriptRoot
if ([string]::IsNullOrWhiteSpace($RaizScripts) -and $MyInvocation.MyCommand.Path) {
    $RaizScripts = Split-Path -Parent $MyInvocation.MyCommand.Path
}
if ([string]::IsNullOrWhiteSpace($RaizScripts)) { $RaizScripts = (Get-Location).Path }

foreach ($m in @('CoretiAdProtecao.psm1', 'CoretiAdApi.psm1')) {
    $caminho = Join-Path $RaizScripts $m
    if (-not (Test-Path -LiteralPath $caminho)) {
        throw "Modulo nao encontrado: $caminho. Sem ele o expurgo nao roda."
    }
    Import-Module $caminho -Force
}

if (-not $Log) { $Log = Join-Path $RaizScripts 'expurgo_ad.log' }

function Escrever {
    param([string]$Texto, [string]$Cor = 'Gray')
    $linha = "[{0}] {1}" -f (Get-Date -Format 'yyyy-MM-dd HH:mm:ss'), $Texto
    Write-Host $linha -ForegroundColor $Cor
    Add-Content -LiteralPath $Log -Value $linha -Encoding UTF8
}

# Associacao de grupo do zero: quem virou privilegiado depois da aprovacao
# precisa ser barrado agora.
[void](Get-CoretiPrivilegiadosDN -Recarregar)

$reservados = Get-CoretiContasProtegidas
$ridMaximo = Get-CoretiRidMaximoReservado
$quem = "$env:USERDOMAIN\$env:USERNAME"

$totalExcluidas = 0
$totalPuladas = 0
$totalFalhas = 0
$ciclo = 0

while ($ciclo -lt $MaxCiclos) {
    $ciclo++

    $r = Invoke-CoretiApi -Rota 'api/ad/exclusao/aprovado' -Metodo GET -ConfigPath $ConfigPath

    if (-not $r.Sucesso) {
        Escrever "FALHA ao consultar o CoreTI (HTTP $($r.Status)): $($r.Dados)" 'Red'
        exit 1
    }

    if ($r.Status -eq 204 -or $null -eq $r.Dados -or $null -eq $r.Dados.contas) {
        if ($ciclo -eq 1) { Escrever "Nenhum lote aprovado. Nada a fazer." 'Green' }
        break
    }

    $referencia = [string]$r.Dados.referencia
    $contas = @($r.Dados.contas)

    if ($contas.Count -eq 0) { break }

    Escrever "Lote $referencia - parte $ciclo com $($contas.Count) contas (restam $($r.Dados.total_aprovados) aprovadas)." 'Cyan'

    # ------------------------------------------------------------ barreira
    # A aprovacao no CoreTI diz o que PODE ser excluido. Quem decide se uma
    # conta e protegida e o dominio, aqui.
    $bloqueadas = @()
    $prontas = [System.Collections.Generic.List[object]]::new()

    foreach ($c in $contas) {
        $sam = [string]$c.nome_usuario
        $dn = [string]$c.distinguished_name

        try {
            $conta = Get-ADUser -Identity $dn -Properties (Get-CoretiPropriedadesNecessarias) -ErrorAction Stop
        }
        catch {
            $prontas.Add([pscustomobject]@{ Sam = $sam; DN = $dn; Objeto = $null; Ausente = $true })
            continue
        }

        $motivo = Test-CoretiContaProtegida -Usuario $conta
        if ($motivo) { $bloqueadas += "$sam ($motivo)" }

        # Trava independente do modulo, para o caso de defeito no proprio modulo.
        if ($reservados -contains $sam) {
            $bloqueadas += "$sam (nome reservado - trava independente)"
        }
        # krbtgt_<RODC> tem RID normal e nenhum grupo privilegiado: sem esta
        # verificacao de prefixo, nada mais a pegaria.
        if ($sam -like 'krbtgt*') {
            $bloqueadas += "$sam (krbtgt de RODC - trava independente)"
        }
        $sidTexto = [string]$conta.SID.Value
        if ($sidTexto -match '-(\d+)$' -and [int]$Matches[1] -lt $ridMaximo) {
            $bloqueadas += "$sam (RID $($Matches[1]) - trava independente)"
        }

        $prontas.Add([pscustomobject]@{ Sam = $sam; DN = $dn; Objeto = $conta; Ausente = $false })
    }

    if ($bloqueadas.Count -gt 0) {
        Escrever "ABORTADO: conta protegida apareceu na lista aprovada." 'Red'
        $bloqueadas | ForEach-Object { Escrever "  - $_" 'Red' }
        Escrever "Nenhuma conta desta parte foi excluida. Investigue antes de rodar de novo." 'Red'
        exit 1
    }

    if (-not $Apply) {
        Escrever "SIMULACAO - seriam excluidas $($prontas.Count) contas:" 'Yellow'
        $prontas | Select-Object -First 20 -ExpandProperty Sam | ForEach-Object { Escrever "  - $_" }
        Escrever "Use -Apply para executar." 'Yellow'
        exit 0
    }

    # ------------------------------------------------------------ exclusao
    $resultados = [System.Collections.Generic.List[object]]::new()

    foreach ($p in $prontas) {
        if ($p.Ausente) {
            $resultados.Add(@{
                nome_usuario = $p.Sam
                situacao     = 'pulado'
                detalhe      = 'ja nao existe no AD'
            })
            $totalPuladas++
            continue
        }

        # A conta pode ter sido reativada entre a aprovacao e agora.
        if ($p.Objeto.Enabled) {
            Escrever "PULADA: $($p.Sam) foi reativada no AD." 'Yellow'
            $resultados.Add(@{
                nome_usuario = $p.Sam
                situacao     = 'pulado'
                detalhe      = 'conta reativada no AD'
            })
            $totalPuladas++
            continue
        }

        try {
            if ($PSCmdlet.ShouldProcess($p.Sam, 'Remove-ADUser')) {
                Remove-ADUser -Identity $p.DN -Confirm:$false
                $resultados.Add(@{
                    nome_usuario = $p.Sam
                    situacao     = 'excluido'
                    # ISO 8601 COM fuso: o CoreTI roda em UTC e leria a hora
                    # local do Windows como se ja fosse UTC, deslocando a
                    # auditoria em 3 horas.
                    excluido_em  = ([datetimeoffset](Get-Date)).ToString('o')
                    excluido_por = $quem
                })
                $totalExcluidas++
            }
        }
        catch {
            $msg = $_.Exception.Message
            Escrever "FALHA em $($p.Sam): $msg" 'Red'
            $resultados.Add(@{
                nome_usuario = $p.Sam
                situacao     = 'falhou'
                detalhe      = $msg.Substring(0, [Math]::Min(200, $msg.Length))
            })
            $totalFalhas++
        }
    }

    # --------------------------------------------------------- devolucao
    # O CoreTI marca excluido=1 em ad_users a partir daqui. Sem este POST as
    # contas somem do AD e o inventario segue dizendo que existem - e, pior, a
    # mesma lista voltaria na proxima consulta.
    $corpoResultado = @{
        referencia = [string]$referencia
        resultados = $resultados.ToArray()
    }

    $envio = Invoke-CoretiApi -Rota 'api/ad/exclusao/resultado' -Metodo POST -ConfigPath $ConfigPath -Corpo $corpoResultado

    if (-not $envio.Sucesso) {
        Escrever "ATENCAO: contas excluidas do AD, mas o CoreTI nao recebeu o resultado (HTTP $($envio.Status))." 'Red'
        Escrever "Detalhe: $($envio.Dados)" 'Red'
        Escrever "Interrompendo para nao repetir a consulta com a lista desatualizada." 'Red'
        exit 1
    }

    $restantes = [int]$envio.Dados.restantes
    Escrever "Parte $ciclo concluida. Restam $restantes contas aprovadas no lote." 'Green'

    if ($restantes -le 0) { break }

    if ($ciclo -lt $MaxCiclos) {
        Escrever "Aguardando $PausaSegundos s antes da proxima parte (replicacao entre os DCs)." 'Gray'
        Start-Sleep -Seconds $PausaSegundos
    }
}

if ($ciclo -ge $MaxCiclos) {
    Escrever "Teto de $MaxCiclos partes atingido nesta execucao; o restante sai na proxima." 'Yellow'
}

if ($totalExcluidas -gt 0 -or $totalPuladas -gt 0 -or $totalFalhas -gt 0) {
    Escrever "TOTAL - excluidas: $totalExcluidas | puladas: $totalPuladas | falhas: $totalFalhas" 'Green'
}

exit 0
