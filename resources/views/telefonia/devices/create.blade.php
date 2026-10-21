<x-app-layout>
    <div class="mx-auto max-w-4xl space-y-6 px-4 py-8 sm:px-6">
        <div>
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Telefonia Corporativa</p>
            <h1 class="mt-1 text-2xl font-bold tracking-tight text-slate-900 sm:text-3xl">Novo aparelho</h1>
            <p class="mt-1 text-sm text-slate-500">Cadastre um aparelho no parque de telefonia.</p>
        </div>

        @include('partials.modulo-flash')

        <form method="POST" action="{{ route('administrativo.telefonia.devices.store') }}" class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            @csrf
            @include('telefonia.devices._form', ['temVinculoAtivo' => false])

            <div class="mt-6 flex justify-end gap-2 border-t border-slate-100 pt-5">
                <a href="{{ route('administrativo.telefonia.devices.index') }}" class="inline-flex min-h-10 items-center justify-center rounded-md border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-700 hover:bg-slate-50">Cancelar</a>
                <button style="background-color: #033151; border-color: #033151;" class="inline-flex min-h-10 items-center justify-center rounded-lg px-4 text-sm font-semibold text-white hover:opacity-90">Cadastrar aparelho</button>
            </div>
        </form>
    </div>
</x-app-layout>
