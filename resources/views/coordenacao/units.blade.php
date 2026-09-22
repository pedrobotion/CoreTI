<x-app-layout>
    <div class="mx-auto max-w-7xl space-y-6">
        @include('coordenacao.partials.nav', ['active' => 'units'])

        <form method="GET" class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <div class="grid gap-4 md:grid-cols-3">
                <div>
                    <label class="text-sm font-medium text-slate-700 dark:text-slate-200">Busca</label>
                    <input type="text" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Nome da unidade" class="mt-1 w-full rounded-md border-slate-300 text-sm shadow-sm focus:border-slate-900 focus:ring-slate-900 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-100">
                </div>

                <div>
                    <label class="text-sm font-medium text-slate-700 dark:text-slate-200">Por página</label>
                    <select name="per_page" class="mt-1 w-full rounded-md border-slate-300 text-sm shadow-sm focus:border-slate-900 focus:ring-slate-900 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-100">
                        @foreach ([25, 50, 100] as $size)
                            <option value="{{ $size }}" @selected((int) request('per_page', 25) === $size)>{{ $size }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="mt-4 flex flex-wrap gap-2">
                <button type="submit" class="inline-flex min-h-10 items-center justify-center rounded-md border border-slate-900 bg-slate-900 px-4 text-sm font-semibold text-white shadow-sm hover:bg-slate-800">Filtrar</button>
                <a href="{{ route('coordenacao.units') }}" class="inline-flex min-h-10 items-center justify-center rounded-md border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100 dark:hover:bg-slate-800">Limpar</a>
                <a href="{{ route('coordenacao.export', request()->query()) }}" class="inline-flex min-h-10 items-center justify-center rounded-md border border-emerald-300 bg-emerald-50 px-4 text-sm font-semibold text-emerald-800 shadow-sm hover:bg-emerald-100 dark:border-emerald-900/40 dark:bg-emerald-950/30 dark:text-emerald-200">Exportar Excel</a>
            </div>
        </form>

        <section class="rounded-lg border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <div class="border-b border-slate-200 px-5 py-4 dark:border-slate-800">
                <h2 class="text-base font-semibold text-slate-950 dark:text-white">Custos por unidade</h2>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full min-w-[900px] text-sm">
                    <thead class="bg-slate-50 text-left text-xs font-semibold uppercase text-slate-500 dark:bg-slate-950/40 dark:text-slate-400">
                        <tr>
                            <th class="px-5 py-3">Unidade</th>
                            <th class="px-5 py-3">Colaboradores</th>
                            <th class="px-5 py-3">Custo Office</th>
                            <th class="px-5 py-3">Custo Grupos</th>
                            <th class="px-5 py-3">Custo mensal</th>
                            <th class="px-5 py-3">Custo pontual</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        @forelse ($units as $unit)
                            <tr>
                                <td class="px-5 py-4 font-medium text-slate-900 dark:text-slate-100">{{ $unit['unit'] }}</td>
                                <td class="px-5 py-4 text-slate-600 dark:text-slate-300">{{ number_format($unit['users'], 0, ',', '.') }}</td>
                                <td class="px-5 py-4 text-slate-600 dark:text-slate-300">{{ 'R$ ' . number_format((float) $unit['office_cost'], 2, ',', '.') }}</td>
                                <td class="px-5 py-4 text-slate-600 dark:text-slate-300">{{ 'R$ ' . number_format((float) $unit['groups_cost'], 2, ',', '.') }}</td>
                                <td class="px-5 py-4 font-semibold text-slate-950 dark:text-slate-100">{{ 'R$ ' . number_format((float) $unit['monthly_cost'], 2, ',', '.') }}</td>
                                <td class="px-5 py-4 font-semibold text-slate-950 dark:text-slate-100">{{ 'R$ ' . number_format((float) $unit['one_time_cost'], 2, ',', '.') }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-5 py-8 text-center text-slate-500 dark:text-slate-400">Nenhuma unidade encontrada.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        <div>
            {{ $units->links() }}
        </div>
    </div>
</x-app-layout>
