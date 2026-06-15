<section class="rounded-lg border-2 border-indigo-200 bg-indigo-50 p-5 shadow-sm">
    <div class="mb-3 flex items-center justify-between gap-3">
        <div>
            <h2 class="text-base font-bold text-indigo-900">Nota de Saída (Unidades)</h2>
            <p class="mt-1 text-xs text-indigo-900/80">Pesquise pela plaqueta para localizar rapidamente o equipamento.</p>
        </div>
        <span class="rounded-md bg-indigo-200 px-2 py-1 text-xs font-semibold text-indigo-900">{{ ($pendingOutboundNote ?? collect())->count() }} pendências</span>
    </div>

    <form method="GET" action="{{ route('administrativo.nota-saida') }}" class="mb-4 flex flex-wrap items-center gap-2">
        <input
            type="text"
            name="nota_plaqueta"
            value="{{ $notaSaidaSearch ?? request('nota_plaqueta', '') }}"
            placeholder="Pesquisar plaqueta"
            class="h-10 min-w-[240px] flex-1 rounded-md border-slate-300 text-sm"
        >
        <button type="submit" class="inline-flex h-10 items-center justify-center rounded-md border border-[#033151] bg-[#033151] px-4 text-sm font-semibold text-white shadow-sm transition hover:opacity-90">Buscar</button>
        <a href="{{ route('administrativo.nota-saida') }}" class="inline-flex h-10 items-center justify-center rounded-md border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50">Limpar</a>
    </form>

    <div class="overflow-x-auto">
        <table class="w-full min-w-[1060px] text-sm">
            <thead class="bg-slate-900 text-left text-xs font-semibold uppercase text-white">
                <tr>
                    <th class="px-3 py-2">Plaqueta</th>
                    <th class="px-3 py-2">Tipo</th>
                    <th class="px-3 py-2">Unidade</th>
                    <th class="px-3 py-2">Chegada</th>
                    <th class="px-3 py-2">Ação</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-200">
                @forelse(($pendingOutboundNote ?? collect()) as $item)
                    <tr x-data="{ modalOpen: false }">
                        <td class="px-3 py-2">{{ $item->plaqueta }}</td>
                        <td class="px-3 py-2">{{ $item->tipo_equipamento }}</td>
                        <td class="px-3 py-2">{{ $item->unidade_setor }}</td>
                        <td class="px-3 py-2">{{ optional($item->data_chegada)->format('d/m/Y') ?: '-' }}</td>
                        <td class="px-3 py-2">
                            <button type="button" @click="modalOpen=true" class="rounded-md border border-[#033151] bg-[#033151] px-3 py-2 text-xs font-semibold text-white shadow-sm transition hover:opacity-90">Emitir nota de saída</button>

                            <div x-cloak x-show="modalOpen" x-transition.opacity class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 px-4" @keydown.escape.window="modalOpen=false">
                                <div class="w-full max-w-3xl overflow-hidden rounded-lg bg-white shadow-2xl" @click.outside="modalOpen=false">
                                    <div class="flex items-center justify-between border-b border-slate-200 px-5 py-4">
                                        <h3 class="text-lg font-bold text-slate-900">Nota de saída | {{ $item->plaqueta }}</h3>
                                        <button type="button" @click="modalOpen=false" class="rounded-md border border-slate-300 bg-white px-3 py-1.5 text-sm font-semibold text-slate-700 hover:bg-slate-50">Fechar</button>
                                    </div>

                                    <form method="POST" enctype="multipart/form-data" action="{{ route('bancada-servicos.assets.administrative.process', $item) }}" class="grid gap-3 px-5 py-4 sm:grid-cols-2">
                                        @csrf
                                        @method('PATCH')
                                        <input type="hidden" name="action" value="nota_emitida">

                                        <div>
                                            <label class="text-sm font-medium">Docto de entrada</label>
                                            <div class="mt-1 rounded-md border border-slate-200 bg-slate-50 px-3 py-2 text-sm">{{ $item->nota_documento_entrada ?: '-' }}</div>
                                        </div>
                                        <div>
                                            <label class="text-sm font-medium">Número da Nota de Entrada</label>
                                            <div class="mt-1 rounded-md border border-slate-200 bg-slate-50 px-3 py-2 text-sm">{{ $item->nota_numero_entrada ?: '-' }}</div>
                                        </div>
                                        <div class="sm:col-span-2">
                                            <label class="text-sm font-medium">Valor da nota de entrada</label>
                                            <div class="mt-1 rounded-md border border-slate-200 bg-slate-50 px-3 py-2 text-sm">{{ $item->nota_valor_entrada !== null ? 'R$ ' . number_format((float) $item->nota_valor_entrada, 2, ',', '.') : '-' }}</div>
                                        </div>
                                        <div>
                                            <label class="text-sm font-medium">Número da nota de saída</label>
                                            <input name="nota_numero_saida" required class="mt-1 w-full rounded-md border-slate-300 text-sm">
                                        </div>
                                        <div class="sm:col-span-2">
                                            <label class="text-sm font-medium">Anexo da nota de saída</label>
                                            <input type="file" name="nota_anexo_saida" class="mt-1 w-full rounded-md border-slate-300 text-sm">
                                        </div>
                                        <div class="sm:col-span-2 flex justify-end gap-2 border-t border-slate-200 pt-3">
                                            <button type="button" @click="modalOpen=false" class="inline-flex min-h-10 items-center justify-center rounded-md border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-700 hover:bg-slate-50">Cancelar</button>
                                            <button class="inline-flex min-h-10 items-center justify-center rounded-md border border-[#033151] bg-[#033151] px-4 text-sm font-semibold text-white shadow-sm transition hover:opacity-90">Registrar nota de saída</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-3 py-4 text-center text-slate-500">Sem pendências de nota de saída.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</section>
