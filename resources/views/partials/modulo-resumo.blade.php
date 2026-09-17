{{--
    Cartões de resumo.

    'alerta' pinta o cartão de âmbar e serve para número que pede conferência —
    por exemplo itens marcados "Em Uso" sem vínculo registrado, que somem da
    lista de disponíveis sem ninguém perceber.

    'moeda' formata o valor como real.
--}}
@php
    // Classe fixa por quantidade: o Tailwind remove classes montadas em tempo de execução.
    $colunas = match (count($cards)) {
        1, 2, 3 => 'lg:grid-cols-3',
        4 => 'lg:grid-cols-4',
        6 => 'lg:grid-cols-6',
        default => 'lg:grid-cols-5',
    };
@endphp
<div class="grid grid-cols-2 gap-3 sm:grid-cols-3 {{ $colunas }}">
    @foreach ($cards as $card)
        <div class="rounded-lg border {{ ($card['alerta'] ?? false) && $card['valor'] > 0 ? 'border-amber-300 bg-amber-50' : 'border-slate-200 bg-white' }} p-4 shadow-sm">
            <p class="text-xs font-medium uppercase tracking-wide {{ ($card['alerta'] ?? false) && $card['valor'] > 0 ? 'text-amber-800' : 'text-slate-500' }}">{{ $card['titulo'] }}</p>
            <p class="mt-1 {{ ($card['moeda'] ?? false) ? 'text-xl' : 'text-2xl' }} font-bold {{ ($card['alerta'] ?? false) && $card['valor'] > 0 ? 'text-amber-900' : 'text-slate-900' }}">{{ ($card['moeda'] ?? false) ? 'R$ '.number_format($card['valor'], 2, ',', '.') : number_format($card['valor'], 0, ',', '.') }}</p>
            @if (! empty($card['nota']))
                <p class="mt-1 text-xs {{ ($card['alerta'] ?? false) && $card['valor'] > 0 ? 'text-amber-700' : 'text-slate-400' }}">{{ $card['nota'] }}</p>
            @endif
        </div>
    @endforeach
</div>
