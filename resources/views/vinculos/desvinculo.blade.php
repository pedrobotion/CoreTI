<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-gray-800">Registrar devolução</h2>
    </x-slot>

    <div class="mx-auto max-w-3xl space-y-6 px-6 py-8">
        <div>
            <p class="text-xs uppercase tracking-widest text-slate-500">{{ $ctx->label }}</p>
            <h1 class="text-3xl font-semibold text-slate-900">Registrar devolução</h1>
            <p class="mt-1 text-sm text-slate-500">{{ $assignment->collaborator?->name }} · matrícula {{ $assignment->collaborator_matricula }}</p>
        </div>

        @include('partials.modulo-flash')

        <section class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="text-sm font-semibold uppercase tracking-wide text-slate-500">Itens que voltam ao estoque</h2>
            <ul class="mt-3 divide-y divide-slate-100">
                @foreach ($assignment->items as $item)
                    <li class="py-2">
                        <p class="text-sm text-slate-900">{{ $item->descricao() }}</p>
                        <p class="text-xs text-slate-500">{{ $item->tipoLabel() }}</p>
                    </li>
                @endforeach
            </ul>
            <p class="mt-3 text-xs text-slate-500">
                Encerrar este registro libera <span class="font-semibold">todos</span> os itens acima — o termo era um só e está sendo devolvido por inteiro.
                Uma linha que ainda tenha outro vínculo ativo continua como "Em Uso".
            </p>
        </section>

        <form method="POST" action="{{ $ctx->route('desvincular', $assignment) }}" class="rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
            @csrf

            <div>
                <label for="motivo_desvinculo" class="mb-1 block text-sm font-medium text-slate-700">Motivo <span class="text-rose-600">*</span></label>
                <select id="motivo_desvinculo" name="motivo_desvinculo" required class="w-full rounded-md border-slate-300 text-sm">
                    <option value="">Selecione</option>
                    @foreach ($motivos as $motivo)
                        <option value="{{ $motivo }}" @selected(old('motivo_desvinculo') === $motivo)>{{ $motivo }}</option>
                    @endforeach
                </select>
                <x-input-error :messages="$errors->get('motivo_desvinculo')" class="mt-1" />
            </div>

            <div class="mt-4">
                <label for="observacoes_desvinculo" class="mb-1 block text-sm font-medium text-slate-700">Observações</label>
                <textarea id="observacoes_desvinculo" name="observacoes_desvinculo" rows="3" maxlength="1000"
                          placeholder="Estado do item na devolução, pendências, quem recebeu…"
                          class="w-full rounded-md border-slate-300 text-sm">{{ old('observacoes_desvinculo') }}</textarea>
                <x-input-error :messages="$errors->get('observacoes_desvinculo')" class="mt-1" />
            </div>

            <div class="mt-6 flex justify-end gap-2 border-t border-slate-100 pt-5">
                <a href="{{ $ctx->route('show', $assignment) }}" class="rounded-md border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Cancelar</a>
                <button class="rounded-md bg-rose-700 px-4 py-2 text-sm font-semibold text-white hover:opacity-90">Confirmar devolução</button>
            </div>
        </form>
    </div>
</x-app-layout>
