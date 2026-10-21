<section class="rounded-2xl border border-slate-200 bg-white shadow-sm">
    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 p-5 sm:px-6">
        <div class="flex items-center gap-3">
            <span class="grid h-9 w-9 shrink-0 place-items-center rounded-lg bg-rose-50 text-rose-600">
                <svg viewBox="0 0 24 24" class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M10 3H6a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-8"/><path d="M14 3v6h6M21 3l-7 7"/></svg>
            </span>
            <div>
                <h2 class="text-base font-bold text-slate-900">Nota de Saída (Unidades)</h2>
                <p class="mt-0.5 text-sm text-slate-500">Equipamentos prontos para entrega aguardando emissão da nota.</p>
            </div>
        </div>
        <span class="inline-flex items-center rounded-full bg-rose-50 px-2.5 py-1 text-xs font-semibold text-rose-700 ring-1 ring-inset ring-rose-200">{{ ($pendingOutboundNote ?? collect())->count() }} pendências</span>
    </div>

    <div class="p-5 sm:px-6">
        <form method="GET" action="{{ route('administrativo.nota-saida') }}" class="flex flex-wrap items-center gap-2">
            <label class="flex h-11 min-w-[240px] flex-1 items-center gap-3 rounded-xl border border-slate-200 bg-slate-50 px-4 focus-within:border-[#033151] focus-within:ring-2 focus-within:ring-[#033151]/15">
                <svg viewBox="0 0 24 24" class="h-5 w-5 text-slate-400" fill="none" stroke="currentColor" stroke-width="1.9"><circle cx="11" cy="11" r="7"/><path d="M21 21l-4-4" stroke-linecap="round"/></svg>
                <input type="text" name="nota_plaqueta" value="{{ $notaSaidaSearch ?? request('nota_plaqueta', '') }}" placeholder="Pesquisar pela plaqueta…" class="min-w-0 flex-1 border-0 bg-transparent p-0 text-sm text-slate-800 placeholder:text-slate-400 focus:ring-0">
            </label>
            <button type="submit" class="inline-flex h-11 items-center justify-center rounded-xl border border-[#033151] bg-[#033151] px-5 text-sm font-semibold text-white transition hover:opacity-90">Buscar</button>
            <a href="{{ route('administrativo.nota-saida') }}" class="inline-flex h-11 items-center justify-center rounded-xl border border-slate-200 bg-white px-4 text-sm font-semibold text-slate-700 transition hover:border-slate-300">Limpar</a>
        </form>
    </div>

    <div class="overflow-x-auto">
        <table class="w-full min-w-[960px] text-sm">
            <thead>
                <tr class="border-b border-slate-100 text-left text-xs font-semibold uppercase tracking-wide text-slate-400">
                    <th class="px-5 py-3 sm:px-6">Plaqueta</th>
                    <th class="px-5 py-3">Tipo</th>
                    <th class="px-5 py-3">Unidade</th>
                    <th class="px-5 py-3">Chegada</th>
                    <th class="px-5 py-3 text-right">Ação</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse(($pendingOutboundNote ?? collect()) as $item)
                    <tr x-data="{ modalOpen: false }" class="transition hover:bg-slate-50">
                        <td class="px-5 py-3 font-mono text-xs text-slate-500 sm:px-6">{{ $item->plaqueta ?: '—' }}</td>
                        <td class="px-5 py-3 font-semibold text-slate-800">{{ $item->tipo_equipamento }}</td>
                        <td class="px-5 py-3 text-slate-600">{{ $item->unidade_setor }}</td>
                        <td class="px-5 py-3 text-slate-500">{{ optional($item->data_chegada)->format('d/m/Y') ?: '—' }}</td>
                        <td class="px-5 py-3 text-right">
                            <button type="button" @click="modalOpen=true" class="inline-flex items-center justify-center gap-1.5 whitespace-nowrap rounded-lg border border-[#033151] bg-[#033151] px-3 py-2 text-xs font-semibold text-white transition hover:opacity-90"><svg viewBox="0 0 24 24" class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 12h14M13 6l6 6-6 6" stroke-linecap="round" stroke-linejoin="round"/></svg>Emitir nota de saída</button>

                            <div x-cloak x-show="modalOpen" x-transition.opacity class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 px-4 text-left" @keydown.escape.window="modalOpen=false">
                                <div class="w-full max-w-3xl overflow-hidden rounded-2xl bg-white shadow-2xl" @click.outside="modalOpen=false">
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
                                            <button type="button" @click="modalOpen=false" class="inline-flex min-h-10 items-center justify-center rounded-lg border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-700 hover:bg-slate-50">Cancelar</button>
                                            <button class="inline-flex min-h-10 items-center justify-center rounded-lg border border-[#033151] bg-[#033151] px-4 text-sm font-semibold text-white transition hover:opacity-90">Registrar nota de saída</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-6 py-10 text-center text-sm text-slate-400">Sem pendências de nota de saída. 🎉</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</section>
