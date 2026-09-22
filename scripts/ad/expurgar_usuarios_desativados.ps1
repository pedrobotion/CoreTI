<#
.SYNOPSIS
    Lista e (opcionalmente) exclui contas do Active Directory desativadas ha
    mais de N dias.

.DESCRIPTION
    Executar no servidor que tem o modulo ActiveDirectory (DC ou estacao com
    RSAT).

    Por padrao NAO exclui nada: apenas mostra a previa e grava um CSV com os
    candidatos. A exclusao so acontece com -Apply.

    PROTECOES:
      - contas internas do Windows nunca entram na lista (krbtgt, Administrador,
        Convidado, DefaultAccount...). Excluir a krbtgt derruba a autenticacao
        Kerberos do dominio inteiro;
      - contas com cara de servico sao marcadas para conferencia e, por padrao,
        nao sao excluidas (use -IncluirServicos para incluir);
      - a data considerada e a de desativacao real da conta no AD, nao a data
        de criacao nem a do ultimo logon.

.PARAMETER Dias
    Dias apos a desativacao. Padrao: 30.

.PARAMETER SearchBase
    Limita a busca a uma OU. Recomendado. Ex.:
    "OU=Desativados,DC=cocari,DC=com,DC=br"

.PARAMETER Apply
    Executa a exclusao. Sem este parametro, e apenas simulacao.

.EXAMPLE
    # 1) Previa - nao altera nada
    .\expurgar_usuarios_desativados.ps1 -Dias 30

.EXAMPLE
    # 2) Conferir o CSV gerado e so entao executar
    .\expurgar_usuarios_desativados.ps1 -Dias 30 -Apply
#>
# Sem ConfirmImpact='High': a confirmacao deste script e digitar EXCLUIR uma
# vez. Com o prompt do ShouldProcess seria uma segunda pergunta, por conta.
[CmdletBinding(SupportsShouldProcess = $true)]
param(
    [int]$Dias = 30,

    [string]$SearchBase,

    [string]$RelatorioPrevia = ".\ad_expurgo_previa.csv",

    [string]$RelatorioExcluidos = ".\ad_expurgo_excluidos.csv",

    [switch]$IncluirServicos,

    # Teto de exclusoes por execucao. 0 = sem teto. Excluir milhares de contas
    # de uma vez gera uma onda de replicacao entre os controladores.
    [int]$MaxPorExecucao = 0,

    [switch]$Apply
)

$ErrorActionPreference = 'Stop'

if (-not (Get-Module -ListAvailable -Name ActiveDirectory)) {
    throw 'O modulo ActiveDirectory nao esta disponivel nesta maquina.'
}
Import-Module ActiveDirectory

# A regra de protecao vive em CoretiAdProtecao.psm1 e e a MESMA usada pelo
# script de teste. Copiar a logica aqui permitiria que as duas divergissem -
# o teste continuaria passando enquanto a exclusao usasse outro criterio.
# $PSScriptRoot fica vazio quando o conteudo e colado no console em vez de o
# arquivo .ps1 ser executado. Nesse caso caimos para a pasta atual, e so
# entao desistimos - com uma mensagem que diz o que fazer.
$RaizScripts = $PSScriptRoot
if ([string]::IsNullOrWhiteSpace($RaizScripts) -and $MyInvocation.MyCommand.Path) {
    $RaizScripts = Split-Path -Parent $MyInvocation.MyCommand.Path
}
if ([string]::IsNullOrWhiteSpace($RaizScripts)) {
    $RaizScripts = (Get-Location).Path
}

$moduloProtecao = Join-Path $RaizScripts 'CoretiAdProtecao.psm1'
if (-not (Test-Path -LiteralPath $moduloProtecao)) {
    throw @"
Modulo de protecao nao encontrado em: $moduloProtecao

Os tres arquivos precisam estar na MESMA pasta:
  CoretiAdProtecao.psm1
  testar_protecoes.ps1
  expurgar_usuarios_desativados.ps1

Execute pelo caminho do arquivo, nao colando o conteudo no console:
  cd C:\Scripts\CoreTI
  .\expurgar_usuarios_desativados.ps1 -Dias 30
"@
}
Import-Module $moduloProtecao -Force

