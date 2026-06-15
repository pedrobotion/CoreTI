<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            Indicadores
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            @php
                $options = [
                    ['title' => 'Visão geral', 'href' => route('indicadores.visao-geral')],
                    ['title' => 'Tendências', 'href' => route('indicadores.tendencias')],
                    ['title' => 'Metas', 'href' => route('indicadores.metas')],
                    ['title' => 'Exportação', 'href' => route('indicadores.exportacao')],
                ];
            @endphp

            @include('partials.panel-dropdown', ['label' => 'Indicadores', 'options' => $options])
        </div>
    </div>
</x-app-layout>
