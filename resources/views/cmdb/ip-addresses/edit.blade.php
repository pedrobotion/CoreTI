<x-app-layout>
    <div class="mx-auto max-w-7xl space-y-6">
        @include('cmdb.partials._nav', ['title' => 'Editar endereço IP', 'description' => 'Atualização de IP por ativo.'])

        @include('cmdb.ip-addresses._form', ['assets' => $assets, 'asset' => $asset, 'ipAddress' => $ipAddress])
    </div>
</x-app-layout>
