<#
.SYNOPSIS
    TESTE - verifica que as contas internas do Windows nao entram na lista de
    exclusao. Nao altera absolutamente nada.

.DESCRIPTION
    Rode ANTES de qualquer exclusao, e sempre que a regra de protecao mudar.

    Este script NAO tem parametro de exclusao: nao ha caminho de codigo aqui
    que remova conta alguma. Ele importa a MESMA funcao de protecao usada pelo
    expurgo (CoretiAdProtecao.psm1), entao o que ele valida e exatamente o que
    sera aplicado na hora de excluir.

    O que verifica:
      1. as contas internas conhecidas existem e sao reconhecidas como
         protegidas, com o motivo;
      2. nenhuma conta protegida aparece entre as candidatas a exclusao;
      3. mostra o que SERIA excluido, para conferencia.

    Codigo de saida 0 = tudo certo. 1 = alguma protecao falhou.

.EXAMPLE
    .\testar_protecoes.ps1 -Dias 30
#>
[CmdletBinding()]
param(
    [int]$Dias = 30,
    [string]$SearchBase,
    [string]$Relatorio = ".\ad_teste_protecoes.csv"
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

Os tres arquivos precisam estar na MESMA pasta:
  CoretiAdProtecao.psm1
  testar_protecoes.ps1
  expurgar_usuarios_desativados.ps1

Execute pelo caminho do arquivo, nao colando o conteudo no console:
  cd C:\Scripts\CoreTI
  .\testar_protecoes.ps1 -Dias 30
"@
}
Import-Module $moduloProtecao -Force

# Le a associacao de grupo do zero a cada execucao: alteracoes recentes em
# "Membro de" precisam valer agora, nao no proximo reinicio do console.
[void](Get-CoretiPrivilegiadosDN -Recarregar)

$Limite = (Get-Date).AddDays(-$Dias)
$falhas = 0

Write-Host ""
Write-Host "=========================================================" -ForegroundColor Cyan
Write-Host " TESTE DE PROTECAO - nenhuma conta sera alterada" -ForegroundColor Cyan
Write-Host "=========================================================" -ForegroundColor Cyan
Write-Host " Dominio ..: $env:USERDNSDOMAIN"
Write-Host " Corte ....: desativadas antes de $($Limite.ToString('dd/MM/yyyy HH:mm'))"
if ($SearchBase) { Write-Host " OU .......: $SearchBase" }
Write-Host ""

# ---------------------------------------------------------------- etapa 1
Write-Host "[1/3] As contas internas sao reconhecidas como protegidas?" -ForegroundColor Yellow
Write-Host ""

foreach ($nome in (Get-CoretiContasProtegidas)) {
    try {
        $conta = Get-ADUser -Identity $nome -Properties (Get-CoretiPropriedadesNecessarias) -ErrorAction Stop
    }
    catch {
        Write-Host ("  {0,-20} nao existe neste dominio (ok)" -f $nome) -ForegroundColor DarkGray
        continue
    }

    $rid = if ($conta.SID.Value -match '-(\d+)$') { $Matches[1] } else { '?' }
    $motivo = Test-CoretiContaProtegida -Usuario $conta

    if ($motivo) {
        Write-Host ("  {0,-20} RID {1,-6} PROTEGIDA - {2}" -f $conta.SamAccountName, $rid, $motivo) -ForegroundColor Green
    }
    else {
        Write-Host ("  {0,-20} RID {1,-6} *** DESPROTEGIDA ***" -f $conta.SamAccountName, $rid) -ForegroundColor Red
        $falhas++
    }
}

# ---------------------------------------------------------------- etapa 2
Write-Host ""
Write-Host "[2/3] Montando a lista de candidatas..." -ForegroundColor Yellow

$parametros = @{
    Filter     = 'Enabled -eq $false'
    Properties = @('whenChanged', 'whenCreated', 'LastLogonDate', 'Description',
                   'DisplayName', 'Mail', 'DistinguishedName') + (Get-CoretiPropriedadesNecessarias)
}
if ($SearchBase) { $parametros['SearchBase'] = $SearchBase }

