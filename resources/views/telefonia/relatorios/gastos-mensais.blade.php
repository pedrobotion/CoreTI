<x-app-layout>
    <div class="mx-auto max-w-7xl space-y-6 px-4 py-8 sm:px-6">
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Telefonia Corporativa · Relatórios</p>
                <h1 class="mt-1 text-2xl font-bold tracking-tight text-slate-900 sm:text-3xl">Gastos mensais por operadora</h1>
                <p class="mt-1 text-sm text-slate-500">Custo corrente das linhas e evolução do que foi efetivamente cobrado.</p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                @if (count($scopeOptions) > 1)
                    <form method="GET" class="flex gap-2">
                        <select name="scope" class="rounded-md border-slate-300 text-sm" onchange="this.form.submit()">
                            <option value="">Sede e Cerrado</option>
                            @foreach ($scopeOptions as $valor => $rotulo)
                                <option value="{{ $valor }}" @selected($scope === $valor)>Só {{ $rotulo }}</option>
                            @endforeach
                        </select>
                    </form>
                @endif
                <a href="{{ route('administrativo.telefonia.relatorios.gastos.export', ['formato' => 'excel'] + request()->query()) }}" class="inline-flex min-h-10 items-center justify-center rounded-md border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-700 hover:bg-slate-50">Excel</a>
                <a href="{{ route('administrativo.telefonia.relatorios.gastos.export', ['formato' => 'pdf'] + request()->query()) }}" class="inline-flex min-h-10 items-center justify-center rounded-md border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-700 hover:bg-slate-50">PDF</a>
            </div>
        </div>

        @include('partials.modulo-flash')

        <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Custo mensal corrente</p>
            <p class="mt-1 text-4xl font-bold text-slate-950">R$ {{ number_format($custoTotal, 2, ',', '.') }}</p>
        </section>

        <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="text-base font-bold text-slate-900">Custo por operadora</h2>
            <div class="mt-4">
                @include('telefonia.relatorios._barras', ['itens' => $porOperadora->map(fn ($o) => [
                    'rotulo' => $o->operadora,
                    'valor' => $o->custo,
                    'quantidade' => $o->quantidade,
                    'detalhe' => '· '.$o->quantidade.' linha(s)',
                ])->all()])
            </div>

            {{-- Os mesmos números em tabela: quem precisa do valor exato, ou lê por
                 leitor de tela, não depende do desenho. --}}
            <div class="mt-5 overflow-x-auto border-t border-slate-100 pt-4">
                <table class="w-full min-w-[520px] text-left text-sm">
                    <thead class="border-b border-slate-100 text-xs font-semibold uppercase tracking-wide text-slate-400">
                        <tr><th class="px-3 py-2">Operadora</th><th class="px-3 py-2 text-right">Linhas</th><th class="px-3 py-2 text-right">Custo mensal</th><th class="px-3 py-2 text-right">Média por linha</th></tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($porOperadora as $item)
                            <tr>
                                <td class="px-3 py-2 text-slate-900">{{ $item->operadora }}</td>
                                <td class="px-3 py-2 text-right text-slate-600">{{ $item->quantidade }}</td>
                                <td class="px-3 py-2 text-right font-mono text-slate-900">R$ {{ number_format($item->custo, 2, ',', '.') }}</td>
                                <td class="px-3 py-2 text-right font-mono text-slate-600">R$ {{ number_format($item->quantidade > 0 ? $item->custo / $item->quantidade : 0, 2, ',', '.') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot class="border-t border-slate-200 bg-slate-50 font-semibold">
                        <tr>
                            <td class="px-3 py-2 text-slate-900">Total</td>
                            <td class="px-3 py-2 text-right text-slate-700">{{ $porOperadora->sum('quantidade') }}</td>
                            <td class="px-3 py-2 text-right font-mono text-slate-900">R$ {{ number_format($porOperadora->sum('custo'), 2, ',', '.') }}</td>
                            <td class="px-3 py-2"></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </section>

        <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="text-base font-bold text-slate-900">Evolução do valor cobrado</h2>
            <p class="mt-1 text-xs text-slate-500">Somatório das faturas importadas, por competência.</p>
            <div class="mt-4">
                @include('telefonia.relatorios._evolucao', ['pontos' => $evolucao])
            </div>

            @if ($evolucao->count() > 1)
                <div class="mt-5 overflow-x-auto border-t border-slate-100 pt-4">
                    <table class="w-full min-w-[520px] text-left text-sm">
                        <thead class="border-b border-slate-100 text-xs font-semibold uppercase tracking-wide text-slate-400">
                            <tr><th class="px-3 py-2">Competência</th><th class="px-3 py-2 text-right">Linhas</th><th class="px-3 py-2 text-right">Total cobrado</th><th class="px-3 py-2 text-right">Variação</th></tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach ($evolucao as $i => $ponto)
                                @php $anterior = $i > 0 ? $evolucao[$i - 1]->custo : null; @endphp
                                <tr>
                                    <td class="px-3 py-2 text-slate-900">{{ \Illuminate\Support\Carbon::parse($ponto->competencia)->format('m/Y') }}</td>
                                    <td class="px-3 py-2 text-right text-slate-600">{{ $ponto->quantidade }}</td>
                                    <td class="px-3 py-2 text-right font-mono text-slate-900">R$ {{ number_format($ponto->custo, 2, ',', '.') }}</td>
                                    <td class="px-3 py-2 text-right">
                                        @if ($anterior && $anterior > 0)
                                            @php $variacao = ($ponto->custo - $anterior) / $anterior * 100; @endphp
                                            <span class="font-medium {{ $variacao > 0 ? 'text-rose-700' : ($variacao < 0 ? 'text-emerald-700' : 'text-slate-500') }}">
                                                {{ $variacao > 0 ? '+' : '' }}{{ number_format($variacao, 1, ',', '.') }}%
                                            </span>
                                        @else
                                            <span class="text-slate-400">—</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>

        <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="text-base font-bold text-slate-900">Linhas de maior custo</h2>
            <p class="mt-1 text-xs text-slate-500">As 20 que mais pesam na conta mensal.</p>
            <div class="mt-3 overflow-x-auto">
                <table class="w-full min-w-[620px] text-left text-sm">
                    <thead class="border-b border-slate-100 text-xs font-semibold uppercase tracking-wide text-slate-400">
                        <tr><th class="px-3 py-2">Linha</th><th class="px-3 py-2">Operadora</th><th class="px-3 py-2">Plano</th><th class="px-3 py-2">Operação</th><th class="px-3 py-2 text-right">Valor mensal</th></tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($maioresLinhas as $linha)
                            <tr>
                                <td class="px-3 py-2 font-mono text-slate-900"><a href="{{ route('administrativo.telefonia.lines.show', $linha) }}" class="hover:underline">{{ $linha->numero_formatado }}</a></td>
                                <td class="px-3 py-2 text-slate-600">{{ $linha->operadora }}</td>
                                <td class="px-3 py-2 text-slate-600">{{ $linha->nome_plano ?: '—' }}</td>
                                <td class="px-3 py-2 text-slate-600">{{ $linha->scope_label }}</td>
                                <td class="px-3 py-2 text-right font-mono font-semibold text-slate-900">R$ {{ number_format((float) $linha->valor_mensal, 2, ',', '.') }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="px-3 py-8 text-center text-sm text-slate-500">Nenhuma linha com valor cadastrado.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</x-app-layout>
