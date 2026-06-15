<section class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
    <div class="mb-3 flex items-center justify-between">
        <h2 class="text-base font-bold text-slate-900">Histórico Administrativo</h2>
        <span class="rounded-md bg-slate-100 px-2 py-1 text-xs font-semibold text-slate-700">{{ $administrativeHistory->count() }} eventos</span>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full min-w-[980px] text-sm">
            <thead class="bg-slate-900 text-left text-xs font-semibold uppercase text-white"><tr><th class="px-3 py-2">Data/Hora</th><th class="px-3 py-2">Ação</th><th class="px-3 py-2">Plaqueta</th><th class="px-3 py-2">De</th><th class="px-3 py-2">Para</th><th class="px-3 py-2">Usuário</th></tr></thead>
            <tbody class="divide-y divide-slate-200">
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
                    <tr><td class="px-3 py-2">{{ optional($item->created_at)->format('d/m/Y H:i') ?: '-' }}</td><td class="px-3 py-2">{{ $actionLabel }}</td><td class="px-3 py-2">{{ $item->equipment?->plaqueta ?: '-' }}</td><td class="px-3 py-2">{{ $item->previous_status ?: '-' }}</td><td class="px-3 py-2">{{ $item->new_status ?: '-' }}</td><td class="px-3 py-2">{{ $item->performer?->name ?: 'Sistema' }}</td></tr>
                @empty
                    <tr><td colspan="6" class="px-3 py-4 text-center text-slate-500">Sem histórico administrativo ainda.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</section>
