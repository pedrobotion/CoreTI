<x-app-layout>
    <div class="mx-auto max-w-7xl space-y-6">

        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="text-xl font-bold text-slate-950">Backups de banco de dados</h1>
                <p class="text-sm text-slate-500">
                    Situação atual de cada par servidor/banco, a partir dos eventos enviados pelos agentes.
                </p>
            </div>
            <a href="{{ route('cmdb.database-backups.events') }}" class="btn-coreti-secondary text-sm">Histórico de eventos</a>
        </div>

        @include('admissao.partials.flash')

        {{--
            "Falta configurar" e "está desligado de propósito" são situações
            diferentes, e a tela dizia a primeira nos dois casos — mandando
            preencher variáveis que já estavam preenchidas.
        --}}
        @if($jiraMotivo === 'nao_configurado')
            <div class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                Jira não configurado no CoreTI — falhas são registradas, mas o chamado é apenas simulado.
                Preencha <code class="rounded bg-amber-100 px-1">JIRA_ENABLED</code>, <code class="rounded bg-amber-100 px-1">JIRA_BASE_URL</code>,
                <code class="rounded bg-amber-100 px-1">JIRA_EMAIL</code> e <code class="rounded bg-amber-100 px-1">JIRA_API_TOKEN</code> no .env.
            </div>
        @elseif($jiraMotivo === 'desligado')
            <div class="rounded-lg border border-slate-300 bg-slate-50 px-4 py-3 text-sm text-slate-700">
                <strong>Abertura automática de chamado desligada.</strong>
                A credencial do Jira está configurada e funcionando — o que está desativado é a abertura
                automática pelo CoreTI (<code class="rounded bg-slate-200 px-1">BACKUP_JIRA_ENABLED</code>).
                As falhas continuam sendo registradas aqui, e o chamado pode ser aberto manualmente.
            </div>
        @endif

        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-6">
            <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Pares monitorados</p>
                <p class="mt-2 text-3xl font-bold text-slate-950">{{ $resumo['pares'] }}</p>
            </div>
            <div class="rounded-lg border border-emerald-200 bg-emerald-50 p-5 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wide text-emerald-700">Saudáveis</p>
                <p class="mt-2 text-3xl font-bold text-emerald-800">{{ $resumo['ok'] }}</p>
            </div>
            <div class="rounded-lg border border-rose-200 bg-rose-50 p-5 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wide text-rose-700">Com falha</p>
                <p class="mt-2 text-3xl font-bold text-rose-800">{{ $resumo['falha'] }}</p>
            </div>
            <div class="rounded-lg border border-amber-200 bg-amber-50 p-5 shadow-sm" title="Sem reportar há mais de {{ $horasParaAtraso }}h, mas há menos de {{ $diasParaInativo }} dias">
                <p class="text-xs font-semibold uppercase tracking-wide text-amber-700">Em silêncio</p>
                <p class="mt-2 text-3xl font-bold text-amber-800">{{ $resumo['atrasados'] }}</p>
            </div>
            @if(($resumo['inativos'] ?? 0) > 0)
                <a href="{{ route('cmdb.database-backups.index', ['situacao' => 'inativos']) }}"
                   class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm transition hover:border-slate-400"
                   title="Sem reportar há mais de {{ $diasParaInativo }} dias — provavelmente identificador antigo, não backup falhando">
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Descontinuados</p>
                    <p class="mt-2 text-3xl font-bold text-slate-700">{{ $resumo['inativos'] }}</p>
                    <p class="mt-1 text-xs text-slate-400">fora da contagem</p>
                </a>
            @endif
            <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm" title="Host sem ativo correspondente no CMDB">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Sem ativo no CMDB</p>
                <p class="mt-2 text-3xl font-bold text-slate-950">{{ $resumo['sem_ativo'] }}</p>
            </div>
            <div class="rounded-lg border {{ ($resumo['divergentes'] ?? 0) > 0 ? 'border-rose-300 bg-rose-100' : 'border-slate-200 bg-white' }} p-5 shadow-sm" title="Agente reportou sucesso, mas o arquivo no servidor não confirma (últimos 7 dias)">
                <p class="text-xs font-semibold uppercase tracking-wide {{ ($resumo['divergentes'] ?? 0) > 0 ? 'text-rose-800' : 'text-slate-500' }}">Divergências</p>
                <p class="mt-2 text-3xl font-bold {{ ($resumo['divergentes'] ?? 0) > 0 ? 'text-rose-900' : 'text-slate-950' }}">{{ $resumo['divergentes'] ?? 0 }}</p>
            </div>
        </div>

        <form method="GET" class="flex flex-wrap items-end gap-3 rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
            <div>
                <label class="block text-xs font-semibold text-slate-600">Situação</label>
                <select name="situacao" class="mt-1 rounded-md border-slate-300 text-sm">
                    <option value="todos" @selected($situacao === 'todos')>Todas</option>
                    <option value="falha" @selected($situacao === 'falha')>Com falha</option>
                    <option value="atrasados" @selected($situacao === 'atrasados')>Em silêncio</option>
                    <option value="ok" @selected($situacao === 'ok')>Saudáveis</option>
                    <option value="sem_ativo" @selected($situacao === 'sem_ativo')>Sem ativo no CMDB</option>
                    <option value="inativos" @selected($situacao === 'inativos')>Descontinuados</option>
                </select>
            </div>
            <div class="min-w-64 flex-1">
                <label class="block text-xs font-semibold text-slate-600">Buscar</label>
                <input type="text" name="q" value="{{ $busca }}" placeholder="Servidor ou banco" class="mt-1 w-full rounded-md border-slate-300 text-sm">
            </div>
            <button type="submit" class="btn-coreti-secondary text-sm">Filtrar</button>
        </form>

        <div class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-100 text-sm">
                    <thead class="border-b border-slate-100">
                        <tr class="text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                            <th class="px-4 py-3">Servidor / Banco</th>
                            <th class="px-4 py-3">Ativo no CMDB</th>
                            <th class="px-4 py-3">Último backup</th>
                            <th class="px-4 py-3">Situação (agente)</th>
                            <th class="px-4 py-3">Arquivo (CoreTI)</th>
                            <th class="px-4 py-3">Tamanho</th>
                            <th class="px-4 py-3">Duração</th>
                            <th class="px-4 py-3">Chamado</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse($pares as $par)
                            <tr class="{{ ! $par->sucesso ? 'bg-rose-50' : ($par->atrasado ? 'bg-amber-50' : '') }}">
                                <td class="px-4 py-3">
                                    <a href="{{ route('cmdb.database-backups.show', $par) }}" class="font-medium text-slate-900 hover:underline">
                                        {{ $par->host }}
                                    </a>
                                    <p class="text-xs text-slate-400">{{ $par->database_name }} · {{ $par->backup_type ?: 'tipo não informado' }}</p>
                                </td>
                                <td class="px-4 py-3 text-slate-600">
                                    @if($par->asset)
                                        <a href="{{ route('cmdb.assets.show', $par->asset_id) }}" class="text-slate-700 hover:underline">
                                            {{ $par->asset->name ?? $par->asset->hostname ?? ('#'.$par->asset_id) }}
                                        </a>
                                    @else
                                        <span class="text-xs text-slate-400">não vinculado</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-slate-600">
                                    {{ optional($par->data_backup ?? $par->received_at)->format('d/m/Y H:i') ?: '-' }}
                                    @if($par->atrasado)
                                        <p class="text-xs font-semibold text-amber-700">sem reportar há mais de {{ $horasParaAtraso }}h</p>
                                    @endif
                                </td>
                                <td class="px-4 py-3">
                                    @if($par->sucesso)
                                        <span class="rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-semibold text-emerald-800">{{ $par->status }}</span>
                                    @else
                                        <span class="rounded-full bg-rose-100 px-2 py-0.5 text-xs font-semibold text-rose-800">{{ $par->status }}</span>
                                    @endif
                                </td>
                                @php $verificacao = $verificacoes[$par->host.'|'.$par->database_name] ?? null; @endphp
                                <td class="px-4 py-3">
                                    @if($verificacao?->last_status === 'ok')
                                        <span class="rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-semibold text-emerald-800">confere</span>
                                    @elseif($verificacao?->last_status)
                                        <span class="rounded-full bg-rose-100 px-2 py-0.5 text-xs font-semibold text-rose-800" title="{{ $verificacao->statusLabel() }}">
                                            {{ $verificacao->last_status }}
                                        </span>
                                    @else
                                        <span class="text-xs text-slate-400">não verificado</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-slate-600">{{ $par->tamanhoLegivel() }}</td>
                                <td class="px-4 py-3 text-slate-600">{{ $par->duracaoLegivel() }}</td>
                                <td class="px-4 py-3 text-xs">
                                    @if($par->jira_issue_url)
                                        <a href="{{ $par->jira_issue_url }}" target="_blank" rel="noopener" class="text-slate-700 hover:underline">{{ $par->jira_issue_key }}</a>
                                    @else
                                        <span class="text-slate-400">{{ $par->jira_issue_key ?: '-' }}</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="px-4 py-10 text-center text-sm text-slate-500">
                                    Nenhum evento de backup recebido ainda.
                                    <span class="block text-xs text-slate-400">Aponte os agentes para {{ route('webhooks.backup') }}</span>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-app-layout>
