<x-app-layout>
    <div class="mx-auto max-w-7xl space-y-6">
        @include('cmdb.partials._nav', ['title' => 'Duplicar ativo', 'description' => 'Clone os dados do ativo, ajustando o que for necessário.'])

        <section class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
            @if(!empty($sourceAsset))
                <div class="mb-4 rounded-lg border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-600">
                    Fonte da duplicação: <strong class="text-slate-900">{{ $sourceAsset->name }}</strong>
                </div>
            @endif

            @include('cmdb.quick-create._form', [
                'prefill' => $prefill,
                'assetTypes' => $assetTypes,
                'statusOptions' => $statusOptions,
                'criticalityOptions' => $criticalityOptions,
                'environmentOptions' => $environmentOptions,
                'manufacturers' => $manufacturers,
                'models' => $models,
                'racks' => $racks,
                'unitOptions' => $unitOptions,
                'connectors' => $connectors,
                'services' => $services,
                'sourceAsset' => $sourceAsset,
                'action' => route('cmdb.assets.duplicate.store', $sourceAsset),
                'submitLabel' => 'Duplicar ativo',
            ])
        </section>
    </div>
</x-app-layout>
