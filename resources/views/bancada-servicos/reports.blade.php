<x-app-layout>
    <div class="mx-auto max-w-7xl space-y-6">
        <div class="flex flex-wrap items-end justify-between gap-3">
            <div>
                <p class="text-sm font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Bancada de Serviços</p>
                <h1 class="mt-1 text-2xl font-bold text-slate-950 dark:text-white">Relatórios da Bancada</h1>
            </div>
            <form method="GET">
                <select name="days" onchange="this.form.submit()" class="toolbar-select text-sm">
                    <option value="7" @selected($days === 7)>7 dias</option>
                    <option value="15" @selected($days === 15)>15 dias</option>
                    <option value="30" @selected($days === 30)>30 dias</option>
                    <option value="60" @selected($days === 60)>60 dias</option>
                    <option value="90" @selected($days === 90)>90 dias</option>
                </select>
            </form>
        </div>

        <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-6">
            <div class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm"><p class="text-xs uppercase text-slate-500">Ativos</p><p class="mt-2 text-2xl font-bold">{{ $stats['ativos'] }}</p></div>
            <div class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm"><p class="text-xs uppercase text-slate-500">Entregues</p><p class="mt-2 text-2xl font-bold">{{ $stats['entregues'] }}</p></div>
            <div class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm"><p class="text-xs uppercase text-slate-500">Descartados</p><p class="mt-2 text-2xl font-bold">{{ $stats['descartados'] }}</p></div>
            <div class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm"><p class="text-xs uppercase text-slate-500">Backup</p><p class="mt-2 text-2xl font-bold">{{ $stats['backup'] }}</p></div>
            <div class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm"><p class="text-xs uppercase text-slate-500">Entradas ({{ $days }}d)</p><p class="mt-2 text-2xl font-bold text-emerald-700">{{ $stats['entradas_periodo'] }}</p></div>
            <div class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm"><p class="text-xs uppercase text-slate-500">Saídas ({{ $days }}d)</p><p class="mt-2 text-2xl font-bold text-amber-700">{{ $stats['saidas_periodo'] }}</p></div>
        </section>

        <section class="grid gap-6 xl:grid-cols-3">
            <div class="rounded-lg border border-slate-200 bg-white shadow-sm">
                <div class="border-b border-slate-200 px-5 py-4 text-sm font-semibold text-slate-800">Por status</div>
                <div class="p-5">
                    @foreach($equipmentByStatus as $item)
                        <div class="mb-3 flex items-center justify-between text-sm">
                            <span>{{ $item->status }}</span><span class="font-semibold">{{ $item->total }}</span>
                        </div>
                    @endforeach
                </div>
            </div>
            <div class="rounded-lg border border-slate-200 bg-white shadow-sm">
                <div class="border-b border-slate-200 px-5 py-4 text-sm font-semibold text-slate-800">Top tipos</div>
                <div class="p-5">
                    @foreach($equipmentByType as $item)
                        <div class="mb-3 flex items-center justify-between text-sm">
                            <span>{{ $item->tipo }}</span><span class="font-semibold">{{ $item->total }}</span>
                        </div>
                    @endforeach
                </div>
            </div>
            <div class="rounded-lg border border-slate-200 bg-white shadow-sm">
                <div class="border-b border-slate-200 px-5 py-4 text-sm font-semibold text-slate-800">Top unidades/setores</div>
                <div class="p-5">
                    @foreach($equipmentByUnit as $item)
                        <div class="mb-3 flex items-center justify-between text-sm">
                            <span>{{ $item->unidade }}</span><span class="font-semibold">{{ $item->total }}</span>
                        </div>
                    @endforeach
                </div>
            </div>
        </section>
    </div>
</x-app-layout>

