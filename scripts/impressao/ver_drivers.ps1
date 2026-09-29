<#
.SYNOPSIS
    Lista os drivers de impressao em uso neste servidor.

.DESCRIPTION
    Quem preenche o nome do documento no evento 307 e o aplicativo, atraves do
    driver. Driver universal costuma descartar esse dado e gravar sempre o
    mesmo texto; driver nativo do fabricante costuma repassar o titulo.

    Este script so LE. Serve para saber se ha um driver dominante e se vale
    testar um nativo numa impressora.
#>
[CmdletBinding()]
param()

$ErrorActionPreference = 'Stop'

Write-Host "Servidor .......... $env:COMPUTERNAME"
Write-Host ''

$impressoras = Get-Printer -ErrorAction Stop

Write-Host "Impressoras ....... $($impressoras.Count)"
Write-Host ''
Write-Host '=== drivers em uso ==='

$impressoras |
    Group-Object DriverName |
    Sort-Object Count -Descending |
    ForEach-Object {
        Write-Host ("  {0,4}x  {1}" -f $_.Count, $_.Name)
    }

Write-Host ''
Write-Host '=== processador de impressao ==='

$impressoras |
    Group-Object PrintProcessor |
    Sort-Object Count -Descending |
    ForEach-Object {
        Write-Host ("  {0,4}x  {1}" -f $_.Count, $_.Name)
    }

Write-Host ''
Write-Host 'Driver com "Universal", "Global" ou "Class" no nome costuma ser o que'
Write-Host 'descarta o titulo do documento.'
