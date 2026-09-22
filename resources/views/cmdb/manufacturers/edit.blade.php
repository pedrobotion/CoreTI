<x-app-layout>
    <div class="mx-auto max-w-7xl space-y-6">
        @include('cmdb.partials._nav', ['title' => 'Editar fabricante', 'description' => 'Atualização do fabricante do CMDB.'])

        <section class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
            @include('cmdb.manufacturers._form', ['manufacturer' => $manufacturer])
        </section>
    </div>
</x-app-layout>
