@php
    $tabs = [
        [
            'label' => 'Dashboard',
            'route' => 'network-datacenter.index',
            'active' => request()->routeIs('network-datacenter.index') || request()->routeIs('network-datacenter.dashboard'),
            'group' => null,
        ],
        [
            'label' => 'Serviços de Conectividade',
            'route' => 'network-datacenter.servicos.index',
            'active' => request()->routeIs('network-datacenter.servicos.*'),
            'group' => 'Links Unidades',
        ],
        [
            'label' => 'Fichas / Importação',
            'route' => 'network-datacenter.servicos.create',
            'active' => request()->routeIs('network-datacenter.servicos.create') || request()->routeIs('network-datacenter.servicos.edit') || request()->routeIs('network-datacenter.servicos.show'),
            'group' => 'Links Unidades',
        ],
    ];
@endphp

<section class="rounded-lg border border-slate-200 bg-white shadow-sm">
    <div class="border-b border-slate-200 px-5 py-4">
        <p class="text-sm font-semibold uppercase tracking-wide text-slate-500">CMDB</p>
        <h1 class="mt-1 text-xl font-bold text-slate-950">{{ $title ?? 'Módulo' }}</h1>
        @if(!empty($description))
            <p class="mt-1 text-sm text-slate-600">{{ $description }}</p>
        @endif
    </div>
    <div class="space-y-4 px-5 py-4">
        @foreach(collect($tabs)->groupBy('group') as $group => $groupTabs)
            @if($group)
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ $group }}</p>
                    <div class="mt-2 flex flex-wrap gap-2">
                        @foreach($groupTabs as $tab)
                            <a
                                href="{{ route($tab['route']) }}"
                                class="inline-flex min-h-10 items-center justify-center rounded-md px-4 text-sm font-semibold transition {{ $tab['active'] ? 'bg-[#033151] text-white' : 'border border-slate-300 bg-white text-slate-700 hover:bg-slate-50' }}"
                            >
                                {{ $tab['label'] }}
                            </a>
                        @endforeach
                    </div>
                </div>
            @else
                <div class="flex flex-wrap gap-2">
                    @foreach($groupTabs as $tab)
                        <a
                            href="{{ route($tab['route']) }}"
                            class="inline-flex min-h-10 items-center justify-center rounded-md px-4 text-sm font-semibold transition {{ $tab['active'] ? 'bg-[#033151] text-white' : 'border border-slate-300 bg-white text-slate-700 hover:bg-slate-50' }}"
                        >
                            {{ $tab['label'] }}
                        </a>
                    @endforeach
                </div>
            @endif
        @endforeach
    </div>
</section>
