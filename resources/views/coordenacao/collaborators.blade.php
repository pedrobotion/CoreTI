<x-app-layout>
    <div class="mx-auto max-w-7xl space-y-6">
        @include('coordenacao.partials.nav', ['active' => 'collaborators'])
        @php $warnings = $report['warnings'] ?? collect(); @endphp

        <form method="GET" class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <div class="grid gap-4 md:grid-cols-4">
                <div>
                    <label class="text-sm font-medium text-slate-700 dark:text-slate-200">Busca</label>
                    <input type="text" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Nome, e-mail ou unidade" class="mt-1 w-full rounded-md border-slate-300 text-sm shadow-sm focus:border-slate-900 focus:ring-slate-900 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-100">
                </div>

                <div>
                    <label class="text-sm font-medium text-slate-700 dark:text-slate-200">Unidade</label>
                    <select name="unit" class="mt-1 w-full rounded-md border-slate-300 text-sm shadow-sm focus:border-slate-900 focus:ring-slate-900 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-100">
                        <option value="">Todas</option>
                        @foreach ($unitsFilter as $unit)
                            <option value="{{ $unit }}" @selected(($filters['unit'] ?? '') === $unit)>{{ $unit }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="text-sm font-medium text-slate-700 dark:text-slate-200">Status</label>
                    <select name="status" class="mt-1 w-full rounded-md border-slate-300 text-sm shadow-sm focus:border-slate-900 focus:ring-slate-900 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-100">
                        <option value="">Todos</option>
                        <option value="active" @selected(($filters['status'] ?? '') === 'active')>Ativos</option>
                        <option value="inactive" @selected(($filters['status'] ?? '') === 'inactive')>Inativos</option>
                    </select>
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
                <button type="submit" class="inline-flex min-h-10 items-center justify-center rounded-lg border border-[#033151] bg-[#033151] px-4 text-sm font-semibold text-white hover:opacity-90">Filtrar</button>
                <a href="{{ route('coordenacao.collaborators') }}" class="inline-flex min-h-10 items-center justify-center rounded-md border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100 dark:hover:bg-slate-800">Limpar</a>
                <a href="{{ route('coordenacao.export', request()->query()) }}" class="inline-flex min-h-10 items-center justify-center rounded-md border border-emerald-300 bg-emerald-50 px-4 text-sm font-semibold text-emerald-800 shadow-sm hover:bg-emerald-100 dark:border-emerald-900/40 dark:bg-emerald-950/30 dark:text-emerald-200">Exportar Excel</a>
            </div>
        </form>

        <section class="rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-100 px-5 py-4 sm:px-6">
                <h2 class="text-base font-semibold text-slate-950 dark:text-white">Colaboradores</h2>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full min-w-[1200px] text-sm">
                    <thead class="border-b border-slate-100 text-left text-xs font-semibold uppercase tracking-wide text-slate-400">
                        <tr>
                            <th class="px-5 py-3">Nome</th>
                            <th class="px-5 py-3">E-mail</th>
                            <th class="px-5 py-3">Unidade</th>
                            <th class="px-5 py-3">Office</th>
                            <th class="px-5 py-3">Grupos AD</th>
                            <th class="px-5 py-3">Regras</th>
                            <th class="px-5 py-3">Custo mensal</th>
                            <th class="px-5 py-3">Custo pontual</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        @forelse ($people as $person)
                            <tr>
                                <td class="px-5 py-4">
                                    <div class="font-medium text-slate-900 dark:text-slate-100">{{ $person['name'] ?: 'Sem nome' }}</div>
                                    <div class="text-xs text-slate-500 dark:text-slate-400">{{ $person['status'] }}</div>
                                </td>
                                <td class="px-5 py-4 text-slate-600 dark:text-slate-300">{{ $person['email'] ?: 'Sem e-mail' }}</td>
                                <td class="px-5 py-4 text-slate-600 dark:text-slate-300">{{ $person['resolved_unit'] ?: 'Sem unidade' }}</td>
                                <td class="px-5 py-4 text-slate-600 dark:text-slate-300">{{ trim(implode(' | ', array_filter([
                                    !empty($person['office_flags']['office_apps']) ? 'Apps' : null,
                                    !empty($person['office_flags']['office_business']) ? 'Business' : null,
                                    !empty($person['office_flags']['powerbi_pro']) ? 'PBI Pro' : null,
                                    !empty($person['office_flags']['powerbi_premium']) ? 'PBI Premium' : null,
                                    !empty($person['office_flags']['visio_plan']) ? 'Visio' : null,
                                ]))) ?: '-' }}</td>
                                <td class="px-5 py-4 text-slate-600 dark:text-slate-300">{{ ! empty($person['ad_groups_display']) ? implode(' | ', array_slice($person['ad_groups_display'], 0, 4)) . (count($person['ad_groups_display']) > 4 ? '…' : '') : '-' }}</td>
                                <td class="px-5 py-4 text-slate-600 dark:text-slate-300">{{ ! empty($person['matched_rule_names']) ? implode(' | ', $person['matched_rule_names']) : '-' }}</td>
                                <td class="px-5 py-4 font-semibold text-slate-950 dark:text-slate-100">{{ 'R$ ' . number_format((float) ($person['monthly_cost'] ?? 0), 2, ',', '.') }}</td>
                                <td class="px-5 py-4 font-semibold text-slate-950 dark:text-slate-100">{{ 'R$ ' . number_format((float) ($person['one_time_cost'] ?? 0), 2, ',', '.') }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="px-5 py-8 text-center text-slate-500 dark:text-slate-400">Nenhum colaborador encontrado para os filtros informados.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        <div>
            {{ $people->links() }}
        </div>

    </div>
</x-app-layout>