$desativadas = Get-ADUser @parametros

$protegidas = New-Object System.Collections.Generic.List[object]
$candidatas = New-Object System.Collections.Generic.List[object]
$recentes = 0

foreach ($u in $desativadas) {
    $motivo = Test-CoretiContaProtegida -Usuario $u

    if ($motivo) {
        $protegidas.Add([pscustomobject]@{
            nome_usuario = $u.SamAccountName
            motivo       = $motivo
        })
        continue
    }

    if ($u.whenChanged -gt $Limite) { $recentes++; continue }

    $candidatas.Add([pscustomobject]@{
        nome_usuario    = $u.SamAccountName
        nome_exibicao   = $u.DisplayName
        email           = $u.Mail
        desativada_em   = $u.whenChanged
        dias_desativada = [int]((Get-Date) - $u.whenChanged).TotalDays
        ultimo_logon    = $u.LastLogonDate
        descricao       = $u.Description
    })
}

Write-Host ""
Write-Host ("  Desativadas no AD ................ {0}" -f $desativadas.Count)
Write-Host ("  Protegidas (fora da lista) ....... {0}" -f $protegidas.Count) -ForegroundColor Green
Write-Host ("  Desativadas ha menos de $Dias dias ... {0}" -f $recentes)
Write-Host ("  CANDIDATAS A EXCLUSAO ............ {0}" -f $candidatas.Count) -ForegroundColor Red

if ($protegidas.Count -gt 0) {
    Write-Host ""
    Write-Host "  Protegidas encontradas:" -ForegroundColor Green
    $protegidas | Format-Table -AutoSize | Out-String | Write-Host
}

# ---------------------------------------------------------------- etapa 3
Write-Host "[3/3] Nenhuma protegida vazou para as candidatas?" -ForegroundColor Yellow
Write-Host ""

$vazaram = @()
foreach ($c in $candidatas) {
    $conta = Get-ADUser -Identity $c.nome_usuario -Properties (Get-CoretiPropriedadesNecessarias)
    $motivo = Test-CoretiContaProtegida -Usuario $conta
    if ($motivo) { $vazaram += "$($c.nome_usuario) - $motivo" }
}

if ($vazaram.Count -eq 0) {
    Write-Host "  OK - nenhuma conta protegida na lista de exclusao." -ForegroundColor Green
}
else {
    Write-Host "  *** FALHA - conta protegida entre as candidatas ***" -ForegroundColor Red
    $vazaram | ForEach-Object { Write-Host "    - $_" -ForegroundColor Red }
    $falhas += $vazaram.Count
}

# ------------------------------------------------------------------ saida
if ($candidatas.Count -gt 0) {
    $candidatas | Sort-Object dias_desativada -Descending |
        Export-Csv -LiteralPath $Relatorio -Delimiter ';' -NoTypeInformation -Encoding UTF8

    Write-Host ""
    Write-Host "Amostra do que seria excluido (20 mais antigas):" -ForegroundColor Cyan
    $candidatas | Sort-Object dias_desativada -Descending | Select-Object -First 20 `
        nome_usuario, nome_exibicao, desativada_em, dias_desativada |
        Format-Table -AutoSize | Out-String | Write-Host

    Write-Host "Lista completa em: $Relatorio" -ForegroundColor Cyan
}

Write-Host ""
Write-Host "=========================================================" -ForegroundColor Cyan
if ($falhas -eq 0) {
    Write-Host " RESULTADO: protecoes funcionando." -ForegroundColor Green
    Write-Host " Nenhuma conta foi alterada - este script so le." -ForegroundColor Green
    exit 0
}
else {
    Write-Host " RESULTADO: $falhas FALHA(S). NAO execute o expurgo." -ForegroundColor Red
    exit 1
}
