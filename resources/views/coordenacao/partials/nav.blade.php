@php
    $active = $active ?? 'dashboard';
    $tabs = [
        ['key' => 'dashboard', 'route' => 'coordenacao.dashboard', 'label' => 'Visão geral'],
        ['key' => 'products', 'route' => 'coordenacao.products.index', 'label' => 'Produtos de TI'],
        ['key' => 'rateio', 'route' => 'coordenacao.rateio.index', 'label' => 'Rateio mensal'],
        ['key' => 'collaborators', 'route' => 'coordenacao.collaborators', 'label' => 'Colaboradores'],
        ['key' => 'units', 'route' => 'coordenacao.units', 'label' => 'Unidades'],
        ['key' => 'rules', 'route' => 'coordenacao.rules', 'label' => 'Histórico legado'],
        ['key' => 'pending', 'route' => 'coordenacao.pending.index', 'label' => 'Pendências'],
        ['key' => 'closings', 'route' => 'coordenacao.closings.index', 'label' => 'Fechamentos'],
    ];
@endphp

<div class="rounded-2xl border border-slate-200 bg-white shadow-sm">
    <div class="flex flex-col gap-4 p-5 sm:px-6 lg:flex-row lg:items-center lg:justify-between">
        <div>
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Coordenação</p>
            <h1 class="mt-1 text-2xl font-bold tracking-tight text-slate-900 sm:text-3xl">Custos e rateio de serviços de TI</h1>
            <p class="mt-2 max-w-3xl text-sm text-slate-500">
                Organiza produtos de TI, participantes, custos individuais e rateios compartilhados por competência.
            </p>
        </div>

        <div class="flex flex-wrap gap-2">
            <a href="{{ route('coordenacao.export', request()->query()) }}" style="background-color: #033151; border-color: #033151;" class="inline-flex min-h-10 items-center justify-center rounded-lg px-4 text-sm font-semibold text-white hover:opacity-90">
                Exportar Excel
            </a>
        </div>
    </div>

    <div class="flex flex-wrap gap-x-5 gap-y-1 overflow-x-auto border-t border-slate-100 px-5 sm:px-6">
        @foreach($tabs as $tab)
            <a href="{{ route($tab['route']) }}" class="inline-flex min-h-11 items-center whitespace-nowrap border-b-2 px-1 text-sm font-semibold {{ $active === $tab['key'] ? 'border-[#033151] text-[#033151]' : 'border-transparent text-slate-500 hover:text-slate-700' }}">
                {{ $tab['label'] }}
            </a>
        @endforeach
    </div>
</div>
