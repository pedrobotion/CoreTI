<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-gray-800">Editar equipamento</h2>
    </x-slot>

    <div class="mx-auto max-w-5xl space-y-6 px-6 py-8">
        <div class="flex flex-wrap items-end justify-between gap-3">
            <div>
                <p class="text-xs uppercase tracking-widest text-slate-500">ServiceDesk · Equipamentos</p>
                <h1 class="text-3xl font-semibold text-slate-900">{{ $equipment->nome }}</h1>
                <p class="mt-1 text-sm text-slate-500">
                    Patrimônio <span class="font-mono font-semibold">{{ $equipment->patrimonio }}</span>
                    · {{ $equipment->local }} · {{ $equipment->scope_label }}
                </p>
            </div>
            @if ($temHistorico)
                <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-600">Possui histórico de entrega</span>
            @endif
        </div>

        @include('partials.modulo-flash')

        <form method="POST" action="{{ route('service-desk.equipamentos.update', $equipment) }}" class="rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
            @csrf @method('PUT')
            @include('equipamentos._form')

            <div class="mt-6 flex justify-end gap-2 border-t border-slate-100 pt-5">
                <a href="{{ route('service-desk.equipamentos.index') }}" class="rounded-md border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Cancelar</a>
                <button class="rounded-md bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:opacity-90">Salvar alterações</button>
            </div>
        </form>

        @if ($temHistorico)
            <p class="text-xs text-slate-500">
                Este equipamento já foi entregue a alguém, então não pode ser excluído — o histórico se perderia junto.
                Se ele saiu do parque, mude a situação para <span class="font-semibold">Baixado</span>.
            </p>
        @endif
    </div>
</x-app-layout>
