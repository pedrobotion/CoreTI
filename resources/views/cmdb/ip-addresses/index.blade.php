<x-app-layout>
    <div class="mx-auto max-w-7xl space-y-6">
        @include('cmdb.partials._nav', ['title' => 'Endereços IP', 'description' => 'Cadastro e consulta de IPs vinculados aos ativos.'])

        @if(!empty($setupPending))
            <div class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                Estrutura do CMDB ainda não foi criada no banco. Execute as migrations para liberar o módulo.
            </div>
        @endif

        <section class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
            <form method="GET" class="flex flex-wrap items-end gap-2">
                <div class="min-w-[240px] flex-1">
                    <label class="text-xs font-semibold uppercase text-slate-500">Busca</label>
                    <input name="q" value="{{ $filters['q'] ?? '' }}" placeholder="IP, gateway, DNS, VLAN ou MAC" class="mt-1 h-10 w-full rounded-md border-slate-300 text-sm">
                </div>
                <div class="min-w-[220px]">
                    <label class="text-xs font-semibold uppercase text-slate-500">Ativo</label>
                    <select name="asset_id" class="mt-1 h-10 w-full rounded-md border-slate-300 text-sm">
                        <option value="">Todos</option>
                        @foreach($assets as $item)
                            <option value="{{ $item->id }}" @selected((string) ($filters['asset_id'] ?? '') === (string) $item->id)>
                                {{ $item->name }}{{ $item->hostname ? ' • ' . $item->hostname : '' }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="min-w-[160px]">
                    <label class="text-xs font-semibold uppercase text-slate-500">VLAN</label>
                    <input name="vlan" value="{{ $filters['vlan'] ?? '' }}" placeholder="Ex.: 100" class="mt-1 h-10 w-full rounded-md border-slate-300 text-sm">
                </div>
                <div class="min-w-[160px]">
                    <label class="text-xs font-semibold uppercase text-slate-500">Principal</label>
                    <select name="is_primary" class="mt-1 h-10 w-full rounded-md border-slate-300 text-sm">
                        <option value="">Todos</option>
                        <option value="1" @selected(($filters['is_primary'] ?? '') === '1')>Sim</option>
                        <option value="0" @selected(($filters['is_primary'] ?? '') === '0')>Não</option>
                    </select>
                </div>
                <div class="flex gap-2">
                    <button type="submit" class="inline-flex h-10 items-center justify-center rounded-md bg-[#033151] px-4 text-sm font-semibold text-white hover:opacity-90">Buscar</button>
                    <a href="{{ route('cmdb.ip-addresses.index') }}" class="inline-flex h-10 items-center justify-center rounded-md border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-700 hover:bg-slate-50">Limpar</a>
                </div>
            </form>
        </section>

        @include('cmdb.ip-addresses._form', ['assets' => $assets, 'asset' => $asset, 'ipAddress' => $ipAddress])

        <section class="rounded-lg border border-slate-200 bg-white shadow-sm">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-100 text-sm">
                    <thead class="border-b border-slate-100 text-left text-xs font-semibold uppercase tracking-wide text-slate-400">
                        <tr>
                            <th class="px-5 py-3">Ativo</th>
                            <th class="px-5 py-3">IP</th>
                            <th class="px-5 py-3">Máscara</th>
                            <th class="px-5 py-3">Gateway</th>
                            <th class="px-5 py-3">DNS</th>
                            <th class="px-5 py-3">VLAN</th>
                            <th class="px-5 py-3">MAC Address</th>
                            <th class="px-5 py-3">Principal</th>
                            <th class="px-5 py-3">Ações</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse($items as $item)
                            <tr>
                                <td class="px-5 py-3">
                                    @if($item->asset)
                                        <a href="{{ route('cmdb.assets.show', $item->asset) }}" class="font-semibold text-[#033151] hover:underline">{{ $item->asset->name }}</a>
                                        @if($item->asset->hostname)
                                            <div class="text-xs text-slate-500">{{ $item->asset->hostname }}</div>
                                        @endif
                                    @else
                                        -
                                    @endif
                                </td>
                                <td class="px-5 py-3">{{ $item->ip_address }}</td>
                                <td class="px-5 py-3">{{ $item->subnet_mask ?: '-' }}</td>
                                <td class="px-5 py-3">{{ $item->gateway ?: '-' }}</td>
                                <td class="px-5 py-3">{{ $item->dns ?: '-' }}</td>
                                <td class="px-5 py-3">{{ $item->vlan ?: '-' }}</td>
                                <td class="px-5 py-3">{{ $item->mac_address ?: '-' }}</td>
                                <td class="px-5 py-3">
                                    @if($item->is_primary)
                                        <span class="inline-flex rounded-full bg-emerald-100 px-2.5 py-0.5 text-xs font-semibold text-emerald-800">Principal</span>
                                    @else
                                        <span class="inline-flex rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-semibold text-slate-700">Secundário</span>
                                    @endif
                                </td>
                                <td class="px-5 py-3">
                                    <div class="flex flex-wrap gap-2">
                                        <a href="{{ route('cmdb.ip-addresses.edit', $item) }}" class="inline-flex rounded-md border border-[#033151] bg-white px-3 py-1.5 text-xs font-semibold text-[#033151] hover:bg-slate-50">Editar</a>
                                        <form method="POST" action="{{ route('cmdb.ip-addresses.destroy', $item) }}" onsubmit="return confirm('Remover este IP?')">
                                            @csrf
                                            @method('DELETE')
                                            <button class="inline-flex rounded-md border border-rose-200 bg-white px-3 py-1.5 text-xs font-semibold text-rose-700 hover:bg-rose-50">Excluir</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="px-5 py-6 text-center text-slate-500">Nenhum endereço IP cadastrado.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="border-t border-slate-200 px-5 py-4">
                {{ $items->links() }}
            </div>
        </section>
    </div>
</x-app-layout>
