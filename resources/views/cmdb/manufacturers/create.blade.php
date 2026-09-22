<x-app-layout>
    <div class="mx-auto max-w-7xl space-y-6">
        @include('cmdb.partials._nav', ['title' => 'Novo fabricante', 'description' => 'Cadastro de fabricante do CMDB.'])

        <section class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
            @include('cmdb.manufacturers._form', ['manufacturer' => $manufacturer])
        </section>
    </div>
</x-app-layout>
