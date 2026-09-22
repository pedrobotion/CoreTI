@php
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
@endphp

<x-app-layout>
    <div class="mx-auto max-w-7xl space-y-6">
        @include('cmdb.partials._nav', ['title' => 'Diagrama do Ativo', 'description' => 'Visão simples dos relacionamentos do ativo.'])

        <div class="flex flex-wrap gap-2">
            <a href="{{ route('cmdb.assets.show', $asset) }}" class="inline-flex min-h-10 items-center justify-center rounded-md bg-[#033151] px-4 text-sm font-semibold text-white hover:opacity-90">Voltar ao ativo</a>
            <a href="{{ route('cmdb.assets.dependencies', $asset) }}" class="inline-flex min-h-10 items-center justify-center rounded-md border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-700 hover:bg-slate-50">Dependências</a>
        </div>

        <section class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
            <div class="flex flex-wrap items-center gap-2">
                <h2 class="text-lg font-semibold text-slate-900">{{ $asset->name }}</h2>
                <span class="inline-flex rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-semibold text-slate-700">{{ $types[$asset->asset_type] ?? $asset->asset_type }}</span>
            </div>
            <p class="mt-2 text-sm text-slate-500">O diagrama abaixo é uma visão operacional simples, baseada na tabela de relacionamentos do CMDB.</p>
        </section>

        <div class="grid gap-6 xl:grid-cols-3">
            <section class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                <h3 class="text-base font-semibold text-slate-900">Ativo principal</h3>
                <div class="mt-4 rounded-md border border-slate-200 bg-slate-50 p-4">
                    <div class="font-semibold text-slate-900">{{ $asset->name }}</div>
                    <div class="text-sm text-slate-500">{{ $asset->hostname ?: 'Sem hostname' }}</div>
                </div>
            </section>

            <section class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                <h3 class="text-base font-semibold text-slate-900">Relacionamentos de saída</h3>
                <div class="mt-4 space-y-3">
                    @forelse($asset->outgoingRelationships as $relationship)
                        <div class="rounded-md border border-slate-200 p-3">
                            <span class="inline-flex rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-semibold text-slate-700">{{ $relationshipLabels[$relationship->relationship_type] ?? $relationship->relationship_type }}</span>
                            <div class="mt-2">
                                @if($relationship->targetAsset)
                                    <a href="{{ route('cmdb.assets.show', $relationship->targetAsset) }}" class="font-semibold text-[#033151] hover:underline">{{ $relationship->targetAsset->name }}</a>
                                @else
                                    <span class="text-slate-500">Sem ativo relacionado</span>
                                @endif
                            </div>
                        </div>
                    @empty
                        <p class="text-sm text-slate-500">Nenhum relacionamento de saída.</p>
                    @endforelse
                </div>
            </section>

            <section class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                <h3 class="text-base font-semibold text-slate-900">Relacionamentos de entrada</h3>
                <div class="mt-4 space-y-3">
                    @forelse($asset->incomingRelationships as $relationship)
                        <div class="rounded-md border border-slate-200 p-3">
                            <span class="inline-flex rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-semibold text-slate-700">{{ $relationshipLabels[$relationship->relationship_type] ?? $relationship->relationship_type }}</span>
                            <div class="mt-2">
                                @if($relationship->sourceAsset)
                                    <a href="{{ route('cmdb.assets.show', $relationship->sourceAsset) }}" class="font-semibold text-[#033151] hover:underline">{{ $relationship->sourceAsset->name }}</a>
                                @else
                                    <span class="text-slate-500">Sem ativo relacionado</span>
                                @endif
                            </div>
                        </div>
                    @empty
                        <p class="text-sm text-slate-500">Nenhum relacionamento de entrada.</p>
                    @endforelse
                </div>
            </section>
        </div>
    </div>
</x-app-layout>
