<x-app-layout>
    <div class="mx-auto max-w-7xl space-y-6" x-data="{ editId: null }">
        @if(!empty($setupPending))
            <div class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                Estrutura do módulo ainda não foi criada no banco. Execute as migrations do Network &amp; DataCenter para liberar os cadastros.
            </div>
        @endif

        <section class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
            <form method="GET" class="flex flex-wrap items-end gap-2">
                <div class="min-w-[240px] flex-1">
                    <label class="text-xs font-semibold uppercase text-slate-500">Busca</label>
                    <input name="q" value="{{ $search }}" placeholder="Pesquisar por nome ou descrição" class="mt-1 h-10 w-full rounded-md border-slate-300 text-sm">
                </div>
                <div class="flex gap-2">
                    <button type="submit" class="inline-flex h-10 items-center justify-center rounded-md bg-[#033151] px-4 text-sm font-semibold text-white hover:opacity-90">Buscar</button>
                    <a href="{{ route('network-datacenter.produtos.index') }}" class="inline-flex h-10 items-center justify-center rounded-md border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-700 hover:bg-slate-50">Limpar</a>
                </div>
            </form>
        </section>

        <section class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="text-base font-semibold text-slate-900">Novo produto</h2>
            <form method="POST" action="{{ route('network-datacenter.produtos.store') }}" class="mt-4 grid gap-3 md:grid-cols-2">
                @csrf
                <div>
                    <label class="text-sm font-medium text-slate-700">Nome</label>
                    <input name="nome" required class="mt-1 w-full rounded-md border-slate-300 text-sm">
                </div>
                <div>
                    <label class="text-sm font-medium text-slate-700">Descrição</label>
                    <input name="descricao" class="mt-1 w-full rounded-md border-slate-300 text-sm">
                </div>
                <div class="md:col-span-2 flex justify-end">
                    <button type="submit" class="inline-flex min-h-10 items-center justify-center rounded-md bg-[#033151] px-4 text-sm font-semibold text-white hover:opacity-90">Adicionar</button>
                </div>
            </form>
        </section>

        <section class="rounded-lg border border-slate-200 bg-white shadow-sm">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="px-5 py-3">Nome</th>
                            <th class="px-5 py-3">Descrição</th>
                            <th class="px-5 py-3">Status</th>
                            <th class="px-5 py-3">Ações</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200">
                        @forelse($items as $item)
                            <tr x-data="{ open: false }">
                                <td class="px-5 py-3">{{ $item->nome }}</td>
                                <td class="px-5 py-3">{{ $item->descricao ?: '-' }}</td>
                                <td class="px-5 py-3">
                                    <span class="inline-flex rounded-full px-2 py-1 text-xs font-semibold {{ $item->ativo ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-100 text-slate-700' }}">
                                        {{ $item->ativo ? 'Ativo' : 'Inativo' }}
                                    </span>
                                </td>
                                <td class="px-5 py-3">
                                    <div class="flex flex-wrap gap-2">
                                        <button type="button" @click="open = !open" class="inline-flex rounded-md border border-slate-300 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50">Editar</button>
                                        <form method="POST" action="{{ route('network-datacenter.produtos.toggle', $item) }}">
                                            @csrf
                                            @method('PATCH')
                                            <button type="submit" class="inline-flex rounded-md bg-[#033151] px-3 py-1.5 text-xs font-semibold text-white hover:opacity-90">
                                                {{ $item->ativo ? 'Desativar' : 'Ativar' }}
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                            <tr x-show="open">
                                <td colspan="4" class="bg-slate-50 px-5 py-4">
                                    <form method="POST" action="{{ route('network-datacenter.produtos.update', $item) }}" class="grid gap-3 md:grid-cols-2">
                                        @csrf
                                        @method('PATCH')
                                        <div>
                                            <label class="text-sm font-medium text-slate-700">Nome</label>
                                            <input name="nome" value="{{ $item->nome }}" class="mt-1 w-full rounded-md border-slate-300 text-sm">
                                        </div>
                                        <div>
                                            <label class="text-sm font-medium text-slate-700">Descrição</label>
                                            <input name="descricao" value="{{ $item->descricao }}" class="mt-1 w-full rounded-md border-slate-300 text-sm">
                                        </div>
                                        <div class="md:col-span-2 flex justify-end">
                                            <button type="submit" class="inline-flex min-h-10 items-center justify-center rounded-md bg-[#033151] px-4 text-sm font-semibold text-white hover:opacity-90">Salvar alterações</button>
                                        </div>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="px-5 py-6 text-center text-slate-500">Nenhum produto cadastrado.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</x-app-layout>
