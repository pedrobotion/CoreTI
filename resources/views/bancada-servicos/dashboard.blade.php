<x-app-layout>
    <div class="mx-auto max-w-7xl space-y-6">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
            <div>
                <p class="text-sm font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Bancada de Serviços</p>
                <h1 class="mt-1 text-2xl font-bold text-slate-950 dark:text-white">Central da Bancada</h1>
                <p class="mt-2 max-w-2xl text-sm text-slate-600 dark:text-slate-300">
                    Visão geral da operação de bancada para chamados, equipamentos e controle de SLA.
                </p>
            </div>
        </div>
        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-8">
            <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <p class="text-sm font-medium text-slate-500 dark:text-slate-400">Equipamentos em bancada</p>
                <p class="mt-3 text-3xl font-bold text-slate-950 dark:text-white">{{ $stats['em_bancada'] }}</p>
            </div>
            <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <p class="text-sm font-medium text-slate-500 dark:text-slate-400">Aguardando peça</p>
                <p class="mt-3 text-3xl font-bold text-slate-950 dark:text-white">{{ $stats['aguardando_peca'] }}</p>
            </div>
            <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <p class="text-sm font-medium text-slate-500 dark:text-slate-400">Prontos para entrega</p>
                <p class="mt-3 text-3xl font-bold text-amber-600 dark:text-amber-300">{{ $stats['prontos'] }}</p>
            </div>
            <div class="rounded-lg border-2 border-amber-300 bg-amber-50 p-5 shadow-sm">
                <p class="text-sm font-medium text-amber-800">Aguardando Entrada</p>
                <p class="mt-3 text-3xl font-bold text-amber-900">{{ $stats['aguardando_entrada'] }}</p>
            </div>
            <div class="rounded-lg border-2 border-rose-300 bg-rose-50 p-5 shadow-sm">
                <p class="text-sm font-medium text-rose-800">Terceiros pendentes</p>
                <p class="mt-3 text-3xl font-bold text-rose-900">{{ $stats['terceiros_pendentes'] }}</p>
            </div>
            <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <p class="text-sm font-medium text-slate-500 dark:text-slate-400">Finalizados hoje</p>
                <p class="mt-3 text-3xl font-bold text-slate-950 dark:text-white">{{ $stats['finalizados_hoje'] }}</p>
            </div>
            <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <p class="text-sm font-medium text-slate-500 dark:text-slate-400">Chamados abertos (squad)</p>
                <p class="mt-3 text-3xl font-bold text-slate-950 dark:text-white">{{ $stats['chamados_abertos'] }}</p>
            </div>
            <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <p class="text-sm font-medium text-slate-500 dark:text-slate-400">SLA em atenção (squad)</p>
                <p class="mt-3 text-3xl font-bold text-amber-600 dark:text-amber-300">{{ $stats['chamados_sla'] }}</p>
            </div>
        </div>
        <div class="grid gap-6 xl:grid-cols-2">
            <section class="rounded-lg border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <h2 class="text-base font-semibold text-slate-950 dark:text-white">Últimas movimentações de equipamentos</h2>
                    <a href="{{ route('bancada-servicos.assets') }}" style="background-color: #033151; border-color: #033151;" class="inline-flex min-h-10 items-center justify-center rounded-md px-4 text-sm font-semibold text-white shadow-sm hover:opacity-90">Abrir equipamentos</a>
                </div>
                <div class="mt-4 overflow-x-auto">
                    <table class="w-full min-w-[700px] text-sm">
                        <thead style="background-color: #033151;" class="text-left text-xs font-semibold uppercase text-white">
                            <tr>
                                <th class="px-4 py-3">Tipo</th>
                                <th class="px-4 py-3">Plaqueta</th>
                                <th class="px-4 py-3">Unidade</th>
                                <th class="px-4 py-3">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200 dark:divide-slate-800">
                            @forelse($recent as $item)
                                <tr class="odd:bg-slate-50 even:bg-white dark:odd:bg-slate-950 dark:even:bg-slate-900">
                                    <td class="px-4 py-3">{{ $item->tipo_equipamento }}</td>
                                    <td class="px-4 py-3">{{ $item->plaqueta }}</td>
                                    <td class="px-4 py-3">{{ $item->unidade_setor }}</td>
                                    <td class="px-4 py-3">{{ $item->status }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="px-4 py-6 text-center text-slate-500">Sem dados ainda.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>
            <section class="rounded-lg border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <h2 class="text-base font-semibold text-slate-950 dark:text-white">Chamados recentes do Time Bancada de Serviços</h2>
                    <a href="{{ route('bancada-servicos.tickets') }}" style="background-color: #033151; border-color: #033151;" class="inline-flex min-h-10 items-center justify-center rounded-md px-4 text-sm font-semibold text-white shadow-sm hover:opacity-90">Abrir chamados</a>
                </div>
                <div class="mt-4 overflow-x-auto">
                    <table class="w-full min-w-[700px] text-sm">
                        <thead style="background-color: #033151;" class="text-left text-xs font-semibold uppercase text-white">
                            <tr>
                                <th class="px-4 py-3">ID</th>
                                <th class="px-4 py-3">Resumo</th>
                                <th class="px-4 py-3">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200 dark:divide-slate-800">
                            @forelse($recentTickets as $ticket)
                                <tr class="odd:bg-slate-50 even:bg-white dark:odd:bg-slate-950 dark:even:bg-slate-900">
                                    <td class="px-4 py-3 font-semibold">
                                        <a href="{{ $jiraTicketBaseUrl . '/' . rawurlencode((string) $ticket->chave) }}" target="_blank" rel="noopener noreferrer" class="text-blue-600 hover:text-blue-800">{{ $ticket->chave }}</a>
                                    </td>
                                    <td class="px-4 py-3">{{ $ticket->resumo ?? '-' }}</td>
                                    <td class="px-4 py-3">{{ $ticket->status ?? $ticket->currentstatus_status ?? '-' }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="3" class="px-4 py-6 text-center text-slate-500">Sem chamados para o squad.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    </div>
</x-app-layout>
