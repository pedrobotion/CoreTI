[CmdletBinding(SupportsShouldProcess = $true)]
param(
    [Parameter(Mandatory = $true)]
    [string]$InputCsv,

    [string]$OutputReport = ".\ad_users_import_result.csv",

    [switch]$Apply
)

$ErrorActionPreference = 'Stop'

if (-not (Get-Module -ListAvailable -Name ActiveDirectory)) {
    throw 'O modulo ActiveDirectory nao esta instalado/disponivel nesta maquina.'
}

Import-Module ActiveDirectory

if (-not (Test-Path -LiteralPath $InputCsv)) {
    throw "CSV nao encontrado: $InputCsv"
}

function Normalize-Text([object]$Value) {
    if ($null -eq $Value) { return '' }
    return ([string]$Value).Trim()
}

function Normalize-CostCenter([object]$Value) {
    $raw = Normalize-Text $Value
    if ([string]::IsNullOrWhiteSpace($raw)) { return '' }

    if ($raw -match '^\s*(\d+)\s*[.]\s*(\d+)\s*$') {
        return ('{0:D2}.{1:D3}' -f [int]$Matches[1], [int]$Matches[2])
    }

    return $raw
}

$rows = Import-Csv -LiteralPath $InputCsv -Delimiter ';' -Encoding UTF8
$results = New-Object System.Collections.Generic.List[object]
$seen = @{}

foreach ($row in $rows) {
    $username = Normalize-Text $row.nome_usuario
    $matricula = Normalize-Text $row.matricula
    $costCenter = Normalize-CostCenter $row.centro_custo
    $cargo = Normalize-Text $row.Cargo

    if ([string]::IsNullOrWhiteSpace($username)) {
        $results.Add([pscustomobject]@{
            nome_usuario = ''
            resultado = 'ignorado'
            motivo = 'nome_usuario ausente'
        })
        continue
    }

    $key = $username.ToLowerInvariant()
    if ($seen.ContainsKey($key)) {
        $results.Add([pscustomobject]@{
            nome_usuario = $username
            resultado = 'ignorado'
            motivo = 'nome_usuario duplicado no CSV'
        })
        continue
    }
    $seen[$key] = $true

    try {
        $adUser = Get-ADUser -Identity $username -Properties telephoneNumber, physicalDeliveryOfficeName, description
    }
    catch {
        $results.Add([pscustomobject]@{
            nome_usuario = $username
            resultado = 'nao_encontrado'
            motivo = $_.Exception.Message
        })
        continue
    }

    $changes = @{}

    # Matricula: armazenada no campo telefone, conforme o procedimento atual.
    if ($matricula -ne '' -and $matricula -ne (Normalize-Text $adUser.telephoneNumber)) {
        $changes['telephoneNumber'] = $matricula
    }

    # Centro de custo: armazenado no campo Escritorio do AD.
    if ($costCenter -ne '' -and $costCenter -ne (Normalize-Text $adUser.physicalDeliveryOfficeName)) {
        $changes['physicalDeliveryOfficeName'] = $costCenter
    }

    # Cargo: armazenado na Descricao do usuario no AD.
    if ($cargo -ne '' -and $cargo -ne (Normalize-Text $adUser.description)) {
        $changes['description'] = $cargo
    }

    if ($changes.Count -eq 0) {
        $results.Add([pscustomobject]@{
            nome_usuario = $username
            resultado = 'ja_correto'
            motivo = ''
        })
        continue
    }

    $result = 'simulado'
    if ($Apply) {
        Set-ADUser -Identity $adUser.DistinguishedName -Replace $changes
        $result = 'atualizado'
    }

    $results.Add([pscustomobject]@{
        nome_usuario = $username
        matricula = $matricula
        centro_custo = $costCenter
        cargo = $cargo
        campos = ($changes.Keys -join ',')
        resultado = $result
        motivo = if ($Apply) { '' } else { 'Execute com -Apply para gravar' }
    })
}

$results | Export-Csv -LiteralPath $OutputReport -Delimiter ';' -NoTypeInformation -Encoding UTF8

$results | Group-Object resultado | Sort-Object Name | ForEach-Object {
    Write-Host ("{0}: {1}" -f $_.Name, $_.Count)
}

Write-Host "Relatorio: $OutputReport"
if (-not $Apply) {
    Write-Warning 'Nenhuma alteracao foi gravada. Esta foi uma simulacao.'
}
