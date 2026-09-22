@php
    $model = $model ?? null;
    $isEdit = (bool) ($model?->exists);
@endphp

<form method="POST" action="{{ $isEdit ? route('cmdb.models.update', $model) : route('cmdb.models.store') }}" class="space-y-6">
    @csrf
    @if($isEdit)
        @method('PUT')
    @endif

    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
        <div>
            <label class="text-sm font-medium text-slate-700">Fabricante</label>
            <select name="manufacturer_id" class="mt-1 w-full rounded-md border-slate-300 text-sm">
                <option value="">Sem fabricante</option>
                @foreach($manufacturers as $manufacturer)
                    <option value="{{ $manufacturer->id }}" @selected((string) old('manufacturer_id', $model->manufacturer_id ?? '') === (string) $manufacturer->id)>{{ $manufacturer->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="text-sm font-medium text-slate-700">Nome</label>
            <input name="name" value="{{ old('name', $model->name ?? '') }}" required class="mt-1 w-full rounded-md border-slate-300 text-sm">
        </div>
        <div>
            <label class="text-sm font-medium text-slate-700">Slug</label>
            <input name="slug" value="{{ old('slug', $model->slug ?? '') }}" class="mt-1 w-full rounded-md border-slate-300 text-sm">
        </div>
        <div>
            <label class="text-sm font-medium text-slate-700">Tipo de ativo</label>
            <input name="asset_type" value="{{ old('asset_type', $model->asset_type ?? '') }}" class="mt-1 w-full rounded-md border-slate-300 text-sm" placeholder="server, switch, firewall...">
        </div>
        <div>
            <label class="text-sm font-medium text-slate-700">Part number</label>
            <input name="part_number" value="{{ old('part_number', $model->part_number ?? '') }}" class="mt-1 w-full rounded-md border-slate-300 text-sm">
        </div>
        <div>
            <label class="text-sm font-medium text-slate-700">Rack units</label>
            <input name="rack_units" type="number" min="0" value="{{ old('rack_units', $model->rack_units ?? '') }}" class="mt-1 w-full rounded-md border-slate-300 text-sm">
        </div>
        <div>
            <label class="text-sm font-medium text-slate-700">EOL</label>
            <input name="eol_date" type="date" value="{{ old('eol_date', optional($model->eol_date ?? null)->format('Y-m-d')) }}" class="mt-1 w-full rounded-md border-slate-300 text-sm">
        </div>
        <div>
            <label class="text-sm font-medium text-slate-700">EOS</label>
            <input name="eos_date" type="date" value="{{ old('eos_date', optional($model->eos_date ?? null)->format('Y-m-d')) }}" class="mt-1 w-full rounded-md border-slate-300 text-sm">
        </div>
        <div>
            <label class="text-sm font-medium text-slate-700">Fim do suporte</label>
            <input name="support_end_date" type="date" value="{{ old('support_end_date', optional($model->support_end_date ?? null)->format('Y-m-d')) }}" class="mt-1 w-full rounded-md border-slate-300 text-sm">
        </div>
        <div>
            <label class="text-sm font-medium text-slate-700">URL do manual</label>
            <input name="manual_url" value="{{ old('manual_url', $model->manual_url ?? '') }}" class="mt-1 w-full rounded-md border-slate-300 text-sm">
        </div>
        <div>
            <label class="text-sm font-medium text-slate-700">Imagem</label>
            <input name="image_url" value="{{ old('image_url', $model->image_url ?? '') }}" class="mt-1 w-full rounded-md border-slate-300 text-sm">
        </div>
        <div>
            <label class="text-sm font-medium text-slate-700">Consumo (W)</label>
            <input name="power_consumption_watts" type="number" min="0" value="{{ old('power_consumption_watts', $model->power_consumption_watts ?? '') }}" class="mt-1 w-full rounded-md border-slate-300 text-sm">
        </div>
        <div class="flex items-center gap-2 md:pt-8">
            <input type="checkbox" name="is_active" value="1" class="rounded border-slate-300 text-[#033151]" @checked(old('is_active', $model->is_active ?? true))>
            <label class="text-sm font-medium text-slate-700">Ativo</label>
        </div>
        <div class="md:col-span-2 xl:col-span-3">
            <label class="text-sm font-medium text-slate-700">Observações</label>
            <textarea name="notes" rows="4" class="mt-1 w-full rounded-md border-slate-300 text-sm">{{ old('notes', $model->notes ?? '') }}</textarea>
        </div>
    </div>

    <div class="flex justify-end gap-2">
        <a href="{{ $isEdit ? route('cmdb.models.show', $model) : route('cmdb.models.index') }}" class="inline-flex min-h-10 items-center justify-center rounded-md border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-700 hover:bg-slate-50">Voltar</a>
        <button type="submit" class="inline-flex min-h-10 items-center justify-center rounded-md bg-[#033151] px-4 text-sm font-semibold text-white hover:opacity-90">{{ $isEdit ? 'Salvar alterações' : 'Salvar modelo' }}</button>
    </div>
</form>
