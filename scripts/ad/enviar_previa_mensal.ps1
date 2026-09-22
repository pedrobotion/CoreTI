<#
.SYNOPSIS
    Monta a previa mensal de expurgo e envia ao CoreTI. Nao exclui nada.

.DESCRIPTION
    Somente leitura no Active Directory. Nao existe Remove-ADUser neste arquivo.

    Usa a MESMA funcao de protecao do expurgo (CoretiAdProtecao.psm1), entao a
    lista enviada e exatamente a que seria excluida.

    Agende para o dia 1 de cada mes. O CoreTI guarda a previa como um lote
    "aguardando aprovacao" e avisa por e-mail; ninguem e excluido antes de uma
    pessoa conferir e aprovar na tela.

.PARAMETER Dias
    Dias apos a desativacao. Padrao: 30.

.PARAMETER SearchBase
    Limita a busca a uma OU. Ex.: "OU=Desativados,DC=cocari,DC=com,DC=br"

.EXAMPLE
    .\enviar_previa_mensal.ps1 -Dias 30
#>
[CmdletBinding()]
param(
    [int]$Dias = 30,
    [string]$SearchBase,
    [string]$Referencia,
    [string]$ConfigPath
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
        throw "Modulo nao encontrado: $caminho. Todos os arquivos precisam estar na mesma pasta."
    }
    Import-Module $caminho -Force
}

# Le a associacao de grupo do zero: alteracoes em "Membro de" precisam valer agora.
[void](Get-CoretiPrivilegiadosDN -Recarregar)

$PadroesServico = @('svc', 'srv', 'service', 'sistema', 'sql', 'backup', 'admin')
$Limite = (Get-Date).AddDays(-$Dias)
if (-not $Referencia) { $Referencia = (Get-Date).ToString('yyyy-MM') }

Write-Host ""
Write-Host "=========================================================" -ForegroundColor Cyan
Write-Host " PREVIA MENSAL DE EXPURGO - somente leitura" -ForegroundColor Cyan
Write-Host "=========================================================" -ForegroundColor Cyan
Write-Host " Competencia .: $Referencia"
Write-Host " Corte .......: desativadas antes de $($Limite.ToString('dd/MM/yyyy HH:mm'))"
if ($SearchBase) { Write-Host " OU ..........: $SearchBase" }
Write-Host ""

$parametros = @{
    Filter     = 'Enabled -eq $false'
    Properties = @('whenChanged', 'whenCreated', 'LastLogonDate', 'Description',
                   'DisplayName', 'Mail') + (Get-CoretiPropriedadesNecessarias)
}
if ($SearchBase) { $parametros['SearchBase'] = $SearchBase }

$desativadas = @(Get-ADUser @parametros)

$candidatos = [System.Collections.Generic.List[object]]::new()
$protegidas = 0
$recentes = 0

$jaPrivilegiadas = 0

foreach ($u in $desativadas) {
    if (Test-CoretiContaProtegida -Usuario $u) { $protegidas++; continue }
    if ($u.whenChanged -gt $Limite) { $recentes++; continue }

    if (Test-CoretiJaFoiPrivilegiada -Usuario $u) { $jaPrivilegiadas++ }

    $sam = [string]$u.SamAccountName
    $ehServico = $false
    foreach ($p in $PadroesServico) {
        if ($sam -like "*$p*") { $ehServico = $true; break }
    }

    # ISO 8601 COM fuso em tudo que e data: o CoreTI roda em UTC e leria a
    # hora local do Windows como se ja fosse UTC.
    $ultimoLogon = $null
    if ($u.LastLogonDate) { $ultimoLogon = ([datetimeoffset]$u.LastLogonDate).ToString('o') }

    $candidatos.Add(@{
        nome_usuario       = [string]$sam
        nome_exibicao      = [string]$u.DisplayName
        email              = [string]$u.Mail
        distinguished_name = [string]$u.DistinguishedName
        desativada_em      = ([datetimeoffset]$u.whenChanged).ToString('o')
        dias_desativada    = [int]((Get-Date) - $u.whenChanged).TotalDays
        ultimo_logon       = $ultimoLogon
        descricao          = [string]$u.Description
        parece_servico     = [bool]$ehServico
        ja_privilegiada    = [bool](Test-CoretiJaFoiPrivilegiada -Usuario $u)
    })
}

