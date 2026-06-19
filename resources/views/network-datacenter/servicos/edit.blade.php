<x-app-layout>
    <div class="mx-auto max-w-7xl space-y-6">
        <div>
            <p class="text-sm font-semibold uppercase tracking-wide text-slate-500">Network &amp; DataCenter</p>
            <h1 class="mt-1 text-2xl font-bold text-slate-950">Editar Serviço</h1>
        </div>

        @include('network-datacenter.servicos._form', ['mode' => 'edit'])
    </div>
</x-app-layout>
