<x-app-layout>
    <div class="mx-auto max-w-6xl space-y-6 px-4 py-8 sm:px-6">
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Telefonia Corporativa</p>
                <h1 class="mt-1 text-2xl font-bold tracking-tight text-slate-900 sm:text-3xl">{{ $fatura->operadora }} · {{ $fatura->competencia_label }}</h1>
                <p class="mt-1 text-sm text-slate-500">
                    Conta {{ $fatura->numero_conta ?: '—' }} · {{ $fatura->scope_label }}
                    @if ($fatura->vencimento) · vence {{ $fatura->vencimento->format('d/m/Y') }} @endif
                </p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <a href="{{ route('administrativo.telefonia.faturas.index') }}" class="inline-flex min-h-10 items-center justify-center rounded-md border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-700 hover:bg-slate-50">Voltar</a>
                @if ($fatura->arquivo_pdf)
                    <a href="{{ route('administrativo.telefonia.faturas.download', $fatura) }}" class="inline-flex min-h-10 items-center justify-center rounded-md border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-700 hover:bg-slate-50">Baixar PDF original</a>
                @endif
            </div>
        </div>

        @include('partials.modulo-flash')

        @if ($fatura->observacao_parser)
            <div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
                <p class="font-semibold">Observações da leitura:</p>
                <p class="mt-1 whitespace-pre-line">{{ $fatura->observacao_parser }}</p>
            </div>
        @endif

        @include('partials.modulo-resumo', ['cards' => [
            ['titulo' => 'Linhas', 'valor' => $fatura->quantidade_linhas],
            ['titulo' => 'Total declarado', 'valor' => (float) $fatura->valor_total_declarado, 'moeda' => true],
            ['titulo' => 'Soma das linhas', 'valor' => (float) $fatura->valor_total_calculado, 'moeda' => true],
            ['titulo' => 'Diferença', 'valor' => abs($fatura->diferencaTotal()), 'moeda' => true, 'alerta' => ! $fatura->totaisBatem()],
        ]])

        <section class="rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 p-5 sm:px-6">
                <div class="flex items-center gap-3">
                    <span class="grid h-9 w-9 shrink-0 place-items-center rounded-lg bg-emerald-50 text-emerald-600">
                        <svg viewBox="0 0 24 24" class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M14 3H6a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9z"/><path d="M14 3v6h6"/></svg>
                    </span>
                    <div>
                        <h2 class="text-base font-bold text-slate-900">Detalhamento</h2>
                        <p class="mt-0.5 text-sm text-slate-500">
                            Importada em {{ $fatura->created_at?->format('d/m/Y H:i') }}{{ $fatura->importadoPor ? ' por '.$fatura->importadoPor->name : '' }}
                            @if ($fatura->arquivo_nome_original) · {{ $fatura->arquivo_nome_original }} @endif
                        </p>
                    </div>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full min-w-[820px] text-left text-sm">
                    <thead>
                        <tr class="border-b border-slate-100 text-xs font-semibold uppercase tracking-wide text-slate-400">
                            <th class="px-5 py-3 sm:px-6">Linha</th>
                            <th class="px-5 py-3">Plano</th>
                            <th class="px-5 py-3 text-right">Anterior</th>
                            <th class="px-5 py-3 text-right">Cobrado</th>
                            <th class="px-5 py-3 text-right">Variação</th>
                            <th class="px-5 py-3">Cadastro</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($itens as $item)
                            <tr class="transition hover:bg-slate-50 {{ $item->linha_nova ? 'bg-amber-50/60' : '' }}">
                                <td class="px-5 py-3 font-mono text-slate-900 sm:px-6">
                                    @if ($item->corp_line_id)
                                        <a href="{{ route('administrativo.telefonia.lines.show', $item->corp_line_id) }}" class="hover:underline">{{ $item->numero_formatado }}</a>
                                    @else
                                        {{ $item->numero_formatado }}
                                    @endif
                                </td>
                                <td class="px-5 py-3 text-slate-600">{{ $item->nome_plano ?: '—' }}</td>
                                <td class="px-5 py-3 text-right font-mono text-slate-500">{{ $item->valor_anterior !== null ? 'R$ '.number_format((float) $item->valor_anterior, 2, ',', '.') : '—' }}</td>
                                <td class="px-5 py-3 text-right font-mono font-semibold text-slate-900">R$ {{ number_format((float) $item->valor, 2, ',', '.') }}</td>
                                <td class="px-5 py-3 text-right">
                                    @if ($item->variacao_percentual !== null)
                                        <span class="font-semibold {{ $item->variacao_percentual > 0 ? 'text-rose-700' : ($item->variacao_percentual < 0 ? 'text-emerald-700' : 'text-slate-500') }}">
                                            {{ $item->variacao_percentual > 0 ? '+' : '' }}{{ number_format((float) $item->variacao_percentual, 2, ',', '.') }}%
                                        </span>
                                    @else
                                        <span class="text-slate-400">—</span>
                                    @endif
                                </td>
                                <td class="px-5 py-3">
                                    @if ($item->linha_nova)
                                        <span class="inline-flex items-center rounded-full bg-amber-50 px-2 py-1 text-xs font-semibold text-amber-800 ring-1 ring-inset ring-amber-200">Não cadastrada</span>
                                    @else
                                        <span class="inline-flex items-center rounded-full bg-slate-100 px-2 py-1 text-xs font-medium text-slate-600">{{ $item->linha?->status ?? 'Cadastrada' }}</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</x-app-layout>
