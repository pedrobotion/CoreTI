@php
    $bloqueado = $temVinculoAtivo ?? false;
@endphp

<div x-data="{ status: @js(old('status', $line->status ?? \App\Models\CorpLine::STATUS_DISPONIVEL)) }" class="space-y-5">
    @if ($bloqueado)
        <div class="rounded-md border border-sky-200 bg-sky-50 px-4 py-3 text-sm text-sky-900">
            Esta linha está vinculada a um colaborador. Situação e operação ficam travadas até o desvínculo — plano, valor e observação continuam editáveis.
        </div>
    @endif

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
        @if (count($scopeOptions) > 1)
            <div>
                <label for="scope" class="mb-1 block text-sm font-medium text-slate-700">Operação <span class="text-rose-600">*</span></label>
                <select id="scope" name="scope" @disabled($bloqueado) class="w-full rounded-md border-slate-300 text-sm disabled:bg-slate-100">
                    @foreach ($scopeOptions as $valor => $rotulo)
                        <option value="{{ $valor }}" @selected(old('scope', $line->scope) === $valor)>{{ $rotulo }}</option>
                    @endforeach
                </select>
                @if ($bloqueado)
                    <input type="hidden" name="scope" value="{{ $line->scope }}">
                @endif
                <x-input-error :messages="$errors->get('scope')" class="mt-1" />
            </div>
        @else
            {{-- Um escopo só: não há escolha a fazer, o campo só ocuparia espaço. --}}
            <input type="hidden" name="scope" value="{{ array_key_first($scopeOptions) }}">
        @endif

        <div>
            <label for="operadora" class="mb-1 block text-sm font-medium text-slate-700">Operadora <span class="text-rose-600">*</span></label>
            <select id="operadora" name="operadora" required class="w-full rounded-md border-slate-300 text-sm">
                <option value="">Selecione</option>
                @foreach ($operadoras as $operadora)
                    <option value="{{ $operadora }}" @selected(old('operadora', $line->operadora) === $operadora)>{{ $operadora }}</option>
                @endforeach
            </select>
            <x-input-error :messages="$errors->get('operadora')" class="mt-1" />
        </div>

        <div class="grid grid-cols-3 gap-2">
            <div>
                <label for="ddd" class="mb-1 block text-sm font-medium text-slate-700">DDD <span class="text-rose-600">*</span></label>
                <input id="ddd" name="ddd" value="{{ old('ddd', $line->ddd) }}" required maxlength="2" inputmode="numeric" placeholder="44" class="w-full rounded-md border-slate-300 text-sm">
                <x-input-error :messages="$errors->get('ddd')" class="mt-1" />
            </div>
            <div class="col-span-2">
                <label for="numero" class="mb-1 block text-sm font-medium text-slate-700">Número <span class="text-rose-600">*</span></label>
                <input id="numero" name="numero" value="{{ old('numero', $line->numero) }}" required maxlength="9" inputmode="numeric" placeholder="997540714" class="w-full rounded-md border-slate-300 text-sm">
                <p class="mt-1 text-xs text-slate-500">Sem o DDD. 9 dígitos para celular, 8 para fixo.</p>
                <x-input-error :messages="$errors->get('numero')" class="mt-1" />
            </div>
        </div>

        <div>
            <label for="status" class="mb-1 block text-sm font-medium text-slate-700">Situação <span class="text-rose-600">*</span></label>
            <select id="status" name="status" x-model="status" @disabled($bloqueado) required class="w-full rounded-md border-slate-300 text-sm disabled:bg-slate-100">
                @foreach ($statuses as $status)
                    <option value="{{ $status }}">{{ $status }}</option>
                @endforeach
            </select>
            @if ($bloqueado)
                <input type="hidden" name="status" value="{{ $line->status }}">
            @endif
            <x-input-error :messages="$errors->get('status')" class="mt-1" />
        </div>

        <div x-show="status === @js(\App\Models\CorpLine::STATUS_CANCELADA)" x-cloak>
            <label for="data_cancelamento" class="mb-1 block text-sm font-medium text-slate-700">Cancelada em <span class="text-rose-600">*</span></label>
            <input id="data_cancelamento" name="data_cancelamento" type="date" value="{{ old('data_cancelamento', $line->data_cancelamento?->format('Y-m-d')) }}" class="w-full rounded-md border-slate-300 text-sm">
            <x-input-error :messages="$errors->get('data_cancelamento')" class="mt-1" />
        </div>

        <div>
            <label for="nome_plano" class="mb-1 block text-sm font-medium text-slate-700">Plano</label>
            <input id="nome_plano" name="nome_plano" value="{{ old('nome_plano', $line->nome_plano) }}" maxlength="255" class="w-full rounded-md border-slate-300 text-sm">
            <x-input-error :messages="$errors->get('nome_plano')" class="mt-1" />
        </div>

        <div>
            <label for="valor_mensal" class="mb-1 block text-sm font-medium text-slate-700">Valor mensal (R$)</label>
            <input id="valor_mensal" name="valor_mensal" type="number" step="0.01" min="0" value="{{ old('valor_mensal', $line->valor_mensal) }}" class="w-full rounded-md border-slate-300 text-sm">
            <x-input-error :messages="$errors->get('valor_mensal')" class="mt-1" />
        </div>
    </div>

    <div>
        <label for="observacao" class="mb-1 block text-sm font-medium text-slate-700">Observação</label>
        <textarea id="observacao" name="observacao" rows="3" maxlength="2000" class="w-full rounded-md border-slate-300 text-sm">{{ old('observacao', $line->observacao) }}</textarea>
        <x-input-error :messages="$errors->get('observacao')" class="mt-1" />
    </div>
</div>
