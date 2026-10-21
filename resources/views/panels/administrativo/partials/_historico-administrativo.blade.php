<section class="rounded-2xl border border-slate-200 bg-white shadow-sm">
    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 p-5 sm:px-6">
        <div class="flex items-center gap-3">
            <span class="grid h-9 w-9 shrink-0 place-items-center rounded-lg bg-slate-100 text-slate-500">
                <svg viewBox="0 0 24 24" class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 8v4l3 2M21 12a9 9 0 1 1-9-9" stroke-linecap="round"/></svg>
            </span>
            <div>
                <h2 class="text-base font-bold text-slate-900">Histórico Administrativo</h2>
                <p class="mt-0.5 text-sm text-slate-500">Ações registradas nas etapas fiscais.</p>
            </div>
        </div>
        <span class="inline-flex items-center rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-600 ring-1 ring-inset ring-slate-200">{{ $administrativeHistory->count() }} eventos</span>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full min-w-[980px] text-sm">
            <thead><tr class="border-b border-slate-100 text-left text-xs font-semibold uppercase tracking-wide text-slate-400"><th class="px-5 py-3 sm:px-6">Data/Hora</th><th class="px-5 py-3">Ação</th><th class="px-5 py-3">Plaqueta</th><th class="px-5 py-3">De</th><th class="px-5 py-3">Para</th><th class="px-5 py-3">Usuário</th></tr></thead>
            <tbody class="divide-y divide-slate-100">
                @forelse($administrativeHistory as $item)
                    @php
                        $actionLabel = match ($item->action) {
                            'entrada_fiscal_realizada' => 'Entrada fiscal realizada',
                            'terceiro_enviado' => 'Terceiro enviado',
                            'terceiro_informacoes_reparo_aprovado' => 'Informações do reparo aprovadas',
                            'terceiro_informacoes_reparo_reprovado' => 'Informações do reparo reprovadas',
                            'terceiro_retorno_fisico_registrado' => 'Retorno físico registrado',
                            'terceiro_retorno_positivo' => 'Retorno positivo (legado)',
                            'terceiro_retorno_negativo' => 'Retorno negativo (legado)',
                            'requisicao_cd_realizada' => 'Requisição CD realizada',
                            'pedido_internet_realizado' => 'Pedido internet realizado',
                            'pedido_dell_realizado' => 'Pedido Dell realizado',
                            'nota_saida_emitida' => 'Nota de saída emitida',
                            default => $item->action,
                        };
                    @endphp
                    <tr class="transition hover:bg-slate-50"><td class="px-5 py-3 text-slate-500 sm:px-6">{{ optional($item->created_at)->format('d/m/Y H:i') ?: '—' }}</td><td class="px-5 py-3 font-semibold text-slate-800">{{ $actionLabel }}</td><td class="px-5 py-3 font-mono text-xs text-slate-500">{{ $item->equipment?->plaqueta ?: '—' }}</td><td class="px-5 py-3 text-slate-600">{{ $item->previous_status ?: '—' }}</td><td class="px-5 py-3 text-slate-600">{{ $item->new_status ?: '—' }}</td><td class="px-5 py-3 text-slate-600">{{ $item->performer?->name ?: 'Sistema' }}</td></tr>
                @empty
                    <tr><td colspan="6" class="px-6 py-10 text-center text-sm text-slate-400">Sem histórico administrativo ainda.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</section>
