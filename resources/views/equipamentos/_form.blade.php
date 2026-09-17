@php
    $bloqueado = $temVinculoAtivo ?? false;
@endphp

<div x-data="{ unidade: @js(old('organization_unit_id', $equipment->organization_unit_id)) }" class="space-y-5">
    @if ($bloqueado)
        <div class="rounded-md border border-sky-200 bg-sky-50 px-4 py-3 text-sm text-sky-900">
            Este equipamento está com um colaborador. Situação e unidade ficam travadas até a devolução — os demais campos continuam editáveis.
        </div>
    @endif

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
        <div>
            <label for="tipo" class="mb-1 block text-sm font-medium text-slate-700">Tipo <span class="text-rose-600">*</span></label>
            <select id="tipo" name="tipo" required class="w-full rounded-md border-slate-300 text-sm">
                <option value="">Selecione</option>
                @foreach ($tipos as $tipo)
                    <option value="{{ $tipo }}" @selected(old('tipo', $equipment->tipo) === $tipo)>{{ $tipo }}</option>
                @endforeach
            </select>
            <x-input-error :messages="$errors->get('tipo')" class="mt-1" />
        </div>

        <div class="lg:col-span-2">
            <label for="nome" class="mb-1 block text-sm font-medium text-slate-700">Equipamento <span class="text-rose-600">*</span></label>
            <input id="nome" name="nome" value="{{ old('nome', $equipment->nome) }}" required maxlength="200" placeholder="Dell Optiplex 7070" class="w-full rounded-md border-slate-300 text-sm">
            <p class="mt-1 text-xs text-slate-500">Marca e modelo, como aparece na etiqueta.</p>
            <x-input-error :messages="$errors->get('nome')" class="mt-1" />
        </div>

        <div>
            <label for="patrimonio" class="mb-1 block text-sm font-medium text-slate-700">Patrimônio <span class="text-rose-600">*</span></label>
            <input id="patrimonio" name="patrimonio" value="{{ old('patrimonio', $equipment->patrimonio) }}" required maxlength="100" placeholder="023731" class="w-full rounded-md border-slate-300 font-mono text-sm">
            <p class="mt-1 text-xs text-slate-500">Único em todo o parque. Mantenha os zeros à esquerda.</p>
            <x-input-error :messages="$errors->get('patrimonio')" class="mt-1" />
        </div>

        <div>
            <label for="numero_serie" class="mb-1 block text-sm font-medium text-slate-700">Número de série</label>
            <input id="numero_serie" name="numero_serie" value="{{ old('numero_serie', $equipment->numero_serie) }}" maxlength="100" class="w-full rounded-md border-slate-300 text-sm">
            <x-input-error :messages="$errors->get('numero_serie')" class="mt-1" />
        </div>

        <div>
            <label for="endereco_ip" class="mb-1 block text-sm font-medium text-slate-700">Endereço IP</label>
            <input id="endereco_ip" name="endereco_ip" value="{{ old('endereco_ip', $equipment->endereco_ip) }}" maxlength="45" placeholder="172.17.3.10" class="w-full rounded-md border-slate-300 font-mono text-sm">
            <x-input-error :messages="$errors->get('endereco_ip')" class="mt-1" />
        </div>

        <div>
            <label for="organization_unit_id" class="mb-1 block text-sm font-medium text-slate-700">Unidade</label>
            <select id="organization_unit_id" name="organization_unit_id" x-model="unidade" @disabled($bloqueado) class="w-full rounded-md border-slate-300 text-sm disabled:bg-slate-100">
                <option value="">Sem unidade definida</option>
                @foreach ($unidades as $unidade)
                    <option value="{{ $unidade->id }}">{{ $unidade->name }}</option>
                @endforeach
            </select>
            @if ($bloqueado)
                <input type="hidden" name="organization_unit_id" value="{{ $equipment->organization_unit_id }}">
            @endif
            <p class="mt-1 text-xs text-slate-500">A operação (Sede ou Cerrado) é definida pela unidade.</p>
            <x-input-error :messages="$errors->get('organization_unit_id')" class="mt-1" />
        </div>

        <div>
            <label for="organization_department_id" class="mb-1 block text-sm font-medium text-slate-700">Departamento</label>
            <select id="organization_department_id" name="organization_department_id" class="w-full rounded-md border-slate-300 text-sm">
                <option value="">Sem departamento</option>
                @foreach ($departamentos as $departamento)
                    <option value="{{ $departamento->id }}" @selected((string) old('organization_department_id', $equipment->organization_department_id) === (string) $departamento->id)>{{ $departamento->name }}</option>
                @endforeach
            </select>
            <x-input-error :messages="$errors->get('organization_department_id')" class="mt-1" />
        </div>

        <div>
            <label for="centro_custo_codigo" class="mb-1 block text-sm font-medium text-slate-700">Centro de custo</label>
            <select id="centro_custo_codigo" name="centro_custo_codigo" class="w-full rounded-md border-slate-300 text-sm">
                <option value="">Sem centro de custo</option>
                @foreach ($centrosCusto as $centro)
                    <option value="{{ $centro->code }}" @selected(old('centro_custo_codigo', $equipment->centro_custo_codigo) === $centro->code)>{{ $centro->code }} — {{ $centro->name }}</option>
                @endforeach
            </select>
            <x-input-error :messages="$errors->get('centro_custo_codigo')" class="mt-1" />
        </div>

        <div>
            <label for="status" class="mb-1 block text-sm font-medium text-slate-700">Situação <span class="text-rose-600">*</span></label>
            <select id="status" name="status" @disabled($bloqueado) required class="w-full rounded-md border-slate-300 text-sm disabled:bg-slate-100">
                @foreach ($statuses as $status)
                    <option value="{{ $status }}" @selected(old('status', $equipment->status) === $status)>{{ $status }}</option>
                @endforeach
            </select>
            @if ($bloqueado)
                <input type="hidden" name="status" value="{{ $equipment->status }}">
            @endif
            <x-input-error :messages="$errors->get('status')" class="mt-1" />
        </div>

        {{-- Sem unidade não há de onde deduzir a operação, então ela é escolhida. --}}
        <div x-show="!unidade" x-cloak>
            @if (count($scopeOptions) > 1)
                <label for="scope" class="mb-1 block text-sm font-medium text-slate-700">Operação <span class="text-rose-600">*</span></label>
                <select id="scope" name="scope" class="w-full rounded-md border-slate-300 text-sm">
                    @foreach ($scopeOptions as $valor => $rotulo)
                        <option value="{{ $valor }}" @selected(old('scope', $equipment->scope) === $valor)>{{ $rotulo }}</option>
                    @endforeach
                </select>
                <p class="mt-1 text-xs text-slate-500">Necessária porque nenhuma unidade foi escolhida.</p>
                <x-input-error :messages="$errors->get('scope')" class="mt-1" />
            @else
                <input type="hidden" name="scope" value="{{ array_key_first($scopeOptions) }}">
            @endif
        </div>

        <div class="lg:col-span-2">
            <label for="localizacao" class="mb-1 block text-sm font-medium text-slate-700">Localização</label>
            <input id="localizacao" name="localizacao" value="{{ old('localizacao', $equipment->localizacao) }}" maxlength="255" placeholder="Bancada da TI, emprestado para..." class="w-full rounded-md border-slate-300 text-sm">
            <p class="mt-1 text-xs text-slate-500">Para quando o equipamento não está numa unidade: bancada, empréstimo, remessa em trânsito.</p>
            <x-input-error :messages="$errors->get('localizacao')" class="mt-1" />
        </div>
    </div>

    <div>
        <label for="observacoes" class="mb-1 block text-sm font-medium text-slate-700">Observações</label>
        <textarea id="observacoes" name="observacoes" rows="3" maxlength="2000" class="w-full rounded-md border-slate-300 text-sm">{{ old('observacoes', $equipment->observacoes) }}</textarea>
        <x-input-error :messages="$errors->get('observacoes')" class="mt-1" />
    </div>
</div>
