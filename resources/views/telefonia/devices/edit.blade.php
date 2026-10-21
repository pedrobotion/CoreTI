<x-app-layout>
    <div class="mx-auto max-w-4xl space-y-6 px-4 py-8 sm:px-6">
        <div class="flex flex-wrap items-end justify-between gap-3">
            <div>
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Telefonia Corporativa</p>
                <h1 class="mt-1 text-2xl font-bold tracking-tight text-slate-900 sm:text-3xl">{{ $device->descricao }}</h1>
                <p class="mt-1 font-mono text-sm text-slate-500">{{ $device->imei_1_formatado }} · {{ $device->scope_label }}</p>
            </div>
            @if ($temHistorico)
                <span class="inline-flex items-center rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-600 ring-1 ring-inset ring-slate-200">Possui histórico de vínculo</span>
            @endif
        </div>

        @include('partials.modulo-flash')

        <form method="POST" action="{{ route('administrativo.telefonia.devices.update', $device) }}" class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            @csrf @method('PUT')
            @include('telefonia.devices._form')

            <div class="mt-6 flex justify-end gap-2 border-t border-slate-100 pt-5">
                <a href="{{ route('administrativo.telefonia.devices.index') }}" class="inline-flex min-h-10 items-center justify-center rounded-md border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-700 hover:bg-slate-50">Cancelar</a>
                <button style="background-color: #033151; border-color: #033151;" class="inline-flex min-h-10 items-center justify-center rounded-lg px-4 text-sm font-semibold text-white hover:opacity-90">Salvar alterações</button>
            </div>
        </form>

        @if ($temHistorico)
            <p class="text-xs text-slate-500">
                Este aparelho já esteve com alguém, então não pode ser excluído — o histórico do vínculo se perderia junto.
                Se ele saiu do parque, mude a situação para <span class="font-semibold">Baixado</span>.
            </p>
        @endif
    </div>
</x-app-layout>
