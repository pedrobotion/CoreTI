<x-app-layout>
    <div class="mx-auto max-w-7xl space-y-6">
        @include('cmdb.partials._nav', ['title' => 'VLANs', 'description' => 'Redes e VLANs identificadas pelo vCenter, separadas dos servidores e máquinas virtuais.'])

        <section class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
            <form method="GET" class="flex flex-wrap items-end gap-3">
                <div class="min-w-[260px] flex-1"><label class="text-xs font-semibold uppercase text-slate-500">Buscar VLAN/rede</label><input name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Nome ou identificador da rede" class="mt-1 h-10 w-full rounded-md border-slate-300 text-sm"></div>
                <div><label class="text-xs font-semibold uppercase text-slate-500">Status</label><select name="status" class="mt-1 h-10 rounded-md border-slate-300 text-sm"><option value="">Todos</option>@foreach($statusLabels as $key => $label)<option value="{{ $key }}" @selected(($filters['status'] ?? '') === $key)>{{ $label }}</option>@endforeach</select></div>
                <button class="h-10 rounded-md bg-[#033151] px-4 text-sm font-semibold text-white">Filtrar</button>
                <a href="{{ route('cmdb.vlans.index') }}" class="inline-flex h-10 items-center rounded-md border border-slate-300 px-4 text-sm font-semibold text-slate-700">Limpar</a>
            </form>
        </section>

        <section class="rounded-lg border border-slate-200 bg-white shadow-sm">
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-200 px-5 py-4"><div><h2 class="font-semibold text-slate-900">Redes / VLANs</h2><p class="text-sm text-slate-500">{{ $vlans->total() }} registro(s) encontrado(s).</p></div><a href="{{ route('cmdb.discovered-assets.index', ['type' => 'vcenter_network']) }}" class="text-sm font-semibold text-[#033151] hover:underline">Ver em descobertas</a></div>
            <div class="overflow-x-auto"><table class="min-w-full divide-y divide-slate-100 text-sm"><thead class="border-b border-slate-100 text-left text-xs font-semibold uppercase tracking-wide text-slate-400"><tr><th class="px-5 py-3">Nome da rede</th><th class="px-5 py-3">Identificador</th><th class="px-5 py-3">Origem</th><th class="px-5 py-3">Status</th><th class="px-5 py-3">Última visualização</th><th class="px-5 py-3">Ações</th></tr></thead><tbody class="divide-y divide-slate-100">@forelse($vlans as $vlan)<tr><td class="px-5 py-3 font-medium text-slate-900">{{ $vlan->hostname ?: $vlan->description ?: '-' }}</td><td class="px-5 py-3">{{ $vlan->source_external_id ?: $vlan->object_id ?: '-' }}</td><td class="px-5 py-3">{{ $vlan->connector?->name ?: 'vCenter' }}</td><td class="px-5 py-3"><span class="rounded-full bg-amber-100 px-2.5 py-1 text-xs font-semibold text-amber-800">{{ $statusLabels[$vlan->status] ?? $vlan->status }}</span></td><td class="px-5 py-3">{{ optional($vlan->last_seen_at ?: $vlan->discovered_at)->format('d/m/Y H:i') ?: '-' }}</td><td class="px-5 py-3"><a href="{{ route('cmdb.discovered-assets.show', $vlan) }}" class="font-semibold text-[#033151] hover:underline">Revisar</a></td></tr>@empty<tr><td colspan="6" class="px-5 py-8 text-center text-slate-500">Nenhuma VLAN encontrada.</td></tr>@endforelse</tbody></table></div>
            <div class="border-t border-slate-200 px-5 py-4">{{ $vlans->links() }}</div>
        </section>
    </div>
</x-app-layout>
