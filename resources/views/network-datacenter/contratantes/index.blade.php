<x-app-layout>
    <div class="mx-auto max-w-7xl space-y-6" x-data="{ editId: null }">
        <div class="flex items-start justify-between gap-3">
            <div>
                <p class="text-sm font-semibold uppercase tracking-wide text-slate-500">Network &amp; DataCenter</p>
                <h1 class="mt-1 text-2xl font-bold text-slate-950">Contratantes</h1>
            </div>
        </div>

        <section class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
            <form method="GET" class="flex flex-wrap items-end gap-2">
                <div class="min-w-[240px] flex-1">
                    <label class="text-xs font-semibold uppercase text-slate-500">Busca</label>
                    <input name="q" value="{{ $search }}" placeholder="Razão social, nome fantasia ou CNPJ" class="mt-1 h-10 w-full rounded-md border-slate-300 text-sm">
                </div>
                <div class="flex gap-2">
                    <button type="submit" class="inline-flex h-10 items-center justify-center rounded-md bg-[#033151] px-4 text-sm font-semibold text-white hover:opacity-90">Buscar</button>
                    <a href="{{ route('network-datacenter.contratantes.index') }}" class="inline-flex h-10 items-center justify-center rounded-md border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-700 hover:bg-slate-50">Limpar</a>
                </div>
            </form>
        </section>

        <section class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="text-base font-semibold text-slate-900">Novo contratante</h2>
            <form method="POST" action="{{ route('network-datacenter.contratantes.store') }}" class="mt-4 grid gap-3 md:grid-cols-2">
                @csrf
                <div>
                    <label class="text-sm font-medium text-slate-700">Nome fantasia</label>
                    <input name="nome_fantasia" class="mt-1 w-full rounded-md border-slate-300 text-sm">
                </div>
                <div>
                    <label class="text-sm font-medium text-slate-700">Razão social</label>
                    <input name="razao_social" required class="mt-1 w-full rounded-md border-slate-300 text-sm">
                </div>
                <div>
                    <label class="text-sm font-medium text-slate-700">CNPJ</label>
                    <input name="cnpj" required class="mt-1 w-full rounded-md border-slate-300 text-sm">
                </div>
                <div>
                    <label class="text-sm font-medium text-slate-700">Inscrição estadual</label>
                    <input name="inscricao_estadual" class="mt-1 w-full rounded-md border-slate-300 text-sm">
                </div>
                <div class="md:col-span-2">
                    <label class="text-sm font-medium text-slate-700">Observação</label>
                    <textarea name="observacao" rows="3" class="mt-1 w-full rounded-md border-slate-300 text-sm"></textarea>
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
                            <th class="px-5 py-3">Razão social</th>
                            <th class="px-5 py-3">Nome fantasia</th>
                            <th class="px-5 py-3">CNPJ</th>
                            <th class="px-5 py-3">Status</th>
                            <th class="px-5 py-3">Ações</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200">
                        @forelse($items as $item)
                            <tr x-data="{ open: false }">
                                <td class="px-5 py-3">{{ $item->razao_social }}</td>
                                <td class="px-5 py-3">{{ $item->nome_fantasia ?: '-' }}</td>
                                <td class="px-5 py-3">{{ $item->cnpj }}</td>
                                <td class="px-5 py-3">
                                    <span class="inline-flex rounded-full px-2 py-1 text-xs font-semibold {{ $item->ativo ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-100 text-slate-700' }}">
                                        {{ $item->ativo ? 'Ativo' : 'Inativo' }}
                                    </span>
                                </td>
                                <td class="px-5 py-3">
                                    <div class="flex flex-wrap gap-2">
                                        <button type="button" @click="open = !open" class="inline-flex rounded-md border border-slate-300 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50">Editar</button>
                                        <form method="POST" action="{{ route('network-datacenter.contratantes.toggle', $item) }}">
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
                                <td colspan="5" class="bg-slate-50 px-5 py-4">
                                    <form method="POST" action="{{ route('network-datacenter.contratantes.update', $item) }}" class="grid gap-3 md:grid-cols-2">
                                        @csrf
                                        @method('PATCH')
                                        <div>
                                            <label class="text-sm font-medium text-slate-700">Nome fantasia</label>
                                            <input name="nome_fantasia" value="{{ $item->nome_fantasia }}" class="mt-1 w-full rounded-md border-slate-300 text-sm">
                                        </div>
                                        <div>
                                            <label class="text-sm font-medium text-slate-700">Razão social</label>
                                            <input name="razao_social" value="{{ $item->razao_social }}" class="mt-1 w-full rounded-md border-slate-300 text-sm">
                                        </div>
                                        <div>
                                            <label class="text-sm font-medium text-slate-700">CNPJ</label>
                                            <input name="cnpj" value="{{ $item->cnpj }}" class="mt-1 w-full rounded-md border-slate-300 text-sm">
                                        </div>
                                        <div>
                                            <label class="text-sm font-medium text-slate-700">Observação</label>
                                            <input name="observacao" value="{{ $item->observacao }}" class="mt-1 w-full rounded-md border-slate-300 text-sm">
                                        </div>
                                        <div class="md:col-span-2 flex justify-end">
                                            <button type="submit" class="inline-flex min-h-10 items-center justify-center rounded-md bg-[#033151] px-4 text-sm font-semibold text-white hover:opacity-90">Salvar alterações</button>
                                        </div>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-5 py-6 text-center text-slate-500">Nenhum contratante cadastrado.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</x-app-layout>