Write-Host "  Desativadas no AD ............ $($desativadas.Count)"
Write-Host "  Protegidas (fora da lista) ... $protegidas" -ForegroundColor Green
Write-Host "  Desativadas ha menos de $Dias dias  $recentes"
Write-Host "  CANDIDATAS ................... $($candidatos.Count)" -ForegroundColor Yellow
if ($jaPrivilegiadas -gt 0) {
    Write-Host "    destas, ja foram privilegiadas: $jaPrivilegiadas" -ForegroundColor DarkYellow
    Write-Host "    (vao desmarcadas na tela do CoreTI)" -ForegroundColor DarkYellow
}
Write-Host ""

try {
    $lista = @()
    if ($candidatos.Count -gt 0) { $lista = $candidatos.ToArray() }

    $corpo = @{
        referencia    = [string]$Referencia
        dias_criterio = [int]$Dias
        origem_host   = [string]$env:COMPUTERNAME
        search_base   = [string]$SearchBase
        candidatos    = $lista
    }

    # Converte aqui, e nao dentro do modulo, para o erro apontar esta linha se
    # algum valor nao for serializavel.
    $json = $corpo | ConvertTo-Json -Depth 6 -Compress
    Write-Host "  JSON montado: $($json.Length) caracteres" -ForegroundColor DarkGray
}
catch {
    Write-Host ""
    Write-Host "FALHA ao montar o corpo da requisicao." -ForegroundColor Red
    Write-Host "  Tipo ....: $($_.Exception.GetType().FullName)" -ForegroundColor Red
    Write-Host "  Mensagem : $($_.Exception.Message)" -ForegroundColor Red
    if ($_.Exception.InnerException) {
        Write-Host "  Interna .: $($_.Exception.InnerException.Message)" -ForegroundColor Red
    }
    Write-Host "  Origem ..: $($_.InvocationInfo.ScriptName):$($_.InvocationInfo.ScriptLineNumber)" -ForegroundColor Red
    Write-Host "  Linha ...: $($_.InvocationInfo.Line.Trim())" -ForegroundColor DarkRed
    exit 1
}

Write-Host "Enviando ao CoreTI..." -ForegroundColor Cyan

try {
    $r = Invoke-CoretiApi -Rota 'api/ad/exclusao/previa' -Metodo POST -Corpo $corpo -ConfigPath $ConfigPath
}
catch {
    # Detalhe completo: um "os tipos de argumento nao correspondem" sem origem
    # nao diz onde olhar.
    Write-Host ""
    Write-Host "FALHA ao montar ou enviar a requisicao." -ForegroundColor Red
    Write-Host "  Tipo ....: $($_.Exception.GetType().FullName)" -ForegroundColor Red
    Write-Host "  Mensagem : $($_.Exception.Message)" -ForegroundColor Red
    if ($_.Exception.InnerException) {
        Write-Host "  Interna .: $($_.Exception.InnerException.Message)" -ForegroundColor Red
    }
    Write-Host "  Origem ..: $($_.InvocationInfo.ScriptName):$($_.InvocationInfo.ScriptLineNumber)" -ForegroundColor Red
    Write-Host "  Linha ...: $($_.InvocationInfo.Line.Trim())" -ForegroundColor DarkRed
    exit 1
}

if ($r.Sucesso) {
    Write-Host ""
    Write-Host "Previa registrada. Lote $($r.Dados.referencia) aguardando aprovacao no CoreTI." -ForegroundColor Green
    Write-Host "Nenhuma conta foi alterada - este script so le." -ForegroundColor Green
    exit 0
}

if ($r.Status -eq 409) {
    Write-Host ""
    Write-Warning "O lote de $Referencia ja foi decidido no CoreTI; a previa nao substitui a decisao."
    exit 0
}

Write-Host ""
Write-Host "FALHA ao enviar (HTTP $($r.Status)):" -ForegroundColor Red
Write-Host $r.Dados -ForegroundColor Red
exit 1
