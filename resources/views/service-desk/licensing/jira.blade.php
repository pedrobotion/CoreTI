<x-app-layout>
    <div class="mx-auto max-w-7xl space-y-6">
        <div>
            <p class="text-sm font-semibold uppercase tracking-wide text-slate-500">Licenciamento</p>
            <h1 class="mt-1 text-2xl font-bold text-slate-950">Jira</h1>
        </div>

        <section class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
            <p class="mb-3 text-sm text-slate-600">
                A cotação do dólar é buscada automaticamente via API.
            </p>
            <form method="GET" class="grid gap-2 md:grid-cols-[220px_auto_auto]">
                <input name="valor_custo_usd" type="number" step="0.01" min="0" value="{{ request('valor_custo_usd', $totals['cost_usd']) }}" placeholder="Valor custo (USD)" class="toolbar-input rounded-md border-slate-300 text-sm">
                <button class="toolbar-btn-primary inline-flex items-center justify-center rounded-md border border-slate-900 bg-slate-900 px-4 text-sm font-semibold text-white">Calcular</button>
                <button name="export" value="xlsx" class="toolbar-btn inline-flex items-center justify-center rounded-md border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-700">Exportar XLSX</button>
            </form>
            <p class="mt-3 text-xs text-slate-500">
                Cotação API atual:
                <strong>
                    @if(!empty($totals['usd_brl_api']))
                        {{ number_format($totals['usd_brl_api'], 4, ',', '.') }}
                    @else
                        indisponível no momento
                    @endif
                </strong>
                | Cotação usada no cálculo: <strong>{{ number_format($totals['usd_brl'], 4, ',', '.') }}</strong>
            </p>
        </section>

        <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <div class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm"><p class="text-xs uppercase text-slate-500">Projetos ativos</p><p class="mt-2 text-2xl font-bold">{{ $totals['projetos'] }}</p></div>
            <div class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm"><p class="text-xs uppercase text-slate-500">Valor USD</p><p class="mt-2 text-2xl font-bold">$ {{ number_format($totals['cost_usd'], 2, '.', ',') }}</p></div>
            <div class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm"><p class="text-xs uppercase text-slate-500">Valor BRL</p><p class="mt-2 text-2xl font-bold">R$ {{ number_format($totals['cost_brl'], 2, ',', '.') }}</p></div>
            <div class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm"><p class="text-xs uppercase text-slate-500">Custo unitário</p><p class="mt-2 text-2xl font-bold">R$ {{ number_format($totals['unit_cost'], 2, ',', '.') }}</p></div>
        </section>

        <section class="overflow-x-auto rounded-lg border border-slate-200 bg-white shadow-sm">
            <table class="w-full min-w-[960px] text-sm">
                <thead class="bg-slate-900 text-left text-xs font-semibold uppercase text-white">
                    <tr>
                        <th class="px-4 py-3">Unidade</th>
                        <th class="px-4 py-3">Centro de Custo</th>
                        <th class="px-4 py-3">Projeto/Grupo</th>
                        <th class="px-4 py-3">Qtd. Projetos</th>
                        <th class="px-4 py-3">Custo Total</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200">
                    @foreach ($rows as $row)
                        <tr>
                            <td class="px-4 py-3">{{ $row->unidade }}</td>
                            <td class="px-4 py-3">{{ $row->centro_custo }}</td>
                            <td class="px-4 py-3">{{ $row->projeto_grupo }}</td>
                            <td class="px-4 py-3">{{ $row->total }}</td>
                            <td class="px-4 py-3">R$ {{ number_format($totals['unit_cost'] * $row->total, 2, ',', '.') }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </section>
    </div>
</x-app-layout>
