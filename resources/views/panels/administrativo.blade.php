<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            Administrativo
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            @php
                $options = [
                    ['title' => 'Visão geral', 'href' => route('administrativo.visao-geral')],
                    ['title' => 'Relatórios', 'href' => route('administrativo.relatorios')],
                    ['title' => 'Configurações', 'href' => route('administrativo.configuracoes')],
                    ['title' => 'Solicitações', 'href' => route('administrativo.solicitacoes')],
                ];
            @endphp

            @include('partials.panel-dropdown', ['label' => 'Administrativo', 'options' => $options])
        </div>
    </div>
</x-app-layout>
