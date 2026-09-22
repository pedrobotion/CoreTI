<x-app-layout>
    <div class="mx-auto max-w-7xl space-y-6">
        @include('cmdb.partials._nav', ['title' => 'Novo conector', 'description' => 'Cadastro de conexão para descoberta e sincronização do CMDB.'])

        <section class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
            @include('cmdb.connectors._form', ['connector' => $connector, 'types' => $types, 'statuses' => $statuses, 'isEdit' => false])
        </section>
    </div>
</x-app-layout>
