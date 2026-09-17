<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-gray-800">Fatura {{ $fatura->competencia_label }}</h2>
    </x-slot>

    <div class="mx-auto max-w-6xl space-y-6 px-6 py-8">
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <p class="text-xs uppercase tracking-widest text-slate-500">Telefonia Corporativa</p>
                <h1 class="text-3xl font-semibold text-slate-900">{{ $fatura->operadora }} · {{ $fatura->competencia_label }}</h1>
                <p class="mt-1 text-sm text-slate-500">
                    Conta {{ $fatura->numero_conta ?: '—' }} · {{ $fatura->scope_label }}
                    @if ($fatura->vencimento) · vence {{ $fatura->vencimento->format('d/m/Y') }} @endif
                </p>
            </div>
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('administrativo.telefonia.faturas.index') }}" class="rounded-md border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Voltar</a>
                @if ($fatura->arquivo_pdf)
                    <a href="{{ route('administrativo.telefonia.faturas.download', $fatura) }}" class="rounded-md border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Baixar PDF original</a>
                @endif
            </div>
        </div>

        @include('partials.modulo-flash')

        @if ($fatura->observacao_parser)
            <div class="rounded-md border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
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

        <section class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
            <div class="mb-3 flex flex-wrap items-center justify-between gap-3">
                <h2 class="text-lg font-semibold text-slate-900">Detalhamento</h2>
                <p class="text-xs text-slate-500">
                    Importada em {{ $fatura->created_at?->format('d/m/Y H:i') }}{{ $fatura->importadoPor ? ' por '.$fatura->importadoPor->name : '' }}
                    @if ($fatura->arquivo_nome_original) · {{ $fatura->arquivo_nome_original }} @endif
                </p>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full min-w-[820px] text-left text-sm">
                    <thead class="border-b border-slate-200 text-xs uppercase text-slate-500">
                        <tr>
                            <th class="px-3 py-3">Linha</th>
                            <th class="px-3 py-3">Plano</th>
                            <th class="px-3 py-3 text-right">Anterior</th>
                            <th class="px-3 py-3 text-right">Cobrado</th>
                            <th class="px-3 py-3 text-right">Variação</th>
                            <th class="px-3 py-3">Cadastro</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($itens as $item)
                            <tr class="{{ $item->linha_nova ? 'bg-amber-50/60' : '' }}">
                                <td class="px-3 py-2 font-mono text-slate-900">
                                    @if ($item->corp_line_id)
                                        <a href="{{ route('administrativo.telefonia.lines.show', $item->corp_line_id) }}" class="hover:underline">{{ $item->numero_formatado }}</a>
                                    @else
                                        {{ $item->numero_formatado }}
                                    @endif
                                </td>
                                <td class="px-3 py-2 text-slate-600">{{ $item->nome_plano ?: '—' }}</td>
                                <td class="px-3 py-2 text-right font-mono text-slate-500">{{ $item->valor_anterior !== null ? 'R$ '.number_format((float) $item->valor_anterior, 2, ',', '.') : '—' }}</td>
                                <td class="px-3 py-2 text-right font-mono font-semibold text-slate-900">R$ {{ number_format((float) $item->valor, 2, ',', '.') }}</td>
                                <td class="px-3 py-2 text-right">
                                    @if ($item->variacao_percentual !== null)
                                        <span class="font-medium {{ $item->variacao_percentual > 0 ? 'text-rose-700' : ($item->variacao_percentual < 0 ? 'text-emerald-700' : 'text-slate-500') }}">
                                            {{ $item->variacao_percentual > 0 ? '+' : '' }}{{ number_format((float) $item->variacao_percentual, 2, ',', '.') }}%
                                        </span>
                                    @else
                                        <span class="text-slate-400">—</span>
                                    @endif
                                </td>
                                <td class="px-3 py-2">
                                    @if ($item->linha_nova)
                                        <span class="rounded-full bg-amber-200 px-2 py-1 text-xs font-semibold text-amber-900">Não cadastrada</span>
                                    @else
                                        <span class="rounded-full bg-slate-100 px-2 py-1 text-xs text-slate-600">{{ $item->linha?->status ?? 'Cadastrada' }}</span>
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
