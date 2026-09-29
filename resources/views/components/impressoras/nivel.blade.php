@props(['supply'])

@php
    $nivel = $supply->percent_left;
    // Faixas alinhadas com os cartões do topo e com o filtro de situação.
    $cor = match (true) {
        $nivel === null => '#94a3b8',
        $nivel < \App\Models\Printer::NIVEL_CRITICO => '#e11d48',
        $nivel < \App\Models\Printer::NIVEL_ALERTA => '#d97706',
        default => '#059669',
    };

    // Toner tem cor; fusor, cilindro e rolete não — para esses o que
    // identifica a peça é o tipo.
    $rotulo = $supply->cor ? $supply->tipo_label.' '.$supply->cor : $supply->tipo_label;
@endphp

<div class="flex items-center gap-2">
    <div class="h-2 w-16 overflow-hidden rounded-full bg-slate-100 dark:bg-slate-800">
        <div class="h-full rounded-full" style="width: {{ max((int) $nivel, 2) }}%; background: {{ $cor }}"></div>
    </div>
    <span class="text-xs font-semibold" style="color: {{ $cor }}">{{ $nivel !== null ? $nivel.'%' : '—' }}</span>
    <span class="truncate text-xs text-slate-500" title="{{ $supply->descricao }}">{{ $rotulo }}</span>
</div>
