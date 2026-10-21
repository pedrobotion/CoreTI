<x-app-layout>
    <div class="mx-auto max-w-7xl space-y-6 px-4 py-8 sm:px-6">
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Telefonia Corporativa · Relatórios</p>
                <h1 class="mt-1 text-2xl font-bold tracking-tight text-slate-900 sm:text-3xl">Comparativo entre unidades</h1>
                <p class="mt-1 text-sm text-slate-500">Onde está o custo da telefonia, pela unidade de quem usa a linha.</p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <a href="{{ route('administrativo.telefonia.relatorios.unidades.export', ['formato' => 'excel']) }}" class="inline-flex min-h-10 items-center justify-center rounded-md border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-700 hover:bg-slate-50">Excel</a>
                <a href="{{ route('administrativo.telefonia.relatorios.unidades.export', ['formato' => 'pdf']) }}" class="inline-flex min-h-10 items-center justify-center rounded-md border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-700 hover:bg-slate-50">PDF</a>
            </div>
        </div>

        @include('partials.modulo-flash')

        <div class="rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-600">
            A linha não tem unidade própria — quem tem é a pessoa que está com ela. Por isso o rateio abaixo considera
            o vínculo ativo. Linhas sem vínculo aparecem separadas, para o total continuar batendo com o custo mensal.
        </div>

        @if ($duplicadas->isNotEmpty())
            <div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
                <p class="font-semibold">{{ $duplicadas->count() }} linha(s) em mais de um vínculo ativo ao mesmo tempo.</p>
                <p class="mt-1">
                    Vieram assim do gestaoTI — o cadastro do CoreTI não deixa criar. Enquanto o vínculo duplicado não for
                    encerrado, o rateio abaixo atribui cada uma delas apenas ao vínculo mais recente, para o total continuar fechando.
                </p>
                <ul class="mt-2 list-disc space-y-0.5 pl-5 font-mono text-xs">
                    @foreach ($duplicadas as $linha)
                        <li>
                            <a href="{{ route('administrativo.telefonia.lines.show', $linha->linha_id) }}" class="underline">
                                ({{ $linha->ddd }}) {{ $linha->numero }}
                            </a> — {{ $linha->vinculos }} vínculos ativos
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif

        <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="text-base font-bold text-slate-900">Por operação</h2>
            <div class="mt-4">
                @include('telefonia.relatorios._barras', ['itens' => $porOperacao->map(fn ($o) => [
                    'rotulo' => $o->operacao,
                    'valor' => $o->custo,
                    'quantidade' => $o->quantidade,
                    'detalhe' => '· '.$o->quantidade.' linha(s)',
                ])->all()])
            </div>
        </section>

        <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="text-base font-bold text-slate-900">Por unidade</h2>
            <p class="mt-1 text-xs text-slate-500">Somente linhas com vínculo ativo.</p>
            <div class="mt-4">
                @include('telefonia.relatorios._barras', ['itens' => $porUnidade->take(15)->map(fn ($u) => [
                    'rotulo' => $u->unidade,
                    'valor' => $u->custo,
                    'quantidade' => $u->quantidade,
                    'detalhe' => '· '.$u->operacao.' · '.$u->quantidade.' linha(s)',
                ])->all()])
            </div>
            @if ($porUnidade->count() > 15)
                <p class="mt-3 text-xs text-slate-400">Mostrando as 15 de maior custo. A tabela abaixo traz todas as {{ $porUnidade->count() }}.</p>
            @endif

            <div class="mt-5 overflow-x-auto border-t border-slate-100 pt-4">
                <table class="w-full min-w-[620px] text-left text-sm">
                    <thead class="border-b border-slate-100 text-xs font-semibold uppercase tracking-wide text-slate-400">
                        <tr><th class="px-3 py-2">Unidade</th><th class="px-3 py-2">Operação</th><th class="px-3 py-2 text-right">Linhas</th><th class="px-3 py-2 text-right">Custo mensal</th><th class="px-3 py-2 text-right">Média por linha</th></tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($porUnidade as $item)
                            <tr>
                                <td class="px-3 py-2 text-slate-900">{{ $item->unidade }}</td>
                                <td class="px-3 py-2 text-slate-600">{{ $item->operacao }}</td>
                                <td class="px-3 py-2 text-right text-slate-600">{{ $item->quantidade }}</td>
                                <td class="px-3 py-2 text-right font-mono text-slate-900">R$ {{ number_format($item->custo, 2, ',', '.') }}</td>
                                <td class="px-3 py-2 text-right font-mono text-slate-600">R$ {{ number_format($item->quantidade > 0 ? $item->custo / $item->quantidade : 0, 2, ',', '.') }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="px-3 py-8 text-center text-sm text-slate-500">Nenhuma linha com vínculo ativo.</td></tr>
                        @endforelse
                    </tbody>
                    <tfoot class="border-t border-slate-200 bg-slate-50">
                        <tr class="font-semibold">
                            <td class="px-3 py-2 text-slate-900">Com vínculo</td>
                            <td class="px-3 py-2"></td>
                            <td class="px-3 py-2 text-right text-slate-700">{{ $porUnidade->sum('quantidade') }}</td>
                            <td class="px-3 py-2 text-right font-mono text-slate-900">R$ {{ number_format($porUnidade->sum('custo'), 2, ',', '.') }}</td>
                            <td class="px-3 py-2"></td>
                        </tr>
                        <tr class="text-slate-600">
                            <td class="px-3 py-2">Sem vínculo ativo</td>
                            <td class="px-3 py-2"></td>
                            <td class="px-3 py-2 text-right">{{ $semVinculo['quantidade'] }}</td>
                            <td class="px-3 py-2 text-right font-mono">R$ {{ number_format($semVinculo['custo'], 2, ',', '.') }}</td>
                            <td class="px-3 py-2"></td>
                        </tr>
                        <tr class="border-t border-slate-200 font-bold">
                            <td class="px-3 py-2 text-slate-900">Total</td>
                            <td class="px-3 py-2"></td>
                            <td class="px-3 py-2 text-right text-slate-900">{{ $porUnidade->sum('quantidade') + $semVinculo['quantidade'] }}</td>
                            <td class="px-3 py-2 text-right font-mono text-slate-900">R$ {{ number_format($porUnidade->sum('custo') + $semVinculo['custo'], 2, ',', '.') }}</td>
                            <td class="px-3 py-2"></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </section>
    </div>
</x-app-layout>
