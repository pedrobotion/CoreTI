<x-app-layout>
    <div class="mx-auto max-w-7xl space-y-6">
        <div class="flex flex-wrap items-end justify-between gap-3">
            <div>
                <p class="text-sm font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Bancada de Serviços</p>
                <h1 class="mt-1 text-2xl font-bold text-slate-950 dark:text-white">SLA da Bancada</h1>
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

        <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <div class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm"><p class="text-xs uppercase text-slate-500">Chamados abertos</p><p class="mt-2 text-2xl font-bold">{{ $stats['abertos'] }}</p></div>
            <div class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm"><p class="text-xs uppercase text-slate-500">SLA em alerta</p><p class="mt-2 text-2xl font-bold text-amber-700">{{ $stats['em_alerta'] }}</p></div>
            <div class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm"><p class="text-xs uppercase text-slate-500">Resolvidos no período</p><p class="mt-2 text-2xl font-bold text-emerald-700">{{ $stats['resolvidos_periodo'] }}</p></div>
            <div class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm"><p class="text-xs uppercase text-slate-500">Taxa de alerta</p><p class="mt-2 text-2xl font-bold">{{ number_format($stats['taxa_alerta'], 1, ',', '.') }}%</p></div>
        </section>

        <section class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_minmax(0,1fr)]">
            <div class="rounded-lg border border-slate-200 bg-white shadow-sm">
                <div class="border-b border-slate-200 px-5 py-4 text-sm font-semibold text-slate-800">Status mais frequentes ({{ $days }} dias)</div>
                <div class="p-5">
                    @forelse($ticketsByStatus as $item)
                        <div class="mb-3">
                            <div class="mb-1 flex items-center justify-between text-sm">
                                <span>{{ $item->status_name }}</span>
                                <span class="font-semibold">{{ $item->total }}</span>
                            </div>
                            <div class="h-2 w-full rounded bg-slate-100">
                                <div class="h-2 rounded bg-sky-600" style="width: {{ min(100, ($item->total / max(1, $ticketsByStatus->max('total'))) * 100) }}%"></div>
                            </div>
                        </div>
                    @empty
                        <p class="text-sm text-slate-500">Sem dados no período.</p>
                    @endforelse
                </div>
            </div>

            <div class="rounded-lg border border-slate-200 bg-white shadow-sm">
                <div class="border-b border-slate-200 px-5 py-4 text-sm font-semibold text-slate-800">Chamados em alerta</div>
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[680px] text-sm">
                        <thead class="bg-slate-900 text-left text-xs font-semibold uppercase text-white">
                            <tr>
                                <th class="px-4 py-3">TIC</th>
                                <th class="px-4 py-3">Resumo</th>
                                <th class="px-4 py-3">Status</th>
                                <th class="px-4 py-3">Responsável</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200">
                            @forelse($recentAlerts as $item)
                                <tr>
                                    <td class="px-4 py-3"><a href="{{ $jiraTicketBaseUrl . '/' . rawurlencode((string) $item->chave) }}" target="_blank" class="text-blue-600 hover:text-blue-800">{{ $item->chave }}</a></td>
                                    <td class="px-4 py-3">{{ $item->resumo ?? '-' }}</td>
                                    <td class="px-4 py-3">{{ $item->status ?? $item->currentstatus_status ?? '-' }}</td>
                                    <td class="px-4 py-3">{{ $item->responsavel_nome ?? '-' }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="px-4 py-6 text-center text-slate-500">Nenhum alerta no período.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </section>
    </div>
</x-app-layout>