# Le a associacao de grupo do zero a cada execucao: alteracoes recentes em
# "Membro de" precisam valer agora, nao no proximo reinicio do console.
[void](Get-CoretiPrivilegiadosDN -Recarregar)

# Marcadas para conferencia: contas de servico costumam ficar desativadas
# de proposito e ainda serem referenciadas por sistemas.
$PadroesServico = @('svc', 'srv', 'service', 'sistema', 'sql', 'backup', 'admin')

$Limite = (Get-Date).AddDays(-$Dias)

Write-Host ""
Write-Host "Buscando contas desativadas antes de $($Limite.ToString('dd/MM/yyyy HH:mm'))..." -ForegroundColor Cyan

$parametros = @{
    Filter     = 'Enabled -eq $false'
    Properties = @('whenChanged', 'whenCreated', 'LastLogonDate', 'Description',
                   'DisplayName', 'Mail') + (Get-CoretiPropriedadesNecessarias)
}
if ($SearchBase) { $parametros['SearchBase'] = $SearchBase }

$desativadas = @(Get-ADUser @parametros)

$candidatos = New-Object System.Collections.Generic.List[object]
$protegidas = New-Object System.Collections.Generic.List[object]
$servicos   = New-Object System.Collections.Generic.List[object]
$recentes   = 0

foreach ($u in $desativadas) {
    $sam = $u.SamAccountName

    $motivoProtecao = Test-CoretiContaProtegida -Usuario $u
    if ($motivoProtecao) {
        $protegidas.Add([pscustomobject]@{
            Conta   = $sam
            Motivo  = $motivoProtecao
            Interna = ($motivoProtecao -like 'conta interna do Windows*')
        })
        continue
    }

    # whenChanged e a melhor aproximacao da data de desativacao disponivel no
    # AD. Se voces registram a data em outro atributo, ajuste aqui.
    $desativadaEm = $u.whenChanged

    if ($desativadaEm -gt $Limite) { $recentes++; continue }

    $ehServico = $false
    foreach ($p in $PadroesServico) {
        if ($sam -like "*$p*") { $ehServico = $true; break }
    }

    $registro = [pscustomobject]@{
        objeto_ad         = $u
        nome_usuario      = $sam
        nome_exibicao     = $u.DisplayName
        email             = $u.Mail
        desativada_em     = $desativadaEm
        dias_desativada   = [int]((Get-Date) - $desativadaEm).TotalDays
        ultimo_logon      = $u.LastLogonDate
        criada_em         = $u.whenCreated
        descricao         = $u.Description
        distinguished_name = $u.DistinguishedName
        classificacao     = if ($ehServico) { 'REVISAR (parece conta de servico)' } else { 'ok para excluir' }
    }

    if ($ehServico -and -not $IncluirServicos) {
        $servicos.Add($registro)
    } else {
        $candidatos.Add($registro)
    }
}

# ------------------------------------------------------------------ previa
Write-Host ""
Write-Host "=== RESUMO ===" -ForegroundColor Yellow
Write-Host ("  Contas desativadas no AD .............. {0}" -f $desativadas.Count)
Write-Host ("  Desativadas ha menos de $Dias dias ....... {0}" -f $recentes)
Write-Host ("  Protegidas (nunca excluir) ............ {0}" -f $protegidas.Count)
Write-Host ("  Parecem conta de servico (revisar) .... {0}" -f $servicos.Count)
Write-Host ("  CANDIDATAS A EXCLUSAO ................. {0}" -f $candidatos.Count) -ForegroundColor Red
Write-Host ""

