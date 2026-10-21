<x-app-layout>
    <div class="mx-auto max-w-7xl space-y-6 px-4 py-8 sm:px-6">
        <div>
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Service Desk</p>
            <h1 class="mt-1 text-2xl font-bold tracking-tight text-slate-900 sm:text-3xl">Chamados Jira</h1>
            <p class="mt-1 max-w-2xl text-sm text-slate-500">
                Consulta dos chamados sincronizados automaticamente pela tabela Jira.
            </p>
        </div>

        <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:px-6">
            <form method="GET" action="{{ route('service-desk.tickets') }}" class="toolbar-search-grid">
                <select name="status" class="toolbar-select rounded-md border-slate-300 text-sm">
                    <option value="">Status</option>
                    @foreach ($statuses as $status)
                        <option value="{{ $status }}" @selected(($filters['status'] ?? '') === $status)>{{ $status }}</option>
                    @endforeach
                </select>

                <select name="prioridade" class="toolbar-select rounded-md border-slate-300 text-sm">
                    <option value="">Prioridade</option>
                    @foreach ($priorities as $priority)
                        <option value="{{ $priority }}" @selected(($filters['prioridade'] ?? '') === $priority)>{{ $priority }}</option>
                    @endforeach
                </select>

                <select name="per_page" class="toolbar-select rounded-md border-slate-300 text-sm">
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
                    class="toolbar-input rounded-md border-slate-300 text-sm"
                >

                <button type="submit" style="background-color: #033151; border-color: #033151;" class="toolbar-btn-primary inline-flex items-center justify-center rounded-lg px-4 text-sm font-semibold text-white hover:opacity-90">
                    Filtrar
                </button>

                <a href="{{ route('service-desk.tickets') }}" class="toolbar-btn inline-flex items-center justify-center rounded-md border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                    Limpar
                </a>
            </form>
        </section>

        <section class="rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 p-5 sm:px-6">
                <div class="flex items-center gap-3">
                    <span class="grid h-9 w-9 shrink-0 place-items-center rounded-lg bg-blue-50 text-blue-600">
                        <svg viewBox="0 0 24 24" class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
                    </span>
                    <div>
                        <h2 class="text-base font-bold text-slate-900">Chamados</h2>
                        <p class="mt-0.5 text-sm text-slate-500">Resultado dos filtros aplicados.</p>
                    </div>
                </div>
                <span class="inline-flex items-center rounded-full bg-blue-50 px-2.5 py-1 text-xs font-semibold text-blue-700 ring-1 ring-inset ring-blue-200">{{ number_format($tickets->total(), 0, ',', '.') }} chamado(s)</span>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full min-w-[1120px] text-sm">
                    <thead>
                        <tr class="border-b border-slate-100 text-left text-xs font-semibold uppercase tracking-wide text-slate-400">
                            <th class="px-5 py-3 sm:px-6">Chave</th>
                            <th class="px-5 py-3">Resumo</th>
                            <th class="px-5 py-3">Solicitante</th>
                            <th class="px-5 py-3">Responsável</th>
                            <th class="px-5 py-3">Prioridade</th>
                            <th class="px-5 py-3">Status</th>
                            <th class="px-5 py-3">Unidade</th>
                            <th class="px-5 py-3">Atualizado</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($tickets as $ticket)
                            <tr class="transition hover:bg-slate-50">
                                <td class="px-5 py-4 font-semibold text-slate-900 sm:px-6">
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
                                        <a href="{{ $jiraUrl }}" target="_blank" rel="noopener noreferrer" class="text-blue-600 hover:text-blue-800">
                                            {{ $ticket->chave }}
                                        </a>
                                    @else
                                        {{ $ticket->chave }}
                                    @endif
                                </td>
                                <td class="max-w-sm px-5 py-4 text-slate-700">
                                    <span class="line-clamp-2">{{ $ticket->resumo ?? '-' }}</span>
                                </td>
                                <td class="px-5 py-4 text-slate-600">{{ $ticket->relator_nome ?? $ticket->relator_email ?? '-' }}</td>
                                <td class="px-5 py-4 text-slate-600">{{ $ticket->responsavel_nome ?? '-' }}</td>
                                <td class="px-5 py-4 text-slate-600">{{ $ticket->prioridade ?? '-' }}</td>
                                <td class="px-5 py-4 text-slate-600">{{ $ticket->status ?? $ticket->currentstatus_status ?? '-' }}</td>
                                <td class="px-5 py-4 text-slate-600">{{ $ticket->unidade ?? '-' }}</td>
                                <td class="px-5 py-4 text-slate-600">{{ optional($ticket->data_hora_atualizacao ?? $ticket->data_hora_criacao)->format('d/m/Y H:i') ?? '-' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="px-5 py-10 text-center text-sm text-slate-400">Nenhum chamado encontrado.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="border-t border-slate-100 p-5 sm:px-6">
                {{ $tickets->links() }}
            </div>
        </section>
    </div>
</x-app-layout>
