<#
.SYNOPSIS
    Lista os usuarios do AD que PODEM ser excluidos. Nao altera nada.

.DESCRIPTION
    Somente leitura. Nao existe caminho de codigo aqui que remova conta alguma.

    Usa a MESMA funcao de protecao do expurgo (CoretiAdProtecao.psm1), entao a
    lista mostrada e exatamente a que seria excluida.

    Um usuario so entra na lista se:
      - estiver desativado no AD;
      - estiver desativado ha mais de N dias;
      - NAO for conta interna do Windows (RID < 1000 ou nome reservado);
      - NAO pertencer, direta ou indiretamente, a grupo privilegiado;
      - NAO tiver grupo primario privilegiado;
      - NAO tiver adminCount = 1.

.PARAMETER Dias
    Dias apos a desativacao. Padrao: 30.

.PARAMETER SearchBase
    Limita a busca a uma OU. Ex.: "OU=Desativados,DC=cocari,DC=com,DC=br"

.PARAMETER IncluirServicos
    Inclui na lista as contas com cara de servico (svc, sql, backup...), que
    por padrao ficam separadas para conferencia.

.EXAMPLE
    .\listar_excluiveis.ps1 -Dias 30
#>
[CmdletBinding()]
param(
    [int]$Dias = 30,
    [string]$SearchBase,
    [string]$Relatorio = ".\ad_excluiveis.csv",
    [switch]$IncluirServicos
)

$ErrorActionPreference = 'Stop'

if (-not (Get-Module -ListAvailable -Name ActiveDirectory)) {
    throw 'O modulo ActiveDirectory nao esta disponivel nesta maquina.'
}
Import-Module ActiveDirectory

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

Os arquivos precisam estar na MESMA pasta:
  CoretiAdProtecao.psm1
  listar_excluiveis.ps1

Execute pelo caminho do arquivo, nao colando o conteudo no console:
  cd C:\Scripts\CoreTI
  .\listar_excluiveis.ps1 -Dias 30
"@
}
Import-Module $moduloProtecao -Force

$PadroesServico = @('svc', 'srv', 'service', 'sistema', 'sql', 'backup', 'admin')
$Limite = (Get-Date).AddDays(-$Dias)

Write-Host ""
Write-Host "=========================================================" -ForegroundColor Cyan
Write-Host " USUARIOS QUE PODEM SER EXCLUIDOS - somente leitura" -ForegroundColor Cyan
Write-Host "=========================================================" -ForegroundColor Cyan
Write-Host " Dominio ..: $env:USERDNSDOMAIN"
Write-Host " Corte ....: desativadas antes de $($Limite.ToString('dd/MM/yyyy HH:mm'))"
if ($SearchBase) { Write-Host " OU .......: $SearchBase" }
Write-Host ""

# --------------------------------------------------- grupos privilegiados
Write-Host "Mapeando grupos privilegiados (inclui grupos aninhados)..." -ForegroundColor Yellow
$privilegiados = Get-CoretiPrivilegiadosDN -Recarregar

Get-CoretiGruposPrivilegiados |
    Where-Object { $_.usuarios -gt 0 } |
    Format-Table @{n='Grupo privilegiado';e={$_.grupo}}, @{n='Usuarios';e={$_.usuarios}} -AutoSize |
    Out-String | Write-Host

Write-Host ("  Contas privilegiadas distintas: {0}" -f $privilegiados.Count) -ForegroundColor Green
Write-Host ""

# --------------------------------------------------------- classificacao
$parametros = @{
    Filter     = 'Enabled -eq $false'
    Properties = @('whenChanged', 'whenCreated', 'LastLogonDate', 'Description',
                   'DisplayName', 'Mail') + (Get-CoretiPropriedadesNecessarias)
}
if ($SearchBase) { $parametros['SearchBase'] = $SearchBase }

$desativadas = @(Get-ADUser @parametros)

$excluiveis  = New-Object System.Collections.Generic.List[object]
$protegidas  = New-Object System.Collections.Generic.List[object]
$servicos    = New-Object System.Collections.Generic.List[object]
$residuais   = New-Object System.Collections.Generic.List[object]
$recentes    = 0

