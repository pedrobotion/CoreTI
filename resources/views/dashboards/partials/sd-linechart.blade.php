@php
    // Props: $id (único), $titulo, $cor (hex), $pontos = [['rotulo'=>, 'valor'=>], ...]
    $pts = array_values($pontos);
    $n = count($pts);
    $max = max(1, collect($pts)->max('valor'));
    $step = max(5, (int) ceil($max / 4 / 5) * 5);
    $topo = $step * 4;
    $W = 360; $H = 172; $padL = 34; $padR = 14; $padT = 16; $padB = 34;
    $plotW = $W - $padL - $padR; $plotH = $H - $padT - $padB;
    $base = $padT + $plotH;
    $px = fn ($i) => round($padL + ($n > 1 ? $i / ($n - 1) * $plotW : $plotW / 2), 1);
    $py = fn ($v) => round($padT + $plotH - ($v / $topo) * $plotH, 1);
    $linha = implode(' ', array_map(fn ($i) => $px($i).','.$py($pts[$i]['valor']), range(0, $n - 1)));
    $areaPts = $linha.' '.$px($n - 1).','.$base.' '.$px(0).','.$base;
@endphp
<div class="rounded-lg border border-white/10 bg-white/[0.02] p-3">
    <div class="mb-1 flex items-center gap-2 text-xs font-bold text-slate-200">
        <span class="h-2.5 w-2.5 rounded-full" style="background-color: {{ $cor }}"></span>{{ $titulo }}
    </div>
    <svg viewBox="0 0 {{ $W }} {{ $H }}" class="w-full">
        <defs>
            <linearGradient id="grad-{{ $id }}" x1="0" y1="0" x2="0" y2="1">
                <stop offset="0%" stop-color="{{ $cor }}" stop-opacity="0.30"/>
                <stop offset="100%" stop-color="{{ $cor }}" stop-opacity="0"/>
            </linearGradient>
        </defs>
        @for ($g = 0; $g <= 4; $g++)
            @php $gy = round($padT + $plotH - $g / 4 * $plotH, 1); @endphp
            <line x1="{{ $padL }}" y1="{{ $gy }}" x2="{{ $W - $padR }}" y2="{{ $gy }}" stroke="#1e293b" stroke-width="1"/>
            <text x="{{ $padL - 6 }}" y="{{ $gy + 3 }}" text-anchor="end" fill="#475569" style="font-size:9px">{{ $g * $step }}</text>
        @endfor
        <polygon points="{{ $areaPts }}" fill="url(#grad-{{ $id }})"/>
        <polyline points="{{ $linha }}" fill="none" stroke="{{ $cor }}" stroke-width="2.5" stroke-linejoin="round" stroke-linecap="round"/>
        @foreach ($pts as $i => $p)
            <circle cx="{{ $px($i) }}" cy="{{ $py($p['valor']) }}" r="4" fill="#0b1220" stroke="{{ $cor }}" stroke-width="2.5"/>
            <text x="{{ $px($i) }}" y="{{ $py($p['valor']) - 9 }}" text-anchor="middle" fill="{{ $cor }}" style="font-size:11px;font-weight:800">{{ number_format($p['valor'], 0, ',', '.') }}</text>
            <text x="{{ $px($i) }}" y="{{ $H - 8 }}" text-anchor="middle" fill="#cbd5e1" style="font-size:11px;font-weight:700">{{ $p['rotulo'] }}</text>
        @endforeach
    </svg>
</div>
