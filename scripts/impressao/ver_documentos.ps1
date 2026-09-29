<#
.SYNOPSIS
    Mostra o que o log traz no campo "nome do documento".

.DESCRIPTION
    O evento 307 tem um campo com o nome do documento (Param2). Quem preenche
    ele NAO e o Windows: e o aplicativo que mandou imprimir, atraves do driver.

    Por isso o conteudo varia. Alguns aplicativos mandam o nome do arquivo,
    outros mandam um titulo generico, e certos drivers universais descartam a
    informacao e gravam sempre a mesma coisa.

    Este script so LE e conta: nao envia nada e nao grava nada. Serve para
    decidir se vale a pena guardar esse campo.
#>
[CmdletBinding()]
param(
    [int]$Dias = 7,
    [int]$Amostra = 5000
)

$ErrorActionPreference = 'Stop'
$canal = 'Microsoft-Windows-PrintService/Operational'

Write-Host "Servidor .......... $env:COMPUTERNAME"
Write-Host "Amostra ........... ate $Amostra eventos dos ultimos $Dias dias"
Write-Host ''

$ms = [int64]$Dias * 24 * 60 * 60 * 1000
$xpath = "*[System[(EventID=307) and TimeCreated[timediff(@SystemTime) <= $ms]]]"

$consulta = New-Object System.Diagnostics.Eventing.Reader.EventLogQuery(
    $canal, [System.Diagnostics.Eventing.Reader.PathType]::LogName, $xpath)
$leitor = New-Object System.Diagnostics.Eventing.Reader.EventLogReader($consulta)

$contagem = @{}
$lidos = 0

while ($lidos -lt $Amostra) {
    $ev = $leitor.ReadEvent()
    if ($null -eq $ev) { break }

    $p = $ev.Properties

    if ($p.Count -ge 8) {
        # Indice 1 = Param2 = nome do documento.
        $nome = [string]$p[1].Value
        if ([string]::IsNullOrWhiteSpace($nome)) { $nome = '(vazio)' }

        if ($contagem.ContainsKey($nome)) { $contagem[$nome]++ } else { $contagem[$nome] = 1 }
        $lidos++
    }

    $ev.Dispose()
}

$leitor.Dispose()

Write-Host "Eventos lidos ..... $lidos"
Write-Host "Nomes distintos ... $($contagem.Count)"
Write-Host ''

if ($lidos -eq 0) {
    Write-Host 'Nenhum evento na janela.' -ForegroundColor Yellow
    exit 0
}

Write-Host '=== 20 nomes mais frequentes ==='
$contagem.GetEnumerator() |
    Sort-Object Value -Descending |
    Select-Object -First 20 |
    ForEach-Object {
        $pct = $_.Value / $lidos * 100
        Write-Host ("  {0,6} ({1,5:N1}%)  {2}" -f $_.Value, $pct, $_.Key)
    }

Write-Host ''

<#
    O veredito.

    Poucos nomes distintos para muitos eventos significa que o driver esta
    descartando o titulo e gravando sempre o mesmo texto — nesse caso guardar
    o campo nao acrescenta nada.
#>
$proporcao = $contagem.Count / $lidos

if ($contagem.Count -le 5) {
    Write-Host "Apenas $($contagem.Count) nome(s) distinto(s) em $lidos eventos." -ForegroundColor Yellow
    Write-Host 'O driver esta descartando o titulo: guardar este campo nao serve para nada.'
} elseif ($proporcao -lt 0.05) {
    Write-Host 'Quase todos os eventos repetem poucos nomes genericos.' -ForegroundColor Yellow
    Write-Host 'O campo tem pouco valor, mas alguns aplicativos parecem mandar o titulo.'
} else {
    Write-Host 'Ha variedade real de nomes: o campo carrega informacao util.' -ForegroundColor Green
    Write-Host 'ATENCAO: nome de documento revela conteudo (salarios, demissoes, contratos).'
    Write-Host 'Decida se quer guardar isso antes de ligar a coleta desse campo.'
}
