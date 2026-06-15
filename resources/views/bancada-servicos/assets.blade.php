<x-app-layout>
    @include('bancada-servicos.partials.label-printer')
    <div class="mx-auto max-w-7xl space-y-6">
        <div>
    </x-app-layout>

    @include('bancada-servicos.partials.documents-modal')
        </div>

        <div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_360px]">
            <section x-data="bancadaLabelPrinter()" class="rounded-lg border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <div class="border-b border-slate-200 p-5 dark:border-slate-800">
                    <form method="GET" class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-2">
                        <select name="status" class="toolbar-select text-sm w-full min-w-0">
                            <option value="Ativos" @selected($status === 'Ativos')>Ativos</option>
                            <option value="Todos" @selected($status === 'Todos')>Todos</option>
                            @foreach($statuses as $option)
                                <option value="{{ $option }}" @selected($status === $option)>{{ $option }}</option>
                            @endforeach
                        </select>
                        <input name="q" value="{{ $search }}" placeholder="Buscar tipo, plaqueta, unidade, observação ou TIC" class="toolbar-input rounded-md border-slate-300 text-sm w-full min-w-0 md:col-span-2">
                        <div class="flex gap-2 w-full min-w-0">
                            <button class="toolbar-btn-primary inline-flex items-center justify-center rounded-md border border-[#033151] bg-[#033151] px-4 text-sm font-semibold text-white w-full">Buscar</button>
                            <a href="{{ route('bancada-servicos.assets') }}" class="toolbar-btn inline-flex items-center justify-center rounded-md border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-700 w-full">Limpar</a>
                        </div>
                    </form>
                </div>

                <div class="w-full max-w-full overflow-x-auto">
                    <table class="w-full min-w-[1150px] text-sm">
                    <thead class="bg-[#033151] text-left text-xs font-semibold uppercase text-white">
                        <tr>
                            <th class="px-4 py-3">Tipo</th>
                            <th class="px-4 py-3">Plaqueta</th>
                            <th class="px-4 py-3">Unidade</th>
                            <th class="px-4 py-3">Chegada</th>
                            <th class="px-4 py-3">Saída</th>
                            <th class="px-4 py-3">Status</th>
                            <th class="px-4 py-3">TIC</th>
                            <th class="px-4 py-3">Observação</th>
                            <th class="px-4 py-3">Ações</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200 dark:divide-slate-800">
                        @forelse($assets as $asset)
                            <tr class="odd:bg-slate-50 even:bg-white dark:odd:bg-slate-950 dark:even:bg-slate-900">
                                <td class="px-4 py-3">{{ $asset->tipo_equipamento }}</td>
                                <td class="px-4 py-3">{{ $asset->plaqueta }}</td>
                                <td class="px-4 py-3">{{ $asset->unidade_setor }}</td>
                                <td class="px-4 py-3">{{ optional($asset->data_chegada)->format('d/m/Y') }}</td>
                                <td class="px-4 py-3">{{ optional($asset->data_saida)->format('d/m/Y') ?: '-' }}</td>
                                <td class="px-4 py-3">
                                    <form method="POST" action="{{ route('bancada-servicos.assets.status', $asset) }}" class="flex items-center gap-2">
                                        @csrf
                                        @method('PATCH')
                                        <select name="status" class="rounded-md border-slate-300 text-xs">
                                            @foreach($statusOptions as $option)
                                                <option value="{{ $option }}" @selected($asset->status === $option)>{{ $option }}</option>
                                            @endforeach
                                        </select>
                                        <button class="rounded-md border border-slate-300 px-2 py-1 text-xs font-semibold">Atualizar</button>
                                    </form>
                                </td>
                                <td class="px-4 py-3">
                                    @if($asset->tic)
                                        <a href="https://cocari.atlassian.net/browse/{{ $asset->tic }}" target="_blank" class="text-blue-600 hover:text-blue-800">{{ $asset->tic }}</a>
                                    @else
                                        -
                                    @endif
                                </td>
                                <td class="px-4 py-3">{{ $asset->observacao ?: '-' }}</td>
                                <td class="px-4 py-3">
                                    <button type="button" @click="window.dispatchEvent(new CustomEvent('open-documents',{detail:{url: @js(route('bancada-servicos.assets.documents', $asset)), plaqueta: @js($asset->plaqueta)}}))" class="inline-flex items-center gap-2 rounded-md border border-slate-300 px-2 py-1 text-sm text-slate-700 hover:bg-slate-50" title="Ver documentos">Documentos</button>
                                    <button
                                        type="button"
                                        @click="print(@js($asset->unidade_setor), @js(optional($asset->data_chegada)->format('d/m/Y')), @js($asset->observacao))"
                                        class="ml-2 text-emerald-700 hover:text-emerald-900"
                                    >
                                        Imprimir etiqueta
                                    </button>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="9" class="px-4 py-6 text-center text-slate-500">Nenhum equipamento encontrado.</td></tr>
                        @endforelse
                    </tbody>
                    </table>
                </div>

                <div class="flex flex-col gap-3 border-t border-slate-200 px-5 py-4 sm:flex-row sm:items-center sm:justify-between dark:border-slate-800">
                    <form method="GET" class="flex items-center gap-2 text-sm text-slate-600">
                        <span>Itens por página:</span>
                        <input type="hidden" name="q" value="{{ $search }}">
                        <input type="hidden" name="status" value="{{ $status }}">
                        <select name="per_page" onchange="this.form.submit()" class="toolbar-select text-sm">
                            <option value="10" @selected($perPage === 10)>10</option>
                            <option value="25" @selected($perPage === 25)>25</option>
                            <option value="50" @selected($perPage === 50)>50</option>
                        </select>
                    </form>
                    {{ $assets->links() }}
                </div>
            </section>

            <aside class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900 xl:sticky xl:top-6 xl:self-start">
                <h2 class="text-base font-semibold text-slate-950 dark:text-white">Adicionar equipamento</h2>
                <form method="POST" action="{{ route('bancada-servicos.assets.store') }}" class="mt-4 grid gap-3">
                    @csrf
                    <input name="tipo_equipamento" placeholder="Tipo do equipamento" required class="rounded-md border-slate-300 text-sm">
                    <input name="plaqueta" placeholder="Plaqueta" required class="rounded-md border-slate-300 text-sm">
                    <input name="unidade_setor" list="bancada-unidades-setores" placeholder="Unidade/Setor" required class="rounded-md border-slate-300 text-sm">
                    <datalist id="bancada-unidades-setores">
                        @foreach($unitOptions as $unitOption)
                            <option value="{{ $unitOption }}"></option>
                        @endforeach
                    </datalist>
                    <input type="date" name="data_chegada" value="{{ now()->toDateString() }}" required class="rounded-md border-slate-300 text-sm">
                    <select name="status" class="rounded-md border-slate-300 text-sm">
                        @foreach($statusOptions as $option)
                            <option value="{{ $option }}" @selected($option === 'Em bancada')>{{ $option }}</option>
                        @endforeach
                    </select>
                    <input name="tic" placeholder="TIC-12345 (opcional)" class="rounded-md border-slate-300 text-sm">
                    <textarea name="observacao" rows="3" placeholder="Observação (opcional)" class="rounded-md border-slate-300 text-sm"></textarea>
                    <button type="submit" class="mt-1 inline-flex min-h-10 items-center justify-center rounded-md border border-[#033151] bg-[#033151] px-4 text-sm font-semibold text-white">Salvar</button>
                </form>
            </aside>
        </div>
    </div>
</x-app-layout>
