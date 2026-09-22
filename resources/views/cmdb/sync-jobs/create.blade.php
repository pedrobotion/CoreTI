<x-app-layout>
    <div class="mx-auto max-w-7xl space-y-6">
        @include('cmdb.partials._nav', ['title' => 'Novo job de sincronização', 'description' => 'Cadastro de execução manual ou agendada.'])

        <section class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
            @include('cmdb.sync-jobs._form', ['job' => $job, 'connectors' => $connectors, 'scheduleTypes' => $scheduleTypes, 'isEdit' => false])
        </section>
    </div>
</x-app-layout>
