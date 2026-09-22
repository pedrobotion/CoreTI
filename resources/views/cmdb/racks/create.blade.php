<x-app-layout>
    <div class="mx-auto max-w-7xl space-y-6">
        @include('cmdb.partials._nav', ['title' => 'Novo rack', 'description' => 'Cadastro de rack do CMDB.'])

        @include('cmdb.racks._form', ['rack' => $rack, 'unitOptions' => $unitOptions])
    </div>
</x-app-layout>
