<x-app-layout>
    <div class="mx-auto max-w-7xl space-y-6">
        @include('cmdb.partials._nav', ['title' => 'Racks', 'description' => 'Cadastro e consulta dos racks do CMDB.'])

        @if(!empty($setupPending))
            <div class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                Estrutura do CMDB ainda não foi criada no banco. Execute as migrations para liberar o módulo.
            </div>
        @endif

        <section class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
            <div class="grid gap-3 md:grid-cols-3">
                <div class="rounded-lg border border-slate-200 bg-slate-50 p-4">
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Racks listados</p>
                    <p class="mt-2 text-2xl font-semibold text-slate-950">{{ $items->total() }}</p>
                </div>
                <div class="rounded-lg border border-slate-200 bg-slate-50 p-4">
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Ativos vinculados</p>
                    <p class="mt-2 text-2xl font-semibold text-slate-950">{{ $items->sum('assets_count') }}</p>
                </div>
                <div class="rounded-lg border border-slate-200 bg-slate-50 p-4">
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Capacidade usada</p>
                    <p class="mt-2 text-sm text-slate-600">Consulte o detalhe de cada rack para verificar a ocupação.</p>
                </div>
            </div>
        </section>

        @include('cmdb.racks._form', ['rack' => $rack, 'unitOptions' => $unitOptions])

        <section class="rounded-lg border border-slate-200 bg-white shadow-sm">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-100 text-sm">
                    <thead class="border-b border-slate-100 text-left text-xs font-semibold uppercase tracking-wide text-slate-400">
                        <tr>
                            <th class="px-5 py-3">Nome</th>
                            <th class="px-5 py-3">Unidade</th>
                            <th class="px-5 py-3">Localização</th>
                            <th class="px-5 py-3">Capacidade</th>
                            <th class="px-5 py-3">Ativos</th>
                            <th class="px-5 py-3">Ações</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse($items as $item)
                            <tr>
                                <td class="px-5 py-3">{{ $item->name }}</td>
                                <td class="px-5 py-3">{{ $unitLabels[$item->organization_unit_id] ?? ($item->unit_id ?: '-') }}</td>
                                <td class="px-5 py-3">{{ $item->location ?: '-' }}</td>
                                <td class="px-5 py-3">{{ $item->capacity_u ?: '-' }}</td>
                                <td class="px-5 py-3">
                                    <span class="inline-flex rounded-full bg-blue-100 px-2.5 py-0.5 text-xs font-semibold text-blue-800">{{ $item->assets_count ?? 0 }} ativos</span>
                                </td>
                                <td class="px-5 py-3">
                                    <div class="flex flex-wrap gap-2">
                                        <a href="{{ route('cmdb.racks.show', $item) }}" class="inline-flex rounded-md border border-[#033151] bg-white px-3 py-1.5 text-xs font-semibold text-[#033151] hover:bg-slate-50">Abrir</a>
                                        <a href="{{ route('cmdb.racks.edit', $item) }}" class="inline-flex rounded-md border border-slate-300 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50">Editar</a>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-5 py-6 text-center text-slate-500">Nenhum rack cadastrado.</td>
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
