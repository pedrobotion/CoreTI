@php
    $active = $active ?? 'dashboard';
@endphp

<div class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900">
    <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
        <div>
            <p class="text-sm font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Coordenação</p>
            <h1 class="mt-1 text-2xl font-bold text-slate-950 dark:text-white">Custos e rateio de serviços de TI</h1>
            <p class="mt-2 max-w-3xl text-sm text-slate-600 dark:text-slate-300">
                Organiza produtos de TI, participantes, custos individuais e rateios compartilhados por competência.
            </p>
        </div>

        <div class="flex flex-wrap gap-2">
            <a href="{{ route('coordenacao.export', request()->query()) }}" class="inline-flex min-h-10 items-center justify-center rounded-md border border-slate-900 bg-slate-900 px-4 text-sm font-semibold text-white shadow-sm hover:bg-slate-800">
                Exportar Excel
            </a>
        </div>
    </div>

    <div class="mt-5 flex flex-wrap gap-2 border-t border-slate-100 pt-4 dark:border-slate-800">
        <a href="{{ route('coordenacao.dashboard') }}" class="inline-flex min-h-10 items-center rounded-md px-4 text-sm font-semibold {{ $active === 'dashboard' ? 'bg-slate-900 text-white' : 'bg-slate-100 text-slate-700 hover:bg-slate-200 dark:bg-slate-800 dark:text-slate-200 dark:hover:bg-slate-700' }}">
            Visão geral
        </a>
        <a href="{{ route('coordenacao.products.index') }}" class="inline-flex min-h-10 items-center rounded-md px-4 text-sm font-semibold {{ $active === 'products' ? 'bg-slate-900 text-white' : 'bg-slate-100 text-slate-700 hover:bg-slate-200 dark:bg-slate-800 dark:text-slate-200 dark:hover:bg-slate-700' }}">Produtos de TI</a>
        <a href="{{ route('coordenacao.rateio.index') }}" class="inline-flex min-h-10 items-center rounded-md px-4 text-sm font-semibold {{ $active === 'rateio' ? 'bg-slate-900 text-white' : 'bg-slate-100 text-slate-700 hover:bg-slate-200 dark:bg-slate-800 dark:text-slate-200 dark:hover:bg-slate-700' }}">Rateio mensal</a>
        <a href="{{ route('coordenacao.collaborators') }}" class="inline-flex min-h-10 items-center rounded-md px-4 text-sm font-semibold {{ $active === 'collaborators' ? 'bg-slate-900 text-white' : 'bg-slate-100 text-slate-700 hover:bg-slate-200 dark:bg-slate-800 dark:text-slate-200 dark:hover:bg-slate-700' }}">
            Colaboradores
        </a>
        <a href="{{ route('coordenacao.units') }}" class="inline-flex min-h-10 items-center rounded-md px-4 text-sm font-semibold {{ $active === 'units' ? 'bg-slate-900 text-white' : 'bg-slate-100 text-slate-700 hover:bg-slate-200 dark:bg-slate-800 dark:text-slate-200 dark:hover:bg-slate-700' }}">
            Unidades
        </a>
        <a href="{{ route('coordenacao.rules') }}" class="inline-flex min-h-10 items-center rounded-md px-4 text-sm font-semibold {{ $active === 'rules' ? 'bg-slate-900 text-white' : 'bg-slate-100 text-slate-700 hover:bg-slate-200 dark:bg-slate-800 dark:text-slate-200 dark:hover:bg-slate-700' }}">
            Histórico legado
        </a>
        <a href="{{ route('coordenacao.pending.index') }}" class="inline-flex min-h-10 items-center rounded-md px-4 text-sm font-semibold {{ $active === 'pending' ? 'bg-slate-900 text-white' : 'bg-slate-100 text-slate-700 hover:bg-slate-200 dark:bg-slate-800 dark:text-slate-200 dark:hover:bg-slate-700' }}">Pendências</a>
        <a href="{{ route('coordenacao.closings.index') }}" class="inline-flex min-h-10 items-center rounded-md px-4 text-sm font-semibold {{ $active === 'closings' ? 'bg-slate-900 text-white' : 'bg-slate-100 text-slate-700 hover:bg-slate-200 dark:bg-slate-800 dark:text-slate-200 dark:hover:bg-slate-700' }}">Fechamentos</a>
    </div>
</div>
