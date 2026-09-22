@php $selecionados = $profile->exists ? $profile->items->pluck('task_type')->all() : []; @endphp

<div class="grid gap-4 md:grid-cols-4">
    <div class="md:col-span-2">
        <label class="block text-xs font-semibold text-slate-600">Nome *</label>
        <input type="text" name="nome" value="{{ $profile->nome }}" required class="mt-1 w-full rounded-md border-slate-300 text-sm">
    </div>
    <div>
        <label class="block text-xs font-semibold text-slate-600">Prioridade</label>
        <input type="number" name="prioridade" value="{{ $profile->prioridade ?? 0 }}" min="0" max="999" class="mt-1 w-full rounded-md border-slate-300 text-sm">
    </div>
    <div class="flex items-end gap-4">
        <label class="inline-flex items-center gap-2 text-xs text-slate-600">
            <input type="hidden" name="cumulativo" value="0">
            <input type="checkbox" name="cumulativo" value="1" @checked($profile->cumulativo)> Cumulativo
        </label>
        <label class="inline-flex items-center gap-2 text-xs text-slate-600">
            <input type="hidden" name="ativo" value="0">
            <input type="checkbox" name="ativo" value="1" @checked($profile->exists ? $profile->ativo : true)> Ativo
        </label>
    </div>

    <div class="md:col-span-4">
        <label class="block text-xs font-semibold text-slate-600">Descrição</label>
        <input type="text" name="descricao" value="{{ $profile->descricao }}" class="mt-1 w-full rounded-md border-slate-300 text-sm">
    </div>

    <div>
        <label class="block text-xs font-semibold text-slate-600">Cargo (código)</label>
        <input type="text" name="cargo_source_id" value="{{ $profile->cargo_source_id }}" class="mt-1 w-full rounded-md border-slate-300 text-sm">
    </div>
    <div>
        <label class="block text-xs font-semibold text-slate-600">Área (código)</label>
        <input type="text" name="area_source_id" value="{{ $profile->area_source_id }}" class="mt-1 w-full rounded-md border-slate-300 text-sm">
    </div>
    <div>
        <label class="block text-xs font-semibold text-slate-600">Departamento (código)</label>
        <input type="text" name="departamento_source_id" value="{{ $profile->departamento_source_id }}" class="mt-1 w-full rounded-md border-slate-300 text-sm">
    </div>
    <div>
        <label class="block text-xs font-semibold text-slate-600">Unidade (código)</label>
        <input type="text" name="unidade_source_id" value="{{ $profile->unidade_source_id }}" class="mt-1 w-full rounded-md border-slate-300 text-sm">
    </div>
</div>

<div>
    <p class="text-xs font-semibold text-slate-600">Itens do pacote</p>
    <div class="mt-2 grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
        @foreach($taskTypes as $chave => $definicao)
            <label class="inline-flex items-start gap-2 rounded-md border border-slate-200 px-2 py-1.5 text-xs text-slate-700">
                <input type="checkbox" name="items[]" value="{{ $chave }}" class="mt-0.5" @checked(in_array($chave, $selecionados, true))>
                <span>
                    {{ $definicao['label'] }}
                    <span class="block text-slate-400">
                        {{ config("admissao.areas.{$definicao['area']}.label", $definicao['area']) }} · D-{{ $definicao['sla_dias_antes'] }}
                    </span>
                </span>
            </label>
        @endforeach
    </div>
</div>
