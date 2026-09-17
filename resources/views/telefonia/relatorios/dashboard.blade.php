<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-gray-800">Painel de Telefonia</h2>
    </x-slot>

    <div class="mx-auto max-w-7xl space-y-6 px-6 py-8">
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <p class="text-xs uppercase tracking-widest text-slate-500">Telefonia Corporativa</p>
                <h1 class="text-3xl font-semibold text-slate-900">Painel</h1>
                <p class="mt-1 text-sm text-slate-500">Situação do parque, custo corrente e pendências que pedem conferência.</p>
            </div>
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
        </div>

        @include('partials.modulo-flash')

        {{-- Número que o painel lidera: é o que a diretoria pergunta primeiro. --}}
        <section class="rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
            <p class="text-xs font-medium uppercase tracking-wide text-slate-500">Custo mensal corrente</p>
            <p class="mt-1 text-5xl font-bold text-slate-950">R$ {{ number_format($numeros['custo_mensal'], 2, ',', '.') }}</p>
            <p class="mt-1 text-sm text-slate-500">
                {{ $numeros['linhas_total'] - $numeros['linhas_canceladas'] }} linha(s) vigente(s)
                @if ($numeros['linhas_total'] - $numeros['linhas_canceladas'] > 0)
                    · média de R$ {{ number_format($numeros['custo_mensal'] / ($numeros['linhas_total'] - $numeros['linhas_canceladas']), 2, ',', '.') }} por linha
                @endif
            </p>
        </section>

        @include('partials.modulo-resumo', ['cards' => [
            ['titulo' => 'Linhas em uso', 'valor' => $numeros['linhas_em_uso']],
            ['titulo' => 'Linhas disponíveis', 'valor' => $numeros['linhas_disponiveis']],
            ['titulo' => 'Aparelhos disponíveis', 'valor' => $numeros['aparelhos_disponiveis'], 'nota' => 'de '.$numeros['aparelhos_total']],
            ['titulo' => 'Vínculos ativos', 'valor' => $numeros['vinculos_ativos']],
            ['titulo' => 'Linhas canceladas', 'valor' => $numeros['linhas_canceladas']],
        ]])

        <section class="rounded-lg border border-amber-200 bg-amber-50 p-5 shadow-sm">
            <h2 class="text-sm font-semibold uppercase tracking-wide text-amber-900">Pendências</h2>
            <div class="mt-3 grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-5">
                @foreach ([
                    ['rotulo' => 'Sem termo assinado', 'valor' => $numeros['sem_termo'], 'rota' => route('administrativo.telefonia.assignments.index', ['termo' => 'pendente'])],
                    ['rotulo' => 'Termos vencidos', 'valor' => $numeros['vencidos'], 'rota' => route('administrativo.telefonia.assignments.index', ['vencimento' => 'vencidos'])],
                    ['rotulo' => 'Colaborador inativo com item', 'valor' => $numeros['colaborador_inativo'], 'rota' => route('administrativo.telefonia.assignments.index', ['inconsistentes' => 1])],
                    ['rotulo' => 'Linhas em uso sem vínculo', 'valor' => $numeros['linhas_sem_vinculo'], 'rota' => route('administrativo.telefonia.lines.index', ['status' => 'Em Uso'])],
                    ['rotulo' => 'Linha em dois vínculos ativos', 'valor' => $numeros['linhas_duplicadas'], 'rota' => route('administrativo.telefonia.relatorios.unidades')],
                ] as $pendencia)
                    <a href="{{ $pendencia['rota'] }}" class="rounded-md border {{ $pendencia['valor'] > 0 ? 'border-amber-300 bg-white' : 'border-amber-200 bg-amber-50/50' }} p-4 transition hover:shadow-sm">
                        <p class="text-2xl font-bold {{ $pendencia['valor'] > 0 ? 'text-amber-900' : 'text-slate-400' }}">{{ $pendencia['valor'] }}</p>
                        <p class="mt-0.5 text-xs {{ $pendencia['valor'] > 0 ? 'text-amber-800' : 'text-slate-400' }}">{{ $pendencia['rotulo'] }}</p>
                    </a>
                @endforeach
            </div>
        </section>

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
            <section class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                <h2 class="text-base font-semibold text-slate-900">Custo mensal por operadora</h2>
                <p class="mt-1 text-xs text-slate-500">Soma do valor das linhas vigentes.</p>
                <div class="mt-4">
                    @include('telefonia.relatorios._barras', ['itens' => $porOperadora->map(fn ($o) => [
                        'rotulo' => $o->operadora,
                        'valor' => $o->custo,
                        'quantidade' => $o->quantidade,
                        'detalhe' => '· '.$o->quantidade.' linha(s)',
                    ])->all()])
                </div>
            </section>

            <section class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                <h2 class="text-base font-semibold text-slate-900">Evolução do valor cobrado</h2>
                <p class="mt-1 text-xs text-slate-500">O que veio nas faturas importadas, por competência.</p>
                <div class="mt-4">
                    @include('telefonia.relatorios._evolucao', ['pontos' => $evolucao])
                </div>
            </section>
        </div>

        <section class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
            <div class="mb-3 flex flex-wrap items-center justify-between gap-3">
                <h2 class="text-base font-semibold text-slate-900">Últimas faturas</h2>
                <a href="{{ route('administrativo.telefonia.faturas.index') }}" class="text-sm font-medium text-slate-600 hover:underline">Ver todas</a>
            </div>
            @if ($ultimasFaturas->isEmpty())
                <p class="rounded-md bg-slate-50 px-4 py-6 text-center text-sm text-slate-500">Nenhuma fatura importada ainda.</p>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[620px] text-left text-sm">
                        <thead class="border-b border-slate-200 text-xs uppercase text-slate-500">
                            <tr><th class="px-3 py-2">Competência</th><th class="px-3 py-2">Operadora</th><th class="px-3 py-2">Operação</th><th class="px-3 py-2 text-right">Linhas</th><th class="px-3 py-2 text-right">Total</th><th class="px-3 py-2"></th></tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach ($ultimasFaturas as $fatura)
                                <tr>
                                    <td class="px-3 py-2 text-slate-900">{{ $fatura->competencia_label }}</td>
                                    <td class="px-3 py-2 text-slate-600">{{ $fatura->operadora }}</td>
                                    <td class="px-3 py-2 text-slate-600">{{ $fatura->scope_label }}</td>
                                    <td class="px-3 py-2 text-right text-slate-600">{{ $fatura->quantidade_linhas }}</td>
                                    <td class="px-3 py-2 text-right font-mono text-slate-900">R$ {{ number_format((float) $fatura->valor_total_declarado, 2, ',', '.') }}</td>
                                    <td class="px-3 py-2 text-right"><a href="{{ route('administrativo.telefonia.faturas.show', $fatura) }}" class="rounded-md border border-slate-300 px-3 py-1 text-xs font-medium text-slate-700 hover:bg-slate-50">Abrir</a></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>

        <div class="flex flex-wrap gap-3">
            <a href="{{ route('administrativo.telefonia.relatorios.gastos') }}" class="rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Relatório de gastos mensais</a>
            <a href="{{ route('administrativo.telefonia.relatorios.unidades') }}" class="rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Comparativo entre unidades</a>
        </div>
    </div>
</x-app-layout>
