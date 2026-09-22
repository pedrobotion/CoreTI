<#
    CoretiAdApi.psm1

    Conversa com o CoreTI. O sentido e sempre AD -> CoreTI: nada entra no
    dominio a partir do site.

    A URL e o token ficam em coreti.config.json, ao lado dos scripts:

        {
          "url":   "https://coreti.cocari.com.br",
          "token": "<mesmo valor de AD_PURGE_TOKEN no .env do CoreTI>"
        }

    Restrinja o acesso a esse arquivo - o token autoriza consultar e reportar
    exclusoes de contas:

        icacls .\coreti.config.json /inheritance:r /grant:r "SYSTEM:(R)" "Administradores:(R)"
#>

$script:Configuracao = $null

function Get-CoretiConfiguracao {
    [CmdletBinding()]
    param([string]$Caminho)

    if ($null -ne $script:Configuracao -and -not $Caminho) {
        return $script:Configuracao
    }

    if (-not $Caminho) {
        $Caminho = Join-Path $PSScriptRoot 'coreti.config.json'
    }

    if (-not (Test-Path -LiteralPath $Caminho)) {
        throw @"
Configuracao nao encontrada: $Caminho

Crie o arquivo com o conteudo:
{
  "url":   "https://coreti.cocari.com.br",
  "token": "<valor de AD_PURGE_TOKEN no .env do CoreTI>"
}
"@
    }

    $cfg = Get-Content -LiteralPath $Caminho -Raw -Encoding UTF8 | ConvertFrom-Json

    if ([string]::IsNullOrWhiteSpace($cfg.url))   { throw "Campo 'url' vazio em $Caminho" }
    if ([string]::IsNullOrWhiteSpace($cfg.token)) { throw "Campo 'token' vazio em $Caminho" }

    $script:Configuracao = [pscustomobject]@{
        Url   = ([string]$cfg.url).TrimEnd('/')
        Token = [string]$cfg.token
    }

    return $script:Configuracao
}

<#
.SYNOPSIS
    Chama a API do CoreTI.

.DESCRIPTION
    Devolve um objeto com Sucesso, Status e Dados. Nao lanca excecao em erro
    HTTP: quem chama decide o que fazer. Um 409 ("lote ja decidido") e resposta
    normal do fluxo, nao falha.
#>
function Invoke-CoretiApi {
    [CmdletBinding()]
    param(
        [Parameter(Mandatory = $true)][string]$Rota,
        [ValidateSet('GET', 'POST')][string]$Metodo = 'GET',
        $Corpo,
        [string]$ConfigPath
    )

    $cfg = Get-CoretiConfiguracao -Caminho $ConfigPath

    # Servidores antigos negociam TLS 1.0 por padrao e o handshake falha.
    try {
        [Net.ServicePointManager]::SecurityProtocol = [Net.SecurityProtocolType]::Tls12
    } catch { }

    $parametros = @{
        Uri             = "$($cfg.Url)/$($Rota.TrimStart('/'))"
        Method          = $Metodo
        Headers         = @{ 'X-Coreti-Ad-Token' = $cfg.Token; 'Accept' = 'application/json' }
        UseBasicParsing = $true
        TimeoutSec      = 120
    }

    if ($null -ne $Corpo) {
        $parametros['ContentType'] = 'application/json; charset=utf-8'
        $json = $Corpo | ConvertTo-Json -Depth 6 -Compress
        # ConvertTo-Json devolve UTF-16; sem converter, acentos chegam corrompidos.
        $parametros['Body'] = [System.Text.Encoding]::UTF8.GetBytes($json)
    }

    try {
        $resposta = Invoke-WebRequest @parametros -ErrorAction Stop

        $dados = $null
        if ($resposta.Content) {
            try { $dados = $resposta.Content | ConvertFrom-Json } catch { $dados = $resposta.Content }
        }

        return [pscustomobject]@{
            Sucesso = $true
            Status  = [int]$resposta.StatusCode
            Dados   = $dados
        }
    }
    catch {
        $status = 0
        $corpoErro = $_.Exception.Message

        if ($_.Exception.Response) {
            $status = [int]$_.Exception.Response.StatusCode
            try {
                $leitor = New-Object System.IO.StreamReader($_.Exception.Response.GetResponseStream())
                $texto = $leitor.ReadToEnd()
                $leitor.Close()
                if ($texto) { $corpoErro = $texto }
            } catch { }
        }

        return [pscustomobject]@{
            Sucesso = $false
            Status  = $status
            Dados   = $corpoErro
        }
    }
}

Export-ModuleMember -Function Get-CoretiConfiguracao, Invoke-CoretiApi
