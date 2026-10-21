@php use App\Support\Backups\BackupStatus; @endphp
<x-app-layout>
    <div class="mx-auto max-w-7xl space-y-6">

        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="text-xl font-bold text-slate-950">Auditoria de backups</h1>
                <p class="text-sm text-slate-500">
                    O CoreTI conecta nos servidores e verifica sozinho os backups cadastrados. Sem agente, sem webhook.
                </p>
            </div>
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('cmdb.database-backups.index') }}" class="btn-coreti-secondary text-sm">Histórico legado</a>
                @can('module.cmdb.manage')
                    <a href="{{ route('cmdb.backup-targets.create') }}" class="btn-coreti-primary text-sm">Novo alvo</a>
                @endcan
            </div>
        </div>

        @include('admissao.partials.flash')

        @unless($jiraConfigurado)
            <div class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                <p class="font-semibold">Jira não configurado.</p>
                <p>Falhas são detectadas e registradas, mas nenhum chamado é aberto — e o sistema não reporta sucesso falso.
                   Defina <code class="rounded bg-amber-100 px-1">JIRA_ENABLED</code>, <code class="rounded bg-amber-100 px-1">JIRA_BASE_URL</code>,
                   <code class="rounded bg-amber-100 px-1">JIRA_EMAIL</code> e <code class="rounded bg-amber-100 px-1">JIRA_API_TOKEN</code>.</p>
            </div>
        @endunless

        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4 xl:grid-cols-7">
            @foreach([
                ['Monitorados', $resumo['total'], 'slate'],
                ['Saudáveis', $resumo['saudaveis'], 'emerald'],
                ['Com problema', $resumo['com_problema'], 'rose'],
                ['Atrasados', $resumo['atrasados'], 'rose'],
                ['Arquivo ausente', $resumo['ausentes'], 'rose'],
                ['Tamanho anormal', $resumo['tamanho_anormal'], 'rose'],
                ['Inacessíveis', $resumo['inacessiveis'], 'amber'],
            ] as [$rotulo, $valor, $tom])
                <div class="rounded-lg border p-4 shadow-sm {{ BackupStatus::classesCartao($tom) }}">
                    <p class="text-xs font-semibold uppercase tracking-wide">{{ $rotulo }}</p>
                    <p class="mt-1 text-2xl font-bold {{ BackupStatus::classesNumero($tom) }}">{{ $valor }}</p>
                </div>
            @endforeach
        </div>

        @if($resumo['nunca_verificados'] > 0 || $resumo['inativos'] > 0)
            <p class="text-xs text-slate-500">
                {{ $resumo['nunca_verificados'] }} nunca verificado(s) · {{ $resumo['inativos'] }} inativo(s).
            </p>
        @endif

        <form method="GET" class="flex flex-wrap items-end gap-3 rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
            <div>
                <label class="block text-xs font-semibold text-slate-600">Situação</label>
                <select name="situacao" class="mt-1 rounded-md border-slate-300 text-sm">
                    <option value="todos" @selected($situacao === 'todos')>Todas</option>
                    <option value="problema" @selected($situacao === 'problema')>Problema no backup</option>
                    <option value="auditoria" @selected($situacao === 'auditoria')>Falha de auditoria</option>
                    <option value="saudaveis" @selected($situacao === 'saudaveis')>Saudáveis</option>
                    <option value="nunca" @selected($situacao === 'nunca')>Nunca verificados</option>
                    <option value="inativos" @selected($situacao === 'inativos')>Inativos</option>
                </select>
            </div>
            <div class="min-w-64 flex-1">
                <label class="block text-xs font-semibold text-slate-600">Buscar</label>
                <input type="text" name="q" value="{{ $busca }}" placeholder="Servidor, banco ou nome" class="mt-1 w-full rounded-md border-slate-300 text-sm">
            </div>
            <button type="submit" class="btn-coreti-secondary text-sm">Filtrar</button>
        </form>

        <div class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-100 text-sm">
                    <thead class="border-b border-slate-100">
                        <tr class="text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                            <th class="px-4 py-3">Servidor</th>
                            <th class="px-4 py-3">Banco</th>
                            <th class="px-4 py-3">Tipo</th>
                            <th class="px-4 py-3">Última verificação</th>
                            <th class="px-4 py-3">Último backup</th>
                            <th class="px-4 py-3">Idade</th>
                            <th class="px-4 py-3">Tamanho</th>
                            <th class="px-4 py-3">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse($alvos as $alvo)
                            @php $check = $alvo->ultimoCheck; @endphp
                            <tr class="{{ $alvo->ativo ? '' : 'opacity-50' }}">
                                <td class="px-4 py-3">
                                    <a href="{{ route('cmdb.backup-targets.show', $alvo) }}" class="font-medium text-slate-900 hover:underline">
                                        {{ $alvo->host }}
                                    </a>
                                    @unless($alvo->ativo)
                                        <span class="ml-1 rounded-full bg-slate-100 px-1.5 py-0.5 text-[10px] font-semibold text-slate-600">inativo</span>
                                    @endunless
                                    <p class="text-xs text-slate-400">{{ $alvo->enderecoSsh() }}</p>
                                </td>
                                <td class="px-4 py-3 text-slate-700">{{ $alvo->database_name }}</td>
                                <td class="px-4 py-3 text-slate-600">{{ $alvo->tipoLabel() }}</td>
                                <td class="px-4 py-3 text-slate-600">
                                    {{ optional($alvo->last_checked_at)->format('d/m H:i') ?: '—' }}
                                </td>
                                <td class="px-4 py-3 text-slate-600">
                                    {{ optional($check?->arquivo_modificado_em)->format('d/m H:i') ?: '—' }}
                                </td>
                                <td class="px-4 py-3 text-slate-600">{{ $check?->idadeLegivel() ?? '—' }}</td>
                                <td class="px-4 py-3 text-slate-600">{{ $check?->tamanhoLegivel() ?? '—' }}</td>
                                <td class="px-4 py-3">
                                    <span class="rounded-full px-2 py-0.5 text-xs font-semibold {{ BackupStatus::classesBadge($alvo->last_status) }}"
                                          title="{{ BackupStatus::descricao($alvo->last_status) }}">
                                        {{ $alvo->statusLabel() }}
                                    </span>
                                    @if($alvo->incidenteAberto?->jira_issue_key)
                                        <a href="{{ $alvo->incidenteAberto->jira_issue_url }}" target="_blank" rel="noopener"
                                           class="ml-1 text-[11px] text-slate-500 hover:underline">{{ $alvo->incidenteAberto->jira_issue_key }}</a>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="px-4 py-10 text-center text-sm text-slate-500">
                                    Nenhum alvo cadastrado.
                                    <span class="block text-xs text-slate-400">
                                        Cadastre o primeiro backup que o CoreTI deve auditar.
                                    </span>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-app-layout>
