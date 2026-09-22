<x-app-layout>
    <div class="mx-auto max-w-7xl space-y-6">
        @include('cmdb.partials._nav', ['title' => 'Novo endereço IP', 'description' => 'Cadastro de IP por ativo.'])

        @include('cmdb.ip-addresses._form', ['assets' => $assets, 'asset' => $asset, 'ipAddress' => $ipAddress])
    </div>
</x-app-layout>
