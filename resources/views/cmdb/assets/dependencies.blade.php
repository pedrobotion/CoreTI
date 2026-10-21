@php
    $typeLabel = $types[$asset->asset_type] ?? $asset->asset_type;
    $relationshipLabels = [
        'hosts' => 'Hospeda',
        'depends_on' => 'Depende de',
        'connects_to' => 'Conecta-se a',
        'protects' => 'Protege',
        'backs_up_to' => 'Faz backup para',
        'replicates_to' => 'Replica para',
        'uses' => 'Utiliza',
        'located_in' => 'Está localizado em',
        'powered_by' => 'Alimentado por',
        'monitored_by' => 'Monitorado por',
    ];
    $dependencies = $asset->outgoingRelationships->filter(fn ($relationship) => in_array($relationship->relationship_type, ['depends_on', 'uses', 'powered_by'], true));
    $dependents = $asset->incomingRelationships->filter(fn ($relationship) => in_array($relationship->relationship_type, ['depends_on', 'uses', 'powered_by'], true));
    $hosts = $asset->incomingRelationships->filter(fn ($relationship) => $relationship->relationship_type === 'hosts');
    $hosted = $asset->outgoingRelationships->filter(fn ($relationship) => $relationship->relationship_type === 'hosts');
    $networkRelations = $asset->outgoingRelationships->filter(fn ($relationship) => in_array($relationship->relationship_type, ['connects_to', 'protects', 'backs_up_to', 'replicates_to', 'located_in', 'monitored_by'], true));
    $networkIncoming = $asset->incomingRelationships->filter(fn ($relationship) => in_array($relationship->relationship_type, ['connects_to', 'protects', 'backs_up_to', 'replicates_to', 'located_in', 'monitored_by'], true));
    $badgeClass = fn (string $type) => match ($type) {
        'hosts' => 'bg-blue-50 text-blue-700 ring-1 ring-inset ring-blue-200',
        'depends_on' => 'bg-amber-50 text-amber-700 ring-1 ring-inset ring-amber-200',
        'connects_to' => 'bg-cyan-50 text-cyan-700 ring-1 ring-inset ring-cyan-200',
        'protects' => 'bg-emerald-50 text-emerald-700 ring-1 ring-inset ring-emerald-200',
        'backs_up_to' => 'bg-violet-50 text-violet-700 ring-1 ring-inset ring-violet-200',
        'replicates_to' => 'bg-fuchsia-50 text-fuchsia-700 ring-1 ring-inset ring-fuchsia-200',
        'uses' => 'bg-slate-100 text-slate-700',
        'located_in' => 'bg-indigo-50 text-indigo-700 ring-1 ring-inset ring-indigo-200',
        'powered_by' => 'bg-orange-50 text-orange-700 ring-1 ring-inset ring-orange-200',
        'monitored_by' => 'bg-rose-50 text-rose-700 ring-1 ring-inset ring-rose-200',
        default => 'bg-slate-100 text-slate-700',
    };
@endphp

