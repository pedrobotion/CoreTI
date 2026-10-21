<x-app-layout>
    <div class="mx-auto max-w-7xl space-y-6">
        @include('cmdb.partials._nav', ['title' => 'Modelos', 'description' => 'Catálogo de modelos do CMDB.'])

        @if(!empty($setupPending))
            <div class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                Estrutura de catálogos ainda não foi criada no banco. Execute as migrations para liberar o cadastro.
            </div>
        @endif

        <section class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
            <form method="GET" class="flex flex-wrap items-end gap-2">
                <div class="min-w-[260px] flex-1">
                    <label class="text-xs font-semibold uppercase text-slate-500">Busca</label>
                    <input name="q" value="{{ $filters['q'] ?? '' }}" class="mt-1 h-10 w-full rounded-md border-slate-300 text-sm" placeholder="Nome, slug ou part number">
                </div>
                <div class="min-w-[220px]">
                    <label class="text-xs font-semibold uppercase text-slate-500">Fabricante</label>
                    <select name="manufacturer_id" class="mt-1 h-10 w-full rounded-md border-slate-300 text-sm">
                        <option value="">Todos</option>
                        @foreach($manufacturers as $manufacturer)
                            <option value="{{ $manufacturer->id }}" @selected((string) ($filters['manufacturer_id'] ?? '') === (string) $manufacturer->id)>{{ $manufacturer->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="min-w-[180px]">
                    <label class="text-xs font-semibold uppercase text-slate-500">Tipo</label>
                    <input name="asset_type" value="{{ $filters['asset_type'] ?? '' }}" class="mt-1 h-10 w-full rounded-md border-slate-300 text-sm" placeholder="server, switch...">
                </div>
                <div class="min-w-[180px]">
                    <label class="text-xs font-semibold uppercase text-slate-500">Ativo</label>
                    <select name="is_active" class="mt-1 h-10 w-full rounded-md border-slate-300 text-sm">
                        <option value="">Todos</option>
                        <option value="1" @selected(($filters['is_active'] ?? '') === '1')>Sim</option>
                        <option value="0" @selected(($filters['is_active'] ?? '') === '0')>Não</option>
                    </select>
                </div>
                <div class="flex gap-2">
                    <button class="inline-flex h-10 items-center justify-center rounded-md bg-[#033151] px-4 text-sm font-semibold text-white hover:opacity-90">Filtrar</button>
                    <a href="{{ route('cmdb.models.index') }}" class="inline-flex h-10 items-center justify-center rounded-md border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-700 hover:bg-slate-50">Limpar</a>
                    <a href="{{ route('cmdb.models.create') }}" class="inline-flex h-10 items-center justify-center rounded-md border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-700 hover:bg-slate-50">Novo modelo</a>
                </div>
            </form>
        </section>

        <section class="rounded-lg border border-slate-200 bg-white shadow-sm">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-100 text-sm">
                    <thead class="border-b border-slate-100 text-left text-xs font-semibold uppercase tracking-wide text-slate-400">
                        <tr>
                            <th class="px-5 py-3">Nome</th>
                            <th class="px-5 py-3">Fabricante</th>
                            <th class="px-5 py-3">Tipo</th>
                            <th class="px-5 py-3">Part number</th>
                            <th class="px-5 py-3">Ativo</th>
                            <th class="px-5 py-3">Ações</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse($items as $item)
                            <tr>
                                <td class="px-5 py-3 font-semibold text-slate-900">{{ $item->name }}</td>
                                <td class="px-5 py-3 text-slate-700">{{ $item->manufacturer?->name ?: '-' }}</td>
                                <td class="px-5 py-3 text-slate-700">{{ $item->asset_type ?: '-' }}</td>
                                <td class="px-5 py-3 text-slate-700">{{ $item->part_number ?: '-' }}</td>
                                <td class="px-5 py-3">
                                    <span class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-semibold {{ $item->is_active ? 'bg-emerald-50 text-emerald-700 ring-1 ring-inset ring-emerald-200' : 'bg-slate-100 text-slate-700' }}">{{ $item->is_active ? 'Sim' : 'Não' }}</span>
                                </td>
                                <td class="px-5 py-3">
                                    <div class="flex flex-wrap gap-2">
                                        <a href="{{ route('cmdb.models.show', $item) }}" class="inline-flex rounded-md border border-[#033151] bg-white px-3 py-1.5 text-xs font-semibold text-[#033151] hover:bg-slate-50">Ver</a>
                                        <a href="{{ route('cmdb.models.edit', $item) }}" class="inline-flex rounded-md border border-slate-300 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50">Editar</a>
                                        <form method="POST" action="{{ route('cmdb.models.destroy', $item) }}" onsubmit="return confirm('Remover este modelo?')">
                                            @csrf
                                            @method('DELETE')
                                            <button class="inline-flex rounded-md border border-rose-200 bg-white px-3 py-1.5 text-xs font-semibold text-rose-700 hover:bg-rose-50">Excluir</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-5 py-6 text-center text-slate-500">Nenhum modelo cadastrado.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        <div>{{ $items->links() }}</div>
    </div>
</x-app-layout>
