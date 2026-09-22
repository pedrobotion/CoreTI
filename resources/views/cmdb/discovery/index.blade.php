<x-app-layout>
    @php
        $statusBadgeClass = fn (string $status) => match ($status) {
            'active' => 'bg-emerald-100 text-emerald-800',
            'success' => 'bg-emerald-100 text-emerald-800',
            'error' => 'bg-rose-100 text-rose-800',
            'testing' => 'bg-amber-100 text-amber-800',
            'inactive' => 'bg-slate-100 text-slate-700',
            'running' => 'bg-blue-100 text-blue-800',
            'failed' => 'bg-rose-100 text-rose-800',
            'warning' => 'bg-amber-100 text-amber-800',
            'simulated' => 'bg-slate-100 text-slate-700',
            default => 'bg-slate-100 text-slate-700',
        };
    @endphp

    <div class="mx-auto max-w-7xl space-y-6">
        @include('cmdb.partials._nav', ['title' => 'Descoberta', 'description' => 'Base operacional para descoberta e sincronização da infraestrutura.'])

        @if(!empty($setupPending))
            <div class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                Estrutura de descoberta ainda não foi criada no banco. Execute as migrations para liberar a área.
            </div>
        @endif

        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
            @foreach($cards as $card)
                <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ $card['label'] }}</p>
                    <p class="mt-2 text-3xl font-bold text-slate-950">{{ $card['value'] }}</p>
                </div>
            @endforeach
        </div>

        <section class="rounded-lg border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-200 px-5 py-4 flex items-center justify-between gap-2">
                <div>
                    <h2 class="text-base font-semibold text-slate-900">Fila de revisão</h2>
                    <p class="text-sm text-slate-500">Descobertas SNMP aguardando vínculo, criação de ativo ou descarte.</p>
                </div>
                <a href="{{ route('cmdb.discovered-assets.index') }}" class="text-sm font-semibold text-[#033151] hover:underline">Abrir fila</a>
            </div>
            <div class="p-5 space-y-3">
                @forelse($pendingDiscoveries as $item)
                    <div class="rounded-md border border-slate-200 p-3 text-sm">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <div>
                                <a href="{{ route('cmdb.discovered-assets.show', $item) }}" class="font-semibold text-[#033151] hover:underline">{{ $item->hostname ?: $item->source_external_id ?: 'Descoberta SNMP' }}</a>
                                <div class="text-xs text-slate-500">{{ $item->connector?->name ?: 'Sem conector' }} • {{ $item->ip_address ?: '-' }}</div>
                            </div>
                            <div class="flex items-center gap-2">
                                <span class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-semibold bg-amber-100 text-amber-800">Pendente</span>
                                <a href="{{ route('cmdb.discovered-assets.show', $item) }}" class="inline-flex rounded-md border border-slate-300 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50">Revisar</a>
                            </div>
                        </div>
                        <div class="mt-2 text-xs text-slate-600">
                            <p><span class="font-semibold">Serial:</span> {{ $item->serial_number ?: '-' }}</p>
                            <p><span class="font-semibold">Descrição:</span> {{ $item->description ?: '-' }}</p>
                        </div>
                    </div>
                @empty
                    <p class="text-sm text-slate-500">Nenhuma descoberta pendente registrada.</p>
                @endforelse
            </div>
        </section>

        <section class="rounded-lg border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-200 px-5 py-4 flex items-center justify-between gap-2">
                <div>
                    <h2 class="text-base font-semibold text-slate-900">Descobertas recentes</h2>
                    <p class="text-sm text-slate-500">Itens registrados nos últimos logs de sincronização SNMP.</p>
                </div>
                <a href="{{ route('cmdb.sync-logs.index', ['q' => 'snmp']) }}" class="text-sm font-semibold text-[#033151] hover:underline">Ver logs</a>
            </div>
            <div class="p-5 space-y-3">
                @forelse($recentDiscoveries as $item)
                    @php
                        $recentLabel = match ($item['recommendation']) {
                            'review_required' => 'Revisão necessária',
                            'linked_to_existing_asset' => 'Vinculado',
                            'created' => 'Ativo criado',
                            'ignored' => 'Ignorado',
                            default => 'Revisado',
                        };
                        $recentBadgeClass = match ($item['recommendation']) {
                            'review_required' => 'bg-amber-100 text-amber-800',
                            'ignored' => 'bg-slate-100 text-slate-700',
                            'created' => 'bg-blue-100 text-blue-800',
                            default => 'bg-emerald-100 text-emerald-800',
                        };
                    @endphp
                    <div class="rounded-md border border-slate-200 p-3 text-sm">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <div>
                                <a href="{{ route('cmdb.sync-logs.show', $item['log']) }}" class="font-semibold text-[#033151] hover:underline">{{ $item['hostname'] }}</a>
                                <div class="text-xs text-slate-500">{{ $item['connector']?->name ?: 'Sem conector' }} • {{ $item['ip'] }}</div>
                            </div>
                            <div class="flex items-center gap-2">
                                <span class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-semibold {{ $recentBadgeClass }}">
                                    {{ $recentLabel }}
                                </span>
                                @if(!empty($item['discovered_asset_id']))
                                    <a href="{{ route('cmdb.discovered-assets.show', $item['discovered_asset_id']) }}" class="inline-flex rounded-md border border-[#033151] bg-white px-3 py-1.5 text-xs font-semibold text-[#033151] hover:bg-slate-50">Revisar</a>
                                @else
                                    <a href="{{ route('cmdb.quick-create.create', ['source_log_id' => $item['log']->id, 'source_type' => 'snmp', 'source_connector_id' => $item['log']->connector_id, 'source_external_id' => $item['hostname']]) }}" class="inline-flex rounded-md border border-slate-300 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50">Criar ativo</a>
                                @endif
                            </div>
                        </div>
                        <div class="mt-2 text-xs text-slate-600">
                            <p><span class="font-semibold">Descrição:</span> {{ $item['description'] }}</p>
                            <p><span class="font-semibold">Status:</span> {{ $item['status'] }}</p>
                        </div>
                    </div>
                @empty
                    <p class="text-sm text-slate-500">Nenhuma descoberta recente registrada.</p>
                @endforelse
            </div>
        </section>

        <div class="grid gap-6 xl:grid-cols-2">
            <section class="rounded-lg border border-slate-200 bg-white shadow-sm">
                <div class="border-b border-slate-200 px-5 py-4 flex items-center justify-between gap-2">
                    <h2 class="text-base font-semibold text-slate-900">Conectores ativos</h2>
                    <a href="{{ route('cmdb.connectors.index') }}" class="text-sm font-semibold text-[#033151] hover:underline">Ver conectores</a>
                </div>
                <div class="p-5 space-y-3">
                    @forelse($activeConnectors as $connector)
                        <div class="rounded-md border border-slate-200 p-3 text-sm">
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <div>
                                    <a href="{{ route('cmdb.connectors.show', $connector) }}" class="font-semibold text-[#033151] hover:underline">{{ $connector->name }}</a>
                                    <div class="text-xs text-slate-500">{{ $connector->host ?: 'Sem host' }}</div>
                                </div>
                                <span class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-semibold {{ $statusBadgeClass($connector->status) }}">{{ $connector->status }}</span>
                            </div>
                        </div>
                    @empty
                        <p class="text-sm text-slate-500">Nenhum conector ativo encontrado.</p>
                    @endforelse
                </div>
            </section>

            <section class="rounded-lg border border-slate-200 bg-white shadow-sm">
                <div class="border-b border-slate-200 px-5 py-4 flex items-center justify-between gap-2">
                    <h2 class="text-base font-semibold text-slate-900">Jobs ativos</h2>
                    <a href="{{ route('cmdb.sync-jobs.index') }}" class="text-sm font-semibold text-[#033151] hover:underline">Ver jobs</a>
                </div>
                <div class="p-5 space-y-3">
                    @forelse($activeJobs as $job)
                        <div class="rounded-md border border-slate-200 p-3 text-sm">
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <div>
                                    <a href="{{ route('cmdb.sync-jobs.show', $job) }}" class="font-semibold text-[#033151] hover:underline">{{ $job->name }}</a>
                                    <div class="text-xs text-slate-500">{{ $job->connector?->name ?: 'Sem conector' }}</div>
                                </div>
                                <span class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-semibold {{ $statusBadgeClass($job->schedule_type) }}">{{ $job->schedule_type }}</span>
                            </div>
                        </div>
                    @empty
                        <p class="text-sm text-slate-500">Nenhum job ativo encontrado.</p>
                    @endforelse
                </div>
            </section>
        </div>

        <div class="grid gap-6 xl:grid-cols-2">
            <section class="rounded-lg border border-slate-200 bg-white shadow-sm">
                <div class="border-b border-slate-200 px-5 py-4 flex items-center justify-between gap-2">
                    <h2 class="text-base font-semibold text-slate-900">Falhas recentes de sincronização</h2>
                    <a href="{{ route('cmdb.sync-logs.index', ['status' => 'failed']) }}" class="text-sm font-semibold text-[#033151] hover:underline">Ver logs</a>
                </div>
                <div class="p-5 space-y-3">
                    @forelse($failureLogs as $log)
                        <div class="rounded-md border border-slate-200 p-3 text-sm">
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <div>
                                    <a href="{{ route('cmdb.sync-logs.show', $log) }}" class="font-semibold text-[#033151] hover:underline">{{ $log->message ?: 'Falha de sincronização' }}</a>
                                    <div class="text-xs text-slate-500">{{ $log->connector?->name ?: 'Sem conector' }} • {{ $log->started_at?->format('d/m/Y H:i') }}</div>
                                </div>
                                <span class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-semibold {{ $statusBadgeClass($log->status) }}">{{ $log->status }}</span>
                            </div>
                            @if($log->error_message)
                                <div class="mt-2 text-xs text-rose-700">{{ $log->error_message }}</div>
                            @endif
                        </div>
                    @empty
                        <p class="text-sm text-slate-500">Nenhuma falha recente encontrada.</p>
                    @endforelse
                </div>
            </section>

            <section class="rounded-lg border border-slate-200 bg-white shadow-sm">
                <div class="border-b border-slate-200 px-5 py-4 flex items-center justify-between gap-2">
                    <h2 class="text-base font-semibold text-slate-900">Últimos logs</h2>
                    <div class="text-sm text-slate-500">Última sincronização: {{ $lastSyncAt ? \Illuminate\Support\Carbon::parse($lastSyncAt)->format('d/m/Y H:i') : '-' }}</div>
                </div>
                <div class="p-5 space-y-3">
                    @forelse($recentLogs as $log)
                        <div class="rounded-md border border-slate-200 p-3 text-sm">
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <div>
                                    <a href="{{ route('cmdb.sync-logs.show', $log) }}" class="font-semibold text-[#033151] hover:underline">{{ $log->message ?: 'Sincronização' }}</a>
                                    <div class="text-xs text-slate-500">{{ $log->connector?->name ?: 'Sem conector' }}</div>
                                </div>
                                <span class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-semibold {{ $statusBadgeClass($log->status) }}">{{ $log->status }}</span>
                            </div>
                        </div>
                    @empty
                        <p class="text-sm text-slate-500">Nenhum log registrado.</p>
                    @endforelse
                </div>
            </section>
        </div>
    </div>
</x-app-layout>
