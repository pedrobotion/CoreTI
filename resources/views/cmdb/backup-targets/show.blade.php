@php use App\Support\Backups\BackupStatus; @endphp
<x-app-layout>
    <div class="mx-auto max-w-6xl space-y-6">

        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <div class="flex flex-wrap items-center gap-2">
                    <h1 class="text-xl font-bold text-slate-950">{{ $alvo->nomeExibicao() }}</h1>
                    <span class="rounded-full px-2.5 py-0.5 text-xs font-semibold {{ BackupStatus::classesBadge($alvo->last_status) }}">
                        {{ $alvo->statusLabel() }}
                    </span>
                    @unless($alvo->ativo)
                        <span class="rounded-full bg-slate-100 px-2 py-0.5 text-xs font-semibold text-slate-600">inativo</span>
                    @endunless
                </div>
                <p class="text-sm text-slate-500">
                    {{ $alvo->host }} / {{ $alvo->database_name }} · {{ $alvo->tipoLabel() }} · {{ $alvo->periodicidadeLabel() }}
                </p>
            </div>

            @can('module.cmdb.manage')
                <div class="flex flex-wrap gap-2">
                    <form method="POST" action="{{ route('cmdb.backup-targets.test', $alvo) }}">
                        @csrf
                        <button type="submit" class="btn-coreti-secondary text-sm">Testar conexão SSH</button>
                    </form>
                    <form method="POST" action="{{ route('cmdb.backup-targets.verify', $alvo) }}">
                        @csrf
                        <button type="submit" class="btn-coreti-primary text-sm">Verificar agora</button>
                    </form>
                    <a href="{{ route('cmdb.backup-targets.edit', $alvo) }}" class="btn-coreti-secondary text-sm">Editar</a>
                    <form method="POST" action="{{ route('cmdb.backup-targets.toggle', $alvo) }}">
                        @csrf @method('PATCH')
                        <button type="submit" class="btn-coreti-secondary text-sm">{{ $alvo->ativo ? 'Desativar' : 'Ativar' }}</button>
                    </form>
                </div>
            @endcan
        </div>

        @include('admissao.partials.flash')

        <p class="rounded-lg border border-slate-200 bg-slate-50 px-4 py-2 text-xs text-slate-600">
            {{ BackupStatus::descricao($alvo->last_status) }}
        </p>

        @if($alvo->incidenteAberto)
            @php $inc = $alvo->incidenteAberto; @endphp
            <div class="rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">
                <p class="font-semibold">
                    Problema em aberto: {{ $inc->tipoFalhaLabel() }}
                    @if($inc->ehRecorrente())
                        <span class="font-normal">— {{ $inc->ocorrencias }} ocorrências desde {{ $inc->aberto_em->format('d/m H:i') }}</span>
                    @endif
                </p>
                <p class="mt-0.5">{{ $inc->detalhe }}</p>
                <p class="mt-1 text-xs">
                    Chamado: <strong>{{ $inc->jiraStatusLabel() }}</strong>
                    @if($inc->jira_issue_key)
                        —
                        @if($inc->jira_issue_url)
                            <a href="{{ $inc->jira_issue_url }}" target="_blank" rel="noopener" class="font-semibold underline">{{ $inc->jira_issue_key }}</a>
                        @else
                            {{ $inc->jira_issue_key }}
                        @endif
                    @elseif($inc->jira_erro)
                        — {{ $inc->jira_erro }}
                    @endif
                </p>
            </div>
        @endif

        <div class="grid gap-6 lg:grid-cols-3">
            <section class="rounded-lg border border-slate-200 bg-white shadow-sm">
                <div class="border-b border-slate-200 px-5 py-3">
                    <h2 class="text-sm font-semibold text-slate-900">Configuração</h2>
                </div>
                <dl class="space-y-2 px-5 py-4 text-sm">
                    @foreach([
                        'Arquivo' => $alvo->arquivo,
                        'Host SSH' => $alvo->enderecoSsh(),
                        'Usuário SSH' => $alvo->usuarioSsh(),
                        'Porta' => $alvo->portaSsh(),
                        'Método' => $alvo->metodo_verificacao,
                        'Horário esperado' => $alvo->horario_esperado ? substr($alvo->horario_esperado, 0, 5).' (±'.$alvo->tolerancia_horas.'h)' : '—',
                        'Idade máxima' => $alvo->idadeMaximaHoras().'h',
                        'Tamanho mínimo' => $alvo->tamanhoMinimoBytes()
                            ? number_format($alvo->tamanhoMinimoBytes() / 1073741824, 2, ',', '.').' GB ('.$alvo->origemTamanhoMinimo().')'
                            : 'sem referência ainda',
                        'Última verificação' => optional($alvo->last_checked_at)->format('d/m/Y H:i') ?: 'nunca',
                    ] as $rotulo => $valor)
                        <div class="flex justify-between gap-3">
                            <dt class="shrink-0 text-slate-500">{{ $rotulo }}</dt>
                            <dd class="break-all text-right font-medium text-slate-800">{{ $valor }}</dd>
                        </div>
                    @endforeach
                    <div class="flex justify-between gap-3">
                        <dt class="shrink-0 text-slate-500">Ativo CMDB</dt>
                        <dd class="text-right font-medium text-slate-800">
                            @if($alvo->asset)
                                <a href="{{ route('cmdb.assets.show', $alvo->asset_id) }}" class="hover:underline">
                                    {{ $alvo->asset->name ?? $alvo->asset->hostname }}
                                </a>
                            @else
                                não vinculado
                            @endif
                        </dd>
                    </div>
                </dl>
                @if($alvo->observacao)
                    <p class="border-t border-slate-100 px-5 py-3 text-xs text-slate-500">{{ $alvo->observacao }}</p>
                @endif
            </section>

            <section class="lg:col-span-2 rounded-lg border border-slate-200 bg-white shadow-sm">
                <div class="border-b border-slate-200 px-5 py-3">
                    <h2 class="text-sm font-semibold text-slate-900">Histórico de verificações</h2>
                    <p class="text-xs text-slate-500">Cada execução gera um registro; nada é sobrescrito.</p>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200 text-sm">
                        <thead class="bg-slate-50">
                            <tr class="text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                                <th class="px-4 py-2">Quando</th>
                                <th class="px-4 py-2">Status</th>
                                <th class="px-4 py-2">Backup de</th>
                                <th class="px-4 py-2">Idade</th>
                                <th class="px-4 py-2">Tamanho</th>
                                <th class="px-4 py-2">Duração</th>
                                <th class="px-4 py-2">Origem</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @forelse($checks as $check)
                                <tr>
                                    <td class="px-4 py-2 text-slate-600">{{ $check->executado_em->format('d/m H:i:s') }}</td>
                                    <td class="px-4 py-2">
                                        <span class="rounded-full px-2 py-0.5 text-xs font-semibold {{ BackupStatus::classesBadge($check->status) }}"
                                              title="{{ $check->detalhe }}">{{ $check->statusLabel() }}</span>
                                    </td>
                                    <td class="px-4 py-2 text-slate-600">{{ optional($check->arquivo_modificado_em)->format('d/m H:i') ?: '—' }}</td>
                                    <td class="px-4 py-2 text-slate-600">{{ $check->idadeLegivel() }}</td>
                                    <td class="px-4 py-2 text-slate-600">{{ $check->tamanhoLegivel() }}</td>
                                    <td class="px-4 py-2 text-slate-500">{{ $check->duracao_ms }}ms</td>
                                    <td class="px-4 py-2 text-xs text-slate-400">{{ $check->origem }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="7" class="px-4 py-8 text-center text-slate-500">Nenhuma verificação ainda.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>
        </div>

        @if($incidentes->isNotEmpty())
            <section class="rounded-lg border border-slate-200 bg-white shadow-sm">
                <div class="border-b border-slate-200 px-5 py-3">
                    <h2 class="text-sm font-semibold text-slate-900">Incidentes</h2>
                    <p class="text-xs text-slate-500">Um por problema contínuo — é o que evita chamado repetido a cada execução.</p>
                </div>
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50">
                        <tr class="text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                            <th class="px-4 py-2">Tipo</th>
                            <th class="px-4 py-2">Situação</th>
                            <th class="px-4 py-2">Aberto</th>
                            <th class="px-4 py-2">Recuperado</th>
                            <th class="px-4 py-2">Ocorrências</th>
                            <th class="px-4 py-2">Chamado</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach($incidentes as $inc)
                            <tr class="{{ $inc->estaAberto() ? 'bg-rose-50' : '' }}">
                                <td class="px-4 py-2 text-slate-700">{{ $inc->tipoFalhaLabel() }}</td>
                                <td class="px-4 py-2 text-slate-600">{{ $inc->estaAberto() ? 'Aberto' : 'Recuperado' }}</td>
                                <td class="px-4 py-2 text-slate-600">{{ $inc->aberto_em->format('d/m H:i') }}</td>
                                <td class="px-4 py-2 text-slate-600">{{ optional($inc->recuperado_em)->format('d/m H:i') ?: '—' }}</td>
                                <td class="px-4 py-2 text-slate-600">{{ $inc->ocorrencias }}</td>
                                <td class="px-4 py-2 text-xs">
                                    <span class="text-slate-500">{{ $inc->jiraStatusLabel() }}</span>
                                    @if($inc->jira_issue_url)
                                        <a href="{{ $inc->jira_issue_url }}" target="_blank" rel="noopener" class="ml-1 font-semibold text-slate-700 hover:underline">{{ $inc->jira_issue_key }}</a>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </section>
        @endif
    </div>
</x-app-layout>
