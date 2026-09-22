@php
    $rack = $rack ?? null;
    $isEdit = (bool) $rack;
@endphp

<section class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
    <h2 class="text-base font-semibold text-slate-900">{{ $isEdit ? 'Editar rack' : 'Novo rack' }}</h2>

    <form method="POST" action="{{ $isEdit ? route('cmdb.racks.update', $rack) : route('cmdb.racks.store') }}" class="mt-4 grid gap-4 md:grid-cols-2 xl:grid-cols-3">
        @csrf
        @if($isEdit)
            @method('PUT')
        @endif

        <div>
            <label class="text-sm font-medium text-slate-700">Nome</label>
            <input name="name" value="{{ old('name', $rack->name ?? '') }}" required class="mt-1 w-full rounded-md border-slate-300 text-sm">
        </div>
        <div>
            <label class="text-sm font-medium text-slate-700">Unidade</label>
            <select name="organization_unit_id" class="mt-1 w-full rounded-md border-slate-300 text-sm">
                <option value="">Selecione</option>
                @foreach($unitOptions as $unit)
                    <option value="{{ $unit['id'] }}" @selected((string) old('organization_unit_id', $rack->organization_unit_id ?? '') === (string) $unit['id'])>{{ $unit['label'] }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="text-sm font-medium text-slate-700">Localização</label>
            <input name="location" value="{{ old('location', $rack->location ?? '') }}" class="mt-1 w-full rounded-md border-slate-300 text-sm">
        </div>
        <div>
            <label class="text-sm font-medium text-slate-700">Sala</label>
            <input name="room" value="{{ old('room', $rack->room ?? '') }}" class="mt-1 w-full rounded-md border-slate-300 text-sm">
        </div>
        <div>
            <label class="text-sm font-medium text-slate-700">Andar</label>
            <input name="floor" value="{{ old('floor', $rack->floor ?? '') }}" class="mt-1 w-full rounded-md border-slate-300 text-sm">
        </div>
        <div>
            <label class="text-sm font-medium text-slate-700">Capacidade em U</label>
            <input name="capacity_u" type="number" min="0" value="{{ old('capacity_u', $rack->capacity_u ?? '') }}" class="mt-1 w-full rounded-md border-slate-300 text-sm">
        </div>
        <div class="md:col-span-2 xl:col-span-3">
            <label class="text-sm font-medium text-slate-700">Observações</label>
            <textarea name="notes" rows="3" class="mt-1 w-full rounded-md border-slate-300 text-sm">{{ old('notes', $rack->notes ?? '') }}</textarea>
        </div>
        <div class="md:col-span-2 xl:col-span-3 flex justify-end gap-2">
            <a href="{{ $isEdit ? route('cmdb.racks.show', $rack) : route('cmdb.racks.index') }}" class="inline-flex min-h-10 items-center justify-center rounded-md border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-700 hover:bg-slate-50">Cancelar</a>
            <button type="submit" class="inline-flex min-h-10 items-center justify-center rounded-md bg-[#033151] px-4 text-sm font-semibold text-white hover:opacity-90">
                {{ $isEdit ? 'Salvar alterações' : 'Salvar rack' }}
            </button>
        </div>
    </form>
</section>
