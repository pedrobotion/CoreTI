<x-app-layout>
    <div class="mx-auto max-w-7xl space-y-6">
        @if(!empty($setupPending))
            <div class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                Estrutura do módulo ainda não foi criada no banco. Execute as migrations do CMDB para liberar os cadastros.
            </div>
        @endif

        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
            <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Total de serviços</p>
                <p class="mt-2 text-3xl font-bold text-slate-950">{{ $total }}</p>
            </div>
            <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Serviços ativos</p>
                <p class="mt-2 text-3xl font-bold text-slate-950">{{ $ativos }}</p>
            </div>
            <div class="rounded-lg border border-rose-200 bg-rose-50 p-5 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wide text-rose-700">Sem ficha anexada</p>
                <p class="mt-2 text-3xl font-bold text-rose-800">{{ $semFicha }}</p>
            </div>
            <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Últimos cadastrados</p>
                <p class="mt-2 text-3xl font-bold text-slate-950">{{ $latest->count() }}</p>
            </div>
        </div>

        <div class="grid gap-6 xl:grid-cols-2">
            <section class="rounded-lg border border-slate-200 bg-white shadow-sm">
                <div class="border-b border-slate-200 px-5 py-4">
                    <h2 class="text-base font-semibold text-slate-900">Serviços por produto</h2>
                </div>
                <div class="p-5">
                    @forelse($byProduto as $row)
                        <div class="flex items-center justify-between border-b border-slate-100 py-2 text-sm last:border-0">
                            <span class="text-slate-700">{{ $row->nome }}</span>
                            <span class="font-semibold text-slate-950">{{ $row->total }}</span>
                        </div>
                    @empty
                        <p class="text-sm text-slate-500">Sem dados cadastrados.</p>
                    @endforelse
                </div>
            </section>

            <section class="rounded-lg border border-slate-200 bg-white shadow-sm">
                <div class="border-b border-slate-200 px-5 py-4">
                    <h2 class="text-base font-semibold text-slate-900">Serviços por contratante</h2>
                </div>
                <div class="p-5">
                    @forelse($byContratante as $row)
                        <div class="flex items-center justify-between border-b border-slate-100 py-2 text-sm last:border-0">
                            <span class="text-slate-700">{{ $row->nome }}</span>
                            <span class="font-semibold text-slate-950">{{ $row->total }}</span>
                        </div>
                    @empty
                        <p class="text-sm text-slate-500">Sem dados cadastrados.</p>
                    @endforelse
                </div>
            </section>
        </div>

        <section class="rounded-lg border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-200 px-5 py-4">
                <h2 class="text-base font-semibold text-slate-900">Últimos serviços cadastrados</h2>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="px-5 py-3">Contrato</th>
                            <th class="px-5 py-3">Contratante</th>
                            <th class="px-5 py-3">Produto</th>
                            <th class="px-5 py-3">Local</th>
                            <th class="px-5 py-3">Status</th>
                            <th class="px-5 py-3">Ações</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200">
                        @forelse($latest as $item)
                            <tr>
                                <td class="px-5 py-3">{{ $item->contrato ?: '-' }}</td>
                                <td class="px-5 py-3">{{ $item->razao_social ?: ($item->contratante->razao_social ?? '-') }}</td>
                                <td class="px-5 py-3">{{ $item->produto?->nome ?: '-' }}</td>
                                <td class="px-5 py-3">{{ $item->local_nome ?: '-' }}</td>
                                <td class="px-5 py-3">
                                    <span class="inline-flex rounded-full px-2 py-1 text-xs font-semibold {{ $item->status === 'ativo' ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-100 text-slate-700' }}">
                                        {{ $item->status }}
                                    </span>
                                </td>
                                <td class="px-5 py-3">
                                    <a href="{{ route('network-datacenter.servicos.show', $item) }}" class="inline-flex rounded-md border border-[#033151] bg-white px-3 py-1.5 text-xs font-semibold text-[#033151] hover:bg-slate-50">Abrir</a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-5 py-6 text-center text-slate-500">Nenhum serviço cadastrado ainda.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

    </div>
</x-app-layout>