foreach ($u in $desativadas) {
    $sam = [string]$u.SamAccountName
    $motivo = Test-CoretiContaProtegida -Usuario $u

    if ($motivo) {
        $protegidas.Add([pscustomobject]@{
            nome_usuario  = $sam
            nome_exibicao = $u.DisplayName
            motivo        = $motivo
        })

        continue
    }

    if ($u.whenChanged -gt $Limite) { $recentes++; continue }

    $ehServico = $false
    foreach ($p in $PadroesServico) {
        if ($sam -like "*$p*") { $ehServico = $true; break }
    }

    # Marca historica do AdminSDHolder: nao bloqueia, mas pede conferencia.
    if (Test-CoretiJaFoiPrivilegiada -Usuario $u) {
        $residuais.Add([pscustomobject]@{
            nome_usuario  = $sam
            nome_exibicao = $u.DisplayName
            desativada_em = $u.whenChanged
        })
    }

    $registro = [pscustomobject]@{
        nome_usuario       = $sam
        nome_exibicao      = $u.DisplayName
        email              = $u.Mail
        desativada_em      = $u.whenChanged
        dias_desativada    = [int]((Get-Date) - $u.whenChanged).TotalDays
        ultimo_logon       = $u.LastLogonDate
        criada_em          = $u.whenCreated
        descricao          = $u.Description
        distinguished_name = $u.DistinguishedName
        classificacao      = if ($ehServico) { 'REVISAR (parece conta de servico)' } else { 'pode excluir' }
    }

    if ($ehServico -and -not $IncluirServicos) {
        $servicos.Add($registro)
    } else {
        $excluiveis.Add($registro)
    }
}

# ---------------------------------------------------------------- resumo
Write-Host "=== RESUMO ===" -ForegroundColor Yellow
Write-Host ("  Contas desativadas no AD ............. {0}" -f $desativadas.Count)
Write-Host ("  Protegidas (nunca excluir) .......... {0}" -f $protegidas.Count) -ForegroundColor Green
Write-Host ("  Desativadas ha menos de $Dias dias ...... {0}" -f $recentes)
Write-Host ("  Parecem conta de servico (revisar) .. {0}" -f $servicos.Count) -ForegroundColor Yellow
Write-Host ("  PODEM SER EXCLUIDAS ................. {0}" -f $excluiveis.Count) -ForegroundColor Red
Write-Host ""

if ($protegidas.Count -gt 0) {
    Write-Host "Protegidas, por motivo:" -ForegroundColor Green
    $protegidas | Group-Object motivo | Sort-Object Count -Descending |
        Format-Table @{n='Motivo';e={$_.Name}}, @{n='Contas';e={$_.Count}} -AutoSize |
        Out-String | Write-Host
}

if ($residuais.Count -gt 0) {
    Write-Host "ATENCAO - candidatas que ja foram privilegiadas (adminCount=1):" -ForegroundColor Yellow
    Write-Host "  Nao estao mais em nenhum grupo privilegiado, mas conservam a marca" -ForegroundColor DarkYellow
    Write-Host "  do AdminSDHolder, que nao e limpa automaticamente na saida do grupo." -ForegroundColor DarkYellow
    Write-Host "  Entram na lista, porem desmarcadas na tela do CoreTI." -ForegroundColor DarkYellow
    Write-Host ""
    $residuais | Sort-Object nome_usuario | Format-Table -AutoSize | Out-String | Write-Host
}

if ($servicos.Count -gt 0) {
    Write-Host "Parecem conta de servico (fora da lista sem -IncluirServicos):" -ForegroundColor Yellow
    $servicos | Select-Object nome_usuario, desativada_em, descricao |
        Format-Table -AutoSize | Out-String | Write-Host
}

# ----------------------------------------------------------------- lista
if ($excluiveis.Count -eq 0) {
    Write-Host "Nenhum usuario elegivel para exclusao." -ForegroundColor Green
    return
}

Write-Host "USUARIOS QUE PODEM SER EXCLUIDOS (30 mais antigos):" -ForegroundColor Red
Write-Host ""
$excluiveis | Sort-Object dias_desativada -Descending | Select-Object -First 30 `
    nome_usuario, nome_exibicao, desativada_em, dias_desativada |
    Format-Table -AutoSize | Out-String | Write-Host

$excluiveis | Sort-Object dias_desativada -Descending |
    Export-Csv -LiteralPath $Relatorio -Delimiter ';' -NoTypeInformation -Encoding UTF8

Write-Host ("Lista completa ({0} contas) em: {1}" -f $excluiveis.Count, $Relatorio) -ForegroundColor Cyan
Write-Host ""
Write-Host "Nada foi alterado no AD - este script so le." -ForegroundColor Green
