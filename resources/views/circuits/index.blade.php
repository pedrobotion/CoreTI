<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Circuitos') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
            @php
                $options = [
                    ['title' => 'Circuitos Unidades', 'href' => route('circuits.units')],
                    ['title' => 'Consultar Chamado - Ligga', 'href' => route('circuits.ligga')],
                    ['title' => 'Consultar Chamado - Embratel', 'href' => route('circuits.embratel')],
                    ['title' => 'Consultar Chamado - OI', 'href' => route('circuits.oi')],
                ];
            @endphp

            @include('partials.panel-dropdown', ['label' => 'Circuitos', 'options' => $options])
        </div>
    </div>
</x-app-layout>
