<#
.SYNOPSIS
    Descobre por qual OID SNMP cada impressora entrega o numero de serie.

.DESCRIPTION
    O OID padrao de impressora (RFC 3805) nao vale em todo equipamento: muitos
    fabricantes so publicam o serial num ramo proprio.

    Este script MOSTRA O ERRO de cada tentativa, de proposito. A mensagem
    distingue coisas muito diferentes:

      "variavel desconhecida"  -> o SNMP funciona, o OID e que nao existe ali
      timeout / sem resposta   -> SNMP bloqueado, desligado ou community errada

    Engolir esses erros esconde exatamente o que precisamos saber. So LE.
#>
[CmdletBinding()]
param(
    [int]$Amostra = 4,
    [string]$Community = 'public',
    [string]$Filtro = ''
)

$ErrorActionPreference = 'Continue'

$candidatos = [ordered]@{
    'sysDescr (existe SNMP?)'     = '1.3.6.1.2.1.1.1.0'
    'prtGeneralSerialNumber .1'   = '1.3.6.1.2.1.43.5.1.1.17.1'
    'prtGeneralSerialNumber .0'   = '1.3.6.1.2.1.43.5.1.1.17.0'
    'hrDeviceDescr'               = '1.3.6.1.2.1.25.3.2.1.3.1'
    'HP serial'                   = '1.3.6.1.4.1.11.2.3.9.4.2.1.1.3.3.0'
    'Samsung serial'              = '1.3.6.1.4.1.236.11.5.1.1.1.5.0'
    'Samsung serial (alt)'        = '1.3.6.1.4.1.236.11.5.11.53.31.1.1.1.0'
}

Write-Host "Servidor .......... $env:COMPUTERNAME"
Write-Host "Community ......... $Community"
Write-Host ''

$portas = @{}
Get-PrinterPort -ErrorAction SilentlyContinue | ForEach-Object {
    if ($_.PrinterHostAddress) { $portas[$_.Name] = $_.PrinterHostAddress }
}

$todas = Get-Printer -ErrorAction Stop | Where-Object { $portas.ContainsKey($_.PortName) }
if ($Filtro -ne '') { $todas = $todas | Where-Object { $_.Name -like "*$Filtro*" } }

$impressoras = $todas | Select-Object -First $Amostra

Write-Host "Com porta TCP/IP: $($todas.Count)  (testando $($impressoras.Count))"
Write-Host ''

foreach ($p in $impressoras) {
    $ip = $portas[$p.PortName]
    Write-Host ("=== {0}  ({1})" -f $p.Name, $ip)
    Write-Host ("    driver: {0}" -f $p.DriverName)

    # Uma conexao por impressora, reaproveitada em todos os OIDs — e assim que
    # a primeira versao funcionou.
    $snmp = $null
    try {
        $snmp = New-Object -ComObject olePrn.OleSNMP
        $snmp.Open($ip, $Community, 2, 3000)
    } catch {
        Write-Host ("    NAO ABRIU: {0}" -f $_.Exception.Message) -ForegroundColor Red
        Write-Host ''
        continue
    }

    foreach ($nome in $candidatos.Keys) {
        $oid = $candidatos[$nome]
        try {
            $v = $snmp.Get($oid)

            if ([string]::IsNullOrWhiteSpace($v)) {
                Write-Host ("    {0,-28} (vazio)" -f $nome)
            } else {
                $t = ([string]$v).Trim()
                $t = $t.Substring(0, [Math]::Min(58, $t.Length))
                Write-Host ("    {0,-28} {1}" -f $nome, $t) -ForegroundColor Green
            }
        } catch {
            # A mensagem e o dado mais util aqui.
            $msg = $_.Exception.Message -replace '\s+', ' '
            Write-Host ("    {0,-28} ERRO: {1}" -f $nome, $msg.Substring(0, [Math]::Min(70, $msg.Length)))
        }
    }

    try { $snmp.Close() } catch {}
    Write-Host ''
}

Write-Host 'Leitura do resultado:'
Write-Host '  - Se sysDescr respondeu, o SNMP esta OK e so falta o OID certo.'
Write-Host '  - Se TUDO deu erro de conexao, o SNMP nao chega no equipamento.'
Write-Host '  - Me mande esta saida: pelo sysDescr eu identifico o fabricante.'
