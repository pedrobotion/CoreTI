<section class="rounded-lg border border-slate-200 bg-white shadow-sm">
    <div class="px-5 py-4">
        <p class="text-sm font-semibold uppercase tracking-wide text-slate-500">CMDB</p>
        <h1 class="mt-1 text-xl font-bold text-slate-950">{{ $title ?? 'CMDB' }}</h1>
        @if(!empty($description))
            <p class="mt-1 text-sm text-slate-600">{{ $description }}</p>
        @endif
    </div>
    <div class="flex flex-wrap items-center gap-2 border-t border-slate-200 px-5 py-3" aria-label="Ações da página do CMDB">
        @if(request()->routeIs('cmdb.dashboard', 'cmdb.index'))
            <a href="{{ route('cmdb.assets.create') }}" class="cmdb-action">Adicionar ativo</a>
            <a href="{{ route('cmdb.quick-create.create') }}" class="cmdb-action">Cadastro rápido</a>
            <a href="{{ route('cmdb.connectors.index') }}" class="cmdb-action">Sincronizar conectores</a>
        @elseif(request()->routeIs('cmdb.assets.index'))
            <a href="{{ route('cmdb.assets.create', request()->only('type')) }}" class="cmdb-action cmdb-action-primary">Adicionar ativo</a>
            <a href="{{ route('cmdb.quick-create.create') }}" class="cmdb-action">Cadastro rápido</a>
            <a href="{{ route('cmdb.import.index') }}" class="cmdb-action">Importar</a>
        @elseif(request()->routeIs('cmdb.discovered-assets.*'))
            <a href="{{ route('cmdb.connectors.index') }}" class="cmdb-action">Sincronizar agora</a>
        @elseif(request()->routeIs('cmdb.pending.*'))
            <a href="{{ route('cmdb.assets.index') }}" class="cmdb-action">Ver ativos</a>
        @else
            <a href="{{ route('cmdb.dashboard') }}" class="cmdb-action">Voltar ao painel</a>
        @endif
    </div>
</section>
