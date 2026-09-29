<#
.SYNOPSIS
    Envia o log de impressao deste servidor para o CoreTI.

.DESCRIPTION
    Le o Evento 307 do canal Microsoft-Windows-PrintService/Operational, que e
    onde o Windows registra cada trabalho impresso, e posta no CoreTI em lotes.

    O sentido e sempre servidor -> CoreTI. O CoreTI nao abre conexao com este
    servidor e nao guarda credencial de dominio: quem le o log e esta tarefa,
    com a conta que ja tem acesso a ele.

    Reenviar a mesma janela e inofensivo: o CoreTI identifica o evento por
    servidor + trabalho + instante + usuario e descarta o que ja tem. Por isso
    a execucao pode se sobrepor sem medo.

.PARAMETER Dias
    Quantos dias buscar no log. Sem informar, usa o que o CoreTI responder.

.PARAMETER MaxEventos
    Teto de eventos por execucao. Impede que a primeira carga, num log com
    meses acumulados, leia tudo de uma vez. O que sobrar entra na execucao
    seguinte, porque o script retoma de onde parou.

.PARAMETER Simular
    Le e mostra o resumo, sem enviar nada.

.EXAMPLE
    .\coletar_impressoes.ps1
    .\coletar_impressoes.ps1 -Dias 30
    .\coletar_impressoes.ps1 -Simular
#>
[CmdletBinding()]
param(
    [int]$Dias = 0,
    [int]$MaxEventos = 50000,
    [switch]$Simular
)

$ErrorActionPreference = 'Stop'
[Net.ServicePointManager]::SecurityProtocol = [Net.SecurityProtocolType]::Tls12

# ---------------------------------------------------------------- configuracao

function Resolve-PastaDoScript {
    if ($PSScriptRoot) { return $PSScriptRoot }
    if ($MyInvocation.MyCommand.Path) { return Split-Path -Parent $MyInvocation.MyCommand.Path }
    throw 'Nao foi possivel descobrir a pasta do script. Rode pelo arquivo .ps1, nao colando o conteudo no console.'
}

$pasta = Resolve-PastaDoScript
$arquivoConfig = Join-Path $pasta 'coreti.impressao.json'

if (-not (Test-Path -LiteralPath $arquivoConfig)) {
    throw "Configuracao nao encontrada: $arquivoConfig. Crie o arquivo com url e token."
}

$config = Get-Content -LiteralPath $arquivoConfig -Raw -Encoding UTF8 | ConvertFrom-Json

foreach ($campo in @('url', 'token')) {
    if ([string]::IsNullOrWhiteSpace($config.$campo)) {
        throw "O campo '$campo' esta vazio em $arquivoConfig."
    }
}

$baseUrl  = $config.url.TrimEnd('/')
$servidor = if ([string]::IsNullOrWhiteSpace($config.servidor)) { $env:COMPUTERNAME } else { $config.servidor }
$cabecalho = @{ 'X-Coreti-Impressao-Token' = $config.token }

