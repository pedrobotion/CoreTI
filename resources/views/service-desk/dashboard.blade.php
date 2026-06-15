<x-app-layout>
    <div class="mx-auto max-w-7xl space-y-6">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
            <div>
                <p class="text-sm font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">ServiceDesk</p>
                <h1 class="mt-1 text-2xl font-bold text-slate-950 dark:text-white">Dashboard Geral</h1>
                <p class="mt-2 max-w-2xl text-sm text-slate-600 dark:text-slate-300">
                    Visão consolidada de E-mail, Office e Jira em uma única tela.
                </p>
            </div>

            <div class="flex flex-wrap gap-2">
                @if ($isAdmin)
                    <form method="POST" action="{{ route('service-desk.workspace-sync.now') }}">
                        @csrf
                        <button type="submit" class="inline-flex min-h-10 items-center justify-center rounded-md border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50">
                            Sincronizar Google Agora
                        </button>
                    </form>
                    <form method="POST" action="{{ route('service-desk.workspace-sync.preview') }}">
                        @csrf
                        <button type="submit" class="inline-flex min-h-10 items-center justify-center rounded-md border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50">
                            Atualizar Prévia
                        </button>
                    </form>
                @endif
                <a href="{{ route('service-desk.tickets') }}" class="inline-flex min-h-10 items-center justify-center rounded-md border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100 dark:hover:bg-slate-800">
                    Ver fila
                </a>
            </div>
        </div>

        <div class="grid gap-4 lg:grid-cols-3">
            <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <p class="text-sm font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">E-mail</p>
                <p class="mt-3 text-3xl font-bold text-slate-950 dark:text-white">{{ $emailStats['total_ativos'] }}</p>
                <div class="mt-4 grid grid-cols-2 gap-2 text-xs">
                    <div class="rounded-md bg-slate-100 px-2 py-2 text-center text-slate-700 dark:bg-slate-800 dark:text-slate-200">Sede<br><span class="font-semibold">{{ $emailStats['sede'] }}</span></div>
                    <div class="rounded-md bg-slate-100 px-2 py-2 text-center text-slate-700 dark:bg-slate-800 dark:text-slate-200">Unidades<br><span class="font-semibold">{{ $emailStats['unidades'] }}</span></div>
                    <div class="rounded-md bg-slate-100 px-2 py-2 text-center text-slate-700 dark:bg-slate-800 dark:text-slate-200">Cerrado<br><span class="font-semibold">{{ $emailStats['cerrado'] }}</span></div>
                    <div class="rounded-md bg-slate-100 px-2 py-2 text-center text-slate-700 dark:bg-slate-800 dark:text-slate-200">Genéricos<br><span class="font-semibold">{{ $emailStats['genericos'] }}</span></div>
                </div>
            </div>
            <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <p class="text-sm font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Office</p>
                <p class="mt-3 text-3xl font-bold text-slate-950 dark:text-white">{{ $officeStats['total_ativos'] }}</p>
                <div class="mt-4 grid grid-cols-2 gap-2 text-xs">
                    <div class="rounded-md bg-slate-100 px-2 py-2 text-center text-slate-700 dark:bg-slate-800 dark:text-slate-200">Apps<br><span class="font-semibold">{{ $officeStats['apps'] }}</span></div>
                    <div class="rounded-md bg-slate-100 px-2 py-2 text-center text-slate-700 dark:bg-slate-800 dark:text-slate-200">Business<br><span class="font-semibold">{{ $officeStats['business'] }}</span></div>
                    <div class="rounded-md bg-slate-100 px-2 py-2 text-center text-slate-700 dark:bg-slate-800 dark:text-slate-200">Power BI<br><span class="font-semibold">{{ $officeStats['powerbi'] }}</span></div>
                    <div class="rounded-md bg-slate-100 px-2 py-2 text-center text-slate-700 dark:bg-slate-800 dark:text-slate-200">Visio<br><span class="font-semibold">{{ $officeStats['visio'] }}</span></div>
                </div>
            </div>
            <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <p class="text-sm font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Jira</p>
                <p class="mt-3 text-3xl font-bold text-slate-950 dark:text-white">{{ $jiraGeneralStats['chamados_total'] }}</p>
                <div class="mt-4 grid grid-cols-2 gap-2 text-xs">
                    <div class="rounded-md bg-slate-100 px-2 py-2 text-center text-slate-700 dark:bg-slate-800 dark:text-slate-200">Abertos<br><span class="font-semibold">{{ $jiraGeneralStats['chamados_abertos'] }}</span></div>
                    <div class="rounded-md bg-slate-100 px-2 py-2 text-center text-slate-700 dark:bg-slate-800 dark:text-slate-200">Resolvidos hoje<br><span class="font-semibold">{{ $jiraGeneralStats['resolvidos_hoje'] }}</span></div>
                    <div class="rounded-md bg-slate-100 px-2 py-2 text-center text-slate-700 dark:bg-slate-800 dark:text-slate-200 col-span-2">SLA em atenção<br><span class="font-semibold text-amber-700 dark:text-amber-300">{{ $jiraGeneralStats['sla_atencao'] }}</span></div>
                </div>
            </div>
        </div>

        <div class="grid gap-6 xl:grid-cols-[1.15fr_0.85fr]">
            <section class="rounded-lg border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <div class="border-b border-slate-200 px-5 py-4 dark:border-slate-800">
                    <h2 class="text-base font-semibold text-slate-950 dark:text-white">Chamados recentes</h2>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[760px] text-sm">
                        <thead class="bg-slate-50 text-left text-xs font-semibold uppercase text-slate-500 dark:bg-slate-950/40 dark:text-slate-400">
                            <tr>
                                <th class="px-5 py-3">ID</th>
                                <th class="px-5 py-3">Solicitação</th>
                                <th class="px-5 py-3">Área</th>
                                <th class="px-5 py-3">Prioridade</th>
                                <th class="px-5 py-3">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                            @forelse ($recentTickets as $ticket)
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
                                    <td class="px-5 py-4 text-slate-700 dark:text-slate-200">{{ $ticket->resumo ?? 'Sem resumo' }}</td>
                                    <td class="px-5 py-4 text-slate-600 dark:text-slate-300">{{ $ticket->departamento ?? $ticket->unidade ?? '-' }}</td>
                                    <td class="px-5 py-4 text-slate-600 dark:text-slate-300">{{ $ticket->prioridade ?? '-' }}</td>
                                    <td class="px-5 py-4 text-slate-600 dark:text-slate-300">{{ $ticket->status ?? $ticket->currentstatus_status ?? '-' }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="px-5 py-8 text-center text-slate-500 dark:text-slate-400">Nenhum chamado encontrado.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <h2 class="text-base font-semibold text-slate-950 dark:text-white">Filas de atendimento</h2>
                <div class="mt-4 space-y-3">
                    @foreach ($queues as $queue)
                        <div class="rounded-md border border-slate-200 bg-slate-50 p-3 dark:border-slate-800 dark:bg-slate-950/40">
                            <div class="flex items-center justify-between gap-3">
                                <p class="font-semibold text-slate-800 dark:text-slate-100">{{ $queue['name'] }}</p>
                                <span class="text-sm font-bold text-slate-900 dark:text-white">{{ $queue['total'] }}</span>
                            </div>
                            <p class="mt-1 text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">{{ $queue['status'] }}</p>
                        </div>
                    @endforeach
                </div>
            </section>
        </div>
    </div>

    @php
        $syncSummary = $workspacePreview['summary'] ?? [];
        $workspacePendingSyncCount =
            ($syncSummary['new_emails'] ?? 0)
            + ($syncSummary['pending'] ?? 0)
            + ($syncSummary['center_cost_changes'] ?? 0)
            + ($syncSummary['sem_centro_custo'] ?? 0);
    @endphp

    @if ($isAdmin && !empty($workspacePreview) && $workspacePendingSyncCount > 0)
            <div id="workspace-sync-modal" class="fixed inset-0 z-50 hidden items-center justify-center bg-slate-900/60 px-3 py-4 sm:px-6">
                <div class="max-h-[calc(100vh-2rem)] w-full max-w-4xl overflow-y-auto rounded-lg border border-slate-200 bg-white p-4 shadow-xl sm:p-5">
                    <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <h2 class="text-lg font-semibold text-slate-900">Sincronização Google Workspace</h2>
                        <button type="button" onclick="closeWorkspaceSyncModal()" class="inline-flex min-h-9 items-center justify-center rounded-md border border-slate-200 px-3 text-sm font-semibold text-slate-600 hover:bg-slate-100 hover:text-slate-900">Fechar</button>
                    </div>
                    <p class="text-sm text-slate-600">
                        Fonte: Google Workspace Admin. Destino: coreti_google_emails.
                    </p>

                    <div class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                        <div class="rounded-md border border-slate-200 bg-slate-50 p-3">
                            <p class="text-xs font-semibold uppercase text-slate-600">Total recebido</p>
                            <p class="mt-1 text-2xl font-bold text-slate-900">{{ ($syncSummary['google_total'] ?? 0) }}</p>
                        </div>
                        <div class="rounded-md border border-emerald-200 bg-emerald-50 p-3">
                            <p class="text-xs font-semibold uppercase text-emerald-700">Novos e-mails</p>
                            <p class="mt-1 text-2xl font-bold text-emerald-800">{{ ($syncSummary['new_emails'] ?? 0) }}</p>
                        </div>
                        <div class="rounded-md border border-sky-200 bg-sky-50 p-3">
                            <p class="text-xs font-semibold uppercase text-sky-700">Existentes / atualizados</p>
                            <p class="mt-1 text-2xl font-bold text-sky-800">{{ ($syncSummary['existing_emails'] ?? 0) }} / {{ ($syncSummary['updated_emails'] ?? 0) }}</p>
                        </div>
                        <div class="rounded-md border border-amber-200 bg-amber-50 p-3">
                            <p class="text-xs font-semibold uppercase text-amber-700">Mapeados / pendentes</p>
                            <p class="mt-1 text-2xl font-bold text-amber-800">{{ ($syncSummary['mapped'] ?? 0) }} / {{ ($syncSummary['pending'] ?? 0) }}</p>
                        </div>
                    </div>

                    <div class="mt-3 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                        <div class="rounded-md border border-violet-200 bg-violet-50 p-3">
                            <p class="text-xs font-semibold uppercase text-violet-700">Sem AD</p>
                            <p class="mt-1 text-2xl font-bold text-violet-800">{{ ($syncSummary['sem_ad'] ?? 0) }}</p>
                        </div>
                        <div class="rounded-md border border-rose-200 bg-rose-50 p-3">
                            <p class="text-xs font-semibold uppercase text-rose-700">Sem centro de custo</p>
                            <p class="mt-1 text-2xl font-bold text-rose-800">{{ ($syncSummary['sem_centro_custo'] ?? 0) }}</p>
                        </div>
                        <div class="rounded-md border border-cyan-200 bg-cyan-50 p-3">
                            <p class="text-xs font-semibold uppercase text-cyan-700">Trocas de centro de custo</p>
                            <p class="mt-1 text-2xl font-bold text-cyan-800">{{ ($syncSummary['center_cost_changes'] ?? 0) }}</p>
                        </div>
                    </div>

                    <div class="mt-4 grid gap-4 lg:grid-cols-2">
                        <div class="rounded-md border border-slate-200 p-3">
                            <p class="text-xs font-semibold uppercase text-slate-500">Amostra: novos e-mails</p>
                            <ul class="mt-2 space-y-1 text-sm text-slate-700">
                                @foreach (($workspacePreview['samples']['missing_in_coreti'] ?? []) as $entry)
                                    @php
                                        $syncEmail = is_array($entry) ? ($entry['email'] ?? '-') : $entry;
                                        $syncUsuario = is_array($entry) ? ($entry['matricula'] ?? null) : null;
                                    @endphp
                                    <li>
                                        {{ $syncEmail }}
                                        @if (!empty($syncUsuario))
                                            <span class="text-slate-500">(usuário: {{ $syncUsuario }})</span>
                                        @endif
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                        <div class="rounded-md border border-slate-200 p-3">
                            <p class="text-xs font-semibold uppercase text-slate-500">Amostra: trocas de centro de custo</p>
                            <ul class="mt-2 space-y-1 text-sm text-slate-700">
                                @foreach (($workspacePreview['samples']['center_cost_changes'] ?? []) as $entry)
                                    @php
                                        $syncEmail = is_array($entry) ? ($entry['email'] ?? '-') : $entry;
                                        $syncOld = is_array($entry) ? ($entry['centro_custo_anterior'] ?? '-') : '-';
                                        $syncNew = is_array($entry) ? ($entry['centro_custo_novo'] ?? '-') : '-';
                                    @endphp
                                    <li>
                                        {{ $syncEmail }}
                                        <span class="text-slate-500">( {{ $syncOld }} → {{ $syncNew }} )</span>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    </div>

                    <div class="mt-5 flex flex-col-reverse gap-2 sm:flex-row sm:items-center sm:justify-end">
                        <button type="button" onclick="closeWorkspaceSyncModal()" class="inline-flex min-h-10 w-full items-center justify-center rounded-md border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-700 hover:bg-slate-50 sm:w-auto">Depois</button>
                        <form method="POST" action="{{ route('service-desk.workspace-sync.apply') }}" class="w-full sm:w-auto">
                            @csrf
                            <button type="submit" class="inline-flex min-h-10 w-full items-center justify-center rounded-md border border-slate-900 bg-slate-900 px-4 text-sm font-semibold text-white hover:bg-slate-800 sm:w-auto">
                                Aplicar alterações
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <script>
                function openWorkspaceSyncModal() {
                    const modal = document.getElementById('workspace-sync-modal');
                    if (!modal) return;
                    modal.classList.remove('hidden');
                    modal.classList.add('flex');
                }

                function closeWorkspaceSyncModal() {
                    const modal = document.getElementById('workspace-sync-modal');
                    if (!modal) return;
                    modal.classList.remove('flex');
                    modal.classList.add('hidden');
                }

                document.addEventListener('DOMContentLoaded', openWorkspaceSyncModal);
            </script>
    @endif
</x-app-layout>
