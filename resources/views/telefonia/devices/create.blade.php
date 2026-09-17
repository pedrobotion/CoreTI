<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-gray-800">Novo aparelho</h2>
    </x-slot>

    <div class="mx-auto max-w-4xl space-y-6 px-6 py-8">
        <div>
            <p class="text-xs uppercase tracking-widest text-slate-500">Telefonia Corporativa</p>
            <h1 class="text-3xl font-semibold text-slate-900">Novo aparelho</h1>
        </div>

        @include('partials.modulo-flash')

        <form method="POST" action="{{ route('administrativo.telefonia.devices.store') }}" class="rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
            @csrf
            @include('telefonia.devices._form', ['temVinculoAtivo' => false])

            <div class="mt-6 flex justify-end gap-2 border-t border-slate-100 pt-5">
                <a href="{{ route('administrativo.telefonia.devices.index') }}" class="rounded-md border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Cancelar</a>
                <button class="rounded-md bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:opacity-90">Cadastrar aparelho</button>
            </div>
        </form>
    </div>
</x-app-layout>
