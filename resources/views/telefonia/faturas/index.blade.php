<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-gray-800">Faturas</h2>
    </x-slot>

    <div class="mx-auto max-w-7xl space-y-6 px-6 py-8">
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <p class="text-xs uppercase tracking-widest text-slate-500">Telefonia Corporativa</p>
                <h1 class="text-3xl font-semibold text-slate-900">Faturas</h1>
                <p class="mt-1 text-sm text-slate-500">Importação do PDF da operadora, conferência linha a linha e histórico de cobrança.</p>
            </div>
            @can('module.telefonia.manage')
                <a href="{{ route('administrativo.telefonia.faturas.create') }}" class="rounded-md bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:opacity-90">Importar fatura</a>
            @endcan
        </div>

        @include('partials.modulo-flash')

        <section class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
            <form method="GET" action="{{ route('administrativo.telefonia.faturas.index') }}" class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-5">
                @if (count($scopeOptions) > 1)
                    <select name="scope" class="rounded-md border-slate-300 text-sm">
                        <option value="">Todas as operações</option>
                        @foreach ($scopeOptions as $valor => $rotulo)
                            <option value="{{ $valor }}" @selected($filtros['scope'] === $valor)>{{ $rotulo }}</option>
                        @endforeach
                    </select>
                @endif

                <select name="operadora" class="rounded-md border-slate-300 text-sm">
                    <option value="">Todas as operadoras</option>
                    @foreach ($operadoras as $operadora)
                        <option value="{{ $operadora }}" @selected($filtros['operadora'] === $operadora)>{{ $operadora }}</option>
                    @endforeach
                </select>

                <input type="month" name="competencia" value="{{ $filtros['competencia'] }}" class="rounded-md border-slate-300 text-sm">

                @include('partials.modulo-por-pagina')

                <div class="flex gap-2">
                    <button class="flex-1 rounded-md border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Filtrar</button>
                    <a href="{{ route('administrativo.telefonia.faturas.index') }}" class="rounded-md border border-slate-300 px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Limpar</a>
                </div>
            </form>
        </section>

        <section class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
            <div class="mb-4 flex items-center justify-between gap-3">
                <h2 class="text-lg font-semibold text-slate-900">Faturas importadas</h2>
                <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-600">{{ number_format($faturas->total(), 0, ',', '.') }} registro(s)</span>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full min-w-[900px] text-left text-sm">
                    <thead class="border-b border-slate-200 text-xs uppercase text-slate-500">
                        <tr>
                            <th class="px-3 py-3">Competência</th>
                            <th class="px-3 py-3">Operadora</th>
                            <th class="px-3 py-3">Conta</th>
                            <th class="px-3 py-3">Operação</th>
                            <th class="px-3 py-3 text-right">Linhas</th>
                            <th class="px-3 py-3 text-right">Total</th>
                            <th class="px-3 py-3">Conferência</th>
                            <th class="px-3 py-3 text-right">Ações</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($faturas as $fatura)
                            <tr class="{{ $fatura->totaisBatem() ? '' : 'bg-amber-50/60' }}">
                                <td class="px-3 py-3 font-medium text-slate-900">{{ $fatura->competencia_label }}</td>
                                <td class="px-3 py-3 text-slate-700">{{ $fatura->operadora }}</td>
                                <td class="px-3 py-3 font-mono text-xs text-slate-600">{{ $fatura->numero_conta ?: '—' }}</td>
                                <td class="px-3 py-3"><span class="rounded-full bg-slate-100 px-2 py-1 text-xs font-medium text-slate-600">{{ $fatura->scope_label }}</span></td>
                                <td class="px-3 py-3 text-right text-slate-700">{{ $fatura->itens_count }}</td>
                                <td class="px-3 py-3 text-right font-mono text-slate-900">R$ {{ number_format((float) $fatura->valor_total_declarado, 2, ',', '.') }}</td>
                                <td class="px-3 py-3">
                                    @if ($fatura->totaisBatem())
                                        <span class="rounded-full bg-emerald-100 px-2 py-1 text-xs font-medium text-emerald-700">Bate</span>
                                    @else
                                        <span class="rounded-full bg-amber-100 px-2 py-1 text-xs font-medium text-amber-800" title="Diferença de R$ {{ number_format(abs($fatura->diferencaTotal()), 2, ',', '.') }}">Divergente</span>
                                    @endif
                                </td>
                                <td class="px-3 py-3">
                                    <div class="flex justify-end gap-2">
                                        <a href="{{ route('administrativo.telefonia.faturas.show', $fatura) }}" class="rounded-md border border-slate-300 px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50">Abrir</a>
                                        @can('module.telefonia.manage')
                                            <form method="POST" action="{{ route('administrativo.telefonia.faturas.destroy', $fatura) }}" onsubmit="return confirm('Remover a fatura de {{ $fatura->competencia_label }}? O histórico de cobrança dela será apagado.')">
                                                @csrf @method('DELETE')
                                                <button class="rounded-md border border-rose-300 px-3 py-1.5 text-xs font-medium text-rose-700 hover:bg-rose-50">Remover</button>
                                            </form>
                                        @endcan
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="8" class="px-3 py-10 text-center text-sm text-slate-500">Nenhuma fatura importada ainda.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-4">{{ $faturas->links() }}</div>
        </section>
    </div>
</x-app-layout>
