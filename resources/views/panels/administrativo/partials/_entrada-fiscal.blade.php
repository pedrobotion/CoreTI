<section class="rounded-2xl border border-slate-200 bg-white shadow-sm">
    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 p-5 sm:px-6">
        <div class="flex items-center gap-3">
            <span class="grid h-9 w-9 shrink-0 place-items-center rounded-lg bg-amber-50 text-amber-600">
                <svg viewBox="0 0 24 24" class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M14 3H6a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9z"/><path d="M14 3v6h6M8 13h8M8 17h5"/></svg>
            </span>
            <div>
                <h2 class="text-base font-bold text-slate-900">Aguardando Entrada Fiscal</h2>
                <p class="mt-0.5 text-sm text-slate-500">Equipamentos de unidade aguardando a nota de entrada.</p>
            </div>
        </div>
        <span class="inline-flex items-center rounded-full bg-amber-50 px-2.5 py-1 text-xs font-semibold text-amber-700 ring-1 ring-inset ring-amber-200">{{ $pendingEntry->count() }} pendências</span>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full min-w-[720px] text-sm">
            <thead>
                <tr class="border-b border-slate-100 text-left text-xs font-semibold uppercase tracking-wide text-slate-400">
                    <th class="px-5 py-3 sm:px-6">Plaqueta</th><th class="px-5 py-3">Tipo</th><th class="px-5 py-3">Unidade/Setor</th>
                    <th class="px-5 py-3">Entrada</th>
                    <th class="px-5 py-3 text-right">Ação</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse($pendingEntry as $item)
                    <tr x-data="{ modalOpen: false }" class="transition hover:bg-slate-50">
                        <td class="px-5 py-3 font-mono text-xs text-slate-500 sm:px-6">{{ $item->plaqueta ?: '—' }}</td>
                        <td class="px-5 py-3 font-semibold text-slate-800">{{ $item->tipo_equipamento }}</td>
                        <td class="px-5 py-3 text-slate-600">{{ $item->unidade_setor }}</td>
                        <td class="px-5 py-3 text-slate-500">{{ optional($item->data_chegada ?? $item->created_at)->format('d/m/Y') ?: '—' }}</td>
                        <td class="px-5 py-3 text-right">
                            <button type="button" @click.stop="modalOpen = true" class="inline-flex items-center gap-1.5 rounded-lg border border-[#033151] bg-[#033151] px-3 py-2 text-xs font-semibold text-white transition hover:opacity-90">Preencher e concluir</button>
                            <div x-cloak x-show="modalOpen" x-transition.opacity class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 px-4 text-left" @keydown.escape.window="modalOpen=false" @click.self="modalOpen=false">
                                <div class="w-full max-w-2xl overflow-hidden rounded-2xl bg-white shadow-2xl" @click.stop @click.outside="modalOpen=false">
                                    <div class="flex items-center justify-between border-b border-slate-200 px-5 py-4">
                                        <h3 class="text-lg font-bold text-slate-900">Entrada fiscal | {{ $item->plaqueta }}</h3>
                                        <button type="button" @click.stop="modalOpen=false" class="text-sm font-semibold text-slate-600">Fechar</button>
                                    </div>
                                    <form x-data="notaEntradaForm(@js($item->nota_valor_entrada))" method="POST" enctype="multipart/form-data" action="{{ route('bancada-servicos.assets.administrative.process', $item) }}" class="grid gap-3 px-5 py-4 sm:grid-cols-2" @submit="beforeSubmit()">
                                        @csrf @method('PATCH')
                                        <input type="hidden" name="action" value="entrada">
                                        <div><label class="text-sm font-medium">Docto de Entrada</label><input name="nota_documento_entrada" value="{{ $item->nota_documento_entrada }}" class="mt-1 w-full rounded-md border-slate-300 text-sm"></div>
                                        <div><label class="text-sm font-medium">Número da nota</label><input name="nota_numero_entrada" value="{{ $item->nota_numero_entrada }}" class="mt-1 w-full rounded-md border-slate-300 text-sm"></div>
                                        <div><label class="text-sm font-medium">Data de Emissão</label><input type="date" name="data_emissao_entrada" value="{{ optional($item->data_emissao_entrada)->format('Y-m-d') }}" class="mt-1 w-full rounded-md border-slate-300 text-sm"></div>
                                        <div><label class="text-sm font-medium">Valor</label><input x-model="valorDisplay" @input="formatMoney()" type="text" inputmode="decimal" placeholder="0,00" class="mt-1 w-full rounded-md border-slate-300 text-sm"><input type="hidden" name="nota_valor_entrada" :value="valorRaw"></div>
                                        <div><label class="text-sm font-medium">Anexo</label><input type="file" name="nota_anexo_entrada" class="mt-1 w-full rounded-md border-slate-300 text-sm"></div>
                                        <div class="sm:col-span-2"><label class="text-sm font-medium">Observação</label><textarea name="nota_observacao_entrada" rows="3" maxlength="2000" placeholder="Divergência de valor, item faltando, nota corrigida, etc. (opcional)" class="mt-1 w-full rounded-md border-slate-300 text-sm">{{ $item->nota_observacao_entrada }}</textarea></div>
                                        <div class="sm:col-span-2 flex justify-end gap-2 border-t border-slate-200 pt-3"><button type="button" @click.stop="modalOpen=false" class="inline-flex min-h-10 items-center justify-center rounded-lg border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-700">Cancelar</button><button type="submit" class="inline-flex min-h-10 items-center justify-center rounded-lg border border-[#033151] bg-[#033151] px-4 text-sm font-semibold text-white transition hover:opacity-90">Concluir e devolver à Bancada</button></div>
                                    </form>
                                </div>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-6 py-10 text-center text-sm text-slate-400">Sem pendências de entrada no momento. 🎉</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</section>
