<x-app-layout>
    @php
        $typeLabels = [
            'server' => 'Servidor físico',
            'virtual_machine' => 'Máquina virtual',
            'database' => 'Banco de dados',
            'switch' => 'Switch',
            'firewall' => 'Firewall',
            'sonicwall' => 'Sonicwall',
            'storage' => 'Storage',
            'ups' => 'Nobreak / UPS',
            'link' => 'Link de internet',
            'circuit' => 'Circuito',
            'rack' => 'Rack',
            'certificate' => 'Certificado SSL',
            'backup' => 'Backup',
            'other' => 'Outro',
        ];
        $statusLabels = [
            'active' => 'Ativo',
            'inactive' => 'Inativo',
            'maintenance' => 'Em manutenção',
            'decommissioned' => 'Descomissionado',
            'planned' => 'Planejado',
        ];
    @endphp
    <div class="mx-auto max-w-7xl space-y-6">
        @include('cmdb.partials._nav', ['title' => $rack->name, 'description' => 'Detalhes do rack'])

        <div class="flex flex-wrap gap-2">
            <a href="{{ route('cmdb.racks.edit', $rack) }}" class="inline-flex min-h-10 items-center justify-center rounded-md bg-[#033151] px-4 text-sm font-semibold text-white hover:opacity-90">Editar</a>
            <a href="{{ route('cmdb.racks.index') }}" class="inline-flex min-h-10 items-center justify-center rounded-md border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-700 hover:bg-slate-50">Voltar</a>
            <a href="{{ route('cmdb.assets.index', ['rack_id' => $rack->id]) }}" class="inline-flex min-h-10 items-center justify-center rounded-md border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-700 hover:bg-slate-50">Ver ativos deste rack</a>
        </div>

        <section class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
            <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Nome</p>
                <p class="mt-2 text-base font-semibold text-slate-950">{{ $rack->name }}</p>
            </div>
            <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Unidade</p>
                <p class="mt-2 text-base font-semibold text-slate-950">{{ $unitLabels[$rack->organization_unit_id] ?? ($rack->unit_id ?: '-') }}</p>
            </div>
            <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Ativos vinculados</p>
                <p class="mt-2 text-2xl font-semibold text-slate-950">{{ $rack->assets->count() }}</p>
            </div>
            <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Localização</p>
                <p class="mt-2 text-base font-semibold text-slate-950">{{ $rack->location ?: '-' }}</p>
            </div>
            <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Sala</p>
                <p class="mt-2 text-base font-semibold text-slate-950">{{ $rack->room ?: '-' }}</p>
            </div>
            <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Andar</p>
                <p class="mt-2 text-base font-semibold text-slate-950">{{ $rack->floor ?: '-' }}</p>
            </div>
            <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Capacidade em U</p>
                <p class="mt-2 text-base font-semibold text-slate-950">{{ $rack->capacity_u ?: '-' }}</p>
            </div>
        </section>

        <section class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="text-base font-semibold text-slate-900">Ativos neste rack</h2>
            <div class="mt-4 overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="px-5 py-3">Nome</th>
                            <th class="px-5 py-3">Tipo</th>
                            <th class="px-5 py-3">Status</th>
                            <th class="px-5 py-3">Ações</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200">
                        @forelse($rack->assets as $asset)
                            <tr>
                                <td class="px-5 py-3">
                                    <a href="{{ route('cmdb.assets.show', $asset) }}" class="font-semibold text-[#033151] hover:underline">{{ $asset->name }}</a>
                                    @if($asset->hostname)
                                        <div class="text-xs text-slate-500">{{ $asset->hostname }}</div>
                                    @endif
                                </td>
                                <td class="px-5 py-3">{{ $typeLabels[$asset->asset_type] ?? $asset->asset_type }}</td>
                                <td class="px-5 py-3">
                                    <span class="inline-flex rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-semibold text-slate-700">{{ $statusLabels[$asset->status] ?? $asset->status }}</span>
                                </td>
                                <td class="px-5 py-3">
                                    <a href="{{ route('cmdb.assets.show', $asset) }}" class="inline-flex rounded-md border border-[#033151] bg-white px-3 py-1.5 text-xs font-semibold text-[#033151] hover:bg-slate-50">Abrir</a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="px-5 py-6 text-center text-slate-500">Nenhum ativo vinculado.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</x-app-layout>
