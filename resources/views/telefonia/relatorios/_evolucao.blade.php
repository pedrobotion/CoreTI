{{--
    Evolução do valor cobrado, mês a mês.

    Série única ao longo do tempo: linha com área de apoio, na mesma cor das
    barras. Sem legenda (uma série só) e sem rótulo em todo ponto — só o
    primeiro, o último e o maior, para o gráfico não virar uma parede de
    números. O valor exato de qualquer mês sai no tooltip e na tabela abaixo.

    Espera $pontos com: competencia (Carbon|string), custo, quantidade.
--}}
@php
    $pontos = collect($pontos)->values();
    $n = $pontos->count();
    $largura = 720;
    $altura = 200;
    $margemX = 8;
    $margemY = 16;
    $maximo = $n ? max($pontos->max('custo'), 0.01) : 1;

    $coordenadas = $pontos->map(function ($ponto, $i) use ($n, $largura, $altura, $margemX, $margemY, $maximo) {
        $x = $n > 1 ? $margemX + ($i / ($n - 1)) * ($largura - 2 * $margemX) : $largura / 2;
        $y = $altura - $margemY - (($ponto->custo / $maximo) * ($altura - 2 * $margemY));
        return ['x' => round($x, 1), 'y' => round($y, 1), 'ponto' => $ponto];
    });

    $indiceMaior = $pontos->search(fn ($p) => $p->custo === $pontos->max('custo'));
    $destaques = array_filter([0, $n - 1, $indiceMaior], fn ($i) => $i !== false && $i >= 0);
@endphp

@if ($n === 0)
    <div class="rounded-md bg-slate-50 px-4 py-10 text-center">
        <p class="text-sm text-slate-500">Nenhuma fatura importada ainda.</p>
        <p class="mt-1 text-xs text-slate-400">A evolução mensal aparece aqui depois da primeira importação.</p>
    </div>
@elseif ($n === 1)
    {{-- Um ponto não é uma tendência: mostrar como número é mais honesto que desenhar uma linha reta. --}}
    @php $unico = $pontos->first(); @endphp
    <div class="rounded-md bg-slate-50 px-4 py-8 text-center">
        <p class="text-xs uppercase tracking-wide text-slate-500">{{ \Illuminate\Support\Carbon::parse($unico->competencia)->format('m/Y') }}</p>
        <p class="mt-1 text-3xl font-bold text-slate-900">R$ {{ number_format($unico->custo, 2, ',', '.') }}</p>
        <p class="mt-1 text-xs text-slate-400">{{ $unico->quantidade }} linha(s) · a comparação entre meses aparece a partir da segunda fatura</p>
    </div>
@else
    <svg viewBox="0 0 {{ $largura }} {{ $altura }}" class="h-52 w-full" role="img"
         aria-label="Evolução do valor cobrado por competência">
        {{-- Grade discreta: três referências, hairline, para não competir com a linha. --}}
        @foreach ([0.25, 0.5, 0.75] as $fracao)
            @php $y = $altura - $margemY - $fracao * ($altura - 2 * $margemY); @endphp
            <line x1="{{ $margemX }}" y1="{{ $y }}" x2="{{ $largura - $margemX }}" y2="{{ $y }}" stroke="#e2e8f0" stroke-width="1" />
        @endforeach

        <path d="M {{ $coordenadas->first()['x'] }},{{ $altura - $margemY }} {{ $coordenadas->map(fn ($c) => "L {$c['x']},{$c['y']}")->implode(' ') }} L {{ $coordenadas->last()['x'] }},{{ $altura - $margemY }} Z"
              fill="#0369a1" fill-opacity="0.08" />

        <polyline points="{{ $coordenadas->map(fn ($c) => "{$c['x']},{$c['y']}")->implode(' ') }}"
                  fill="none" stroke="#0369a1" stroke-width="2" stroke-linejoin="round" stroke-linecap="round" />

        @foreach ($coordenadas as $i => $c)
            <circle cx="{{ $c['x'] }}" cy="{{ $c['y'] }}" r="4" fill="#0369a1" stroke="#fff" stroke-width="2" />
            {{-- Alvo de toque maior que a marca, para o tooltip pegar fácil. --}}
            <circle cx="{{ $c['x'] }}" cy="{{ $c['y'] }}" r="12" fill="transparent">
                <title>{{ \Illuminate\Support\Carbon::parse($c['ponto']->competencia)->format('m/Y') }}: R$ {{ number_format($c['ponto']->custo, 2, ',', '.') }} · {{ $c['ponto']->quantidade }} linha(s)</title>
            </circle>
            @if (in_array($i, $destaques, true))
                <text x="{{ $c['x'] }}" y="{{ max($c['y'] - 10, 10) }}" text-anchor="middle" font-size="10" fill="#334155" font-weight="600">
                    {{ number_format($c['ponto']->custo, 0, ',', '.') }}
                </text>
            @endif
        @endforeach
    </svg>

    <div class="mt-1 flex justify-between text-xs text-slate-400">
        <span>{{ \Illuminate\Support\Carbon::parse($pontos->first()->competencia)->format('m/Y') }}</span>
        <span>{{ \Illuminate\Support\Carbon::parse($pontos->last()->competencia)->format('m/Y') }}</span>
    </div>
@endif
