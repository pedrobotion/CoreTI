<x-app-layout>
    <div class="mx-auto max-w-7xl space-y-6">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <a href="{{ route('impressoras.index') }}" class="text-xs font-semibold uppercase tracking-wide text-slate-500 underline">← Impressoras</a>
                <h1 class="mt-2 text-2xl font-bold text-slate-950 dark:text-white">{{ $impressora->model ?: 'Impressora' }}</h1>
                <p class="mt-1 text-sm text-slate-500">
                    {{ $impressora->manufacturer ?: '—' }} · serial <span class="font-mono">{{ $impressora->serial_number ?: '—' }}</span>
                </p>
            </div>
            <div class="text-right text-sm">
                <p class="font-semibold text-slate-900 dark:text-white">{{ $impressora->local }}</p>
                @unless ($impressora->organization_unit_id)
                    <p class="mt-1 text-xs text-slate-500">Sem unidade no CoreTI. O portal informa “{{ $impressora->insight_location ?: '—' }}”.</p>
                @endunless
            </div>
        </div>

        @if ($semContatoRecente)
            <div class="rounded-lg border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-900">
                <p class="font-semibold">Sem contato há {{ \App\Models\Printer::DIAS_SEM_CONTATO_ALERTA }} dias ou mais.</p>
                <p class="mt-1 text-amber-800">
                    Pode ser o equipamento desligado ou o coletor da unidade fora do ar. De qualquer forma, o contador
                    parou de ser atualizado — e é ele que sustenta a fatura.
                </p>
            </div>
        @endif

        <section class="grid gap-5 xl:grid-cols-[1.4fr_1fr]">
            <div class="rounded-lg border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <h2 class="text-lg font-bold text-slate-950 dark:text-white">Suprimentos</h2>
                <p class="mt-1 text-sm text-slate-500">Do que está acabando primeiro para o que tem mais folga.</p>

                <div class="mt-5 space-y-4">
                    @forelse ($suprimentos as $suprimento)
                        <div class="rounded-lg border border-slate-200 p-3 dark:border-slate-700">
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <div>
                                    <p class="text-sm font-semibold text-slate-800 dark:text-slate-100">{{ $suprimento->descricao ?: $suprimento->tipo }}</p>
                                    <p class="mt-0.5 text-xs text-slate-500">
                                        {{ $suprimento->tipo_label }}@if($suprimento->cor) · {{ $suprimento->cor }}@endif
                                        @if($suprimento->sku) · SKU {{ $suprimento->sku }}@endif
                                    </p>
                                </div>
                                <x-impressoras.nivel :supply="$suprimento" />
                            </div>
                            <div class="mt-2 grid gap-2 text-xs text-slate-500 sm:grid-cols-3">
                                <span>Dias restantes: <strong class="text-slate-700 dark:text-slate-200">{{ $suprimento->days_left ?? 'sem projeção' }}</strong></span>
                                <span>Páginas restantes: <strong class="text-slate-700 dark:text-slate-200">{{ $suprimento->pages_left !== null ? number_format($suprimento->pages_left, 0, ',', '.') : 'sem projeção' }}</strong></span>
                                <span>Última leitura: <strong class="text-slate-700 dark:text-slate-200">{{ $suprimento->last_read?->local()->format('d/m/Y H:i') ?? '—' }}</strong></span>
                            </div>
                        </div>
                    @empty
                        <p class="py-6 text-center text-sm text-slate-500">Nenhum suprimento sincronizado para esta impressora.</p>
                    @endforelse
                </div>
            </div>

            <div class="space-y-5">
                <div class="rounded-lg border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    <h2 class="text-lg font-bold text-slate-950 dark:text-white">Identificação e rede</h2>
                    <dl class="mt-4 space-y-2 text-sm">
                        @foreach ([
                            'Patrimônio' => $impressora->asset_number,
                            'Plaqueta' => $impressora->sticker_number,
                            'SKU' => $impressora->sku,
                            'IP' => $impressora->ip_address,
                            'Hostname' => $impressora->hostname,
                            'MAC' => $impressora->mac_address,
                            'Firmware' => $impressora->firmware,
                            'Monitoramento' => $impressora->monitor_label,
                            'Zona no portal' => $impressora->insight_zone,
                            'Descoberta em' => $impressora->discovery_date?->local()->format('d/m/Y'),
                            'Última sincronização' => $impressora->last_synced_at?->local()->format('d/m/Y H:i'),
                        ] as $rotulo => $valor)
                            <div class="flex justify-between gap-3 border-b border-slate-100 pb-2 dark:border-slate-800">
                                <dt class="text-slate-500">{{ $rotulo }}</dt>
                                <dd class="text-right font-medium text-slate-800 dark:text-slate-100">{{ filled($valor) ? $valor : '—' }}</dd>
                            </div>
                        @endforeach
                    </dl>
                </div>

                <div class="rounded-lg border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    <h2 class="text-lg font-bold text-slate-950 dark:text-white">Contador mais recente</h2>
                    @if ($contador)
                        <p class="mt-1 text-xs text-slate-500">Data contábil {{ $contador->billing_date?->format('d/m/Y') ?? '—' }}</p>
                        <div class="mt-4 grid grid-cols-2 gap-3 text-sm">
                            @foreach ([
                                'Mono A4' => $contador->a4_mono,
                                'Color A4' => $contador->a4_colour,
                                'Páginas mono' => $contador->mono_pages,
                                'Páginas color' => $contador->colour_pages,
                                'Digitalizações' => $contador->scans,
                                'Ciclos do motor' => $contador->engine_cycles,
                            ] as $rotulo => $valor)
                                <div class="rounded-md bg-slate-50 p-3 dark:bg-slate-800/60">
                                    <p class="text-xs uppercase text-slate-500">{{ $rotulo }}</p>
                                    <p class="mt-1 text-lg font-bold text-slate-900 dark:text-white">{{ $valor !== null ? number_format($valor, 0, ',', '.') : '—' }}</p>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <p class="mt-4 text-sm text-slate-500">Nenhuma leitura de contador sincronizada.</p>
                    @endif
                </div>
            </div>
        </section>

        <section class="rounded-lg border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <div class="border-b border-slate-200 px-6 py-5 dark:border-slate-800">
                <h2 class="text-lg font-bold text-slate-950 dark:text-white">Pedidos de consumível</h2>
                <p class="mt-1 text-sm text-slate-500">
                    Últimos {{ $pedidos->count() }}. O portal deixa todos como <span class="font-mono text-xs">NEW</span>;
                    quem diz se o pedido foi atendido é a data de substituição.
                </p>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full min-w-[820px] text-sm">
                    <thead class="bg-slate-50 text-left text-xs font-semibold uppercase text-slate-500 dark:bg-slate-800/60">
                        <tr>
                            <th class="px-6 py-3">Solicitado</th>
                            <th class="px-6 py-3">Consumível</th>
                            <th class="px-6 py-3">Motivo</th>
                            <th class="px-6 py-3">Nível no pedido</th>
                            <th class="px-6 py-3">Substituído</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200 dark:divide-slate-800">
                        @forelse ($pedidos as $pedido)
                            <tr>
                                <td class="px-6 py-3">{{ $pedido->solicitado_em?->local()->format('d/m/Y H:i') ?? '—' }}</td>
                                <td class="px-6 py-3">
                                    {{ $pedido->consumivel_descricao ?: $pedido->consumivel_tipo ?: '—' }}
                                    @if ($pedido->consumivel_cor)
                                        <div class="text-xs text-slate-400">{{ $pedido->consumivel_cor }}</div>
                                    @endif
                                </td>
                                <td class="px-6 py-3 text-xs">{{ $pedido->motivo ?: '—' }}</td>
                                <td class="px-6 py-3">{{ $pedido->nivel_no_pedido !== null ? $pedido->nivel_no_pedido.'%' : '—' }}</td>
                                <td class="px-6 py-3">
                                    @if ($pedido->substituido_em)
                                        <span class="rounded bg-emerald-100 px-2 py-0.5 text-xs font-semibold text-emerald-700">{{ $pedido->substituido_em->local()->format('d/m/Y') }}</span>
                                    @else
                                        <span class="rounded bg-slate-100 px-2 py-0.5 text-xs font-semibold text-slate-600 dark:bg-slate-800">sem registro</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="px-6 py-10 text-center text-slate-500">Nenhum pedido registrado para esta impressora.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</x-app-layout>