$internas = @($protegidas | Where-Object { $_.Interna })
if ($internas.Count -gt 0) {
    Write-Host "CONTAS INTERNAS DO WINDOWS - bloqueadas, mesmo desativadas:" -ForegroundColor Green
    $internas | Select-Object Conta, Motivo | Format-Table -AutoSize | Out-String | Write-Host
}

$outrasProtegidas = @($protegidas | Where-Object { -not $_.Interna })
if ($outrasProtegidas.Count -gt 0) {
    Write-Host "Outras protegidas:" -ForegroundColor Green
    $outrasProtegidas | Group-Object Motivo | Sort-Object Count -Descending |
        Format-Table @{n='Motivo';e={$_.Name}}, @{n='Contas';e={$_.Count}} -AutoSize |
        Out-String | Write-Host
}

if ($servicos.Count -gt 0) {
    Write-Host "Marcadas para conferencia (nao serao excluidas sem -IncluirServicos):" -ForegroundColor Yellow
    $servicos | Select-Object nome_usuario, desativada_em, descricao | Format-Table -AutoSize | Out-String | Write-Host
}

if ($candidatos.Count -eq 0) {
    Write-Host "Nada a excluir." -ForegroundColor Green
    return
}

Write-Host "Primeiras 20 candidatas:" -ForegroundColor Cyan
$candidatos | Select-Object -First 20 nome_usuario, nome_exibicao, desativada_em, dias_desativada |
    Format-Table -AutoSize | Out-String | Write-Host

$candidatosCsv = $candidatos | Select-Object * -ExcludeProperty objeto_ad
$servicosCsv   = $servicos   | Select-Object * -ExcludeProperty objeto_ad
$todas = @($candidatosCsv) + @($servicosCsv) + @($protegidas | Select-Object @{n='nome_usuario';e={$_.Conta}}, @{n='classificacao';e={'PROTEGIDA - ' + $_.Motivo}})
$todas | Export-Csv -LiteralPath $RelatorioPrevia -Delimiter ';' -NoTypeInformation -Encoding UTF8
Write-Host "Previa completa em: $RelatorioPrevia" -ForegroundColor Cyan

if (-not $Apply) {
    Write-Host ""
    if ($candidatos.Count -gt 300) {
        Write-Warning ("{0} contas de uma vez geram uma onda de replicacao entre os controladores." -f $candidatos.Count)
        Write-Warning "Prefira lotes:  -Apply -MaxPorExecucao 200"
        Write-Host ""
    }
    Write-Warning "SIMULACAO. Nenhuma conta foi excluida."
    Write-Warning "Revise o CSV e execute de novo com -Apply para excluir."
    return
}

# --------------------------------------------------------------- exclusao
Write-Host ""
$totalLote = if ($MaxPorExecucao -gt 0) { [Math]::Min($MaxPorExecucao, $candidatos.Count) } else { $candidatos.Count }
Write-Host "ATENCAO: $totalLote contas serao EXCLUIDAS do Active Directory." -ForegroundColor Red
Write-Host "Esta acao nao tem desfazer." -ForegroundColor Red
$confirma = Read-Host "Digite EXCLUIR para confirmar"
if ($confirma -cne 'EXCLUIR') {
    Write-Host "Cancelado." -ForegroundColor Yellow
    return
}

# --------------------------------------------------------------------------
# Ultima barreira: reconfere cada conta imediatamente antes de excluir.
#
# A verificacao ja aconteceu na montagem da lista. Repetir aqui protege contra
# erro de logica entre as duas etapas - e, se algo protegido chegar ate este
# ponto, a execucao INTEIRA e abortada em vez de apenas pular o item. Uma
# conta interna na fila significa que a filtragem falhou, e nada mais deve ser
# excluido ate isso ser entendido.
# --------------------------------------------------------------------------
$escaparam = @()
$reservados = Get-CoretiContasProtegidas
$ridMaximo  = Get-CoretiRidMaximoReservado

