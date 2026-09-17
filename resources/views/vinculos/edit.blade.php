<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-gray-800">Editar {{ mb_strtolower($ctx->itemLabel) }}</h2>
    </x-slot>

    <div class="mx-auto max-w-4xl space-y-6 px-6 py-8">
        <div>
            <p class="text-xs uppercase tracking-widest text-slate-500">{{ $ctx->label }}</p>
            <h1 class="text-3xl font-semibold text-slate-900">Editar {{ mb_strtolower($ctx->itemLabel) }}</h1>
            <p class="mt-1 text-sm text-slate-500">{{ $assignment->collaborator?->name }} · entregue em {{ $assignment->data_vinculo?->format('d/m/Y') }}</p>
        </div>

        @include('partials.modulo-flash')

        @if ($assignment->temTermoAssinado())
            <div class="rounded-md border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
                Há um termo assinado anexado. Trocar item, colaborador ou data <span class="font-semibold">invalida a assinatura</span> —
                o papel deixaria de descrever o que está entregue — e será necessário gerar e anexar uma nova via.
            </div>
        @endif

        <form method="POST" action="{{ $ctx->route('update', $assignment) }}" class="rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
            @csrf @method('PUT')
            @include('vinculos._form')

            <div class="mt-6 flex justify-end gap-2 border-t border-slate-100 pt-5">
                <a href="{{ $ctx->route('show', $assignment) }}" class="rounded-md border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Cancelar</a>
                <button class="rounded-md bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:opacity-90">Salvar alterações</button>
            </div>
        </form>
    </div>
</x-app-layout>
