<x-app-layout>
    <div class="mx-auto max-w-7xl space-y-6">
        <div>
            <p class="text-sm font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Bancada de Serviços</p>
            <h1 class="mt-1 text-2xl font-bold text-slate-950 dark:text-white">Chamados do Time Bancada de Serviços</h1>
        </div>
        <section class="rounded-lg border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <div class="border-b border-slate-200 p-5 dark:border-slate-800">
                <form method="GET" class="flex flex-wrap items-center gap-2 lg:flex-nowrap">
                    <select name="status" class="h-10 w-full min-w-[160px] rounded-md border-slate-300 text-sm md:w-40">
                        <option value="abertos" @selected($status === 'abertos')>Abertos</option>
                        <option value="resolvidos" @selected($status === 'resolvidos')>Resolvidos</option>
                        @foreach($statuses as $option)
                            <option value="{{ $option }}" @selected($status === $option)>{{ $option }}</option>
                        @endforeach
                    </select>
                    <input name="q" value="{{ $search }}" placeholder="Buscar ID, resumo, status, prioridade..." class="h-10 w-full min-w-[260px] rounded-md border-slate-300 text-sm md:flex-1 md:w-auto">
                    <div class="flex h-10 w-full gap-2 md:w-auto">
                        <button type="submit" style="background-color: #033151; border-color: #033151;" class="inline-flex h-10 items-center justify-center rounded-md px-4 text-sm font-semibold text-white hover:opacity-90">Buscar</button>
                        <a href="{{ route('bancada-servicos.tickets') }}" class="inline-flex h-10 items-center justify-center rounded-md border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-700 hover:bg-slate-50">Limpar</a>
                    </div>
                </form>
            </div>
            <div class="w-full max-w-full overflow-x-auto">
                <table class="w-full min-w-[850px] text-sm">
                    <thead style="background-color: #033151;" class="text-left text-xs font-semibold uppercase text-white">
                        <tr>
                            <th class="px-4 py-3">Chamado</th>
                            <th class="px-4 py-3">Resumo</th>
                            <th class="px-4 py-3">Status</th>
                            <th class="px-4 py-3">Prioridade</th>
                            <th class="px-4 py-3">Responsável</th>
                            <th class="px-4 py-3">Atualizado em</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200 dark:divide-slate-800">
                        @forelse($records as $item)
                            <tr class="odd:bg-slate-50 even:bg-white dark:odd:bg-slate-950 dark:even:bg-slate-900">
                                <td class="px-4 py-3">
                                    <a href="{{ $jiraTicketBaseUrl }}/{{ rawurlencode((string) $item->chave) }}" target="_blank" class="text-blue-600 hover:text-blue-800">{{ $item->chave }}</a>
                                </td>
                                <td class="px-4 py-3">{{ $item->resumo ?? '-' }}</td>
                                <td class="px-4 py-3">{{ $item->status ?? $item->currentstatus_status ?? '-' }}</td>
                                <td class="px-4 py-3">{{ $item->prioridade ?? '-' }}</td>
                                <td class="px-4 py-3">{{ $item->responsavel_nome ?? '-' }}</td>
                                <td class="px-4 py-3">{{ ($item->data_hora_atualizacao ?? $item->data_hora_criacao ?? $item->updated_at)?->format('d/m/Y H:i') }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="px-4 py-6 text-center text-slate-500">Nenhum chamado encontrado para o squad.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="flex flex-col gap-3 border-t border-slate-200 px-5 py-4 sm:flex-row sm:items-center sm:justify-between dark:border-slate-800">
                <form method="GET" class="flex items-center gap-2 text-sm text-slate-600">
                    <span>Itens por página:</span>
                    <input type="hidden" name="q" value="{{ $search }}">
                    <input type="hidden" name="status" value="{{ $status }}">
                    <select name="per_page" onchange="this.form.submit()" class="toolbar-select text-sm">
                        <option value="10" @selected($perPage === 10)>10</option>
                        <option value="15" @selected($perPage === 15)>15</option>
                        <option value="25" @selected($perPage === 25)>25</option>
                        <option value="50" @selected($perPage === 50)>50</option>
                    </select>
                </form>
                {{ $records->links() }}
            </div>
        </section>
    </div>
</x-app-layout>
