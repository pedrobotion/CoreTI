{{--
    Gráfico de linha "Incidentes — Criados × Resolvidos (3 meses)".
    Renderizado em 2 proporções (mobile alto × desktop largo) via breakpoint.
    Params: $comparativoMensal, $fmt, $W, $H, $padL, $padR, $padT, $padB,
            $fsFill (bool: usa h-full em tela cheia), $maxH (classe max-h p/ fora da tela cheia).
--}}
@php
    $cm = array_values($comparativoMensal);
    $n = count($cm);
    $maxV = max(1, collect($cm)->flatMap(fn ($m) => [$m['incidentes'], $m['incResolvidos']])->max());
    $step = max(5, (int) ceil($maxV / 4 / 10) * 10);
    $topo = $step * 4;
    $plotW = $W - $padL - $padR; $plotH = $H - $padT - $padB;
    $px = fn ($i) => round($padL + ($n > 1 ? $i / ($n - 1) * $plotW : $plotW / 2), 1);
    $py = fn ($v) => round($padT + $plotH - ($v / $topo) * $plotH, 1);
    $linha = fn ($c) => implode(' ', array_map(fn ($i) => $px($i).','.$py($cm[$i][$c]), range(0, $n - 1)));
    $series = [['c' => 'incidentes', 'cor' => '#f43f5e', 'acima' => true], ['c' => 'incResolvidos', 'cor' => '#38bdf8', 'acima' => false]];
    $maxH = $maxH ?? 'max-h-[40vh]';
@endphp
@if ($fsFill ?? false)
    <svg viewBox="0 0 {{ $W }} {{ $H }}" preserveAspectRatio="xMidYMid meet" class="w-full" :class="fs ? 'h-full' : '{{ $maxH }}'">
@else
    <svg viewBox="0 0 {{ $W }} {{ $H }}" preserveAspectRatio="xMidYMid meet" class="w-full {{ $maxH }}">
@endif
        @for ($g = 0; $g <= 4; $g++)
            @php $gy = round($padT + $plotH - $g / 4 * $plotH, 1); @endphp
            <line x1="{{ $padL }}" y1="{{ $gy }}" x2="{{ $W - $padR }}" y2="{{ $gy }}" stroke="#141c2b" stroke-width="1"/>
            <text x="{{ $padL - 7 }}" y="{{ $gy + 3 }}" text-anchor="end" fill="#3f4a5e" style="font-size:9px">{{ $g * $step }}</text>
        @endfor
        <polyline points="{{ $linha('incidentes') }}" fill="none" stroke="#f43f5e" stroke-width="1.5" stroke-linejoin="round" stroke-linecap="round"/>
        <polyline points="{{ $linha('incResolvidos') }}" fill="none" stroke="#38bdf8" stroke-width="1.5" stroke-linejoin="round" stroke-linecap="round"/>
        @foreach ($series as $s)
            @foreach ($cm as $i => $m)
                <circle cx="{{ $px($i) }}" cy="{{ $py($m[$s['c']]) }}" r="2.6" fill="{{ $s['cor'] }}"/>
                <text x="{{ $px($i) }}" y="{{ $py($m[$s['c']]) + ($s['acima'] ? -8 : 15) }}" text-anchor="middle" fill="{{ $s['cor'] }}" style="font-size:10px;font-weight:600">{{ $fmt($m[$s['c']]) }}</text>
            @endforeach
        @endforeach
        @foreach ($cm as $i => $m)
            <text x="{{ $px($i) }}" y="{{ $H - 18 }}" text-anchor="middle" fill="#e2e8f0" style="font-size:12px;font-weight:700">{{ $m['rotulo'] }}</text>
            <text x="{{ $px($i) }}" y="{{ $H - 5 }}" text-anchor="middle" fill="#7dd3fc" style="font-size:10px;font-weight:700">{{ $fmt($m['incPerf'],1) }}% resolv.</text>
        @endforeach
    </svg>
