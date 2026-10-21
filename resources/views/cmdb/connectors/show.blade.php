<x-app-layout>
    @php
        $typeBadgeClass = fn (string $type) => match ($type) {
            'manual' => 'bg-slate-100 text-slate-700',
            'vmware' => 'bg-blue-50 text-blue-700 ring-1 ring-inset ring-blue-200',
            'proxmox' => 'bg-orange-50 text-orange-700 ring-1 ring-inset ring-orange-200',
            'hyperv' => 'bg-cyan-50 text-cyan-700 ring-1 ring-inset ring-cyan-200',
            'snmp' => 'bg-emerald-50 text-emerald-700 ring-1 ring-inset ring-emerald-200',
            'ssh' => 'bg-indigo-50 text-indigo-700 ring-1 ring-inset ring-indigo-200',
            'winrm' => 'bg-violet-50 text-violet-700 ring-1 ring-inset ring-violet-200',
            'sonicwall', 'fortigate' => 'bg-rose-50 text-rose-700 ring-1 ring-inset ring-rose-200',
            'mikrotik', 'idrac', 'ilo' => 'bg-amber-50 text-amber-700 ring-1 ring-inset ring-amber-200',
            'api' => 'bg-fuchsia-50 text-fuchsia-700 ring-1 ring-inset ring-fuchsia-200',
            default => 'bg-slate-100 text-slate-700',
        };
        $statusBadgeClass = fn (string $status) => match ($status) {
            'active' => 'bg-emerald-50 text-emerald-700 ring-1 ring-inset ring-emerald-200',
            'inactive' => 'bg-slate-100 text-slate-700',
            'error' => 'bg-rose-50 text-rose-700 ring-1 ring-inset ring-rose-200',
            'testing' => 'bg-amber-50 text-amber-700 ring-1 ring-inset ring-amber-200',
            default => 'bg-slate-100 text-slate-700',
        };
    @endphp

    <div class="mx-auto max-w-7xl space-y-6">
        @include('cmdb.partials._nav', ['title' => $connector->name, 'description' => 'Detalhe do conector e suas sincronizações recentes.'])

        @if(session('success'))
            <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
                {{ session('success') }}
            </div>
        @endif

        @if(session('connectorTestResult'))
            @php($testResult = session('connectorTestResult'))
            <section class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <div>
                        <h2 class="text-base font-semibold text-slate-900">Teste de conexão</h2>
                        <p class="text-sm text-slate-500">Resumo técnico da última verificação.</p>
                    </div>
                    <span class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-semibold {{ ($testResult['status'] ?? 'failed') === 'success' ? 'bg-emerald-50 text-emerald-700 ring-1 ring-inset ring-emerald-200' : 'bg-rose-50 text-rose-700 ring-1 ring-inset ring-rose-200' }}">{{ $testResult['status'] ?? 'failed' }}</span>
                </div>

                <div class="mt-4 grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                    <div><p class="text-xs font-semibold uppercase text-slate-500">Status</p><p class="mt-1 text-sm font-semibold text-slate-950">{{ $testResult['message'] ?? '-' }}</p></div>
                    @if($connector->connector_type === 'vmware')
                        <div><p class="text-xs font-semibold uppercase text-slate-500">Versão</p><p class="mt-1 text-sm font-semibold text-slate-950">{{ $testResult['data']['version'] ?? '-' }}</p></div>
                        <div><p class="text-xs font-semibold uppercase text-slate-500">Build</p><p class="mt-1 text-sm font-semibold text-slate-950">{{ $testResult['data']['build'] ?? '-' }}</p></div>
                        @foreach(($testResult['data']['counts'] ?? []) as $type => $count)
                            <div><p class="text-xs font-semibold uppercase text-slate-500">{{ str_replace('_', ' ', ucfirst($type)) }}</p><p class="mt-1 text-sm font-semibold text-slate-950">{{ $count }}</p></div>
                        @endforeach
                    @else
                    <div><p class="text-xs font-semibold uppercase text-slate-500">Hostname</p><p class="mt-1 text-sm font-semibold text-slate-950">{{ $testResult['data']['hostname'] ?? '-' }}</p></div>
                    <div><p class="text-xs font-semibold uppercase text-slate-500">Descrição</p><p class="mt-1 text-sm font-semibold text-slate-950">{{ $testResult['data']['description'] ?? '-' }}</p></div>
                    <div><p class="text-xs font-semibold uppercase text-slate-500">IP</p><p class="mt-1 text-sm font-semibold text-slate-950">{{ $testResult['data']['ip'] ?? '-' }}</p></div>
                    @endif
                    <div><p class="text-xs font-semibold uppercase text-slate-500">Tempo de resposta</p><p class="mt-1 text-sm font-semibold text-slate-950">{{ isset($testResult['data']['response_time_ms']) ? $testResult['data']['response_time_ms'] . ' ms' : '-' }}</p></div>
                    @if(!empty($testResult['data']['error']))
                        <div class="md:col-span-2 xl:col-span-3"><p class="text-xs font-semibold uppercase text-slate-500">Erro</p><p class="mt-1 text-sm text-rose-700 whitespace-pre-line">{{ $testResult['data']['error'] }}</p></div>
                    @endif
                </div>
            </section>
        @endif

        <div class="flex flex-wrap gap-2">
            <a href="{{ route('cmdb.connectors.edit', $connector) }}" class="inline-flex min-h-10 items-center justify-center rounded-md bg-[#033151] px-4 text-sm font-semibold text-white hover:opacity-90">Editar</a>
            <a href="{{ route('cmdb.connectors.index') }}" class="inline-flex min-h-10 items-center justify-center rounded-md border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-700 hover:bg-slate-50">Voltar</a>
            <form method="POST" action="{{ route('cmdb.connectors.test', $connector) }}">@csrf<button class="inline-flex min-h-10 items-center justify-center rounded-md border border-amber-200 bg-white px-4 text-sm font-semibold text-amber-700 hover:bg-amber-50">Testar conexão</button></form>
            <form method="POST" action="{{ route('cmdb.connectors.sync-now', $connector) }}">@csrf<button class="inline-flex min-h-10 items-center justify-center rounded-md border border-emerald-200 bg-white px-4 text-sm font-semibold text-emerald-700 hover:bg-emerald-50">Sincronizar agora</button></form>
        </div>

        <section class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
            <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                <div><p class="text-xs font-semibold uppercase text-slate-500">Tipo</p><span class="mt-1 inline-flex rounded-full px-2.5 py-0.5 text-xs font-semibold {{ $typeBadgeClass($connector->connector_type) }}">{{ $types[$connector->connector_type] ?? $connector->connector_type }}</span></div>
                <div><p class="text-xs font-semibold uppercase text-slate-500">Status</p><span class="mt-1 inline-flex rounded-full px-2.5 py-0.5 text-xs font-semibold {{ $statusBadgeClass($connector->status) }}">{{ $statuses[$connector->status] ?? $connector->status }}</span></div>
                <div><p class="text-xs font-semibold uppercase text-slate-500">Ativo</p><p class="mt-1 text-sm font-semibold text-slate-950">{{ $connector->is_active ? 'Sim' : 'Não' }}</p></div>
                <div><p class="text-xs font-semibold uppercase text-slate-500">Host</p><p class="mt-1 text-sm font-semibold text-slate-950">{{ $connector->host ?: '-' }}</p></div>
                <div><p class="text-xs font-semibold uppercase text-slate-500">Porta</p><p class="mt-1 text-sm font-semibold text-slate-950">{{ $connector->port ?: '-' }}</p></div>
                <div><p class="text-xs font-semibold uppercase text-slate-500">Usuário</p><p class="mt-1 text-sm font-semibold text-slate-950">{{ $connector->username ?: '-' }}</p></div>
                <div><p class="text-xs font-semibold uppercase text-slate-500">Última sincronização</p><p class="mt-1 text-sm font-semibold text-slate-950">{{ $connector->last_sync_at ? $connector->last_sync_at->format('d/m/Y H:i') : '-' }}</p></div>
                <div><p class="text-xs font-semibold uppercase text-slate-500">Último resultado</p><p class="mt-1 text-sm font-semibold text-slate-950">{{ $connector->last_sync_status ?: '-' }}</p></div>
                <div class="md:col-span-2 xl:col-span-3"><p class="text-xs font-semibold uppercase text-slate-500">Último erro</p><p class="mt-1 whitespace-pre-line text-sm text-rose-700">{{ $connector->last_error ?: 'Sem erro registrado.' }}</p></div>
                @if($connector->connector_type === 'snmp')
                    <div class="md:col-span-2 xl:col-span-3">
                        <p class="text-xs font-semibold uppercase text-slate-500">SNMP</p>
                        <div class="mt-2 grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                            <div><p class="text-xs text-slate-500">Versão</p><p class="text-sm font-semibold text-slate-950">{{ $connector->settings['version'] ?? '2c' }}</p></div>
                            <div><p class="text-xs text-slate-500">Timeout</p><p class="text-sm font-semibold text-slate-950">{{ $connector->settings['timeout'] ?? 2 }}s</p></div>
                            <div><p class="text-xs text-slate-500">Retries</p><p class="text-sm font-semibold text-slate-950">{{ $connector->settings['retries'] ?? 1 }}</p></div>
                            <div><p class="text-xs text-slate-500">Security name</p><p class="text-sm font-semibold text-slate-950">{{ $connector->settings['security_name'] ?? '-' }}</p></div>
                            <div><p class="text-xs text-slate-500">Auth protocol</p><p class="text-sm font-semibold text-slate-950">{{ $connector->settings['auth_protocol'] ?? '-' }}</p></div>
                            <div><p class="text-xs text-slate-500">Priv protocol</p><p class="text-sm font-semibold text-slate-950">{{ $connector->settings['priv_protocol'] ?? '-' }}</p></div>
                            <div><p class="text-xs text-slate-500">Community</p><p class="text-sm font-semibold text-slate-950">{{ !empty($connector->settings['community_encrypted']) ? 'Configurado' : '-' }}</p></div>
                            <div><p class="text-xs text-slate-500">Auth password</p><p class="text-sm font-semibold text-slate-950">{{ !empty($connector->settings['auth_password_encrypted']) ? 'Configurado' : '-' }}</p></div>
                            <div><p class="text-xs text-slate-500">Priv password</p><p class="text-sm font-semibold text-slate-950">{{ !empty($connector->settings['priv_password_encrypted']) ? 'Configurado' : '-' }}</p></div>
                        </div>
                    </div>
                @endif
            </div>
        </section>

        <div class="grid gap-6 xl:grid-cols-2">
            <section class="rounded-lg border border-slate-200 bg-white shadow-sm">
                <div class="border-b border-slate-200 px-5 py-4">
                    <h2 class="text-base font-semibold text-slate-900">Jobs vinculados</h2>
                </div>
                <div class="p-5 space-y-3">
                    @forelse($connector->syncJobs as $job)
                        <div class="rounded-md border border-slate-200 p-3 text-sm">
                            <div class="flex items-center justify-between gap-2">
                                <div>
                                    <a href="{{ route('cmdb.sync-jobs.show', $job) }}" class="font-semibold text-[#033151] hover:underline">{{ $job->name }}</a>
                                    <div class="text-xs text-slate-500">{{ $job->schedule_type }}</div>
                                </div>
                                <span class="text-xs text-slate-500">{{ $job->last_status ?: '-' }}</span>
                            </div>
                        </div>
                    @empty
                        <p class="text-sm text-slate-500">Nenhum job vinculado.</p>
                    @endforelse
                </div>
            </section>

            <section class="rounded-lg border border-slate-200 bg-white shadow-sm">
                <div class="border-b border-slate-200 px-5 py-4">
                    <h2 class="text-base font-semibold text-slate-900">Últimos logs</h2>
                </div>
                <div class="p-5 space-y-3">
                    @forelse($connector->syncLogs as $log)
                        <div class="rounded-md border border-slate-200 p-3 text-sm">
                            <div class="flex items-center justify-between gap-2">
                                <div>
                                    <a href="{{ route('cmdb.sync-logs.show', $log) }}" class="font-semibold text-[#033151] hover:underline">{{ $log->message ?: 'Sincronização' }}</a>
                                    <div class="text-xs text-slate-500">{{ $log->started_at?->format('d/m/Y H:i') }}</div>
                                </div>
                                <span class="rounded-full px-2.5 py-0.5 text-xs font-semibold {{ $statusBadgeClass($log->status) }}">{{ $log->status }}</span>
                            </div>
                        </div>
                    @empty
                        <p class="text-sm text-slate-500">Nenhum log registrado.</p>
                    @endforelse
                </div>
            </section>
        </div>

        <section class="rounded-lg border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-200 px-5 py-4">
                <h2 class="text-base font-semibold text-slate-900">Ativos associados</h2>
            </div>
            <div class="p-5 space-y-3">
                @forelse($connector->assets as $asset)
                    <div class="flex items-center justify-between gap-2 border-b border-slate-100 py-2 last:border-0">
                        <a href="{{ route('cmdb.assets.show', $asset) }}" class="font-semibold text-[#033151] hover:underline">{{ $asset->name }}</a>
                        <span class="text-xs text-slate-500">{{ $asset->sync_status ?: 'never_synced' }}</span>
                    </div>
                @empty
                    <p class="text-sm text-slate-500">Nenhum ativo associado.</p>
                @endforelse
            </div>
        </section>
    </div>
</x-app-layout>
