@php
    $manufacturer = $manufacturer ?? null;
    $isEdit = (bool) ($manufacturer?->exists);
@endphp

<form method="POST" action="{{ $isEdit ? route('cmdb.manufacturers.update', $manufacturer) : route('cmdb.manufacturers.store') }}" class="space-y-6">
    @csrf
    @if($isEdit)
        @method('PUT')
    @endif

    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
        <div>
            <label class="text-sm font-medium text-slate-700">Nome</label>
            <input name="name" value="{{ old('name', $manufacturer->name ?? '') }}" required class="mt-1 w-full rounded-md border-slate-300 text-sm">
        </div>
        <div>
            <label class="text-sm font-medium text-slate-700">Slug</label>
            <input name="slug" value="{{ old('slug', $manufacturer->slug ?? '') }}" class="mt-1 w-full rounded-md border-slate-300 text-sm">
        </div>
        <div>
            <label class="text-sm font-medium text-slate-700">Website</label>
            <input name="website" value="{{ old('website', $manufacturer->website ?? '') }}" class="mt-1 w-full rounded-md border-slate-300 text-sm">
        </div>
        <div>
            <label class="text-sm font-medium text-slate-700">URL de suporte</label>
            <input name="support_url" value="{{ old('support_url', $manufacturer->support_url ?? '') }}" class="mt-1 w-full rounded-md border-slate-300 text-sm">
        </div>
        <div class="flex items-center gap-2 md:pt-8">
            <input type="checkbox" name="is_active" value="1" class="rounded border-slate-300 text-[#033151]" @checked(old('is_active', $manufacturer->is_active ?? true))>
            <label class="text-sm font-medium text-slate-700">Ativo</label>
        </div>
        <div class="md:col-span-2 xl:col-span-3">
            <label class="text-sm font-medium text-slate-700">Observações</label>
            <textarea name="notes" rows="4" class="mt-1 w-full rounded-md border-slate-300 text-sm">{{ old('notes', $manufacturer->notes ?? '') }}</textarea>
        </div>
    </div>

    <div class="flex justify-end gap-2">
        <a href="{{ $isEdit ? route('cmdb.manufacturers.show', $manufacturer) : route('cmdb.manufacturers.index') }}" class="inline-flex min-h-10 items-center justify-center rounded-md border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-700 hover:bg-slate-50">Voltar</a>
        <button type="submit" class="inline-flex min-h-10 items-center justify-center rounded-md bg-[#033151] px-4 text-sm font-semibold text-white hover:opacity-90">{{ $isEdit ? 'Salvar alterações' : 'Salvar fabricante' }}</button>
    </div>
</form>
