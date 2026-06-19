<x-app-layout>
    <div class="mx-auto max-w-7xl space-y-6">
        <div class="flex items-start justify-between gap-3">
            <div>
                <p class="text-sm font-semibold uppercase tracking-wide text-slate-500">Network &amp; DataCenter</p>
                <h1 class="mt-1 text-2xl font-bold text-slate-950">Serviços de Conectividade</h1>
            </div>
            <a href="{{ route('network-datacenter.servicos.create') }}" class="inline-flex min-h-10 items-center justify-center rounded-md bg-[#033151] px-4 text-sm font-semibold text-white hover:opacity-90">Novo serviço</a>
        </div>

        <section class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
            <form method="GET" class="flex flex-wrap items-end gap-2">
                <div class="min-w-[220px] flex-1">
                    <label class="text-xs font-semibold uppercase text-slate-500">Busca</label>
                    <input name="q" value="{{ $search }}" placeholder="Contrato, contratante, local, observação" class="mt-1 h-10 w-full rounded-md border-slate-300 text-sm">
                </div>
                <div class="min-w-[180px]">
                    <label class="text-xs font-semibold uppercase text-slate-500">Contrato</label>
                    <input name="contrato" value="{{ $filters['contrato'] ?? '' }}" class="mt-1 h-10 w-full rounded-md border-slate-300 text-sm">
                </div>
                <div class="min-w-[220px]">
                    <label class="text-xs font-semibold uppercase text-slate-500">Contratante</label>
                    <select name="contratante_id" class="mt-1 h-10 w-full rounded-md border-slate-300 text-sm">
                        <option value="">Todos</option>
                        @foreach($contratantes as $contratante)
                            <option value="{{ $contratante->id }}" @selected(($filters['contratante_id'] ?? null) == $contratante->id)>{{ $contratante->razao_social }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="min-w-[200px]">
                    <label class="text-xs font-semibold uppercase text-slate-500">Produto</label>
                    <select name="produto_id" class="mt-1 h-10 w-full rounded-md border-slate-300 text-sm">
                        <option value="">Todos</option>
                        @foreach($produtos as $produto)
                            <option value="{{ $produto->id }}" @selected(($filters['produto_id'] ?? null) == $produto->id)>{{ $produto->nome }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="min-w-[200px]">
                    <label class="text-xs font-semibold uppercase text-slate-500">Local</label>
                    <input name="local" value="{{ $filters['local'] ?? '' }}" class="mt-1 h-10 w-full rounded-md border-slate-300 text-sm">
                </div>
                <div class="min-w-[180px]">
                    <label class="text-xs font-semibold uppercase text-slate-500">Município</label>
                    <input name="municipio" value="{{ $filters['municipio'] ?? '' }}" class="mt-1 h-10 w-full rounded-md border-slate-300 text-sm">
                </div>
                <div class="min-w-[140px]">
                    <label class="text-xs font-semibold uppercase text-slate-500">Status</label>
                    <select name="status" class="mt-1 h-10 w-full rounded-md border-slate-300 text-sm">
                        <option value="">Todos</option>
                        <option value="ativo" @selected(($filters['status'] ?? '') === 'ativo')>Ativo</option>
                        <option value="inativo" @selected(($filters['status'] ?? '') === 'inativo')>Inativo</option>
                    </select>
                </div>
                <div class="min-w-[140px]">
                    <label class="text-xs font-semibold uppercase text-slate-500">Por página</label>
                    <select name="per_page" class="mt-1 h-10 w-full rounded-md border-slate-300 text-sm">
                        @foreach([10, 15, 25, 50] as $option)
                            <option value="{{ $option }}" @selected($perPage === $option)>{{ $option }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="flex gap-2">
                    <button type="submit" class="inline-flex h-10 items-center justify-center rounded-md bg-[#033151] px-4 text-sm font-semibold text-white hover:opacity-90">Buscar</button>
                    <a href="{{ route('network-datacenter.servicos.index') }}" class="inline-flex h-10 items-center justify-center rounded-md border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-700 hover:bg-slate-50">Limpar</a>
                </div>
            </form>
        </section>

        <section class="rounded-lg border border-slate-200 bg-white shadow-sm">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="px-5 py-3">Contrato</th>
                            <th class="px-5 py-3">Contratante</th>
                            <th class="px-5 py-3">Produto</th>
                            <th class="px-5 py-3">Local</th>
                            <th class="px-5 py-3">Município</th>
                            <th class="px-5 py-3">Status</th>
                            <th class="px-5 py-3">Ficha</th>
                            <th class="px-5 py-3">Ações</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200">
                        @forelse($records as $record)
                            <tr>
                                <td class="px-5 py-3">{{ $record->contrato ?: '-' }}</td>
                                <td class="px-5 py-3">{{ $record->contratante?->razao_social ?: ($record->razao_social ?: '-') }}</td>
                                <td class="px-5 py-3">{{ $record->produto?->nome ?: '-' }}</td>
                                <td class="px-5 py-3">{{ $record->local_nome ?: '-' }}</td>
                                <td class="px-5 py-3">{{ $record->municipio ?: '-' }}</td>
                                <td class="px-5 py-3">
                                    <span class="inline-flex rounded-full px-2 py-1 text-xs font-semibold {{ $record->status === 'ativo' ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-100 text-slate-700' }}">
                                        {{ $record->status }}
                                    </span>
                                </td>
                                <td class="px-5 py-3">
                                    @if($record->ficha_storage_path)
                                        <span class="text-xs text-emerald-700">Anexada</span>
                                    @else
                                        <span class="text-xs text-slate-500">Sem ficha</span>
                                    @endif
                                </td>
                                <td class="px-5 py-3">
                                    <div class="flex flex-wrap gap-2">
                                        <a href="{{ route('network-datacenter.servicos.show', $record) }}" class="inline-flex rounded-md border border-[#033151] bg-white px-3 py-1.5 text-xs font-semibold text-[#033151] hover:bg-slate-50">Abrir</a>
                                        <a href="{{ route('network-datacenter.servicos.edit', $record) }}" class="inline-flex rounded-md border border-slate-300 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50">Editar</a>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="px-5 py-6 text-center text-slate-500">Nenhum serviço encontrado.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="px-5 py-4">
                {{ $records->links('vendor.pagination.cocari') }}
            </div>
        </section>
    </div>
</x-app-layout>
