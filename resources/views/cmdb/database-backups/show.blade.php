<x-app-layout>
    <div class="mx-auto max-w-5xl space-y-6">

        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <h1 class="text-xl font-bold text-slate-950">{{ $backup->host }} / {{ $backup->database_name }}</h1>
                <p class="text-sm text-slate-500">
                    Evento de {{ optional($backup->data_backup ?? $backup->received_at)->format('d/m/Y H:i:s') ?: 'data desconhecida' }}
                </p>
            </div>
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('cmdb.database-backups.events', ['host' => $backup->host, 'database' => $backup->database_name]) }}" class="btn-coreti-secondary text-sm">Eventos deste par</a>
                @unless($backup->sucesso)
                    <form method="POST" action="{{ route('cmdb.database-backups.jira', $backup) }}" data-confirm-message="Abrir chamado no Jira para esta falha?">
                        @csrf
                        <button type="submit" class="btn-coreti-primary text-sm">Abrir chamado</button>
                    </form>
                @endunless
            </div>
        </div>

        @include('admissao.partials.flash')

        <div class="rounded-lg border {{ $backup->sucesso ? 'border-emerald-200 bg-emerald-50' : 'border-rose-200 bg-rose-50' }} px-4 py-3">
            <p class="text-sm font-semibold {{ $backup->sucesso ? 'text-emerald-800' : 'text-rose-800' }}">
                Status reportado: {{ $backup->status }}
            </p>
            @if($backup->observacao)
                <p class="text-sm {{ $backup->sucesso ? 'text-emerald-700' : 'text-rose-700' }}">{{ $backup->observacao }}</p>
            @endif
            @if($backup->jira_error)
                <p class="mt-1 text-xs text-rose-700">Erro ao abrir chamado: {{ $backup->jira_error }}</p>
            @endif
        </div>

        <div class="grid gap-6 lg:grid-cols-2">
            <section class="rounded-lg border border-slate-200 bg-white shadow-sm">
                <div class="border-b border-slate-200 px-5 py-3">
                    <h2 class="text-sm font-semibold text-slate-900">Dados da execução</h2>
                </div>
                <dl class="space-y-2 px-5 py-4 text-sm">
                    @foreach([
                        'Servidor' => $backup->host,
                        'IP' => $backup->ip_address,
                        'Banco' => $backup->database_name,
                        'Tipo' => $backup->backup_type,
                        'Arquivo' => $backup->arquivo,
                        'Tamanho' => $backup->tamanhoLegivel(),
                        'Início' => optional($backup->inicio_backup)->format('d/m/Y H:i:s'),
                        'Fim' => optional($backup->fim_backup)->format('d/m/Y H:i:s'),
                        'Duração' => $backup->duracaoLegivel(),
                        'Verificação' => optional($backup->data_verificacao)->format('d/m/Y H:i:s'),
                        'Recebido em' => optional($backup->received_at)->format('d/m/Y H:i:s'),
                    ] as $rotulo => $valor)
                        <div class="flex justify-between gap-3">
                            <dt class="shrink-0 text-slate-500">{{ $rotulo }}</dt>
                            <dd class="break-all text-right font-medium text-slate-800">{{ $valor ?: '-' }}</dd>
                        </div>
                    @endforeach
                </dl>
            </section>

            <section class="space-y-6">
                <div class="rounded-lg border border-slate-200 bg-white shadow-sm">
                    <div class="border-b border-slate-200 px-5 py-3">
                        <h2 class="text-sm font-semibold text-slate-900">Ativo no CMDB</h2>
                    </div>
                    <div class="px-5 py-4 text-sm">
                        @if($backup->asset)
                            <a href="{{ route('cmdb.assets.show', $backup->asset_id) }}" class="font-medium text-slate-800 hover:underline">
                                {{ $backup->asset->name ?? $backup->asset->hostname }}
                            </a>
                            <p class="text-xs text-slate-400">vinculado por {{ $backup->asset_match_method }}</p>
                        @else
                            <p class="text-slate-500">Nenhum ativo com este hostname no CMDB.</p>
                            <p class="mt-1 text-xs text-slate-400">
                                Cadastre o servidor <strong>{{ $backup->host }}</strong> no CMDB para que os próximos eventos sejam vinculados.
                            </p>
                        @endif
                    </div>
                </div>

                <div class="rounded-lg border border-slate-200 bg-white shadow-sm">
                    <div class="border-b border-slate-200 px-5 py-3">
                        <h2 class="text-sm font-semibold text-slate-900">Chamado</h2>
                    </div>
                    <div class="px-5 py-4 text-sm">
                        @if($backup->jira_issue_key)
                            @if($backup->jira_issue_url)
                                <a href="{{ $backup->jira_issue_url }}" target="_blank" rel="noopener" class="font-medium text-slate-800 hover:underline">{{ $backup->jira_issue_key }}</a>
                            @else
                                <span class="font-medium text-slate-800">{{ $backup->jira_issue_key }}</span>
                            @endif
                            <p class="text-xs text-slate-400">aberto em {{ optional($backup->jira_dispatched_at)->format('d/m/Y H:i') }}</p>
                        @else
                            <p class="text-slate-500">{{ $backup->sucesso ? 'Não aplicável — backup com sucesso.' : 'Nenhum chamado aberto.' }}</p>
                        @endif
                    </div>
                </div>
            </section>
        </div>

        <section class="rounded-lg border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-200 px-5 py-3">
                <h2 class="text-sm font-semibold text-slate-900">Últimas execuções deste par</h2>
            </div>
            <table class="min-w-full divide-y divide-slate-100 text-sm">
                <thead class="border-b border-slate-100">
                    <tr class="text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                        <th class="px-4 py-2">Quando</th>
                        <th class="px-4 py-2">Situação</th>
                        <th class="px-4 py-2">Tamanho</th>
                        <th class="px-4 py-2">Duração</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach($historico as $item)
                        <tr class="{{ $item->id === $backup->id ? 'bg-slate-50' : '' }}">
                            <td class="px-4 py-2">
                                <a href="{{ route('cmdb.database-backups.show', $item) }}" class="text-slate-700 hover:underline">
                                    {{ optional($item->data_backup ?? $item->received_at)->format('d/m/Y H:i') ?: '-' }}
                                </a>
                            </td>
                            <td class="px-4 py-2">
                                <span class="rounded-full px-2 py-0.5 text-xs font-semibold {{ $item->sucesso ? 'bg-emerald-50 text-emerald-700 ring-1 ring-inset ring-emerald-200' : 'bg-rose-50 text-rose-700 ring-1 ring-inset ring-rose-200' }}">
                                    {{ $item->status }}
                                </span>
                            </td>
                            <td class="px-4 py-2 text-slate-600">{{ $item->tamanhoLegivel() }}</td>
                            <td class="px-4 py-2 text-slate-600">{{ $item->duracaoLegivel() }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </section>

        <details class="rounded-lg border border-slate-200 bg-white shadow-sm">
            <summary class="cursor-pointer px-5 py-3 text-sm font-semibold text-slate-700">Payload recebido</summary>
            <pre class="overflow-x-auto border-t border-slate-100 px-5 py-4 text-xs text-slate-600">{{ json_encode($backup->payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) }}</pre>
        </details>
    </div>
</x-app-layout>
