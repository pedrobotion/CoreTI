<x-app-layout>
    <div class="mx-auto max-w-7xl space-y-6">
        @include('cmdb.partials._nav', ['title' => 'Editar modelo', 'description' => 'Atualização do modelo do CMDB.'])

        <section class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
            @include('cmdb.models._form', ['model' => $model, 'manufacturers' => $manufacturers])
        </section>
    </div>
</x-app-layout>
