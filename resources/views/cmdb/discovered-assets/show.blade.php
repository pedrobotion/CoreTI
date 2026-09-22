<x-app-layout>
    @php
        $statusBadgeClass = fn (string $status) => match ($status) {
            'pending' => 'bg-amber-100 text-amber-800',
            'linked' => 'bg-emerald-100 text-emerald-800',
            'created' => 'bg-blue-100 text-blue-800',
            'ignored' => 'bg-slate-100 text-slate-700',
            'duplicate' => 'bg-violet-100 text-violet-800',
            'error' => 'bg-rose-100 text-rose-800',
            default => 'bg-slate-100 text-slate-700',
        };
    @endphp
    @php
        $locationDescription = collect([$item->location, $item->description])->filter(fn ($value) => filled($value))->implode("\n");
    @endphp

    <div class="mx-auto max-w-7xl space-y-6">
        @include('cmdb.partials._nav', ['title' => 'Revisão de descoberta', 'description' => 'Detalhe técnico da descoberta SNMP para revisão manual.'])

        <div class="flex flex-wrap gap-2">
            <a href="{{ route('cmdb.discovered-assets.index') }}" class="inline-flex min-h-10 items-center justify-center rounded-md border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-700 hover:bg-slate-50">Voltar</a>
            @if($item->asset)
                <a href="{{ route('cmdb.assets.show', $item->asset) }}" class="inline-flex min-h-10 items-center justify-center rounded-md border border-[#033151] bg-white px-4 text-sm font-semibold text-[#033151] hover:bg-slate-50">Abrir ativo vinculado</a>
            @endif
            @if(in_array($item->status, ['pending', 'ignored'], true))
                <a href="{{ route('cmdb.discovered-assets.create-asset', $item) }}" class="inline-flex min-h-10 items-center justify-center rounded-md bg-[#033151] px-4 text-sm font-semibold text-white hover:bg-[#02273f]">Criar ativo</a>
            @endif
            @if($item->status === 'pending')
                <form method="POST" action="{{ route('cmdb.discovered-assets.ignore', $item) }}">
                    @csrf
                    <button type="submit" class="inline-flex min-h-10 items-center justify-center rounded-md border border-amber-300 bg-amber-50 px-4 text-sm font-semibold text-amber-800 hover:bg-amber-100">Ignorar</button>
                </form>
            @endif
            @if(in_array($item->status, ['linked', 'created', 'ignored', 'error'], true))
                <form method="POST" action="{{ route('cmdb.discovered-assets.reopen', $item) }}">
                    @csrf
                    <button type="submit" class="inline-flex min-h-10 items-center justify-center rounded-md border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-700 hover:bg-slate-50">Reabrir</button>
                </form>
            @endif
        </div>

        <section class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Status</p>
                    <span class="mt-2 inline-flex rounded-full px-2.5 py-0.5 text-xs font-semibold {{ $statusBadgeClass($item->status) }}">{{ $statuses[$item->status] ?? $item->status }}</span>
                </div>
                <div class="text-sm text-slate-500">
                    <div><span class="font-semibold text-slate-700">Conector:</span> {{ $item->connector?->name ?: '-' }}</div>
                    <div><span class="font-semibold text-slate-700">Descoberto em:</span> {{ optional($item->discovered_at ?? $item->created_at)?->format('d/m/Y H:i') ?: '-' }}</div>
                    <div><span class="font-semibold text-slate-700">Revisado em:</span> {{ $item->reviewed_at?->format('d/m/Y H:i') ?: '-' }}</div>
                    <div><span class="font-semibold text-slate-700">Revisado por:</span> {{ $item->reviewedBy?->name ?: '-' }}</div>
                </div>
            </div>

            @if($item->review_notes)
                <div class="mt-4 rounded-md border border-slate-200 bg-slate-50 p-4 text-sm text-slate-700">
                    {{ $item->review_notes }}
                </div>
            @endif

            <div class="mt-5 grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                <div><p class="text-xs font-semibold uppercase text-slate-500">Hostname</p><p class="mt-1 text-sm font-semibold text-slate-950">{{ $item->hostname ?: '-' }}</p></div>
                <div><p class="text-xs font-semibold uppercase text-slate-500">IP</p><p class="mt-1 text-sm font-semibold text-slate-950">{{ $item->ip_address ?: '-' }}</p></div>
                <div><p class="text-xs font-semibold uppercase text-slate-500">MAC</p><p class="mt-1 text-sm font-semibold text-slate-950">{{ $item->mac_address ?: '-' }}</p></div>
                <div><p class="text-xs font-semibold uppercase text-slate-500">Fabricante</p><p class="mt-1 text-sm font-semibold text-slate-950">{{ $item->manufacturer ?: '-' }}</p></div>
                <div><p class="text-xs font-semibold uppercase text-slate-500">Modelo</p><p class="mt-1 text-sm font-semibold text-slate-950">{{ $item->model ?: '-' }}</p></div>
                <div><p class="text-xs font-semibold uppercase text-slate-500">Serial</p><p class="mt-1 text-sm font-semibold text-slate-950">{{ $item->serial_number ?: '-' }}</p></div>
                <div><p class="text-xs font-semibold uppercase text-slate-500">Object ID</p><p class="mt-1 text-sm font-semibold text-slate-950">{{ $item->object_id ?: '-' }}</p></div>
                <div><p class="text-xs font-semibold uppercase text-slate-500">Uptime</p><p class="mt-1 text-sm font-semibold text-slate-950">{{ $item->uptime ?: '-' }}</p></div>
                <div><p class="text-xs font-semibold uppercase text-slate-500">Contato</p><p class="mt-1 text-sm font-semibold text-slate-950">{{ $item->contact ?: '-' }}</p></div>
                <div class="md:col-span-2 xl:col-span-3"><p class="text-xs font-semibold uppercase text-slate-500">Localização / Descrição</p><p class="mt-1 whitespace-pre-line text-sm text-slate-700">{{ $locationDescription ?: '-' }}</p></div>
                <div><p class="text-xs font-semibold uppercase text-slate-500">Identificador externo</p><p class="mt-1 text-sm font-semibold text-slate-950">{{ $item->source_external_id ?: '-' }}</p></div>
                <div><p class="text-xs font-semibold uppercase text-slate-500">Ativo vinculado</p>
                    @if($item->asset)
                        <a href="{{ route('cmdb.assets.show', $item->asset) }}" class="mt-1 block text-sm font-semibold text-[#033151] hover:underline">{{ $item->asset->name }}</a>
                    @else
                        <p class="mt-1 text-sm text-slate-500">Sem vínculo</p>
                    @endif
                </div>
            </div>
        </section>

        <section class="rounded-lg border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-200 px-5 py-4">
                <h2 class="text-base font-semibold text-slate-900">Ativos sugeridos</h2>
                <p class="text-sm text-slate-500">Os vínculos abaixo são apenas sugestões técnicas baseadas em hostname, IP, serial e identificadores.</p>
            </div>
            <div class="p-5">
                @forelse($suggestions as $suggestion)
                    @php $asset = $suggestion['asset']; @endphp
                    <div class="mb-3 rounded-md border border-slate-200 p-4 last:mb-0">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div>
                                <a href="{{ route('cmdb.assets.show', $asset) }}" class="font-semibold text-[#033151] hover:underline">{{ $asset->name }}</a>
                                <div class="text-xs text-slate-500">{{ $asset->hostname ?: 'Sem hostname' }} • {{ $asset->main_ip ?: 'Sem IP' }}</div>
                            </div>
                            <span class="inline-flex rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-semibold text-slate-700">{{ $asset->asset_type }}</span>
                        </div>
                        <div class="mt-3 flex flex-wrap items-center justify-between gap-3">
                            <div class="flex flex-wrap gap-2">
                                @foreach($suggestion['reasons'] as $reason)
                                    <span class="inline-flex rounded-full bg-amber-100 px-2.5 py-0.5 text-xs font-semibold text-amber-800">{{ $reason }}</span>
                                @endforeach
                            </div>
                            <form method="POST" action="{{ route('cmdb.discovered-assets.link', $item) }}" class="flex items-center gap-2">
                                @csrf
                                <input type="hidden" name="asset_id" value="{{ $asset->id }}">
                                <button type="submit" class="inline-flex rounded-md bg-[#033151] px-3 py-1.5 text-xs font-semibold text-white hover:bg-[#02273f]">Vincular</button>
                            </form>
                        </div>
                    </div>
                @empty
                    <p class="text-sm text-slate-500">Nenhum ativo sugerido encontrado. Use o cadastro rápido para criar um novo ativo.</p>
                @endforelse
            </div>
        </section>

        <section class="rounded-lg border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-200 px-5 py-4">
                <h2 class="text-base font-semibold text-slate-900">Dados brutos</h2>
            </div>
            <pre class="overflow-x-auto p-5 text-xs leading-relaxed text-slate-700">{{ json_encode($item->raw_data ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</pre>
        </section>
    </div>
</x-app-layout>
