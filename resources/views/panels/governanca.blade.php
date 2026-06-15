<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            Governança
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            @php
                $options = [
                    ['title' => 'Visão geral', 'href' => route('governanca.visao-geral')],
                    ['title' => 'Políticas', 'href' => route('governanca.politicas')],
                    ['title' => 'Auditorias', 'href' => route('governanca.auditorias')],
                    ['title' => 'Riscos', 'href' => route('governanca.riscos')],
                ];
            @endphp

            @include('partials.panel-dropdown', ['label' => 'Governança', 'options' => $options])
        </div>
    </div>
</x-app-layout>
