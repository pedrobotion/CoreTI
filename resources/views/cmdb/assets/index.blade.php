<x-app-layout>
    <div class="mx-auto max-w-7xl space-y-6">
        @include('cmdb.partials._nav', ['title' => 'Ativos', 'description' => 'Cadastro e consulta centralizada de ativos do CMDB.'])

        @if(!empty($setupPending))
            <div class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                Estrutura do CMDB ainda não foi criada no banco. Execute as migrations para liberar o módulo.
            </div>
        @endif

        <section class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
            <div class="mb-4 flex flex-wrap items-center gap-2">
                @foreach([
                    ['label' => 'Todos', 'params' => []],
                    ['label' => 'Máquinas virtuais', 'params' => ['asset_type' => 'virtual_machine']],
                    ['label' => 'Hosts ESXi', 'params' => ['view' => 'hosts']],
                    ['label' => 'Servidores físicos', 'params' => ['view' => 'physical']],
                    ['label' => 'Outros', 'params' => ['view' => 'other']],
                ] as $shortcut)
                    <a href="{{ route('cmdb.assets.index', $shortcut['params']) }}" class="inline-flex min-h-9 items-center rounded-full border px-3 text-xs font-semibold {{ request()->query('asset_type') === ($shortcut['params']['asset_type'] ?? null) && request()->query('view') === ($shortcut['params']['view'] ?? null) ? 'border-[#033151] bg-[#033151] text-white' : 'border-slate-300 bg-white text-slate-700 hover:bg-slate-50' }}">{{ $shortcut['label'] }}</a>
                @endforeach
                <a href="{{ route('cmdb.assets.export', request()->query()) }}" class="ml-auto inline-flex min-h-9 items-center rounded-md border border-emerald-300 bg-emerald-50 px-3 text-xs font-semibold text-emerald-800 hover:bg-emerald-100">Exportar XLSX</a>
            </div>
            <form method="GET" class="flex flex-wrap items-end gap-2">
                <div class="min-w-[240px] flex-1">
                    <label class="text-xs font-semibold uppercase text-slate-500">Busca</label>
                    <input name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Hostname, IP, série ou patrimônio" class="mt-1 h-10 w-full rounded-md border-slate-300 text-sm">
                </div>
                <div class="min-w-[180px]">
                    <label class="text-xs font-semibold uppercase text-slate-500">Tipo</label>
                    <select name="asset_type" class="mt-1 h-10 w-full rounded-md border-slate-300 text-sm">
                        <option value="">Todos</option>
                        @foreach($types as $key => $label)
                            <option value="{{ $key }}" @selected(($filters['asset_type'] ?? $filters['type'] ?? '') === $key)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="min-w-[180px]">
                    <label class="text-xs font-semibold uppercase text-slate-500">Status</label>
                    <select name="status" class="mt-1 h-10 w-full rounded-md border-slate-300 text-sm">
                        <option value="">Todos</option>
                        @foreach($statusLabels as $status => $label)
                            <option value="{{ $status }}" @selected(($filters['status'] ?? '') === $status)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="min-w-[180px]">
                    <label class="text-xs font-semibold uppercase text-slate-500">Criticidade</label>
                    <select name="criticality" class="mt-1 h-10 w-full rounded-md border-slate-300 text-sm">
                        <option value="">Todas</option>
                        @foreach($criticalityLabels as $criticality => $label)
                            <option value="{{ $criticality }}" @selected(($filters['criticality'] ?? '') === $criticality)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="min-w-[180px]">
                    <label class="text-xs font-semibold uppercase text-slate-500">Ambiente</label>
                    <select name="environment" class="mt-1 h-10 w-full rounded-md border-slate-300 text-sm">
                        <option value="">Todos</option>
                        @foreach(['production','homologation','development','test'] as $environment)
                            <option value="{{ $environment }}" @selected(($filters['environment'] ?? '') === $environment)>{{ $environment }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="min-w-[180px]">
                    <label class="text-xs font-semibold uppercase text-slate-500">Rack</label>
                    <select name="rack_id" class="mt-1 h-10 w-full rounded-md border-slate-300 text-sm">
                        <option value="">Todos</option>
                        @foreach(($racks ?? collect()) as $rack)
                            <option value="{{ $rack->id }}" @selected((string) ($filters['rack_id'] ?? '') === (string) $rack->id)>{{ $rack->name }}</option>
                        @endforeach
                    </select>
                </div>
                @if(($filters['asset_type'] ?? '') === 'virtual_machine' && !empty($hasBackupTable))
                    <div class="min-w-[180px]">
                        <label class="text-xs font-semibold uppercase text-slate-500">Backup</label>
                        <select name="backup_status" class="mt-1 h-10 w-full rounded-md border-slate-300 text-sm">
                            <option value="">Todos</option>
                            @foreach($backupStatuses as $status => $label)
                                <option value="{{ $status }}" @selected(($filters['backup_status'] ?? '') === $status)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                @endif
                <div class="min-w-[220px] flex-1">
                    <label class="text-xs font-semibold uppercase text-slate-500">Serviço</label>
                    <input name="service" value="{{ $filters['service'] ?? '' }}" placeholder="Buscar por serviço" class="mt-1 h-10 w-full rounded-md border-slate-300 text-sm">
                </div>
                @if(($services ?? collect())->isNotEmpty())
                    <div class="min-w-[220px] flex-1">
                        <label class="text-xs font-semibold uppercase text-slate-500">Serviço cadastrado</label>
                        <select name="service_id" class="mt-1 h-10 w-full rounded-md border-slate-300 text-sm">
                            <option value="">Todos</option>
                            @foreach($services as $service)
                                <option value="{{ $service->id }}" @selected((string) ($filters['service_id'] ?? '') === (string) $service->id)>{{ $service->name }}</option>
                            @endforeach
                        </select>
                    </div>
                @endif
                <label class="inline-flex h-10 items-center gap-2 text-sm text-slate-700"><input type="checkbox" name="without_service" value="1" @checked(!empty($filters['without_service'])) class="rounded border-slate-300"> Sem serviço</label>
                <div class="flex gap-2">
                    <button type="submit" class="inline-flex h-10 items-center justify-center rounded-md bg-[#033151] px-4 text-sm font-semibold text-white hover:opacity-90">Buscar</button>
                    <a href="{{ route('cmdb.assets.index') }}" class="inline-flex h-10 items-center justify-center rounded-md border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-700 hover:bg-slate-50">Limpar</a>
                </div>
            </form>
        </section>

        <div class="flex justify-end">
            @if(($filters['asset_type'] ?? '') === 'virtual_machine')
                <span class="mr-auto self-center text-sm font-semibold text-slate-600">{{ $assets->total() }} máquinas virtuais encontradas</span>
            @endif
            <a href="{{ route('cmdb.assets.create', request()->only('type', 'asset_type')) }}" class="inline-flex min-h-10 items-center justify-center rounded-md bg-[#033151] px-4 text-sm font-semibold text-white hover:opacity-90">Novo ativo</a>
        </div>

        <form id="bulk-assets-form" method="POST" action="{{ route('cmdb.assets.bulk-update') }}" class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
            @csrf
            <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-5">
                <div class="flex flex-col">
                    <label class="mb-1 text-xs font-semibold uppercase tracking-wide text-slate-500">Serviço</label>
                    <select name="service_id" class="h-9 w-full rounded-md border-slate-300 text-sm"><option value="">Não alterar</option>@foreach(($services ?? collect()) as $service)<option value="{{ $service->id }}">{{ $service->name }}</option>@endforeach</select>
                </div>
                <div class="flex flex-col">
                    <label class="mb-1 text-xs font-semibold uppercase tracking-wide text-slate-500">Criticidade</label>
                    <select name="criticality" class="h-9 w-full rounded-md border-slate-300 text-sm"><option value="">Não alterar</option>@foreach($criticalityLabels as $key => $label)<option value="{{ $key }}">{{ $label }}</option>@endforeach</select>
                </div>
                <div class="flex flex-col">
                    <label class="mb-1 text-xs font-semibold uppercase tracking-wide text-slate-500">Status</label>
                    <select name="status" class="h-9 w-full rounded-md border-slate-300 text-sm"><option value="">Não alterar</option>@foreach($statusLabels as $key => $label)<option value="{{ $key }}">{{ $label }}</option>@endforeach</select>
                </div>
                <div class="flex flex-col">
                    <label class="mb-1 text-xs font-semibold uppercase tracking-wide text-slate-500">Ambiente</label>
                    <select name="environment" class="h-9 w-full rounded-md border-slate-300 text-sm"><option value="">Não alterar</option>@foreach(['production' => 'Produção', 'homologation' => 'Homologação', 'development' => 'Desenvolvimento', 'test' => 'Teste'] as $key => $label)<option value="{{ $key }}">{{ $label }}</option>@endforeach</select>
                </div>
                <div class="flex flex-col">
                    <label class="mb-1 text-xs font-semibold uppercase tracking-wide text-slate-500">Responsável</label>
                    <select name="responsible_user_id" class="h-9 w-full rounded-md border-slate-300 text-sm"><option value="">Não alterar</option>@foreach(($users ?? collect()) as $user)<option value="{{ $user->id }}">{{ $user->name }}</option>@endforeach</select>
                </div>
            </div>
            <div class="mt-4 flex flex-wrap items-center gap-3 border-t border-slate-100 pt-4">
                <button type="submit" onclick="return confirm('Aplicar os campos selecionados aos ativos marcados?')" class="inline-flex h-9 items-center justify-center rounded-lg border border-[#033151] bg-[#033151] px-4 text-xs font-semibold text-white transition hover:opacity-90">Aplicar em lote</button>
                <span class="text-xs text-slate-500">Marque ativos na tabela. O histórico será registrado individualmente.</span>
            </div>
        </form>

        <section class="rounded-lg border border-slate-200 bg-white shadow-sm">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-100 text-sm">
                    <thead class="border-b border-slate-100 text-left text-xs font-semibold uppercase tracking-wide text-slate-400">
                        <tr>
                            <th class="px-5 py-3"><span class="sr-only">Selecionar</span></th>
                            <th class="px-5 py-3">Ativo</th>
                            <th class="px-5 py-3">Tipo</th>
                            <th class="px-5 py-3">Status</th>
                            <th class="px-5 py-3">Criticidade</th>
                            <th class="px-5 py-3">Serviços</th>
                            @if(($filters['asset_type'] ?? '') === 'virtual_machine' && !empty($hasBackupTable))
                                <th class="px-5 py-3">Backup</th>
                            @endif
                            <th class="px-5 py-3">Ações</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse($assets as $asset)
                            <tr>
                                <td class="px-5 py-3"><input type="checkbox" name="asset_ids[]" value="{{ $asset->id }}" form="bulk-assets-form" class="rounded border-slate-300" aria-label="Selecionar {{ $asset->hostname ?: $asset->name }}"></td>
                                <td class="px-5 py-3">
                                    <div class="font-medium text-slate-900">{{ $asset->hostname ?: $asset->name ?: '-' }}</div>
                                    @if($asset->hostname && $asset->name && $asset->hostname !== $asset->name)
                                        <div class="text-xs text-slate-500">{{ $asset->name }}</div>
                                    @endif
                                </td>
                                <td class="px-5 py-3">{{ $types[$asset->asset_type] ?? $asset->asset_type }}</td>
                                <td class="px-5 py-3">{{ $statusLabels[$asset->status] ?? $asset->status }}</td>
                                <td class="px-5 py-3">{{ $criticalityLabels[$asset->criticality] ?? $asset->criticality }}</td>
                                <td class="px-5 py-3">
                                    @if(in_array($asset->asset_type, ['server', 'virtual_machine'], true) && $asset->serverDetails)
                                        <div class="max-w-xs space-y-1">
                                            @if($asset->serverDetails->service_type)
                                                <div class="font-medium text-slate-800">{{ $asset->serverDetails->service_type }}</div>
                                            @endif
                                            @if($asset->serverDetails->service)
                                                <div class="font-medium text-slate-800">{{ $asset->serverDetails->service->name }}</div>
                                            @endif
                                            @if($asset->serverDetails->service_description)
                                                <div class="whitespace-pre-line text-xs text-slate-500">{{ $asset->serverDetails->service_description }}</div>
                                            @endif
                                        </div>
                                        @if(!$asset->serverDetails->service_id && !$asset->serverDetails->service_type && !$asset->serverDetails->service_description)
                                            <span class="text-slate-400">-</span>
                                        @endif
                                    @else
                                        <span class="text-slate-400">-</span>
                                    @endif
                                </td>
                                @if(($filters['asset_type'] ?? '') === 'virtual_machine' && !empty($hasBackupTable))
                                    @php($backup = $asset->backupRecords->sortByDesc('last_synced_at')->first())
                                    <td class="min-w-40 px-5 py-3">
                                        @if($backup)
                                            <span class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-semibold {{ match($backup->protection_status) { 'protected' => 'bg-emerald-50 text-emerald-700 ring-1 ring-inset ring-emerald-200', 'overdue' => 'bg-amber-50 text-amber-700 ring-1 ring-inset ring-amber-200', 'failed', 'unprotected' => 'bg-rose-50 text-rose-700 ring-1 ring-inset ring-rose-200', default => 'bg-slate-100 text-slate-700' } }}">{{ $backupStatuses[$backup->protection_status] ?? 'Desconhecido' }}</span>
                                            <div class="mt-1 text-xs text-slate-500">{{ optional($backup->last_successful_backup_at)->format('d/m/Y H:i') ?: '-' }}</div>
                                        @else
                                            <span class="text-slate-400">Sem sincronização</span>
                                        @endif
                                    </td>
                                @endif
                                <td class="px-5 py-3">
                                    <div class="flex flex-wrap gap-2">
                                        <a href="{{ route('cmdb.assets.show', $asset) }}" class="inline-flex rounded-md border border-[#033151] bg-white px-3 py-1.5 text-xs font-semibold text-[#033151] hover:bg-slate-50">Abrir</a>
                                        <a href="{{ route('cmdb.assets.edit', $asset) }}" class="inline-flex rounded-md border border-slate-300 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50">Edição rápida</a>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ (($filters['asset_type'] ?? '') === 'virtual_machine' && !empty($hasBackupTable)) ? 8 : 7 }}" class="px-5 py-6 text-center text-slate-500">Nenhum ativo cadastrado.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if(method_exists($assets, 'links'))
                <div class="border-t border-slate-200 px-5 py-4">
                    {{ $assets->links() }}
                </div>
            @endif
        </section>
    </div>
</x-app-layout>
