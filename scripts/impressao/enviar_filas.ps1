<#
.SYNOPSIS
    Envia ao CoreTI a lista de filas de impressao deste servidor.

.DESCRIPTION
    So lista o que o Windows ja sabe: nome da fila, IP configurado na porta
    TCP/IP e driver. NAO fala SNMP — quem le o numero de serie e o CoreTI, que
    alcanca as impressoras direto. Por isso este script nao depende do
    componente SNMP do Windows, que nao esta funcional nos servidores.

    O CoreTI cruza o serial (lido por ele) com a base da Simpress e aponta as
    divergencias de IP na tela de conciliacao.

.EXAMPLE
    .\enviar_filas.ps1
    .\enviar_filas.ps1 -Simular
#>
[CmdletBinding()]
param([switch]$Simular)

$ErrorActionPreference = 'Stop'
[Net.ServicePointManager]::SecurityProtocol = [Net.SecurityProtocolType]::Tls12

function Resolve-PastaDoScript {
    if ($PSScriptRoot) { return $PSScriptRoot }
    if ($MyInvocation.MyCommand.Path) { return Split-Path -Parent $MyInvocation.MyCommand.Path }
    throw 'Nao foi possivel descobrir a pasta do script. Rode pelo arquivo .ps1.'
}

$pasta = Resolve-PastaDoScript
$arquivoConfig = Join-Path $pasta 'coreti.impressao.json'

if (-not (Test-Path -LiteralPath $arquivoConfig)) {
    throw "Configuracao nao encontrada: $arquivoConfig"
}

$config = Get-Content -LiteralPath $arquivoConfig -Raw -Encoding UTF8 | ConvertFrom-Json
$baseUrl = $config.url.TrimEnd('/')
$servidor = if ([string]::IsNullOrWhiteSpace($config.servidor)) { $env:COMPUTERNAME } else { $config.servidor }
$cabecalho = @{ 'X-Coreti-Impressao-Token' = $config.token }

# IP configurado em cada porta TCP/IP, indexado pelo nome da porta.
$portas = @{}
Get-PrinterPort -ErrorAction SilentlyContinue | ForEach-Object {
    if ($_.PrinterHostAddress) { $portas[$_.Name] = $_.PrinterHostAddress }
}

$filas = New-Object System.Collections.Generic.List[object]

foreach ($p in (Get-Printer -ErrorAction Stop)) {
    $ip = $null
    if ($portas.ContainsKey($p.PortName)) { $ip = $portas[$p.PortName] }

    $filas.Add([pscustomobject]@{
        fila     = $p.Name
        ip_porta = $ip
        driver   = $p.DriverName
    })
}

Write-Host "Servidor .......... $servidor"
Write-Host "Filas encontradas . $($filas.Count)"
Write-Host "Com IP na porta ... $(($filas | Where-Object { $_.ip_porta }).Count)"

if ($Simular) {
    Write-Host ''
    Write-Host 'Simulacao: nada foi enviado. Amostra:'
    $filas | Select-Object -First 8 | Format-Table -AutoSize
    exit 0
}

$json = @{ servidor = $servidor; filas = $filas } | ConvertTo-Json -Depth 5 -Compress
$bytes = [Text.Encoding]::UTF8.GetBytes($json)

try {
    $r = Invoke-RestMethod -Method Post -Uri "$baseUrl/api/impressao/filas" `
        -Headers $cabecalho -ContentType 'application/json; charset=utf-8' -Body $bytes -TimeoutSec 120

    Write-Host ''
    Write-Host "Recebidas ......... $($r.recebidas)"
    Write-Host "Atualizadas ....... $($r.atualizadas)"
    Write-Host ''
    Write-Host 'O CoreTI vai ler o serial por SNMP e conciliar com a Simpress.'
} catch {
    throw "Falha ao enviar as filas: $($_.Exception.Message)"
}
