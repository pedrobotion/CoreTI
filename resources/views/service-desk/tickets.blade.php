<x-app-layout>
    <div class="mx-auto max-w-7xl space-y-6">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
            <div>
                <p class="text-sm font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">ServiceDesk</p>
                <h1 class="mt-1 text-2xl font-bold text-slate-950 dark:text-white">Chamados Jira</h1>
                <p class="mt-2 max-w-2xl text-sm text-slate-600 dark:text-slate-300">
                    Consulta dos chamados sincronizados automaticamente pela tabela Jira.
                </p>
            </div>
        </div>

        <section class="rounded-lg border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <div class="border-b border-slate-200 p-5 dark:border-slate-800">
                <form method="GET" action="{{ route('service-desk.tickets') }}" class="toolbar-search-grid">
                    <select name="status" class="toolbar-select text-sm shadow-sm focus:border-slate-900 focus:ring-slate-900">
                        <option value="">Status</option>
                        @foreach ($statuses as $status)
                            <option value="{{ $status }}" @selected(($filters['status'] ?? '') === $status)>{{ $status }}</option>
                        @endforeach
                    </select>

                    <select name="prioridade" class="toolbar-select text-sm shadow-sm focus:border-slate-900 focus:ring-slate-900">
                        <option value="">Prioridade</option>
                        @foreach ($priorities as $priority)
                            <option value="{{ $priority }}" @selected(($filters['prioridade'] ?? '') === $priority)>{{ $priority }}</option>
                        @endforeach
                    </select>

                    <select name="per_page" class="toolbar-select text-sm shadow-sm focus:border-slate-900 focus:ring-slate-900">
                        <option value="10" @selected($perPage === 10)>10 por página</option>
                        <option value="15" @selected($perPage === 15)>15 por página</option>
                        <option value="25" @selected($perPage === 25)>25 por página</option>
                        <option value="50" @selected($perPage === 50)>50 por página</option>
                    </select>

                    <input
                        type="text"
                        name="q"
                        value="{{ $search }}"
                        placeholder="Buscar por chave, resumo, solicitante, responsável, unidade ou departamento"
                        class="toolbar-input rounded-md border-slate-300 text-sm shadow-sm focus:border-slate-900 focus:ring-slate-900"
                    >

                    <button type="submit" class="toolbar-btn-primary inline-flex items-center justify-center rounded-md border border-slate-900 bg-slate-900 px-4 text-sm font-semibold text-white shadow-sm hover:bg-slate-800">
                        Filtrar
                    </button>

                    <a href="{{ route('service-desk.tickets') }}" class="toolbar-btn inline-flex items-center justify-center rounded-md border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50">
                        Limpar
                    </a>
                </form>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full min-w-[1120px] text-sm">
                    <thead class="bg-slate-50 text-left text-xs font-semibold uppercase text-slate-500 dark:bg-slate-950/40 dark:text-slate-400">
                        <tr>
                            <th class="px-5 py-3">Chave</th>
                            <th class="px-5 py-3">Resumo</th>
                            <th class="px-5 py-3">Solicitante</th>
                            <th class="px-5 py-3">Responsável</th>
                            <th class="px-5 py-3">Prioridade</th>
                            <th class="px-5 py-3">Status</th>
                            <th class="px-5 py-3">Unidade</th>
                            <th class="px-5 py-3">Atualizado</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        @forelse ($tickets as $ticket)
                            <tr>
                                <td class="px-5 py-4 font-semibold text-slate-900 dark:text-slate-100">
                                    @php
                                        $ticketKey = rawurlencode((string) $ticket->chave);
                                        $jiraUrl = null;
                                        if (! empty($jiraTicketBaseUrl)) {
                                            $jiraUrl = str_contains($jiraTicketBaseUrl, '{key}')
                                                ? str_replace('{key}', $ticketKey, $jiraTicketBaseUrl)
                                                : $jiraTicketBaseUrl . '/' . $ticketKey;
                                        }
                                    @endphp
                                    @if ($jiraUrl)
                                        <a href="{{ $jiraUrl }}" target="_blank" rel="noopener noreferrer" class="text-blue-600 hover:text-blue-800 dark:text-blue-300 dark:hover:text-blue-200">
                                            {{ $ticket->chave }}
                                        </a>
                                    @else
                                        {{ $ticket->chave }}
                                    @endif
                                </td>
                                <td class="max-w-sm px-5 py-4 text-slate-700 dark:text-slate-200">
                                    <span class="line-clamp-2">{{ $ticket->resumo ?? '-' }}</span>
                                </td>
                                <td class="px-5 py-4 text-slate-600 dark:text-slate-300">{{ $ticket->relator_nome ?? $ticket->relator_email ?? '-' }}</td>
                                <td class="px-5 py-4 text-slate-600 dark:text-slate-300">{{ $ticket->responsavel_nome ?? '-' }}</td>
                                <td class="px-5 py-4 text-slate-600 dark:text-slate-300">{{ $ticket->prioridade ?? '-' }}</td>
                                <td class="px-5 py-4 text-slate-600 dark:text-slate-300">{{ $ticket->status ?? $ticket->currentstatus_status ?? '-' }}</td>
                                <td class="px-5 py-4 text-slate-600 dark:text-slate-300">{{ $ticket->unidade ?? '-' }}</td>
                                <td class="px-5 py-4 text-slate-600 dark:text-slate-300">{{ optional($ticket->data_hora_atualizacao ?? $ticket->data_hora_criacao)->format('d/m/Y H:i') ?? '-' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="px-5 py-8 text-center text-slate-500 dark:text-slate-400">Nenhum chamado encontrado.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="border-t border-slate-200 px-5 py-4 dark:border-slate-800">
                {{ $tickets->links() }}
            </div>
        </section>
    </div>
</x-app-layout>
