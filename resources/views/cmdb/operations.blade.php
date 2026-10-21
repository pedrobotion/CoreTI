<x-app-layout>
    <div class="mx-auto max-w-7xl space-y-6">
        @include('cmdb.partials._nav', ['title' => 'Centro de Operações', 'description' => 'Painel operacional consolidado do CMDB.'])

        @if(!empty($setupPending))
            <div class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                Estrutura do CMDB ainda não foi criada no banco. Execute as migrations para liberar o módulo.
            </div>
        @endif

        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-5">
            @foreach($cards as $card)
                <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ $card['label'] }}</p>
                    <p class="mt-2 text-3xl font-bold text-slate-950">{{ $card['value'] }}</p>
                </div>
            @endforeach
        </div>

        <section class="rounded-lg border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-200 px-5 py-4">
                <h2 class="text-base font-semibold text-slate-900">Falhas recentes de sincronização</h2>
            </div>
            <div class="p-5 space-y-3">
                @forelse($recentSyncFailures ?? collect() as $log)
                    <div class="rounded-md border border-slate-200 p-3 text-sm">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <div>
                                <a href="{{ route('cmdb.sync-logs.show', $log) }}" class="font-semibold text-[#033151] hover:underline">{{ $log->message ?: 'Falha de sincronização' }}</a>
                                <div class="text-xs text-slate-500">{{ $log->connector?->name ?: 'Sem conector' }} • {{ $log->started_at?->format('d/m/Y H:i') }}</div>
                            </div>
                            <span class="rounded-full bg-rose-100 px-2.5 py-0.5 text-xs font-semibold text-rose-800">{{ $log->status }}</span>
                        </div>
                    </div>
                @empty
                    <p class="text-sm text-slate-500">Nenhuma falha recente encontrada.</p>
                @endforelse
            </div>
        </section>

        <div class="grid gap-6 xl:grid-cols-2">
            <section class="rounded-lg border border-slate-200 bg-white shadow-sm">
                <div class="border-b border-slate-200 px-5 py-4">
                    <h2 class="text-base font-semibold text-slate-900">Ativos críticos</h2>
                </div>
                <div class="p-5 space-y-3">
                    @forelse($criticalAssets as $asset)
                        <div class="rounded-md border border-slate-200 p-3">
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <div>
                                    <a href="{{ route('cmdb.assets.show', $asset) }}" class="font-semibold text-[#033151] hover:underline">{{ $asset->name }}</a>
                                    <div class="text-xs text-slate-500">{{ $asset->hostname ?: 'Sem hostname' }}</div>
                                </div>
                                <span class="rounded-full bg-rose-100 px-2.5 py-0.5 text-xs font-semibold text-rose-800">{{ $types[$asset->asset_type] ?? $asset->asset_type }}</span>
                            </div>
                            <div class="mt-2 text-sm text-slate-600">{{ $asset->rack?->name ?: 'Sem rack' }}</div>
                        </div>
                    @empty
                        <p class="text-sm text-slate-500">Nenhum ativo crítico encontrado.</p>
                    @endforelse
                </div>
            </section>

            <section class="rounded-lg border border-slate-200 bg-white shadow-sm">
                <div class="border-b border-slate-200 px-5 py-4">
                    <h2 class="text-base font-semibold text-slate-900">Vencimentos urgentes</h2>
                </div>
                <div class="p-5 space-y-3">
                    @forelse($urgentExpirations as $item)
                        <div class="rounded-md border border-slate-200 p-3">
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <div>
                                    <div class="font-semibold text-slate-900">{{ $item->title }}</div>
                                    <div class="text-xs text-slate-500">
                                        {{ $item->asset?->name ?: 'Sem ativo vinculado' }}
                                        • {{ $item->expiration_type }}
                                    </div>
                                </div>
                                <div class="text-right">
                                    <div class="text-sm font-semibold text-slate-950">{{ optional($item->expiration_date)->format('d/m/Y') }}</div>
                                    <div class="text-xs text-slate-500">{{ $item->status }}</div>
                                </div>
                            </div>
                            <div class="mt-2 flex flex-wrap gap-2">
                                @if($item->jira_issue_key)
                                    <a href="{{ $item->jira_issue_url ?: '#' }}" target="_blank" class="inline-flex rounded-md border border-slate-300 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50">Abrir chamado Jira</a>
                                    <span class="inline-flex rounded-full bg-emerald-100 px-2.5 py-0.5 text-xs font-semibold text-emerald-800">{{ $item->jira_issue_key }}</span>
                                @else
                                    <form method="POST" action="{{ route('cmdb.expirations.jira', $item) }}">
                                        @csrf
                                        <button class="inline-flex rounded-md border border-[#033151] bg-white px-3 py-1.5 text-xs font-semibold text-[#033151] hover:bg-slate-50">Abrir chamado Jira</button>
                                    </form>
                                @endif
                            </div>
                        </div>
                    @empty
                        <p class="text-sm text-slate-500">Nenhum vencimento urgente encontrado.</p>
                    @endforelse
                </div>
            </section>
        </div>

        <div class="grid gap-6 xl:grid-cols-2">
            <section class="rounded-lg border border-slate-200 bg-white shadow-sm">
                <div class="border-b border-slate-200 px-5 py-4">
                    <h2 class="text-base font-semibold text-slate-900">Ativos incompletos</h2>
                </div>
                <div class="p-5 space-y-3">
                    @forelse($incompleteAssets as $asset)
                        <div class="rounded-md border border-slate-200 p-3">
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <div>
                                    <a href="{{ route('cmdb.assets.show', $asset) }}" class="font-semibold text-[#033151] hover:underline">{{ $asset->name }}</a>
                                    <div class="text-xs text-slate-500">{{ $asset->hostname ?: 'Sem hostname' }}</div>
                                </div>
                                <div class="flex flex-wrap gap-2">
                                    @if(! $asset->ipAddresses->count())
                                        <span class="rounded-full bg-amber-100 px-2.5 py-0.5 text-xs font-semibold text-amber-800">Sem IP</span>
                                    @endif
                                    @if(! $asset->rack_id)
                                        <span class="rounded-full bg-blue-100 px-2.5 py-0.5 text-xs font-semibold text-blue-800">Sem rack</span>
                                    @endif
                                    @if(! $asset->outgoingRelationships->count() && ! $asset->incomingRelationships->count())
                                        <span class="rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-semibold text-slate-700">Sem relacionamento</span>
                                    @endif
                                </div>
                            </div>
                        </div>
                    @empty
                        <p class="text-sm text-slate-500">Nenhum ativo incompleto encontrado.</p>
                    @endforelse
                </div>
            </section>

            <section class="rounded-lg border border-slate-200 bg-white shadow-sm">
                <div class="border-b border-slate-200 px-5 py-4">
                    <h2 class="text-base font-semibold text-slate-900">Últimas alterações do CMDB</h2>
                </div>
                <div class="p-5 space-y-3">
                    @forelse($recentHistories as $history)
                        <div class="rounded-md border border-slate-200 p-3">
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <div>
                                    <div class="font-semibold text-slate-900">{{ $history->title }}</div>
                                    <div class="text-xs text-slate-500">{{ $history->asset?->name ?: 'Sem ativo vinculado' }} • {{ $history->user?->name ?: 'Sistema' }}</div>
                                </div>
                                <div class="text-right text-xs text-slate-500">
                                    <div>{{ optional($history->created_at)->format('d/m/Y H:i') }}</div>
                                    <div>{{ $history->event_type }}</div>
                                </div>
                            </div>
                            @if($history->description)
                                <div class="mt-2 text-sm text-slate-600">{{ $history->description }}</div>
                            @endif
                        </div>
                    @empty
                        <p class="text-sm text-slate-500">Sem histórico registrado ainda.</p>
                    @endforelse
                </div>
            </section>
        </div>

        <section class="rounded-lg border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-200 px-5 py-4 flex items-center justify-between gap-2">
                <div>
                    <h2 class="text-base font-semibold text-slate-900">Descobertas pendentes</h2>
                    <p class="text-sm text-slate-500">Itens SNMP aguardando revisão técnica, vínculo ou criação de ativo.</p>
                </div>
                <a href="{{ route('cmdb.discovered-assets.index') }}" class="text-sm font-semibold text-[#033151] hover:underline">Abrir fila</a>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-100 text-sm">
                    <thead class="border-b border-slate-100 text-left text-xs font-semibold uppercase tracking-wide text-slate-400">
                        <tr>
                            <th class="px-5 py-3">Hostname</th>
                            <th class="px-5 py-3">IP</th>
                            <th class="px-5 py-3">Conector</th>
                            <th class="px-5 py-3">Descoberto em</th>
                            <th class="px-5 py-3">Ações</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse($pendingDiscoveries as $item)
                            <tr>
                                <td class="px-5 py-3">
                                    <a href="{{ route('cmdb.discovered-assets.show', $item) }}" class="font-semibold text-[#033151] hover:underline">{{ $item->hostname ?: $item->source_external_id ?: '-' }}</a>
                                    <div class="text-xs text-slate-500">{{ $item->description ?: '-' }}</div>
                                </td>
                                <td class="px-5 py-3">{{ $item->ip_address ?: '-' }}</td>
                                <td class="px-5 py-3">{{ $item->connector?->name ?: '-' }}</td>
                                <td class="px-5 py-3">{{ optional($item->discovered_at ?? $item->created_at)?->format('d/m/Y H:i') ?: '-' }}</td>
                                <td class="px-5 py-3">
                                    <div class="flex flex-wrap gap-2">
                                        <a href="{{ route('cmdb.discovered-assets.show', $item) }}" class="inline-flex rounded-md border border-[#033151] bg-white px-3 py-1.5 text-xs font-semibold text-[#033151] hover:bg-slate-50">Revisar</a>
                                        <a href="{{ route('cmdb.discovered-assets.create-asset', $item) }}" class="inline-flex rounded-md border border-slate-300 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50">Criar ativo</a>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-5 py-6 text-center text-slate-500">Nenhuma descoberta pendente encontrada.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        <section class="rounded-lg border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-200 px-5 py-4">
                <h2 class="text-base font-semibold text-slate-900">Últimos documentos enviados</h2>
            </div>
            <div class="grid gap-4 p-5 md:grid-cols-2 xl:grid-cols-3">
                @forelse($recentDocuments as $document)
                    <div class="rounded-md border border-slate-200 p-3">
                        <div class="font-semibold text-slate-900">{{ $document->title }}</div>
                        <div class="mt-1 text-xs text-slate-500">{{ $document->asset?->name ?: 'Sem ativo vinculado' }}</div>
                        <div class="mt-2 text-sm text-slate-600">{{ $document->original_name }}</div>
                        <div class="mt-2 flex flex-wrap gap-2">
                            <a href="{{ route('cmdb.assets.documents.download', [$document->asset, $document]) }}" class="inline-flex rounded-md border border-[#033151] bg-white px-3 py-1.5 text-xs font-semibold text-[#033151] hover:bg-slate-50">Baixar</a>
                        </div>
                    </div>
                @empty
                    <p class="text-sm text-slate-500">Nenhum documento enviado recentemente.</p>
                @endforelse
            </div>
        </section>
    </div>
</x-app-layout>
