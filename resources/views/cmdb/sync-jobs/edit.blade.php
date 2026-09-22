<x-app-layout>
    <div class="mx-auto max-w-7xl space-y-6">
        @include('cmdb.partials._nav', ['title' => 'Editar job de sincronização', 'description' => 'Ajuste do job e do conector vinculado.'])

        <section class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
            @include('cmdb.sync-jobs._form', ['job' => $job, 'connectors' => $connectors, 'scheduleTypes' => $scheduleTypes, 'isEdit' => true])
        </section>
    </div>
</x-app-layout>
