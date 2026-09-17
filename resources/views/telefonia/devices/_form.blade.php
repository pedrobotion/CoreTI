@php
    $bloqueado = $temVinculoAtivo ?? false;
@endphp

<div class="space-y-5">
    @if ($bloqueado)
        <div class="rounded-md border border-sky-200 bg-sky-50 px-4 py-3 text-sm text-sky-900">
            Este aparelho está com um colaborador. Situação e operação ficam travadas até o desvínculo — os demais campos continuam editáveis.
        </div>
    @endif

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
        @if (count($scopeOptions) > 1)
            <div>
                <label for="scope" class="mb-1 block text-sm font-medium text-slate-700">Operação <span class="text-rose-600">*</span></label>
                <select id="scope" name="scope" @disabled($bloqueado) class="w-full rounded-md border-slate-300 text-sm disabled:bg-slate-100">
                    @foreach ($scopeOptions as $valor => $rotulo)
                        <option value="{{ $valor }}" @selected(old('scope', $device->scope) === $valor)>{{ $rotulo }}</option>
                    @endforeach
                </select>
                @if ($bloqueado)
                    <input type="hidden" name="scope" value="{{ $device->scope }}">
                @endif
                <x-input-error :messages="$errors->get('scope')" class="mt-1" />
            </div>
        @else
            <input type="hidden" name="scope" value="{{ array_key_first($scopeOptions) }}">
        @endif

        <div>
            <label for="marca" class="mb-1 block text-sm font-medium text-slate-700">Marca <span class="text-rose-600">*</span></label>
            <input id="marca" name="marca" value="{{ old('marca', $device->marca) }}" required maxlength="100" placeholder="Samsung" class="w-full rounded-md border-slate-300 text-sm">
            <x-input-error :messages="$errors->get('marca')" class="mt-1" />
        </div>

        <div>
            <label for="modelo" class="mb-1 block text-sm font-medium text-slate-700">Modelo <span class="text-rose-600">*</span></label>
            <input id="modelo" name="modelo" value="{{ old('modelo', $device->modelo) }}" required maxlength="100" placeholder="Galaxy A15" class="w-full rounded-md border-slate-300 text-sm">
            <x-input-error :messages="$errors->get('modelo')" class="mt-1" />
        </div>

        <div>
            <label for="imei_1" class="mb-1 block text-sm font-medium text-slate-700">IMEI 1</label>
            <input id="imei_1" name="imei_1" value="{{ old('imei_1', $device->imei_1) }}" inputmode="numeric" maxlength="20" placeholder="354833764140550" class="w-full rounded-md border-slate-300 font-mono text-sm">
            <p class="mt-1 text-xs text-slate-500">Pode digitar com espaços; só os dígitos são gravados. Deixe em branco se o aparelho for antigo e o número não estiver disponível.</p>
            <x-input-error :messages="$errors->get('imei_1')" class="mt-1" />
        </div>

        <div>
            <label for="imei_2" class="mb-1 block text-sm font-medium text-slate-700">IMEI 2</label>
            <input id="imei_2" name="imei_2" value="{{ old('imei_2', $device->imei_2) }}" inputmode="numeric" maxlength="20" class="w-full rounded-md border-slate-300 font-mono text-sm">
            <p class="mt-1 text-xs text-slate-500">Para aparelhos com dois chips.</p>
            <x-input-error :messages="$errors->get('imei_2')" class="mt-1" />
        </div>

        <div>
            <label for="numero_serie" class="mb-1 block text-sm font-medium text-slate-700">Número de série</label>
            <input id="numero_serie" name="numero_serie" value="{{ old('numero_serie', $device->numero_serie) }}" maxlength="50" class="w-full rounded-md border-slate-300 text-sm">
            <x-input-error :messages="$errors->get('numero_serie')" class="mt-1" />
        </div>

        <div>
            <label for="estado_aparelho" class="mb-1 block text-sm font-medium text-slate-700">Estado do aparelho <span class="text-rose-600">*</span></label>
            <select id="estado_aparelho" name="estado_aparelho" required class="w-full rounded-md border-slate-300 text-sm">
                @foreach ($estados as $estado)
                    <option value="{{ $estado }}" @selected(old('estado_aparelho', $device->estado_aparelho) === $estado)>{{ $estado }}</option>
                @endforeach
            </select>
            <p class="mt-1 text-xs text-slate-500">"Novo" é aparelho que nunca foi usado por ninguém.</p>
            <x-input-error :messages="$errors->get('estado_aparelho')" class="mt-1" />
        </div>

        <div>
            <label for="status" class="mb-1 block text-sm font-medium text-slate-700">Situação <span class="text-rose-600">*</span></label>
            <select id="status" name="status" @disabled($bloqueado) required class="w-full rounded-md border-slate-300 text-sm disabled:bg-slate-100">
                @foreach ($statuses as $status)
                    <option value="{{ $status }}" @selected(old('status', $device->status) === $status)>{{ $status }}</option>
                @endforeach
            </select>
            @if ($bloqueado)
                <input type="hidden" name="status" value="{{ $device->status }}">
            @endif
            <x-input-error :messages="$errors->get('status')" class="mt-1" />
        </div>
    </div>

    <div>
        <label for="observacao" class="mb-1 block text-sm font-medium text-slate-700">Observação</label>
        <textarea id="observacao" name="observacao" rows="3" maxlength="2000" class="w-full rounded-md border-slate-300 text-sm">{{ old('observacao', $device->observacao) }}</textarea>
        <x-input-error :messages="$errors->get('observacao')" class="mt-1" />
    </div>
</div>
