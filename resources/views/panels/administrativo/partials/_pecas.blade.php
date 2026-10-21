<section class="rounded-2xl border border-slate-200 bg-white shadow-sm" x-data="{ all: false }">
    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 p-5 sm:px-6">
        <div class="flex items-center gap-3">
            <span class="grid h-9 w-9 shrink-0 place-items-center rounded-lg bg-violet-50 text-violet-600">
                <svg viewBox="0 0 24 24" class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M12 3l8 4.5v9L12 21l-8-4.5v-9z" stroke-linejoin="round"/><path d="M4 7.5l8 4.5 8-4.5M12 12v9"/></svg>
            </span>
            <div>
                <h2 class="text-base font-bold text-slate-900">Pendências de Peças</h2>
                <p class="mt-0.5 text-sm text-slate-500">Peças solicitadas (CD / Internet / Dell) aguardando requisição.</p>
            </div>
        </div>
        <span class="inline-flex items-center rounded-full bg-violet-50 px-2.5 py-1 text-xs font-semibold text-violet-700 ring-1 ring-inset ring-violet-200">{{ ($pendingParts ?? collect())->count() }} pendências</span>
    </div>
    <form method="POST" action="{{ route('administrativo.pecas.bulk') }}" @submit="if (![...$el.querySelectorAll('input[name=\'ids[]\']:checked')].length) { $event.preventDefault(); alert('Selecione ao menos uma peça.'); }">
        @csrf
        @method('PATCH')
        <div class="overflow-x-auto">
            <table class="w-full min-w-[960px] text-sm">
                <thead>
                    <tr class="border-b border-slate-100 text-left text-xs font-semibold uppercase tracking-wide text-slate-400">
                        <th class="px-5 py-3 sm:px-6"><input type="checkbox" class="rounded border-slate-300 text-[#033151] focus:ring-[#033151]" x-model="all" @change="$root.querySelectorAll('input[name=\'ids[]\']:not(:disabled)').forEach(c => c.checked = all)"></th>
                        <th class="px-5 py-3">Plaqueta</th><th class="px-5 py-3">Tipo</th><th class="px-5 py-3">Unidade/Setor</th><th class="px-5 py-3">Peça</th><th class="px-5 py-3">Qtd</th><th class="px-5 py-3">Origem</th><th class="px-5 py-3">Status fluxo</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse(($pendingParts ?? collect()) as $item)
                        @php $jaRequisitado = ($item->peca_fluxo_status ?? '') === 'requisicao_realizada'; @endphp
                        <tr class="transition hover:bg-slate-50 {{ $jaRequisitado ? 'bg-emerald-50/40' : '' }}">
                            <td class="px-5 py-3 sm:px-6">
                                <input type="checkbox" class="rounded border-slate-300 text-[#033151] focus:ring-[#033151] disabled:opacity-40" name="ids[]" value="{{ $item->id }}" @checked(false) @disabled($jaRequisitado) title="{{ $jaRequisitado ? 'Já requisitado' : '' }}">
                            </td>
                            <td class="px-5 py-3 font-mono text-xs text-slate-500">{{ $item->plaqueta ?: '—' }}</td>
                            <td class="px-5 py-3 font-semibold text-slate-800">{{ $item->tipo_equipamento }}</td>
                            <td class="px-5 py-3 text-slate-600">{{ $item->unidade_setor }}</td>
                            <td class="px-5 py-3 text-slate-700">{{ $item->peca_nome ?: '—' }}</td>
                            <td class="px-5 py-3 tabular-nums text-slate-600">{{ $item->peca_quantidade ?: '—' }}</td>
                            <td class="px-5 py-3 text-slate-500">{{ $item->peca_origem ?: '—' }}</td>
                            <td class="px-5 py-3">
                                @if($jaRequisitado)
                                    <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-semibold text-emerald-700 ring-1 ring-inset ring-emerald-200"><span class="h-1.5 w-1.5 rounded-full bg-current"></span>Requisitado</span>
                                @else
                                    <span class="inline-flex items-center gap-1.5 rounded-full bg-amber-50 px-2.5 py-1 text-xs font-semibold text-amber-700 ring-1 ring-inset ring-amber-200"><span class="h-1.5 w-1.5 rounded-full bg-current"></span>{{ $item->peca_fluxo_status ?: 'Aguardando' }}</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="px-6 py-10 text-center text-sm text-slate-400">Sem pendências de peças. 🎉</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if(($pendingParts ?? collect())->isNotEmpty())
            <div class="flex flex-wrap items-center justify-end gap-3 border-t border-slate-100 p-4 sm:px-6">
                <span class="text-xs text-slate-500">Selecione as peças já solicitadas ao CD e confirme em lote.</span>
                <button type="submit" class="inline-flex min-h-10 items-center justify-center rounded-lg border border-[#033151] bg-[#033151] px-4 text-sm font-semibold text-white transition hover:opacity-90">Marcar como solicitado ao CD</button>
            </div>
        @endif
    </form>
</section>
