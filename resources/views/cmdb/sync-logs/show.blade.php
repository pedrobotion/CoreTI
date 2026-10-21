<x-app-layout>
    @php
        $statusBadgeClass = fn (string $status) => match ($status) {
            'running' => 'bg-blue-50 text-blue-700 ring-1 ring-inset ring-blue-200',
            'success' => 'bg-emerald-50 text-emerald-700 ring-1 ring-inset ring-emerald-200',
            'warning' => 'bg-amber-50 text-amber-700 ring-1 ring-inset ring-amber-200',
            'failed' => 'bg-rose-50 text-rose-700 ring-1 ring-inset ring-rose-200',
            'simulated' => 'bg-slate-100 text-slate-700',
            default => 'bg-slate-100 text-slate-700',
        };
    @endphp

    <div class="mx-auto max-w-7xl space-y-6">
        @include('cmdb.partials._nav', ['title' => 'Log de sincronização', 'description' => 'Detalhe técnico da execução.'])

        <div class="flex flex-wrap gap-2">
            <a href="{{ route('cmdb.sync-logs.index') }}" class="inline-flex min-h-10 items-center justify-center rounded-md border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-700 hover:bg-slate-50">Voltar</a>
        </div>

        <section class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
            <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                <div><p class="text-xs font-semibold uppercase text-slate-500">Status</p><span class="mt-1 inline-flex rounded-full px-2.5 py-0.5 text-xs font-semibold {{ $statusBadgeClass($item->status) }}">{{ $item->status }}</span></div>
                <div><p class="text-xs font-semibold uppercase text-slate-500">Conector</p><p class="mt-1 text-sm font-semibold text-slate-950">{{ $item->connector?->name ?: '-' }}</p></div>
                <div><p class="text-xs font-semibold uppercase text-slate-500">Job</p><p class="mt-1 text-sm font-semibold text-slate-950">{{ $item->syncJob?->name ?: '-' }}</p></div>
                <div><p class="text-xs font-semibold uppercase text-slate-500">Data início</p><p class="mt-1 text-sm font-semibold text-slate-950">{{ $item->started_at?->format('d/m/Y H:i') ?: '-' }}</p></div>
                <div><p class="text-xs font-semibold uppercase text-slate-500">Data fim</p><p class="mt-1 text-sm font-semibold text-slate-950">{{ $item->finished_at?->format('d/m/Y H:i') ?: '-' }}</p></div>
                <div><p class="text-xs font-semibold uppercase text-slate-500">Duração</p><p class="mt-1 text-sm font-semibold text-slate-950">{{ $item->duration_seconds ? $item->duration_seconds . 's' : '-' }}</p></div>
                <div><p class="text-xs font-semibold uppercase text-slate-500">Encontrados</p><p class="mt-1 text-sm font-semibold text-slate-950">{{ $item->assets_found }}</p></div>
                <div><p class="text-xs font-semibold uppercase text-slate-500">Criados</p><p class="mt-1 text-sm font-semibold text-slate-950">{{ $item->assets_created }}</p></div>
                <div><p class="text-xs font-semibold uppercase text-slate-500">Atualizados</p><p class="mt-1 text-sm font-semibold text-slate-950">{{ $item->assets_updated }}</p></div>
                <div><p class="text-xs font-semibold uppercase text-slate-500">Sem alteração</p><p class="mt-1 text-sm font-semibold text-slate-950">{{ $item->assets_unchanged }}</p></div>
                <div><p class="text-xs font-semibold uppercase text-slate-500">Para revisão</p><p class="mt-1 text-sm font-semibold text-slate-950">{{ $item->assets_review }}</p></div>
                <div><p class="text-xs font-semibold uppercase text-amber-600">Sem correspondência</p><p class="mt-1 text-sm font-semibold {{ $item->assets_unmatched > 0 ? 'text-amber-700' : 'text-slate-950' }}">{{ $item->assets_unmatched }}</p></div>
                <div><p class="text-xs font-semibold uppercase text-slate-500">Falhas</p><p class="mt-1 text-sm font-semibold text-slate-950">{{ $item->assets_failed }}</p></div>
                <div class="md:col-span-2 xl:col-span-3"><p class="text-xs font-semibold uppercase text-slate-500">Mensagem</p><p class="mt-1 text-sm text-slate-700 whitespace-pre-line">{{ $item->message ?: '-' }}</p></div>
                @if($item->error_message)
                    <div class="md:col-span-2 xl:col-span-3"><p class="text-xs font-semibold uppercase text-slate-500">Erro</p><p class="mt-1 text-sm text-rose-700 whitespace-pre-line">{{ $item->error_message }}</p></div>
                @endif
            </div>
        </section>

        @php
            $discovered = data_get($item->details ?? [], 'discovery.items.0')
                ?? data_get($item->details ?? [], 'items.0.discovered')
                ?? data_get($item->details ?? [], 'items.0');
        @endphp

        @if(is_array($discovered) && !empty($discovered))
            <section class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                <div class="flex items-center justify-between gap-2">
                    <div>
                        <h2 class="text-base font-semibold text-slate-900">Dados descobertos</h2>
                        <p class="text-sm text-slate-500">Resumo da primeira descoberta registrada neste log.</p>
                    </div>
                    <span class="inline-flex rounded-full bg-amber-100 px-2.5 py-0.5 text-xs font-semibold text-amber-800">{{ data_get($discovered, 'recommendation', 'review_required') === 'review_required' ? 'Revisão necessária' : 'Vinculado' }}</span>
                </div>

                <div class="mt-4 grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                    <div><p class="text-xs font-semibold uppercase text-slate-500">Hostname</p><p class="mt-1 text-sm font-semibold text-slate-950">{{ data_get($discovered, 'hostname', '-') }}</p></div>
                    <div><p class="text-xs font-semibold uppercase text-slate-500">IP</p><p class="mt-1 text-sm font-semibold text-slate-950">{{ data_get($discovered, 'ip', '-') }}</p></div>
                    <div><p class="text-xs font-semibold uppercase text-slate-500">Object ID</p><p class="mt-1 text-sm font-semibold text-slate-950">{{ data_get($discovered, 'object_id', '-') }}</p></div>
                    <div><p class="text-xs font-semibold uppercase text-slate-500">Uptime</p><p class="mt-1 text-sm font-semibold text-slate-950">{{ data_get($discovered, 'uptime', '-') }}</p></div>
                    <div><p class="text-xs font-semibold uppercase text-slate-500">Contato</p><p class="mt-1 text-sm font-semibold text-slate-950">{{ data_get($discovered, 'contact', '-') }}</p></div>
                    <div><p class="text-xs font-semibold uppercase text-slate-500">Localização</p><p class="mt-1 text-sm font-semibold text-slate-950">{{ data_get($discovered, 'location', '-') }}</p></div>
                    <div class="md:col-span-2 xl:col-span-3"><p class="text-xs font-semibold uppercase text-slate-500">Descrição</p><p class="mt-1 text-sm text-slate-700 whitespace-pre-line">{{ data_get($discovered, 'description', '-') }}</p></div>
                </div>
            </section>
        @endif

        @if(($discoveredAssets ?? collect())->isNotEmpty())
            <section class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                <div class="flex items-center justify-between gap-2">
                    <div>
                        <h2 class="text-base font-semibold text-slate-900">Descobertas revisáveis</h2>
                        <p class="text-sm text-slate-500">Itens persistidos na fila de revisão a partir deste log.</p>
                    </div>
                </div>

                <div class="mt-4 space-y-3">
                    @foreach($discoveredAssets as $discoveredAsset)
                        <div class="rounded-md border border-slate-200 p-4 text-sm">
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <div>
                                    <a href="{{ route('cmdb.discovered-assets.show', $discoveredAsset) }}" class="font-semibold text-[#033151] hover:underline">{{ $discoveredAsset->hostname ?: $discoveredAsset->source_external_id ?: 'Descoberta SNMP' }}</a>
                                    <div class="text-xs text-slate-500">{{ $discoveredAsset->ip_address ?: '-' }} • {{ $discoveredAsset->status }}</div>
                                </div>
                                <a href="{{ route('cmdb.discovered-assets.show', $discoveredAsset) }}" class="inline-flex rounded-md border border-slate-300 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50">Abrir revisão</a>
                            </div>
                        </div>
                    @endforeach
                </div>
            </section>
        @endif

        <section class="rounded-lg border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-200 px-5 py-4">
                <h2 class="text-base font-semibold text-slate-900">Detalhes JSON</h2>
            </div>
            <pre class="overflow-x-auto p-5 text-xs leading-relaxed text-slate-700">{{ json_encode($item->details ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</pre>
        </section>
    </div>
</x-app-layout>
