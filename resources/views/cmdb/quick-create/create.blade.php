<x-app-layout>
    <div class="mx-auto max-w-7xl space-y-6">
        @include('cmdb.partials._nav', ['title' => 'Cadastro Rápido', 'description' => 'Criação simplificada de ativo.'])

        @if($fromDiscovery ?? false)
            <div class="rounded-lg border border-blue-200 bg-blue-50 px-4 py-3 text-sm text-blue-800">
                Dados pré-preenchidos a partir de uma descoberta SNMP. Revise antes de salvar.
            </div>
        @endif

        <section class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
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
                'action' => route('cmdb.quick-create.store'),
                'submitLabel' => 'Salvar ativo',
            ])
        </section>
    </div>
</x-app-layout>
