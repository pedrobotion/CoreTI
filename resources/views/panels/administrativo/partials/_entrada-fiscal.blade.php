<section class="rounded-lg border-2 border-amber-300 bg-amber-50 p-5 shadow-sm">
    <div class="mb-3 flex items-center justify-between">
        <h2 class="text-base font-bold text-amber-900">Aguardando Entrada Fiscal</h2>
        <span class="rounded-md bg-amber-200 px-2 py-1 text-xs font-semibold text-amber-900">{{ $pendingEntry->count() }} pendências</span>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full min-w-[860px] text-sm">
            <thead class="bg-slate-900 text-left text-xs font-semibold uppercase text-white">
                <tr>
                    <th class="px-3 py-2">Plaqueta</th><th class="px-3 py-2">Tipo</th><th class="px-3 py-2">Unidade/Setor</th>
                    <th class="px-3 py-2">Docto de Entrada</th><th class="px-3 py-2">Número</th><th class="px-3 py-2">Data Emissão</th>
                    <th class="px-3 py-2">Valor</th><th class="px-3 py-2">Ação</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-200">
                @forelse($pendingEntry as $item)
                    <tr x-data="{ modalOpen: false }" class="hover:bg-slate-100">
                        <td class="px-3 py-2">{{ $item->plaqueta }}</td><td class="px-3 py-2">{{ $item->tipo_equipamento }}</td><td class="px-3 py-2">{{ $item->unidade_setor }}</td>
                        <td class="px-3 py-2">{{ $item->nota_documento_entrada ?: '-' }}</td><td class="px-3 py-2">{{ $item->nota_numero_entrada ?: '-' }}</td>
                        <td class="px-3 py-2">{{ optional($item->data_emissao_entrada)->format('d/m/Y') ?: '-' }}</td>
                        <td class="px-3 py-2">{{ $item->nota_valor_entrada !== null ? 'R$ ' . number_format((float)$item->nota_valor_entrada, 2, ',', '.') : '-' }}</td>
                        <td class="px-3 py-2">
                            <button type="button" @click.stop="modalOpen = true" class="rounded-md border border-slate-900 bg-slate-900 px-3 py-2 text-xs font-semibold text-white">Preencher e concluir</button>
                            <div x-cloak x-show="modalOpen" x-transition.opacity class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 px-4" @keydown.escape.window="modalOpen=false" @click.self="modalOpen=false">
                                <div class="w-full max-w-2xl overflow-hidden rounded-lg bg-white shadow-2xl" @click.stop @click.outside="modalOpen=false">
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
                                        <div class="sm:col-span-2 flex justify-end gap-2 border-t border-slate-200 pt-3"><button type="button" @click.stop="modalOpen=false" class="inline-flex min-h-10 items-center justify-center rounded-md border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-700">Cancelar</button><button type="submit" class="inline-flex min-h-10 items-center justify-center rounded-md border border-slate-900 bg-slate-900 px-4 text-sm font-semibold text-white">Concluir e devolver à Bancada</button></div>
                                    </form>
                                </div>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="px-3 py-4 text-center text-slate-500">Sem pendências de entrada no momento.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</section>
