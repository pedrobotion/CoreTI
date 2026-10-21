<x-app-layout>
    @php
        $badgeClass = fn (string $type) => match ($type) {
            'hosts' => 'bg-blue-50 text-blue-700 ring-1 ring-inset ring-blue-200',
            'depends_on' => 'bg-amber-50 text-amber-700 ring-1 ring-inset ring-amber-200',
            'connects_to' => 'bg-cyan-50 text-cyan-700 ring-1 ring-inset ring-cyan-200',
            'protects' => 'bg-emerald-50 text-emerald-700 ring-1 ring-inset ring-emerald-200',
            'backs_up_to' => 'bg-violet-50 text-violet-700 ring-1 ring-inset ring-violet-200',
            'replicates_to' => 'bg-fuchsia-50 text-fuchsia-700 ring-1 ring-inset ring-fuchsia-200',
            'uses' => 'bg-slate-100 text-slate-700',
            'located_in' => 'bg-indigo-50 text-indigo-700 ring-1 ring-inset ring-indigo-200',
            'powered_by' => 'bg-orange-50 text-orange-700 ring-1 ring-inset ring-orange-200',
            'monitored_by' => 'bg-rose-50 text-rose-700 ring-1 ring-inset ring-rose-200',
            default => 'bg-slate-100 text-slate-700',
        };
    @endphp
    <div class="mx-auto max-w-7xl space-y-6">
        @include('cmdb.partials._nav', ['title' => 'Editar relacionamento', 'description' => 'Atualização do vínculo entre ativos.'])

        <section class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
            <div class="mb-4 flex flex-wrap gap-2">
                <span class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-semibold {{ $badgeClass($relationship->relationship_type) }}">
                    {{ $types[$relationship->relationship_type] ?? $relationship->relationship_type }}
                </span>
            </div>
            <form method="POST" action="{{ route('cmdb.relationships.update', $relationship) }}" class="grid gap-3 md:grid-cols-2">
                @csrf
                @method('PUT')
                <div>
                    <label class="text-sm font-medium text-slate-700">Ativo de origem</label>
                    <select name="source_asset_id" required class="mt-1 w-full rounded-md border-slate-300 text-sm">
                        @foreach($assets as $asset)
                            <option value="{{ $asset->id }}" @selected((string) old('source_asset_id', $relationship->source_asset_id) === (string) $asset->id)>{{ $asset->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="text-sm font-medium text-slate-700">Ativo de destino</label>
                    <select name="target_asset_id" required class="mt-1 w-full rounded-md border-slate-300 text-sm">
                        @foreach($assets as $asset)
                            <option value="{{ $asset->id }}" @selected((string) old('target_asset_id', $relationship->target_asset_id) === (string) $asset->id)>{{ $asset->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="text-sm font-medium text-slate-700">Tipo de relacionamento</label>
                    <select name="relationship_type" required class="mt-1 w-full rounded-md border-slate-300 text-sm">
                        @foreach($types as $type => $label)
                            <option value="{{ $type }}" @selected(old('relationship_type', $relationship->relationship_type) === $type)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="text-sm font-medium text-slate-700">Descrição</label>
                    <input name="description" value="{{ old('description', $relationship->description ?? '') }}" class="mt-1 w-full rounded-md border-slate-300 text-sm">
                </div>
                <div class="md:col-span-2 flex justify-end gap-2">
                    <a href="{{ route('cmdb.relationships.index') }}" class="inline-flex min-h-10 items-center justify-center rounded-md border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-700 hover:bg-slate-50">Cancelar</a>
                    <button type="submit" class="inline-flex min-h-10 items-center justify-center rounded-md bg-[#033151] px-4 text-sm font-semibold text-white hover:opacity-90">Salvar alterações</button>
                </div>
            </form>
        </section>
    </div>
</x-app-layout>
