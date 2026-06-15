<x-app-layout>
    <div class="mx-auto max-w-5xl space-y-6">
        <div class="flex items-end justify-between">
            <div>
                <p class="text-sm font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Bancada de Serviços</p>
                <h1 class="mt-1 text-2xl font-bold text-slate-950 dark:text-white">Histórico de Status</h1>
                <p class="mt-1 text-sm text-slate-600">Plaqueta {{ $asset->plaqueta }} - {{ $asset->tipo_equipamento }}</p>
            </div>
            <a href="{{ route('bancada-servicos.assets') }}" class="inline-flex min-h-10 items-center justify-center rounded-md border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-700">Voltar</a>
        </div>

        <section class="rounded-lg border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <div class="overflow-x-auto">
                <table class="w-full min-w-[760px] text-sm">
                    <thead class="bg-slate-900 text-left text-xs font-semibold uppercase text-white">
                        <tr>
                            <th class="px-4 py-3">Status</th>
                            <th class="px-4 py-3">Início</th>
                            <th class="px-4 py-3">Fim</th>
                            <th class="px-4 py-3">Duração</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200 dark:divide-slate-800">
                        @forelse($history as $item)
                            <tr class="odd:bg-slate-50 even:bg-white dark:odd:bg-slate-950 dark:even:bg-slate-900">
                                <td class="px-4 py-3">{{ $item->status }}</td>
                                <td class="px-4 py-3">{{ optional($item->start_time)->format('d/m/Y H:i') }}</td>
                                <td class="px-4 py-3">{{ optional($item->end_time)->format('d/m/Y H:i') ?: 'Ativo' }}</td>
                                <td class="px-4 py-3">
                                    @php
                                        $from = $item->start_time;
                                        $to = $item->end_time ?? now();
                                        $minutes = $from ? max($from->diffInMinutes($to), 0) : 0;
                                        $days = intdiv($minutes, 1440);
                                        $hours = intdiv($minutes % 1440, 60);
                                        $mins = $minutes % 60;
                                    @endphp
                                    {{ $days > 0 ? $days . 'd ' : '' }}{{ $hours }}h {{ $mins }}m
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="px-4 py-6 text-center text-slate-500">Sem histórico registrado.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        <section class="rounded-lg border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <div class="border-b border-slate-200 px-4 py-3 dark:border-slate-800">
                <h2 class="text-base font-bold text-slate-900 dark:text-white">Eventos do equipamento</h2>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full min-w-[1100px] text-sm">
                    <thead class="bg-slate-900 text-left text-xs font-semibold uppercase text-white">
                        <tr>
                            <th class="px-4 py-3">Data/Hora</th>
                            <th class="px-4 py-3">Ação</th>
                            <th class="px-4 py-3">Módulo</th>
                            <th class="px-4 py-3">Usuário</th>
                            <th class="px-4 py-3">De</th>
                            <th class="px-4 py-3">Para</th>
                            <th class="px-4 py-3">Observação</th>
                            <th class="px-4 py-3">Metadados</th>
                            <th class="px-4 py-3">Anexos</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200 dark:divide-slate-800">
                        @forelse(($events ?? collect()) as $event)
                            @php
                                $actionLabel = match ($event->action) {
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
                                    default => $event->action,
                                };
                            @endphp
                            <tr class="odd:bg-slate-50 even:bg-white dark:odd:bg-slate-950 dark:even:bg-slate-900">
                                <td class="px-4 py-3">{{ optional($event->created_at)->format('d/m/Y H:i') ?: '-' }}</td>
                                <td class="px-4 py-3">{{ $actionLabel }}</td>
                                <td class="px-4 py-3">{{ $event->module }}</td>
                                <td class="px-4 py-3">{{ $event->performer?->name ?: 'Sistema' }}</td>
                                <td class="px-4 py-3">{{ $event->previous_status ?: '-' }}</td>
                                <td class="px-4 py-3">{{ $event->new_status ?: '-' }}</td>
                                <td class="px-4 py-3">{{ $event->observation ?: '-' }}</td>
                                <td class="px-4 py-3">
                                    @if(!empty($event->metadata))
                                        <pre class="whitespace-pre-wrap text-xs">{{ json_encode($event->metadata, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT) }}</pre>
                                    @else
                                        -
                                    @endif
                                </td>
                                <td class="px-4 py-3">
                                    @forelse($event->attachments as $attachment)
                                        <div>
                                            <a class="text-blue-600 hover:text-blue-800" href="{{ route('bancada-servicos.attachments.download', $attachment) }}">
                                                {{ $attachment->original_name }}
                                            </a>
                                            <span class="text-xs text-slate-500">({{ $attachment->attachment_type }})</span>
                                        </div>
                                    @empty
                                        -
                                    @endforelse
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="9" class="px-4 py-6 text-center text-slate-500">Sem eventos registrados.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</x-app-layout>
