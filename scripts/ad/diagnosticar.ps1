<#
.SYNOPSIS
    Diagnostico do modulo de protecao e do envio ao CoreTI. NAO altera nada e
    NAO envia nada.

.DESCRIPTION
    Roda cada etapa isoladamente e mostra o erro completo quando alguma falha,
    para localizar o problema em vez de adivinhar.

    Nao ha Remove-ADUser aqui, e a etapa de envio monta o JSON mas nao chama a
    API.
#>
[CmdletBinding()]
param(
    [int]$Dias = 30,
    [string]$SearchBase,
    [int]$Amostra = 8
)

$ErrorActionPreference = 'Continue'

function Etapa { param([string]$Texto) Write-Host "`n=== $Texto ===" -ForegroundColor Cyan }
function Falha {
    param($Erro, [string]$Onde)
    Write-Host "  FALHOU em $Onde" -ForegroundColor Red
    Write-Host "  Tipo ....: $($Erro.Exception.GetType().FullName)" -ForegroundColor Red
    Write-Host "  Mensagem : $($Erro.Exception.Message)" -ForegroundColor Red
    if ($Erro.Exception.InnerException) {
        Write-Host "  Interna .: $($Erro.Exception.InnerException.Message)" -ForegroundColor Red
    }
    Write-Host "  Origem ..: $($Erro.InvocationInfo.ScriptName):$($Erro.InvocationInfo.ScriptLineNumber)" -ForegroundColor Red
    Write-Host "  Linha ...: $($Erro.InvocationInfo.Line.Trim())" -ForegroundColor DarkRed
}

Etapa "Ambiente"
Write-Host "  PowerShell ...: $($PSVersionTable.PSVersion)"
Write-Host "  Edicao .......: $($PSVersionTable.PSEdition)"
Write-Host "  Dominio ......: $env:USERDNSDOMAIN"
Write-Host "  Servidor .....: $env:COMPUTERNAME"

Import-Module ActiveDirectory
$Raiz = if ($PSScriptRoot) { $PSScriptRoot } else { (Get-Location).Path }
Import-Module (Join-Path $Raiz 'CoretiAdProtecao.psm1') -Force

Etapa "1. Grupos privilegiados"
$privilegiados = $null
try {
    $privilegiados = Get-CoretiPrivilegiadosDN -Recarregar
    Write-Host "  Tipo devolvido .....: $($privilegiados.GetType().FullName)" -ForegroundColor Green
    Write-Host "  Contas privilegiadas: $($privilegiados.Count)" -ForegroundColor Green
    Get-CoretiGruposPrivilegiados | Where-Object { $_.usuarios -gt 0 } |
        Format-Table @{n='Grupo';e={$_.grupo}}, @{n='Membros';e={$_.usuarios}} -AutoSize | Out-String | Write-Host
}
catch { Falha $_ 'Get-CoretiPrivilegiadosDN' }

Etapa "2. Contas desativadas"
$parametros = @{
    Filter     = 'Enabled -eq $false'
    Properties = @('whenChanged', 'whenCreated', 'LastLogonDate', 'Description',
                   'DisplayName', 'Mail') + (Get-CoretiPropriedadesNecessarias)
}
if ($SearchBase) { $parametros['SearchBase'] = $SearchBase }

$desativadas = @()
try {
    $desativadas = @(Get-ADUser @parametros)
    Write-Host "  Desativadas encontradas: $($desativadas.Count)" -ForegroundColor Green
}
catch { Falha $_ 'Get-ADUser' }

Etapa "3. Motivo de protecao, conta a conta"
$Limite = (Get-Date).AddDays(-$Dias)
$motivos = @{}
$linhas = New-Object System.Collections.Generic.List[object]

foreach ($u in $desativadas) {
    $motivo = $null
    try { $motivo = Test-CoretiContaProtegida -Usuario $u }
    catch { $motivo = "ERRO: $($_.Exception.Message)" }

    $chave = if ($motivo) { $motivo } else { '(nao protegida)' }
    # Agrupa "RID 502" e "RID 503" sob o mesmo rotulo para o resumo.
    $chave = $chave -replace '\(RID \d+\)', '(RID reservado)'
    if (-not $motivos.ContainsKey($chave)) { $motivos[$chave] = 0 }
    $motivos[$chave]++

    $sidTexto = if ($u.SID) { [string]$u.SID.Value } else { '(sem SID)' }
    $rid = if ($sidTexto -match '-(\d+)$') { $Matches[1] } else { '?' }

    $linhas.Add([pscustomobject]@{
        conta        = [string]$u.SamAccountName
        rid          = $rid
        adminCount   = $u.adminCount
        primaryGroup = $u.PrimaryGroupID
        temDN        = -not [string]::IsNullOrWhiteSpace([string]$u.DistinguishedName)
        whenChanged  = $u.whenChanged
        recente      = ($u.whenChanged -gt $Limite)
        motivo       = $motivo
    })
}

Write-Host "  Resumo por motivo:" -ForegroundColor Yellow
$motivos.GetEnumerator() | Sort-Object Value -Descending |
    Format-Table @{n='Motivo';e={$_.Key}}, @{n='Contas';e={$_.Value}} -AutoSize | Out-String | Write-Host

Write-Host "  Amostra de $Amostra contas com os valores crus:" -ForegroundColor Yellow
$linhas | Select-Object -First $Amostra | Format-Table -AutoSize | Out-String | Write-Host

Etapa "4. Montagem do JSON (nao envia)"
try {
    $corpo = [ordered]@{
        referencia    = (Get-Date).ToString('yyyy-MM')
        dias_criterio = $Dias
        origem_host   = $env:COMPUTERNAME
        search_base   = $SearchBase
        candidatos    = @()
    }
    $json = $corpo | ConvertTo-Json -Depth 6 -Compress
    Write-Host "  [ordered] + ConvertTo-Json: OK" -ForegroundColor Green
    Write-Host "  $json" -ForegroundColor DarkGray
}
catch { Falha $_ '[ordered] / ConvertTo-Json' }

Etapa "5. Modulo da API e configuracao"
try {
    Import-Module (Join-Path $Raiz 'CoretiAdApi.psm1') -Force
    $cfg = Get-CoretiConfiguracao
    Write-Host "  URL ...: $($cfg.Url)" -ForegroundColor Green
    Write-Host "  Token .: $($cfg.Token.Length) caracteres" -ForegroundColor Green
}
catch { Falha $_ 'CoretiAdApi / configuracao' }

Write-Host "`nNada foi alterado e nada foi enviado." -ForegroundColor Green
