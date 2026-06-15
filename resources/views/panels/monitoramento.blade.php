<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            Monitoramento
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            @php
                $options = [
                    ['title' => 'Visão geral', 'href' => route('monitoramento.visao-geral')],
                    ['title' => 'Alertas', 'href' => route('monitoramento.alertas')],
                    ['title' => 'Disponibilidade', 'href' => route('monitoramento.disponibilidade')],
                    ['title' => 'Incidentes', 'href' => route('monitoramento.incidentes')],
                ];
            @endphp

            @include('partials.panel-dropdown', ['label' => 'Monitoramento', 'options' => $options])
        </div>
    </div>
</x-app-layout>