function Invoke-CoretiApi {
    param([string]$Metodo, [string]$Caminho, [object]$Corpo)

    $uri = "$baseUrl$Caminho"

    if ($null -eq $Corpo) {
        return Invoke-RestMethod -Method $Metodo -Uri $uri -Headers $cabecalho -TimeoutSec 120
    }

    # Serializa em UTF-8 na mao: o Invoke-RestMethod manda latin-1 e nomes
    # com acento chegariam corrompidos do outro lado.
    $json  = $Corpo | ConvertTo-Json -Depth 6 -Compress
    $bytes = [Text.Encoding]::UTF8.GetBytes($json)

    return Invoke-RestMethod -Method $Metodo -Uri $uri -Headers $cabecalho `
        -ContentType 'application/json; charset=utf-8' -Body $bytes -TimeoutSec 300
}

# ------------------------------------------------------------ janela de coleta

$estado = $null
try {
    $estado = Invoke-CoretiApi -Metodo 'Get' -Caminho "/api/impressao/ultimo-evento?servidor=$servidor"
} catch {
    Write-Warning "Nao foi possivel perguntar ao CoreTI ate onde ja enviamos: $($_.Exception.Message)"
}

if ($Dias -le 0) {
    $Dias = if ($estado -and $estado.dias_padrao) { [int]$estado.dias_padrao } else { 7 }
}

$inicio = (Get-Date).AddDays(-$Dias)

<#
    Retoma de onde parou, com uma hora de folga para tras.

    A folga cobre evento que entrou no log depois da ultima leitura. Repetir
    algumas horas nao custa nada: o CoreTI descarta o que ja tem.
#>
if ($estado -and $estado.ultimo_evento) {
    $retomada = ([datetime]$estado.ultimo_evento).ToLocalTime().AddHours(-1)
    if ($retomada -gt $inicio) { $inicio = $retomada }
}

Write-Host "Servidor .......... $servidor"
Write-Host "Coletando desde ... $($inicio.ToString('dd/MM/yyyy HH:mm'))"

# ------------------------------------------------------------------ leitura

<#
    Leitura pelo EventLogReader do .NET, nao pelo Get-WinEvent.

    O cmdlet monta um objeto rico para cada evento; com dezenas de milhares
    de registros esse custo por evento domina o tempo e a tela fica parada a
    ponto de parecer travamento. Foi o que aconteceu na primeira tentativa,
    num canal com 48 mil registros.

    O filtro vai em XPath e e resolvido pelo proprio servico de eventos, que
    so devolve o que interessa. timediff e em milissegundos.
#>
<#
    O leitor entrega do mais ANTIGO para o mais novo — ao contrario do
    Get-WinEvent. Isso favorece a retomada: se o teto for atingido, ficamos
    com o inicio da janela e a proxima execucao continua dali, em vez de
    pular um pedaco no meio.
#>
$ms = [int64][Math]::Ceiling(((Get-Date) - $inicio).TotalMilliseconds)
$xpath = "*[System[(EventID=307) and TimeCreated[timediff(@SystemTime) <= $ms]]]"

Write-Host 'Lendo o log do Windows...'

$eventos = New-Object System.Collections.Generic.List[object]
$proximoAviso = 1000
$relogio = [Diagnostics.Stopwatch]::StartNew()

try {
    $consulta = New-Object System.Diagnostics.Eventing.Reader.EventLogQuery(
        'Microsoft-Windows-PrintService/Operational',
        [System.Diagnostics.Eventing.Reader.PathType]::LogName,
        $xpath)

    $leitor = New-Object System.Diagnostics.Eventing.Reader.EventLogReader($consulta)
} catch {
    throw "Nao foi possivel abrir o log de impressao: $($_.Exception.Message)"
}

while ($eventos.Count -lt $MaxEventos) {
    $ev = $leitor.ReadEvent()
    if ($null -eq $ev) { break }

    <#
        Campos do evento 307 (DocumentPrinted), na ordem:
          0 trabalho   1 documento   2 usuario   3 estacao
          4 impressora 5 porta       6 bytes     7 paginas

        Ler por indice nao perde robustez: no proprio esquema do evento os
        campos se chamam Param1..Param8 — o nome ja e a posicao.
    #>
    $p = $ev.Properties

    if ($p.Count -ge 8) {
        $eventos.Add([pscustomobject]@{
            impresso_em   = $ev.TimeCreated.ToString('o')   # ISO 8601 com offset
            usuario       = [string]$p[2].Value
            estacao       = [string]$p[3].Value
            impressora    = [string]$p[4].Value
            porta         = [string]$p[5].Value
            doc_numero    = [string]$p[0].Value
            paginas       = [int]$p[7].Value
            tamanho_bytes = [int64]$p[6].Value
        })
    }

    $ev.Dispose()

    # Sinal de vida: sem isto a tela fica parada e parece travamento.
    # Marca de proximo aviso em vez de modulo: evento descartado nao muda o
    # contador, e o modulo repetiria a mesma linha a cada volta.
    if ($eventos.Count -ge $proximoAviso) {
        Write-Host ("  lidos {0}..." -f $eventos.Count)
        $proximoAviso += 1000
    }
}

$leitor.Dispose()
$relogio.Stop()

Write-Host ("Tempo de leitura .. {0:N1}s" -f $relogio.Elapsed.TotalSeconds)

if ($eventos.Count -ge $MaxEventos) {
    Write-Host "Teto de $MaxEventos eventos atingido; o restante entra na proxima execucao." -ForegroundColor Yellow
}

Write-Host "Eventos lidos ..... $($eventos.Count)"

if ($Simular) {
    Write-Host ''
    Write-Host 'Simulacao: nada foi enviado. Amostra:'
    $eventos | Select-Object -First 5 | Format-Table -AutoSize
    exit 0
}

# -------------------------------------------------------------------- envio

$tamanhoLote = 500
$totais = @{ gravados = 0; repetidos = 0; ignorados = 0 }

<#
    Dia sem impressao tambem reporta.

    Fim de semana e feriado tem zero evento legitimamente. Sair calado faria
    isso parecer coletor parado no painel do CoreTI — e como o log do Windows
    retem poucos dias, um alarme que nao dispara custa historico perdido.
    Um lote vazio e o "estou vivo".
#>
if ($eventos.Count -eq 0) {
    try {
        Invoke-CoretiApi -Metodo 'Post' -Caminho '/api/impressao/lote' -Corpo @{
            servidor = $servidor
            eventos  = @()
        } | Out-Null

        Write-Host 'Nenhuma impressao na janela; contato registrado no CoreTI.'
    } catch {
        Write-Warning "Nao foi possivel registrar o contato: $($_.Exception.Message)"
        exit 1
    }

    exit 0
}

for ($i = 0; $i -lt $eventos.Count; $i += $tamanhoLote) {
    $lote = $eventos.GetRange($i, [Math]::Min($tamanhoLote, $eventos.Count - $i))

    try {
        $r = Invoke-CoretiApi -Metodo 'Post' -Caminho '/api/impressao/lote' -Corpo @{
            servidor = $servidor
            eventos  = $lote
        }

        $totais.gravados  += [int]$r.gravados
        $totais.repetidos += [int]$r.repetidos
        $totais.ignorados += [int]$r.ignorados

        Write-Host ("  lote {0,5}-{1,-5} gravados={2,-5} repetidos={3}" -f ($i + 1), ($i + $lote.Count), $r.gravados, $r.repetidos)
    } catch {
        # Um lote que falha nao derruba os demais: a proxima execucao volta
        # a pegar esta janela, porque o reenvio e inofensivo.
        Write-Warning "Lote a partir de $($i + 1) falhou: $($_.Exception.Message)"
    }
}

Write-Host ''
Write-Host "Gravados .......... $($totais.gravados)"
Write-Host "Repetidos ......... $($totais.repetidos)  (ja estavam no CoreTI)"
Write-Host "Ignorados ......... $($totais.ignorados)"
