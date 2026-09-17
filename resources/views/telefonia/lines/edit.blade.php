<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-gray-800">Editar linha</h2>
    </x-slot>

    <div class="mx-auto max-w-4xl space-y-6 px-6 py-8">
        <div class="flex flex-wrap items-end justify-between gap-3">
            <div>
                <p class="text-xs uppercase tracking-widest text-slate-500">Telefonia Corporativa</p>
                <h1 class="text-3xl font-semibold text-slate-900">{{ $line->numero_formatado }}</h1>
                <p class="mt-1 text-sm text-slate-500">{{ $line->operadora }} · {{ $line->scope_label }}</p>
            </div>
            @if ($temHistorico)
                <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-600">Possui histórico de vínculo</span>
            @endif
        </div>

        @include('partials.modulo-flash')

        <form method="POST" action="{{ route('administrativo.telefonia.lines.update', $line) }}" class="rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
            @csrf @method('PUT')
            @include('telefonia.lines._form')

            <div class="mt-6 flex justify-end gap-2 border-t border-slate-100 pt-5">
                <a href="{{ route('administrativo.telefonia.lines.index') }}" class="rounded-md border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Cancelar</a>
                <button class="rounded-md bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:opacity-90">Salvar alterações</button>
            </div>
        </form>

        @if ($temHistorico)
            <p class="text-xs text-slate-500">
                Esta linha já esteve vinculada a alguém, então não pode ser excluída — o histórico do vínculo se perderia junto.
                Se ela saiu do contrato, mude a situação para <span class="font-semibold">Cancelada</span>.
            </p>
        @endif
    </div>
</x-app-layout>
