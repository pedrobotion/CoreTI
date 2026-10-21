<x-app-layout>
        @php
            $typeBadgeClass = fn (string $type) => match ($type) {
            'server' => 'bg-blue-50 text-blue-700 ring-1 ring-inset ring-blue-200',
            'virtual_machine' => 'bg-cyan-50 text-cyan-700 ring-1 ring-inset ring-cyan-200',
            'database' => 'bg-violet-50 text-violet-700 ring-1 ring-inset ring-violet-200',
            'switch' => 'bg-emerald-50 text-emerald-700 ring-1 ring-inset ring-emerald-200',
            'firewall', 'sonicwall' => 'bg-orange-50 text-orange-700 ring-1 ring-inset ring-orange-200',
            'storage' => 'bg-slate-100 text-slate-700',
            'ups' => 'bg-amber-50 text-amber-700 ring-1 ring-inset ring-amber-200',
            'link', 'circuit' => 'bg-indigo-50 text-indigo-700 ring-1 ring-inset ring-indigo-200',
            'certificate' => 'bg-fuchsia-50 text-fuchsia-700 ring-1 ring-inset ring-fuchsia-200',
            'backup' => 'bg-rose-50 text-rose-700 ring-1 ring-inset ring-rose-200',
            default => 'bg-slate-100 text-slate-700',
        };
            $statusBadgeClass = fn (string $status) => match ($status) {
            'active' => 'bg-emerald-50 text-emerald-700 ring-1 ring-inset ring-emerald-200',
            'inactive' => 'bg-slate-100 text-slate-700',
            'maintenance' => 'bg-amber-50 text-amber-700 ring-1 ring-inset ring-amber-200',
            'decommissioned' => 'bg-rose-50 text-rose-700 ring-1 ring-inset ring-rose-200',
            'planned' => 'bg-blue-50 text-blue-700 ring-1 ring-inset ring-blue-200',
                default => 'bg-slate-100 text-slate-700',
            };
            $cardToneClass = fn (string $tone) => match ($tone) {
                'rose' => 'border-rose-200 bg-rose-50 text-rose-900',
                'amber' => 'border-amber-200 bg-amber-50 text-amber-900',
                'blue' => 'border-blue-200 bg-blue-50 text-blue-900',
                default => 'border-slate-200 bg-white text-slate-950',
            };
        @endphp
    <div class="mx-auto max-w-7xl space-y-6">
        @include('cmdb.partials._nav', ['title' => 'Dashboard', 'description' => 'Visão resumida de alertas, pendências e prioridades do CMDB.'])

        @if(!empty($setupPending))
            <div class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                Estrutura do CMDB ainda não foi criada no banco. Execute as migrations para liberar o módulo.
            </div>
        @endif

        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
            @foreach($priorityCards as $card)
                @php
                    $cardUrl = match ($card['key'] ?? '') {
                        'expirations' => route('cmdb.expirations.index'),
                        'critical' => route('cmdb.assets.index', ['criticality' => 'critical']),
                        'maintenance' => route('cmdb.assets.index', ['status' => 'maintenance']),
                        'vms' => route('cmdb.assets.index', ['asset_type' => 'virtual_machine']),
                        'sync' => route('cmdb.sync-logs.index', ['status' => 'failed']),
                        'discoveries' => route('cmdb.discovered-assets.index', ['status' => 'pending']),
                        'ip' => route('cmdb.pending.index', ['type' => 'ip']),
                        'service' => route('cmdb.pending.services'),
                        'veeam_protected' => route('cmdb.assets.index', ['asset_type' => 'virtual_machine', 'backup_status' => 'protected']),
                        'veeam_overdue' => route('cmdb.assets.index', ['asset_type' => 'virtual_machine', 'backup_status' => 'overdue']),
                        'veeam_failed' => route('cmdb.assets.index', ['asset_type' => 'virtual_machine', 'backup_status' => 'failed']),
                        'veeam_unprotected' => route('cmdb.assets.index', ['asset_type' => 'virtual_machine', 'backup_status' => 'unprotected']),
                        'veeam_unknown' => route('cmdb.assets.index', ['asset_type' => 'virtual_machine', 'backup_status' => 'unknown']),
                        default => '#',
                    };
                @endphp
                <a href="{{ $cardUrl }}" aria-label="{{ $card['label'] }}: {{ $card['value'] }}. Ver pendências." class="block rounded-lg border p-5 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md focus:outline-none focus:ring-2 focus:ring-[#033151] {{ $cardToneClass($card['tone'] ?? 'slate') }}">
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ $card['label'] }}</p>
                    <p class="mt-2 text-3xl font-bold">{{ $card['value'] }}</p>
                    <span class="mt-2 block text-xs font-semibold opacity-75">{{ ($card['key'] ?? '') === 'vms' ? 'Abrir ativos' : 'Ver pendências' }}</span>
                </a>
            @endforeach
        </div>

        @if(($serversWithoutServiceCount ?? 0) > 0)
            <section class="rounded-lg border border-amber-300 bg-amber-50 p-5 shadow-sm">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h2 class="text-base font-semibold text-amber-950">Servidores e VMs sem serviço especificado</h2>
                        <p class="mt-1 text-sm text-amber-800">{{ $serversWithoutServiceCount }} ativo(s) precisam ter o serviço informado no cadastro.</p>
                    </div>
                    <a href="{{ route('cmdb.pending.services') }}" class="inline-flex items-center rounded-md border border-amber-400 bg-white px-3 py-2 text-sm font-semibold text-amber-900 hover:bg-amber-100">Revisar pendências</a>
                </div>
                <div class="mt-4 grid gap-2 md:grid-cols-2 xl:grid-cols-3">
                    @foreach($serversWithoutService as $asset)
                        <a href="{{ route('cmdb.assets.show', $asset) }}" class="rounded-md border border-amber-200 bg-white px-3 py-2 text-sm text-slate-800 hover:border-amber-400">
                            <span class="font-semibold">{{ $asset->hostname ?: $asset->name }}</span>
                            <span class="block text-xs text-slate-500">{{ $asset->asset_type === 'virtual_machine' ? 'Máquina virtual' : 'Servidor' }}</span>
                        </a>
                    @endforeach
                </div>
            </section>
        @endif

        <section class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
            <div class="flex flex-wrap items-center gap-3">
                <span class="inline-flex rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-semibold text-slate-700">Jira gerados: {{ $jiraNotifications ?? 0 }}</span>
                <span class="inline-flex rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-semibold text-slate-700">Ativos sincronizados: {{ $syncedAssets ?? 0 }}</span>
                @if(!empty($lastSnmpDiscoveryAt))
                    <span class="inline-flex rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-semibold text-slate-700">Última descoberta SNMP: {{ $lastSnmpDiscoveryAt }}</span>
                @endif
                <span class="inline-flex rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-semibold text-slate-700">SNMP com falha: {{ $snmpFailures ?? 0 }}</span>
            </div>
        </section>

        <div class="grid gap-6 xl:grid-cols-2">
            <section class="rounded-lg border border-slate-200 bg-white shadow-sm">
                <div class="border-b border-slate-200 px-5 py-4">
                    <h2 class="text-base font-semibold text-slate-900">Vencimentos urgentes</h2>
                </div>
                <div class="p-5">
                    @forelse($urgentExpirations as $item)
                        <div class="flex items-center justify-between border-b border-slate-100 py-2 text-sm last:border-0">
                            <div>
                                <div class="font-semibold text-slate-900">{{ $item->title }}</div>
                                <div class="text-xs text-slate-500">{{ $item->asset?->name ?: 'Sem ativo vinculado' }}</div>
                            </div>
                            <span class="font-semibold {{ optional($item->expiration_date)->isPast() ? 'text-rose-700' : 'text-slate-950' }}">{{ optional($item->expiration_date)->format('d/m/Y') }}</span>
                        </div>
                    @empty
                        <p class="text-sm text-slate-500">Sem vencimentos urgentes.</p>
                    @endforelse
                </div>
            </section>

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
        </div>

        <div class="grid gap-6 xl:grid-cols-2">
            <section class="rounded-lg border border-slate-200 bg-white shadow-sm">
                <div class="border-b border-slate-200 px-5 py-4">
                    <h2 class="text-base font-semibold text-slate-900">Ativos críticos</h2>
                </div>
                <div class="p-5">
                    @forelse($criticalAssets as $asset)
                        <div class="flex items-center justify-between gap-3 border-b border-slate-100 py-2 text-sm last:border-0">
                            <div>
                                <div class="font-semibold text-slate-900">{{ $asset->name }}</div>
                                <div class="text-xs text-slate-500">{{ $asset->hostname ?: 'Sem hostname' }}</div>
                            </div>
                            <span class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-semibold {{ $typeBadgeClass($asset->asset_type) }}">{{ $types[$asset->asset_type] ?? $asset->asset_type }}</span>
                        </div>
                    @empty
                        <p class="text-sm text-slate-500">Nenhum ativo crítico no momento.</p>
                    @endforelse
                </div>
            </section>

            <section class="rounded-lg border border-slate-200 bg-white shadow-sm">
                <div class="border-b border-slate-200 px-5 py-4">
                    <h2 class="text-base font-semibold text-slate-900">Ativos em manutenção</h2>
                </div>
                <div class="p-5">
                    @forelse($maintenanceAssets as $asset)
                        <div class="flex items-center justify-between gap-3 border-b border-slate-100 py-2 text-sm last:border-0">
                            <div>
                                <div class="font-semibold text-slate-900">{{ $asset->name }}</div>
                                <div class="text-xs text-slate-500">{{ $asset->hostname ?: 'Sem hostname' }}</div>
                            </div>
                            <a class="text-xs font-semibold text-[#033151]" href="{{ route('cmdb.assets.show', $asset) }}">Abrir</a>
                        </div>
                    @empty
                        <p class="text-sm text-slate-500">Nenhum ativo em manutenção.</p>
                    @endforelse
                </div>
            </section>
        </div>

        <div class="grid gap-6 xl:grid-cols-2">
            <section class="rounded-lg border border-slate-200 bg-white shadow-sm">
                <div class="border-b border-slate-200 px-5 py-4">
                    <h2 class="text-base font-semibold text-slate-900">Descobertas pendentes</h2>
                </div>
                <div class="p-5">
                    @forelse($pendingDiscoveries ?? collect() as $item)
                        <div class="flex items-center justify-between gap-3 border-b border-slate-100 py-2 text-sm last:border-0">
                            <div>
                                <div class="font-semibold text-slate-900">{{ $item->display_name ?: ($item->hostname ?: 'Descoberta sem nome') }}</div>
                                <div class="text-xs text-slate-500">{{ $item->connector?->name ?: 'Sem conector' }}</div>
                            </div>
                            <span class="inline-flex rounded-full bg-amber-100 px-2.5 py-0.5 text-xs font-semibold text-amber-800">Pendente</span>
                        </div>
                    @empty
                        <p class="text-sm text-slate-500">Nenhuma descoberta pendente.</p>
                    @endforelse
                </div>
            </section>

            <section class="rounded-lg border border-slate-200 bg-white shadow-sm">
                <div class="border-b border-slate-200 px-5 py-4">
                    <h2 class="text-base font-semibold text-slate-900">Ativos sem IP</h2>
                </div>
                <div class="p-5">
                    @forelse($assetsWithoutIp as $asset)
                        <div class="flex items-center justify-between gap-3 border-b border-slate-100 py-2 text-sm last:border-0">
                            <span class="text-slate-700">{{ $asset->name }}</span>
                            <a class="text-xs font-semibold text-[#033151]" href="{{ route('cmdb.assets.show', $asset) }}">Abrir</a>
                        </div>
                    @empty
                        <p class="text-sm text-slate-500">Nenhum ativo sem IP.</p>
                    @endforelse
                </div>
            </section>
        </div>
    </div>
</x-app-layout>
