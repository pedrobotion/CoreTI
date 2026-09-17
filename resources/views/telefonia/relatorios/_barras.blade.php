{{--
    Barras horizontais para comparar magnitude entre categorias.

    Uma cor só para todas as barras, de propósito: a categoria (operadora,
    unidade) não tem ordem natural, e pintar cada barra de um tom diferente
    conforme o valor duplicaria o comprimento como cor — a barra já diz o
    tamanho. Cor aqui não carrega informação, só dá corpo à marca.

    Série única, então não há legenda: o título já diz o que está medido.
    Cada barra leva rótulo direto, então o valor nunca depende de passar o
    mouse nem de ler um eixo.

    Espera $itens com: rotulo, valor, quantidade (opcional), detalhe (opcional).
--}}
@php
    $maximo = collect($itens)->max('valor') ?: 1;
    $totalGeral = collect($itens)->sum('valor');
@endphp

<ul class="space-y-3">
    @forelse ($itens as $item)
        @php $proporcao = $maximo > 0 ? max(($item['valor'] / $maximo) * 100, 0.8) : 0; @endphp
        <li>
            <div class="flex items-baseline justify-between gap-3">
                <span class="truncate text-sm text-slate-700">
                    {{ $item['rotulo'] }}
                    @isset($item['detalhe'])
                        <span class="text-xs text-slate-400">{{ $item['detalhe'] }}</span>
                    @endisset
                </span>
                <span class="shrink-0 font-mono text-sm font-semibold text-slate-900">
                    R$ {{ number_format($item['valor'], 2, ',', '.') }}
                    @if ($totalGeral > 0)
                        <span class="ml-1 font-sans text-xs font-normal text-slate-400">{{ number_format($item['valor'] / $totalGeral * 100, 1, ',', '.') }}%</span>
                    @endif
                </span>
            </div>
            <div class="mt-1 h-2 w-full overflow-hidden rounded-full bg-slate-100"
                 title="{{ $item['rotulo'] }}: R$ {{ number_format($item['valor'], 2, ',', '.') }}@isset($item['quantidade']) · {{ $item['quantidade'] }} linha(s)@endisset">
                <div class="h-full rounded-full" style="width: {{ $proporcao }}%; background-color: #0369a1"></div>
            </div>
        </li>
    @empty
        <li class="rounded-md bg-slate-50 px-4 py-6 text-center text-sm text-slate-500">Sem dados para exibir.</li>
    @endforelse
</ul>
