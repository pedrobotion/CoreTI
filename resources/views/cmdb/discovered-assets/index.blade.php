<x-app-layout>
    @php
        $statusBadgeClass = fn (string $status) => match ($status) {
            'pending' => 'bg-amber-50 text-amber-700 ring-1 ring-inset ring-amber-200',
            'linked' => 'bg-emerald-50 text-emerald-700 ring-1 ring-inset ring-emerald-200',
            'created' => 'bg-blue-50 text-blue-700 ring-1 ring-inset ring-blue-200',
            'ignored' => 'bg-slate-100 text-slate-700',
            'duplicate' => 'bg-violet-50 text-violet-700 ring-1 ring-inset ring-violet-200',
            'error' => 'bg-rose-50 text-rose-700 ring-1 ring-inset ring-rose-200',
            default => 'bg-slate-100 text-slate-700',
        };
    @endphp

    <div class="mx-auto max-w-7xl space-y-6">
        @include('cmdb.partials._nav', ['title' => 'Descobertas', 'description' => 'Fila de revisão das descobertas coletadas pelos conectores.'])

        @if(!empty($setupPending))
            <div class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                A estrutura de descobertas ainda não foi criada no banco. Execute as migrations para liberar a área.
            </div>
        @endif

        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-5">
            <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Total</p>
                <p class="mt-2 text-3xl font-bold text-slate-950">{{ $summary['total'] ?? 0 }}</p>
            </div>
            <div class="rounded-lg border border-amber-200 bg-amber-50 p-5 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wide text-amber-700">Pendentes</p>
                <p class="mt-2 text-3xl font-bold text-amber-900">{{ $summary['pending'] ?? 0 }}</p>
            </div>
            <div class="rounded-lg border border-emerald-200 bg-emerald-50 p-5 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wide text-emerald-700">Vinculadas</p>
                <p class="mt-2 text-3xl font-bold text-emerald-900">{{ $summary['linked'] ?? 0 }}</p>
            </div>
            <div class="rounded-lg border border-blue-200 bg-blue-50 p-5 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wide text-blue-700">Criadas</p>
                <p class="mt-2 text-3xl font-bold text-blue-900">{{ $summary['created'] ?? 0 }}</p>
            </div>
            <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Ignoradas</p>
                <p class="mt-2 text-3xl font-bold text-slate-950">{{ $summary['ignored'] ?? 0 }}</p>
            </div>
        </div>

        <section class="rounded-lg border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-200 px-5 py-4">
                <h2 class="text-base font-semibold text-slate-900">Filtros</h2>
            </div>
            <form method="GET" class="grid gap-4 p-5 md:grid-cols-2 xl:grid-cols-4">
                <div>
                    <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Busca</label>
                    <input type="text" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Hostname, IP, serial ou descrição" class="w-full rounded-md border-slate-300 text-sm shadow-sm focus:border-[#033151] focus:ring-[#033151]">
                </div>
                <div>
                    <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Status</label>
                    <select name="status" class="w-full rounded-md border-slate-300 text-sm shadow-sm focus:border-[#033151] focus:ring-[#033151]">
                        <option value="">Todos</option>
                        @foreach($statuses as $key => $label)
                            <option value="{{ $key }}" @selected(($filters['status'] ?? '') === $key)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Conector</label>
                    <select name="connector_id" class="w-full rounded-md border-slate-300 text-sm shadow-sm focus:border-[#033151] focus:ring-[#033151]">
                        <option value="">Todos</option>
                        @foreach($connectors as $connector)
                            <option value="{{ $connector->id }}" @selected((string) ($filters['connector_id'] ?? '') === (string) $connector->id)>{{ $connector->name }} ({{ $connector->connector_type }})</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Hostname</label>
                    <input type="text" name="hostname" value="{{ $filters['hostname'] ?? '' }}" class="w-full rounded-md border-slate-300 text-sm shadow-sm focus:border-[#033151] focus:ring-[#033151]">
                </div>
                <div>
                    <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">IP</label>
                    <input type="text" name="ip_address" value="{{ $filters['ip_address'] ?? '' }}" class="w-full rounded-md border-slate-300 text-sm shadow-sm focus:border-[#033151] focus:ring-[#033151]">
                </div>
                <div>
                    <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Fabricante</label>
                    <input type="text" name="manufacturer" value="{{ $filters['manufacturer'] ?? '' }}" class="w-full rounded-md border-slate-300 text-sm shadow-sm focus:border-[#033151] focus:ring-[#033151]">
                </div>
                <div>
                    <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Modelo</label>
                    <input type="text" name="model" value="{{ $filters['model'] ?? '' }}" class="w-full rounded-md border-slate-300 text-sm shadow-sm focus:border-[#033151] focus:ring-[#033151]">
                </div>
                <div>
                    <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">De</label>
                    <input type="date" name="from" value="{{ $filters['from'] ?? '' }}" class="w-full rounded-md border-slate-300 text-sm shadow-sm focus:border-[#033151] focus:ring-[#033151]">
                </div>
                <div>
                    <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Até</label>
                    <input type="date" name="to" value="{{ $filters['to'] ?? '' }}" class="w-full rounded-md border-slate-300 text-sm shadow-sm focus:border-[#033151] focus:ring-[#033151]">
                </div>
                <div class="flex items-end gap-2 md:col-span-2 xl:col-span-4">
                    <button type="submit" class="inline-flex min-h-10 items-center justify-center rounded-md bg-[#033151] px-4 text-sm font-semibold text-white hover:bg-[#02273f]">Filtrar</button>
                    <a href="{{ route('cmdb.discovered-assets.index') }}" class="inline-flex min-h-10 items-center justify-center rounded-md border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-700 hover:bg-slate-50">Limpar</a>
                </div>
            </form>
        </section>

        <section class="rounded-lg border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-200 px-5 py-4 flex items-center justify-between gap-3">
                <div>
                    <h2 class="text-base font-semibold text-slate-900">Descobertas</h2>
                    <p class="text-sm text-slate-500">Revise itens encontrados por SNMP, vCenter e outros conectores.</p>
                </div>
                <a href="{{ route('cmdb.discovery.index') }}" class="text-sm font-semibold text-[#033151] hover:underline">Voltar para descoberta</a>
            </div>
            <div class="flex flex-wrap gap-2 border-b border-slate-200 px-5 py-3">
                @foreach(['' => 'Todas', 'vcenter_virtual_machine' => 'Máquinas virtuais', 'vcenter_host' => 'Hosts ESXi', 'vcenter_network' => 'Redes / VLANs', 'vcenter_datastore' => 'Datastores', 'snmp' => 'SNMP'] as $type => $label)
                    <a href="{{ route('cmdb.discovered-assets.index', array_merge(request()->except('page'), ['type' => $type ?: null])) }}" class="rounded-full border px-3 py-1.5 text-xs font-semibold {{ ($filters['type'] ?? '') === $type ? 'border-[#033151] bg-[#033151] text-white' : 'border-slate-300 bg-white text-slate-700 hover:bg-slate-50' }}">{{ $label }}</a>
                @endforeach
                <form id="bulk-discovery-form" method="POST" action="{{ route('cmdb.discovered-assets.bulk-ignore') }}" onsubmit="return confirm('Deseja ignorar as descobertas selecionadas?');">@csrf<button class="rounded-md border border-amber-300 bg-amber-50 px-3 py-1.5 text-xs font-semibold text-amber-800">Ignorar selecionados</button></form>
                <button type="submit" form="bulk-discovery-form" formaction="{{ route('cmdb.discovered-assets.bulk-reopen') }}" class="rounded-md border border-slate-300 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700" onclick="return confirm('Deseja reabrir as descobertas selecionadas?');">Reabrir selecionados</button>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-100 text-sm">
                    <thead class="border-b border-slate-100 text-left text-xs font-semibold uppercase tracking-wide text-slate-400">
                        <tr>
                            <th class="px-5 py-3">Selecionar</th>
                            <th class="px-5 py-3">Situação</th>
                            <th class="px-5 py-3">Nome</th>
                            <th class="px-5 py-3">Tipo / origem</th>
                            <th class="px-5 py-3">Correspondência</th>
                            <th class="px-5 py-3">Ação recomendada</th>
                            <th class="px-5 py-3">Ações</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse($items as $item)
                            <tr>
                                <td class="px-5 py-3"><input type="checkbox" name="discovery_ids[]" value="{{ $item->id }}" form="bulk-discovery-form" class="rounded border-slate-300"></td>
                                <td class="px-5 py-3">
                                    <span class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-semibold {{ $statusBadgeClass($item->status) }}">{{ $statuses[$item->status] ?? $item->status }}</span>
                                </td>
                                <td class="px-5 py-3">
                                    <a href="{{ route('cmdb.discovered-assets.show', $item) }}" class="font-semibold text-[#033151] hover:underline">{{ $item->hostname ?: $item->ip_address ?: 'Item sem nome' }}</a>
                                </td>
                                <td class="px-5 py-3">{{ $sourceTypes[$item->source_type] ?? ($item->object_type ?: $item->source_type) }}<div class="text-xs text-slate-500">{{ $item->connector?->name ?: '-' }}</div></td>
                                <td class="px-5 py-3">
                                    @if($item->asset)
                                        <a href="{{ route('cmdb.assets.show', $item->asset) }}" class="font-semibold text-[#033151] hover:underline">{{ $item->asset->name }}</a>
                                    @else
                                        <span class="text-slate-500">Sem correspondência confirmada</span>
                                    @endif
                                </td>
                                <td class="px-5 py-3 text-slate-600">{{ $item->asset ? 'Revisar vínculo' : 'Revisar correspondência' }}</td>
                                <td class="px-5 py-3">
                                    <div class="flex flex-wrap gap-2">
                                        <a href="{{ route('cmdb.discovered-assets.show', $item) }}" class="inline-flex rounded-md border border-[#033151] bg-white px-3 py-1.5 text-xs font-semibold text-[#033151] hover:bg-slate-50">Revisar</a>
                                        @if(in_array($item->status, ['pending', 'ignored'], true))
                                            <a href="{{ route('cmdb.discovered-assets.create-asset', $item) }}" class="inline-flex rounded-md border border-slate-300 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50">Criar ativo</a>
                                        @endif
                                        @if($item->asset)
                                            <a href="{{ route('cmdb.assets.show', $item->asset) }}" class="inline-flex rounded-md border border-emerald-300 bg-emerald-50 px-3 py-1.5 text-xs font-semibold text-emerald-800 hover:bg-emerald-100">Abrir ativo</a>
                                        @endif
                                        @if($item->status === 'pending')
                                            <form method="POST" action="{{ route('cmdb.discovered-assets.ignore', $item) }}">
                                                @csrf
                                                <button type="submit" class="inline-flex rounded-md border border-amber-300 bg-amber-50 px-3 py-1.5 text-xs font-semibold text-amber-800 hover:bg-amber-100">Ignorar</button>
                                            </form>
                                        @endif
                                        @if(in_array($item->status, ['linked', 'created', 'ignored', 'error'], true))
                                            <form method="POST" action="{{ route('cmdb.discovered-assets.reopen', $item) }}">
                                                @csrf
                                                <button type="submit" class="inline-flex rounded-md border border-slate-300 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50">Reabrir</button>
                                            </form>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-5 py-6 text-center text-slate-500">Nenhuma descoberta encontrada.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if(method_exists($items, 'links'))
                <div class="border-t border-slate-200 px-5 py-4">
                    {{ $items->links() }}
                </div>
            @endif
        </section>
    </div>
</x-app-layout>