<x-app-layout>
    <div class="mx-auto max-w-7xl space-y-6">
        @include('cmdb.partials._nav', ['title' => 'Dependências', 'description' => 'Visão de dependência do ativo.'])

        <div class="flex flex-wrap gap-2">
            <a href="{{ route('cmdb.assets.show', $asset) }}" class="inline-flex min-h-10 items-center justify-center rounded-md bg-[#033151] px-4 text-sm font-semibold text-white hover:opacity-90">Voltar ao ativo</a>
            <a href="{{ route('cmdb.assets.edit', $asset) }}" class="inline-flex min-h-10 items-center justify-center rounded-md border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-700 hover:bg-slate-50">Editar ativo</a>
        </div>

        <section class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
            <div class="flex flex-wrap items-center gap-2">
                <h2 class="text-lg font-semibold text-slate-900">{{ $asset->name }}</h2>
                <span class="inline-flex rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-semibold text-slate-700">{{ $typeLabel }}</span>
                <span class="inline-flex rounded-full bg-blue-100 px-2.5 py-0.5 text-xs font-semibold text-blue-800">{{ $asset->status }}</span>
            </div>
            <p class="mt-2 text-sm text-slate-500">Abaixo estão os vínculos diretos e indiretos deste ativo.</p>
        </section>

        <div class="grid gap-6 xl:grid-cols-2">
            <section class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                <h3 class="text-base font-semibold text-slate-900">Ativos dos quais ele depende</h3>
                <div class="mt-4 space-y-3">
                    @forelse($dependencies as $relationship)
                        <div class="rounded-md border border-slate-200 p-3">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-semibold {{ $badgeClass($relationship->relationship_type) }}">{{ $relationshipLabels[$relationship->relationship_type] ?? $relationship->relationship_type }}</span>
                                @if($relationship->targetAsset)
                                    <a href="{{ route('cmdb.assets.show', $relationship->targetAsset) }}" class="text-sm font-semibold text-[#033151] hover:underline">{{ $relationship->targetAsset->name }}</a>
                                @else
                                    <span class="text-sm text-slate-600">Sem ativo relacionado</span>
                                @endif
                            </div>
                            @if($relationship->description)
                                <div class="mt-1 text-xs text-slate-500">{{ $relationship->description }}</div>
                            @endif
                        </div>
                    @empty
                        <p class="text-sm text-slate-500">Nenhuma dependência cadastrada.</p>
                    @endforelse
                </div>
            </section>

            <section class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                <h3 class="text-base font-semibold text-slate-900">Ativos que dependem dele</h3>
                <div class="mt-4 space-y-3">
                    @forelse($dependents as $relationship)
                        <div class="rounded-md border border-slate-200 p-3">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-semibold {{ $badgeClass($relationship->relationship_type) }}">{{ $relationshipLabels[$relationship->relationship_type] ?? $relationship->relationship_type }}</span>
                                @if($relationship->sourceAsset)
                                    <a href="{{ route('cmdb.assets.show', $relationship->sourceAsset) }}" class="text-sm font-semibold text-[#033151] hover:underline">{{ $relationship->sourceAsset->name }}</a>
                                @else
                                    <span class="text-sm text-slate-600">Sem ativo relacionado</span>
                                @endif
                            </div>
                            @if($relationship->description)
                                <div class="mt-1 text-xs text-slate-500">{{ $relationship->description }}</div>
                            @endif
                        </div>
                    @empty
                        <p class="text-sm text-slate-500">Nenhum dependente cadastrado.</p>
                    @endforelse
                </div>
            </section>
        </div>

        <div class="grid gap-6 xl:grid-cols-2">
            <section class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                <h3 class="text-base font-semibold text-slate-900">Ativos hospedados por este ativo</h3>
                <div class="mt-4 space-y-3">
                    @forelse($hosted as $relationship)
                        <div class="rounded-md border border-slate-200 p-3">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-semibold {{ $badgeClass($relationship->relationship_type) }}">{{ $relationshipLabels[$relationship->relationship_type] ?? $relationship->relationship_type }}</span>
                                @if($relationship->targetAsset)
                                    <a href="{{ route('cmdb.assets.show', $relationship->targetAsset) }}" class="text-sm font-semibold text-[#033151] hover:underline">{{ $relationship->targetAsset->name }}</a>
                                @else
                                    <span class="text-sm text-slate-600">Sem ativo relacionado</span>
                                @endif
                            </div>
                        </div>
                    @empty
                        <p class="text-sm text-slate-500">Nenhum ativo hospedado.</p>
                    @endforelse
                </div>
            </section>

            <section class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                <h3 class="text-base font-semibold text-slate-900">Ativos que o hospedam</h3>
                <div class="mt-4 space-y-3">
                    @forelse($hosts as $relationship)
                        <div class="rounded-md border border-slate-200 p-3">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-semibold {{ $badgeClass($relationship->relationship_type) }}">{{ $relationshipLabels[$relationship->relationship_type] ?? $relationship->relationship_type }}</span>
                                @if($relationship->sourceAsset)
                                    <a href="{{ route('cmdb.assets.show', $relationship->sourceAsset) }}" class="text-sm font-semibold text-[#033151] hover:underline">{{ $relationship->sourceAsset->name }}</a>
                                @else
                                    <span class="text-sm text-slate-600">Sem ativo relacionado</span>
                                @endif
                            </div>
                        </div>
                    @empty
                        <p class="text-sm text-slate-500">Nenhum ativo hospedeiro cadastrado.</p>
                    @endforelse
                </div>
            </section>
        </div>

        <div class="grid gap-6 xl:grid-cols-2">
            <section class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                <h3 class="text-base font-semibold text-slate-900">Relacionamentos de rede / suporte</h3>
                <div class="mt-4 space-y-3">
                    @forelse($networkRelations as $relationship)
                        <div class="rounded-md border border-slate-200 p-3">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-semibold {{ $badgeClass($relationship->relationship_type) }}">{{ $relationshipLabels[$relationship->relationship_type] ?? $relationship->relationship_type }}</span>
                                <span class="text-sm text-slate-600">{{ $relationship->targetAsset?->name ?: '-' }}</span>
                            </div>
                        </div>
                    @empty
                        <p class="text-sm text-slate-500">Nenhum relacionamento adicional.</p>
                    @endforelse
                </div>
            </section>

            <section class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                <h3 class="text-base font-semibold text-slate-900">Relacionamentos de entrada</h3>
                <div class="mt-4 space-y-3">
                    @forelse($networkIncoming as $relationship)
                        <div class="rounded-md border border-slate-200 p-3">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-semibold {{ $badgeClass($relationship->relationship_type) }}">{{ $relationshipLabels[$relationship->relationship_type] ?? $relationship->relationship_type }}</span>
                                <span class="text-sm text-slate-600">{{ $relationship->sourceAsset?->name ?: '-' }}</span>
                            </div>
                        </div>
                    @empty
                        <p class="text-sm text-slate-500">Nenhum relacionamento adicional.</p>
                    @endforelse
                </div>
            </section>
        </div>
    </div>
</x-app-layout>
