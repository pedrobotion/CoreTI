<x-app-layout>
    <div class="mx-auto max-w-7xl space-y-6 px-4 py-8 sm:px-6">
        <div>
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Service Desk</p>
            <h1 class="mt-1 text-2xl font-bold tracking-tight text-slate-900 sm:text-3xl">Minha fila</h1>
            <p class="mt-1 max-w-2xl text-sm text-slate-500">
                Mostra chamados onde você é solicitante (relator) por e-mail cadastrado ou aparece como responsável.
            </p>
        </div>

        <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <div class="flex flex-wrap items-end gap-2">
                <form
                    method="POST"
                    action="{{ route('service-desk.my-queue.emails.store') }}"
                    class="grid flex-1 gap-2 sm:grid-cols-[minmax(240px,420px)_auto]"
                    x-data="{ digitando: {{ $emailsDisponiveis->isEmpty() ? 'true' : 'false' }} }"
                >
                    @csrf

                    {{-- Seleção a partir dos e-mails que realmente aparecem nos chamados. --}}
                    <select
                        x-show="!digitando"
                        name="email"
                        :required="!digitando"
                        :disabled="digitando"
                        class="toolbar-input rounded-md border-slate-300 text-sm shadow-sm focus:border-slate-900 focus:ring-slate-900"
                    >
                        <option value="">Selecione um e-mail…</option>
                        @foreach ($emailsDisponiveis as $disponivel)
                            <option value="{{ $disponivel->email }}">
                                {{ $disponivel->email }} ({{ $disponivel->total }} chamados)
                            </option>
                        @endforeach
                    </select>

                    {{-- Escape para e-mail que ainda não abriu chamado nenhum. --}}
                    <input
                        x-show="digitando"
                        x-cloak
                        type="email"
                        name="email"
                        :required="digitando"
                        :disabled="!digitando"
                        placeholder="Digite o e-mail"
                        class="toolbar-input rounded-md border-slate-300 text-sm shadow-sm focus:border-slate-900 focus:ring-slate-900"
                    >

                    <button type="submit" style="background-color: #033151; border-color: #033151;" class="toolbar-btn-primary inline-flex items-center justify-center rounded-lg px-4 text-sm font-semibold text-white hover:opacity-90">
                        Cadastrar e-mail
                    </button>

                    @if ($emailsDisponiveis->isNotEmpty())
                        <button
                            type="button"
                            @click="digitando = !digitando"
                            class="text-left text-xs font-medium text-slate-500 underline-offset-2 hover:text-slate-800 hover:underline sm:col-span-2"
                            x-text="digitando ? 'Escolher da lista' : 'Digitar outro e-mail'"
                        ></button>
                    @endif
                </form>
            </div>

            @if ($queueEmails->isNotEmpty())
                <div class="mt-4 flex flex-wrap gap-2">
                    @foreach ($queueEmails as $queueEmail)
                        <form method="POST" action="{{ route('service-desk.my-queue.emails.destroy', $queueEmail) }}" data-confirm-message="Remover este e-mail da sua fila?">
                            @csrf
                            @method('DELETE')
                            <button class="inline-flex items-center gap-2 rounded-full border border-slate-300 bg-slate-50 px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-100">
                                <span>{{ $queueEmail->email }}</span>
                                <span class="text-red-600">×</span>
                            </button>
                        </form>
                    @endforeach
                </div>
            @else
                <p class="mt-4 text-sm text-slate-500 dark:text-slate-400">Nenhum e-mail cadastrado ainda.</p>
            @endif
        </section>

        <section class="rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-200 p-5 dark:border-slate-800">
                <form method="GET" action="{{ route('service-desk.my-queue') }}" class="toolbar-search-grid">
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
                    <input type="text" name="q" value="{{ $search }}" placeholder="Buscar por chave, resumo, solicitante, responsável, unidade ou departamento" class="toolbar-input rounded-md border-slate-300 text-sm shadow-sm focus:border-slate-900 focus:ring-slate-900">
                    <button type="submit" style="background-color: #033151; border-color: #033151;" class="toolbar-btn-primary inline-flex items-center justify-center rounded-lg px-4 text-sm font-semibold text-white hover:opacity-90">Filtrar</button>
                    <a href="{{ route('service-desk.my-queue') }}" class="toolbar-btn inline-flex items-center justify-center rounded-md border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50">Limpar</a>
                </form>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full min-w-[1120px] text-sm">
                    <thead class="border-b border-slate-100 text-left text-xs font-semibold uppercase tracking-wide text-slate-400">
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
                            <tr class="transition hover:bg-slate-50">
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
                                        <a href="{{ $jiraUrl }}" target="_blank" rel="noopener noreferrer" class="text-blue-600 hover:text-blue-800 dark:text-blue-300 dark:hover:text-blue-200">{{ $ticket->chave }}</a>
                                    @else
                                        {{ $ticket->chave }}
                                    @endif
                                </td>
                                <td class="max-w-sm px-5 py-4 text-slate-700 dark:text-slate-200"><span class="line-clamp-2">{{ $ticket->resumo ?? '-' }}</span></td>
                                <td class="px-5 py-4 text-slate-600 dark:text-slate-300">{{ $ticket->relator_nome ?? $ticket->relator_email ?? '-' }}</td>
                                <td class="px-5 py-4 text-slate-600 dark:text-slate-300">{{ $ticket->responsavel_nome ?? '-' }}</td>
                                <td class="px-5 py-4 text-slate-600 dark:text-slate-300">{{ $ticket->prioridade ?? '-' }}</td>
                                <td class="px-5 py-4 text-slate-600 dark:text-slate-300">{{ $ticket->status ?? $ticket->currentstatus_status ?? '-' }}</td>
                                <td class="px-5 py-4 text-slate-600 dark:text-slate-300">{{ $ticket->unidade ?? '-' }}</td>
                                <td class="px-5 py-4 text-slate-600 dark:text-slate-300">{{ optional($ticket->data_hora_atualizacao ?? $ticket->data_hora_criacao)->format('d/m/Y H:i') ?? '-' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="8" class="px-5 py-8 text-center text-slate-500 dark:text-slate-400">Nenhum chamado encontrado para sua fila.</td></tr>
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