foreach ($c in $candidatos) {
    $motivo = Test-CoretiContaProtegida -Usuario $c.objeto_ad
    if ($motivo) { $escaparam += "$($c.nome_usuario) ($motivo)" }

    # Verificacao direta, sem passar pelo modulo. Duplicada de proposito: se
    # houver defeito no proprio modulo, esta trava ainda pega conta interna.
    if ($reservados -contains $c.nome_usuario) {
        $escaparam += "$($c.nome_usuario) (nome reservado - trava independente)"
    }
    $sidTexto = [string]$c.objeto_ad.SID.Value
    if ($sidTexto -match '-(\d+)$' -and [int]$Matches[1] -lt $ridMaximo) {
        $escaparam += "$($c.nome_usuario) (RID $($Matches[1]) - trava independente)"
    }
}

if ($escaparam.Count -gt 0) {
    Write-Host ""
    Write-Host "ABORTADO: conta protegida chegou a lista de exclusao." -ForegroundColor Red
    $escaparam | ForEach-Object { Write-Host "  - $_" -ForegroundColor Red }
    Write-Host "Nenhuma conta foi excluida. Revise o script antes de tentar de novo." -ForegroundColor Red
    return
}

$aExcluir = $candidatos
if ($MaxPorExecucao -gt 0 -and $candidatos.Count -gt $MaxPorExecucao) {
    $aExcluir = $candidatos | Sort-Object dias_desativada -Descending |
        Select-Object -First $MaxPorExecucao
    Write-Host ""
    Write-Host ("Teto de {0} por execucao: as {1} mais antigas serao excluidas agora." -f $MaxPorExecucao, $MaxPorExecucao) -ForegroundColor Yellow
    Write-Host ("Restam {0} para as proximas execucoes." -f ($candidatos.Count - $MaxPorExecucao)) -ForegroundColor Yellow
}

$excluidas = New-Object System.Collections.Generic.List[object]
$falhas = 0

foreach ($c in $aExcluir) {
    try {
        # Reconfere no objeto atual do AD, e nao no que foi lido no inicio.
        $atual = Get-ADUser -Identity $c.distinguished_name -Properties (Get-CoretiPropriedadesNecessarias)
        $motivo = Test-CoretiContaProtegida -Usuario $atual
        if ($motivo) {
            Write-Warning ("PULADA por seguranca: {0} - {1}" -f $c.nome_usuario, $motivo)
            continue
        }

        # A conta pode ter sido reativada entre a previa e a execucao.
        if ($atual.Enabled) {
            Write-Warning ("PULADA: {0} foi reativada no AD." -f $c.nome_usuario)
            continue
        }

        if ($PSCmdlet.ShouldProcess($c.nome_usuario, 'Remove-ADUser')) {
            Remove-ADUser -Identity $c.distinguished_name -Confirm:$false
            $excluidas.Add([pscustomobject]@{
                nome_usuario  = $c.nome_usuario
                desativada_em = $c.desativada_em
                excluida_em   = (Get-Date).ToString('yyyy-MM-dd HH:mm:ss')
                excluida_por  = "$env:USERDOMAIN\$env:USERNAME"
            })
        }
    }
    catch {
        $falhas++
        Write-Warning ("Falha ao excluir {0}: {1}" -f $c.nome_usuario, $_.Exception.Message)
    }
}

$excluidas | Export-Csv -LiteralPath $RelatorioExcluidos -Delimiter ';' -NoTypeInformation -Encoding UTF8

Write-Host ""
Write-Host ("Excluidas: {0} | Falhas: {1}" -f $excluidas.Count, $falhas) -ForegroundColor Green
Write-Host "Relatorio: $RelatorioExcluidos" -ForegroundColor Cyan
Write-Host ""
Write-Host "Proximo passo, no servidor do CoreTI:" -ForegroundColor Cyan
Write-Host "  php artisan ad:marcar-excluidos --csv=$RelatorioExcluidos"
