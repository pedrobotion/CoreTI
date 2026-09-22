<x-app-layout>
    <div class="mx-auto max-w-7xl space-y-6">
        @include('cmdb.partials._nav', ['title' => 'Editar rack', 'description' => 'Atualização do rack do CMDB.'])

        @include('cmdb.racks._form', ['rack' => $rack, 'unitOptions' => $unitOptions])
    </div>
</x-app-layout>
