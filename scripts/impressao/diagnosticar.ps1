<#
.SYNOPSIS
    Verifica se o log de impressao esta acessivel e mede a velocidade de leitura.

.DESCRIPTION
    Nao conta o log inteiro de proposito: num canal com dezenas de milhares de
    registros isso demora tanto quanto a coleta, e o diagnostico deixaria de
    responder rapido — que e a unica razao dele existir.

    Em vez disso le uma AMOSTRA e mede o ritmo, o que ja diz se a coleta
    completa leva segundos ou minutos.
#>
[CmdletBinding()]
param(
    [int]$Dias = 7,
    [int]$Amostra = 2000
)

$ErrorActionPreference = 'Continue'
$canal = 'Microsoft-Windows-PrintService/Operational'

Write-Host "Servidor .......... $env:COMPUTERNAME"
Write-Host "Canal ............. $canal"
Write-Host ''

$log = Get-WinEvent -ListLog $canal -ErrorAction SilentlyContinue

if (-not $log) {
    Write-Host 'CANAL NAO ENCONTRADO nesta maquina.' -ForegroundColor Red
    exit 1
}

$usoMb = $log.FileSize / 1MB
$maxMb = $log.MaximumSizeInBytes / 1MB

Write-Host ("Habilitado ........ {0}" -f $log.IsEnabled)
Write-Host ("Registros no log .. {0:N0}" -f $log.RecordCount)
Write-Host ("Tamanho ........... {0:N1} MB de {1:N1} MB" -f $usoMb, $maxMb)

if (-not $log.IsEnabled) {
    Write-Host ''
    Write-Host 'O canal esta DESLIGADO: nao ha evento 307 para coletar.' -ForegroundColor Red
    Write-Host 'Ligue em: Visualizador de Eventos > Aplicativos e Servicos > Microsoft >'
    Write-Host '          Windows > PrintService > Operational > botao direito > Habilitar Log'
    exit 1
}

# Log cheio significa que ele ja esta descartando o mais antigo.
if ($maxMb -gt 0 -and ($usoMb / $maxMb) -ge 0.95) {
    Write-Host ''
    Write-Host 'ATENCAO: o log esta praticamente cheio e girando.' -ForegroundColor Yellow
    Write-Host 'Os eventos mais antigos estao sendo apagados para dar lugar aos novos.'
    Write-Host 'Colete com frequencia, ou aumente o tamanho maximo do canal.'
}

if ($log.RecordCount -eq 0) {
    Write-Host ''
    Write-Host 'Canal ligado, mas vazio. Se foi habilitado agora, aguarde as proximas impressoes.' -ForegroundColor Yellow
    exit 0
}

# ------------------------------------------------------------------ amostra

Write-Host ''
Write-Host "Lendo uma amostra de ate $Amostra eventos 307 (ultimos $Dias dias)..."

<#
    EventLogReader do .NET, nao Get-WinEvent.

    O cmdlet monta um objeto rico para cada evento, e esse custo por evento e
    o que faz a leitura de dezenas de milhares parecer travamento. O leitor do
    .NET entrega o mesmo dado sem essa camada.

    O filtro vai em XPath, resolvido pelo proprio servico de eventos: o
    timediff e em milissegundos.
#>
$ms = [int64]$Dias * 24 * 60 * 60 * 1000
$xpath = "*[System[(EventID=307) and TimeCreated[timediff(@SystemTime) <= $ms]]]"

$relogio = [Diagnostics.Stopwatch]::StartNew()
$lidos = 0

try {
    $consulta = New-Object System.Diagnostics.Eventing.Reader.EventLogQuery(
        $canal, [System.Diagnostics.Eventing.Reader.PathType]::LogName, $xpath)
    $leitor = New-Object System.Diagnostics.Eventing.Reader.EventLogReader($consulta)

    # min/max, e nao primeiro/ultimo: o leitor do .NET entrega do mais antigo
    # para o mais novo, ao contrario do Get-WinEvent, e assumir a ordem
    # trocaria os dois rotulos.
    $maisAntigo = $null
    $maisNovo = $null

    while ($lidos -lt $Amostra) {
        $ev = $leitor.ReadEvent()
        if ($null -eq $ev) { break }

        $quando = $ev.TimeCreated
        if ($null -eq $maisAntigo -or $quando -lt $maisAntigo) { $maisAntigo = $quando }
        if ($null -eq $maisNovo -or $quando -gt $maisNovo) { $maisNovo = $quando }

        $lidos++
        $ev.Dispose()
    }

    $leitor.Dispose()
} catch {
    Write-Host "Falha ao ler o log: $($_.Exception.Message)" -ForegroundColor Red
    exit 1
}

$relogio.Stop()
$segundos = [Math]::Max($relogio.Elapsed.TotalSeconds, 0.001)

Write-Host ("Eventos lidos ..... {0:N0}" -f $lidos)
Write-Host ("Tempo ............. {0:N1}s  ({1:N0} eventos/s)" -f $segundos, ($lidos / $segundos))

if ($lidos -gt 0) {
    Write-Host ("Mais antigo lido .. {0}" -f $maisAntigo)
    Write-Host ("Mais novo lido .... {0}" -f $maisNovo)
}

Write-Host ''

if ($lidos -eq 0) {
    Write-Host 'Nenhum evento 307 na janela. Houve impressao neste servidor nesse periodo?' -ForegroundColor Yellow
    exit 0
}

if ($lidos -lt $Amostra) {
    Write-Host "Leitura completa: a janela de $Dias dias tem $lidos eventos." -ForegroundColor Green
} else {
    Write-Host "Ha mais que $Amostra eventos na janela; a amostra parou no teto." -ForegroundColor Green
}

Write-Host 'Pode rodar o coletar_impressoes.ps1.' -ForegroundColor Green
