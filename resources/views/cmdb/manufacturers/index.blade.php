<x-app-layout>
    <div class="mx-auto max-w-7xl space-y-6">
        @include('cmdb.partials._nav', ['title' => 'Fabricantes', 'description' => 'Catálogo de fabricantes do CMDB.'])

        @if(!empty($setupPending))
            <div class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                Estrutura de catálogos ainda não foi criada no banco. Execute as migrations para liberar o cadastro.
            </div>
        @endif

        <section class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
            <form method="GET" class="flex flex-wrap items-end gap-2">
                <div class="min-w-[260px] flex-1">
                    <label class="text-xs font-semibold uppercase text-slate-500">Busca</label>
                    <input name="q" value="{{ $filters['q'] ?? '' }}" class="mt-1 h-10 w-full rounded-md border-slate-300 text-sm" placeholder="Nome, slug, website ou suporte">
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
                    <a href="{{ route('cmdb.manufacturers.index') }}" class="inline-flex h-10 items-center justify-center rounded-md border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-700 hover:bg-slate-50">Limpar</a>
                    <a href="{{ route('cmdb.manufacturers.create') }}" class="inline-flex h-10 items-center justify-center rounded-md border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-700 hover:bg-slate-50">Novo fabricante</a>
                </div>
            </form>
        </section>

        <section class="rounded-lg border border-slate-200 bg-white shadow-sm">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="px-5 py-3">Nome</th>
                            <th class="px-5 py-3">Slug</th>
                            <th class="px-5 py-3">Modelos</th>
                            <th class="px-5 py-3">Ativo</th>
                            <th class="px-5 py-3">Ações</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200">
                        @forelse($items as $item)
                            <tr>
                                <td class="px-5 py-3 font-semibold text-slate-900">{{ $item->name }}</td>
                                <td class="px-5 py-3 text-slate-700">{{ $item->slug }}</td>
                                <td class="px-5 py-3 text-slate-700">{{ $item->models_count ?? 0 }}</td>
                                <td class="px-5 py-3">
                                    <span class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-semibold {{ $item->is_active ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-100 text-slate-700' }}">{{ $item->is_active ? 'Sim' : 'Não' }}</span>
                                </td>
                                <td class="px-5 py-3">
                                    <div class="flex flex-wrap gap-2">
                                        <a href="{{ route('cmdb.manufacturers.show', $item) }}" class="inline-flex rounded-md border border-[#033151] bg-white px-3 py-1.5 text-xs font-semibold text-[#033151] hover:bg-slate-50">Ver</a>
                                        <a href="{{ route('cmdb.manufacturers.edit', $item) }}" class="inline-flex rounded-md border border-slate-300 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50">Editar</a>
                                        <form method="POST" action="{{ route('cmdb.manufacturers.destroy', $item) }}" onsubmit="return confirm('Remover este fabricante?')">
                                            @csrf
                                            @method('DELETE')
                                            <button class="inline-flex rounded-md border border-rose-200 bg-white px-3 py-1.5 text-xs font-semibold text-rose-700 hover:bg-rose-50">Excluir</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-5 py-6 text-center text-slate-500">Nenhum fabricante cadastrado.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        <div>{{ $items->links() }}</div>
    </div>
</x-app-layout>
