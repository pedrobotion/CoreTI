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
        @include('cmdb.partials._nav', ['title' => 'Relacionamentos', 'description' => 'Mapeamento entre ativos do CMDB.'])

        @if(!empty($setupPending))
            <div class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                Estrutura do CMDB ainda não foi criada no banco. Execute as migrations para liberar o módulo.
            </div>
        @endif

        <section class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="text-base font-semibold text-slate-900">Novo relacionamento</h2>
            <form method="POST" action="{{ route('cmdb.relationships.store') }}" class="mt-4 grid gap-3 md:grid-cols-2">
                @csrf
                <div>
                    <label class="text-sm font-medium text-slate-700">Ativo de origem</label>
                    <select name="source_asset_id" required class="mt-1 w-full rounded-md border-slate-300 text-sm">
                        @foreach($assets as $asset)
                            <option value="{{ $asset->id }}">{{ $asset->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="text-sm font-medium text-slate-700">Ativo de destino</label>
                    <select name="target_asset_id" required class="mt-1 w-full rounded-md border-slate-300 text-sm">
                        @foreach($assets as $asset)
                            <option value="{{ $asset->id }}">{{ $asset->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="text-sm font-medium text-slate-700">Tipo de relacionamento</label>
                    <select name="relationship_type" required class="mt-1 w-full rounded-md border-slate-300 text-sm">
                        <option value="">Selecione</option>
                        @foreach($types as $type => $label)
                            <option value="{{ $type }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="text-sm font-medium text-slate-700">Descrição</label>
                    <input name="description" class="mt-1 w-full rounded-md border-slate-300 text-sm">
                </div>
                <div class="md:col-span-2 flex justify-end">
                    <button type="submit" class="inline-flex min-h-10 items-center justify-center rounded-md bg-[#033151] px-4 text-sm font-semibold text-white hover:opacity-90">Salvar</button>
                </div>
            </form>
        </section>

        <section class="rounded-lg border border-slate-200 bg-white shadow-sm">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-100 text-sm">
                    <thead class="border-b border-slate-100 text-left text-xs font-semibold uppercase tracking-wide text-slate-400">
                        <tr>
                            <th class="px-5 py-3">Origem</th>
                            <th class="px-5 py-3">Destino</th>
                            <th class="px-5 py-3">Tipo</th>
                            <th class="px-5 py-3">Descrição</th>
                            <th class="px-5 py-3">Ações</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse($items as $item)
                            <tr>
                                <td class="px-5 py-3">
                                    @if($item->sourceAsset)
                                        <a href="{{ route('cmdb.assets.show', $item->sourceAsset) }}" class="font-semibold text-[#033151] hover:underline">{{ $item->sourceAsset->name }}</a>
                                    @else
                                        -
                                    @endif
                                </td>
                                <td class="px-5 py-3">
                                    @if($item->targetAsset)
                                        <a href="{{ route('cmdb.assets.show', $item->targetAsset) }}" class="font-semibold text-[#033151] hover:underline">{{ $item->targetAsset->name }}</a>
                                    @else
                                        -
                                    @endif
                                </td>
                                <td class="px-5 py-3">
                                    <span class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-semibold {{ $badgeClass($item->relationship_type) }}">
                                        {{ $types[$item->relationship_type] ?? $item->relationship_type }}
                                    </span>
                                </td>
                                <td class="px-5 py-3">{{ $item->description ?: '-' }}</td>
                                <td class="px-5 py-3">
                                    <div class="flex flex-wrap gap-2">
                                        <a href="{{ route('cmdb.relationships.edit', $item) }}" class="inline-flex rounded-md border border-slate-300 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50">Editar</a>
                                        <form method="POST" action="{{ route('cmdb.relationships.destroy', $item) }}" onsubmit="return confirm('Remover este relacionamento?')">
                                            @csrf
                                            @method('DELETE')
                                            <button class="inline-flex rounded-md border border-rose-200 bg-white px-3 py-1.5 text-xs font-semibold text-rose-700 hover:bg-rose-50">Excluir</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-5 py-6 text-center text-slate-500">Nenhum relacionamento cadastrado.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="border-t border-slate-200 px-5 py-4">
                {{ $items->links() }}
            </div>
        </section>
    </div>
</x-app-layout>
