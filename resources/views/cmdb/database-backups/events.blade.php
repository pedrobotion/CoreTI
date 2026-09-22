<x-app-layout>
    <div class="mx-auto max-w-7xl space-y-6">

        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="text-xl font-bold text-slate-950">Histórico de eventos de backup</h1>
                <p class="text-sm text-slate-500">Cada execução reportada pelos agentes.</p>
            </div>
            <a href="{{ route('cmdb.database-backups.index') }}" class="btn-coreti-secondary text-sm">Voltar ao painel</a>
        </div>

        @include('admissao.partials.flash')

        <form method="GET" class="flex flex-wrap items-end gap-3 rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
            <div>
                <label class="block text-xs font-semibold text-slate-600">Servidor</label>
                <select name="host" class="mt-1 rounded-md border-slate-300 text-sm">
                    <option value="">Todos</option>
                    @foreach($hosts as $host)
                        <option value="{{ $host }}" @selected(($filtros['host'] ?? '') === $host)>{{ $host }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-600">Banco</label>
                <input type="text" name="database" value="{{ $filtros['database'] ?? '' }}" class="mt-1 rounded-md border-slate-300 text-sm">
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-600">Situação</label>
                <select name="situacao" class="mt-1 rounded-md border-slate-300 text-sm">
                    <option value="">Todas</option>
                    <option value="falha" @selected(($filtros['situacao'] ?? '') === 'falha')>Falha</option>
                    <option value="ok" @selected(($filtros['situacao'] ?? '') === 'ok')>Sucesso</option>
                </select>
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-600">Últimos dias</label>
                <input type="number" name="dias" value="{{ $filtros['dias'] ?? 30 }}" min="0" max="3650" class="mt-1 w-24 rounded-md border-slate-300 text-sm">
            </div>
            <button type="submit" class="btn-coreti-secondary text-sm">Filtrar</button>
        </form>

        <div class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50">
                        <tr class="text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                            <th class="px-4 py-3">Quando</th>
                            <th class="px-4 py-3">Servidor / Banco</th>
                            <th class="px-4 py-3">Situação</th>
                            <th class="px-4 py-3">Tamanho</th>
                            <th class="px-4 py-3">Duração</th>
                            <th class="px-4 py-3">Arquivo</th>
                            <th class="px-4 py-3">Chamado</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse($eventos as $evento)
                            <tr class="{{ $evento->sucesso ? '' : 'bg-rose-50' }}">
                                <td class="px-4 py-3 text-slate-600">
                                    <a href="{{ route('cmdb.database-backups.show', $evento) }}" class="hover:underline">
                                        {{ optional($evento->data_backup ?? $evento->received_at)->format('d/m/Y H:i') ?: '-' }}
                                    </a>
                                </td>
                                <td class="px-4 py-3">
                                    <span class="font-medium text-slate-800">{{ $evento->host }}</span>
                                    <p class="text-xs text-slate-400">{{ $evento->database_name }}</p>
                                </td>
                                <td class="px-4 py-3">
                                    <span class="rounded-full px-2 py-0.5 text-xs font-semibold {{ $evento->sucesso ? 'bg-emerald-100 text-emerald-800' : 'bg-rose-100 text-rose-800' }}">
                                        {{ $evento->status }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-slate-600">{{ $evento->tamanhoLegivel() }}</td>
                                <td class="px-4 py-3 text-slate-600">{{ $evento->duracaoLegivel() }}</td>
                                <td class="px-4 py-3 text-xs text-slate-500">{{ Str::limit($evento->arquivo, 40) ?: '-' }}</td>
                                <td class="px-4 py-3 text-xs">
                                    @if($evento->jira_issue_url)
                                        <a href="{{ $evento->jira_issue_url }}" target="_blank" rel="noopener" class="text-slate-700 hover:underline">{{ $evento->jira_issue_key }}</a>
                                    @else
                                        <span class="text-slate-400">{{ $evento->jira_issue_key ?: '-' }}</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="px-4 py-10 text-center text-slate-500">Nenhum evento no período.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{ $eventos->links() }}
    </div>
</x-app-layout>
