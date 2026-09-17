<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-gray-800">Nov{{ $ctx->itemLabel === 'Entrega' ? 'a' : 'o' }} {{ mb_strtolower($ctx->itemLabel) }}</h2>
    </x-slot>

    <div class="mx-auto max-w-4xl space-y-6 px-6 py-8">
        <div>
            <p class="text-xs uppercase tracking-widest text-slate-500">{{ $ctx->label }}</p>
            <h1 class="text-3xl font-semibold text-slate-900">Nov{{ $ctx->itemLabel === 'Entrega' ? 'a' : 'o' }} {{ mb_strtolower($ctx->itemLabel) }}</h1>
        </div>

        @include('partials.modulo-flash')

        <form method="POST" action="{{ $ctx->route('store') }}" class="rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
            @csrf
            @include('vinculos._form')

            <div class="mt-6 flex justify-end gap-2 border-t border-slate-100 pt-5">
                <a href="{{ $ctx->route('index') }}" class="rounded-md border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Cancelar</a>
                <button class="rounded-md bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:opacity-90">Registrar {{ mb_strtolower($ctx->itemLabel) }}</button>
            </div>
        </form>
    </div>
</x-app-layout>
