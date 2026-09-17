<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-gray-800">Conferir fatura</h2>
    </x-slot>

    <div class="mx-auto max-w-6xl space-y-6 px-6 py-8">
        <div>
            <p class="text-xs uppercase tracking-widest text-slate-500">Telefonia Corporativa · {{ \App\Support\ScopeRegistry::label($scope) }}</p>
            <h1 class="text-3xl font-semibold text-slate-900">Conferir antes de gravar</h1>
            <p class="mt-1 text-sm text-slate-500">{{ $operadora }} · {{ $nomeArquivo }} · lido com {{ $revisao['parser'] }}</p>
        </div>

        @include('partials.modulo-flash')

        @if ($revisao['avisos'])
            <div class="rounded-md border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
                <p class="font-semibold">A leitura deixou pontos em aberto:</p>
                <ul class="mt-1 list-disc space-y-0.5 pl-5">
                    @foreach ($revisao['avisos'] as $aviso)
                        <li>{{ $aviso }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @include('partials.modulo-resumo', ['cards' => [
            ['titulo' => 'Linhas na fatura', 'valor' => $revisao['quantidade']],
            ['titulo' => 'Conferem com o cadastro', 'valor' => count($revisao['conferidas'])],
            ['titulo' => 'Números novos', 'valor' => count($revisao['novas']), 'alerta' => true, 'nota' => 'Não cadastrados'],
            ['titulo' => 'Não vieram na conta', 'valor' => count($revisao['ausentes']), 'alerta' => true, 'nota' => 'Cadastradas e ativas'],
            ['titulo' => 'Total declarado', 'valor' => (float) $revisao['total_declarado'], 'moeda' => true],
        ]])

        <section class="rounded-lg border {{ $revisao['totais_batem'] ? 'border-emerald-200 bg-emerald-50' : 'border-amber-200 bg-amber-50' }} p-5 shadow-sm">
            <h2 class="text-sm font-semibold uppercase tracking-wide {{ $revisao['totais_batem'] ? 'text-emerald-800' : 'text-amber-900' }}">Conferência dos totais</h2>
            <div class="mt-2 grid grid-cols-1 gap-2 text-sm sm:grid-cols-3">
                <p>Declarado na fatura: <span class="font-mono font-semibold">R$ {{ number_format((float) $revisao['total_declarado'], 2, ',', '.') }}</span></p>
                <p>Soma das linhas lidas: <span class="font-mono font-semibold">R$ {{ number_format((float) $revisao['total_calculado'], 2, ',', '.') }}</span></p>
                <p>Diferença: <span class="font-mono font-semibold">R$ {{ number_format(abs((float) $revisao['diferenca']), 2, ',', '.') }}</span></p>
            </div>
            @unless ($revisao['totais_batem'])
                <p class="mt-2 text-xs text-amber-800">Quando não bate, alguma linha do PDF não foi reconhecida. Dá para importar assim mesmo, mas a conferência de quem paga não vai fechar.</p>
            @endunless
        </section>

        <form method="POST" action="{{ route('administrativo.telefonia.faturas.confirm') }}" class="space-y-6">
            @csrf

            <section class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                <h2 class="text-lg font-semibold text-slate-900">Linhas que conferem <span class="text-sm font-normal text-slate-500">({{ count($revisao['conferidas']) }})</span></h2>
                <p class="mt-1 text-sm text-slate-500">O valor e o plano destas linhas serão atualizados com o que veio na fatura.</p>

                @if ($revisao['conferidas'])
                    <div class="mt-3 max-h-96 overflow-auto">
                        <table class="w-full min-w-[720px] text-left text-sm">
                            <thead class="sticky top-0 border-b border-slate-200 bg-white text-xs uppercase text-slate-500">
                                <tr><th class="px-3 py-2">Linha</th><th class="px-3 py-2">Plano na fatura</th><th class="px-3 py-2 text-right">Valor anterior</th><th class="px-3 py-2 text-right">Valor na fatura</th><th class="px-3 py-2 text-right">Variação</th></tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                @foreach ($revisao['conferidas'] as $item)
                                    <tr class="{{ $item['variacao'] !== null && abs($item['variacao']) >= 10 ? 'bg-amber-50/70' : '' }}">
                                        <td class="px-3 py-2 font-mono text-slate-900">{{ $item['numero_formatado'] }}</td>
                                        <td class="px-3 py-2 text-slate-600">
                                            {{ $item['nome_plano'] }}
                                            @if ($item['plano_anterior'] && $item['plano_anterior'] !== $item['nome_plano'])
                                                <span class="block text-xs text-amber-700">antes: {{ $item['plano_anterior'] }}</span>
                                            @endif
                                        </td>
                                        <td class="px-3 py-2 text-right font-mono text-slate-500">{{ $item['valor_anterior'] !== null ? 'R$ '.number_format($item['valor_anterior'], 2, ',', '.') : '—' }}</td>
                                        <td class="px-3 py-2 text-right font-mono font-semibold text-slate-900">R$ {{ number_format($item['valor'], 2, ',', '.') }}</td>
                                        <td class="px-3 py-2 text-right">
                                            @if ($item['variacao'] !== null)
                                                <span class="font-medium {{ $item['variacao'] > 0 ? 'text-rose-700' : ($item['variacao'] < 0 ? 'text-emerald-700' : 'text-slate-500') }}">
                                                    {{ $item['variacao'] > 0 ? '+' : '' }}{{ number_format($item['variacao'], 2, ',', '.') }}%
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
                @else
                    <p class="mt-3 rounded-md bg-slate-50 px-4 py-3 text-sm text-slate-500">Nenhuma linha da fatura bateu com o cadastro desta operação.</p>
                @endif
            </section>

            @if ($revisao['novas'])
                <section class="rounded-lg border border-amber-200 bg-amber-50 p-5 shadow-sm">
                    <h2 class="text-lg font-semibold text-amber-950">Números cobrados que não estão cadastrados <span class="text-sm font-normal">({{ count($revisao['novas']) }})</span></h2>
                    <p class="mt-1 text-sm text-amber-800">Serão gravados no histórico da fatura, mas <span class="font-semibold">não</span> viram linha cadastrada — cadastre cada um pela tela de Linhas se forem legítimos.</p>
                    <div class="mt-3 max-h-72 overflow-auto">
                        <table class="w-full min-w-[520px] text-left text-sm">
                            <thead class="sticky top-0 border-b border-amber-200 bg-amber-50 text-xs uppercase text-amber-900"><tr><th class="px-3 py-2">Número</th><th class="px-3 py-2">Plano</th><th class="px-3 py-2 text-right">Valor</th></tr></thead>
                            <tbody class="divide-y divide-amber-100">
                                @foreach ($revisao['novas'] as $item)
                                    <tr>
                                        <td class="px-3 py-2 font-mono text-amber-950">{{ $item['numero_formatado'] }}</td>
                                        <td class="px-3 py-2 text-amber-900">{{ $item['nome_plano'] }}</td>
                                        <td class="px-3 py-2 text-right font-mono text-amber-950">R$ {{ number_format($item['valor'], 2, ',', '.') }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </section>
            @endif

            @if ($revisao['ausentes'])
                <section class="rounded-lg border border-slate-300 bg-white p-5 shadow-sm">
                    <h2 class="text-lg font-semibold text-slate-900">Linhas ativas que não vieram na fatura <span class="text-sm font-normal text-slate-500">({{ count($revisao['ausentes']) }})</span></h2>
                    <p class="mt-1 text-sm text-slate-500">Podem ter sido canceladas sem ninguém registrar. Marque as que devem ser dadas como canceladas — as demais ficam como estão.</p>
                    <div class="mt-3 max-h-72 overflow-auto">
                        <table class="w-full min-w-[620px] text-left text-sm">
                            <thead class="sticky top-0 border-b border-slate-200 bg-white text-xs uppercase text-slate-500"><tr><th class="px-3 py-2">Cancelar</th><th class="px-3 py-2">Linha</th><th class="px-3 py-2">Plano</th><th class="px-3 py-2 text-right">Valor cadastrado</th><th class="px-3 py-2">Situação</th></tr></thead>
                            <tbody class="divide-y divide-slate-100">
                                @foreach ($revisao['ausentes'] as $item)
                                    <tr>
                                        <td class="px-3 py-2"><input type="checkbox" name="cancelar[]" value="{{ $item['linha_id'] }}" class="rounded border-slate-300"></td>
                                        <td class="px-3 py-2 font-mono text-slate-900">{{ $item['numero_formatado'] }}</td>
                                        <td class="px-3 py-2 text-slate-600">{{ $item['nome_plano'] ?: '—' }}</td>
                                        <td class="px-3 py-2 text-right font-mono text-slate-600">{{ $item['valor_mensal'] !== null ? 'R$ '.number_format($item['valor_mensal'], 2, ',', '.') : '—' }}</td>
                                        <td class="px-3 py-2"><span class="rounded-full bg-slate-100 px-2 py-1 text-xs text-slate-600">{{ $item['status'] }}</span></td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </section>
            @endif

            <div class="flex flex-wrap justify-end gap-2 border-t border-slate-200 pt-5">
                <a href="{{ route('administrativo.telefonia.faturas.create') }}" class="rounded-md border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Descartar</a>
                <button class="rounded-md bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:opacity-90">Gravar fatura</button>
            </div>
        </form>
    </div>
</x-app-layout>
