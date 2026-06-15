<x-app-layout>
    <div class="py-8">
        <div class="mx-auto w-full max-w-[1500px] px-4 sm:px-6 lg:px-8 space-y-6">
            @if(!empty($missingTable))
                <div class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                    O histórico de incidentes ainda não está disponível porque a tabela <code>circuit_incidents</code> não foi criada no banco.
                </div>
            @endif

            <div class="flex flex-wrap items-end justify-between gap-3">
                <div>
                    <p class="text-sm font-semibold uppercase tracking-wide text-slate-500">Unidades</p>
                    <h1 class="mt-1 text-2xl font-bold text-slate-950">Dashboard de Incidentes</h1>
                </div>
                <form method="GET" class="flex items-center gap-2">
                    <input type="month" name="mes" value="{{ $month }}" class="toolbar-input rounded-md border-slate-300 text-sm shadow-sm focus:border-slate-900 focus:ring-slate-900">
                    <button class="toolbar-btn-primary inline-flex min-h-10 items-center justify-center rounded-md border border-slate-900 bg-slate-900 px-4 text-sm font-semibold text-white">Filtrar</button>
                </form>
            </div>

            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm"><p class="text-sm text-slate-500">Chamados abertos no mês</p><p class="mt-2 text-3xl font-bold">{{ $stats['opened'] }}</p></div>
                <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm"><p class="text-sm text-slate-500">Chamados resolvidos</p><p class="mt-2 text-3xl font-bold">{{ $stats['resolved'] }}</p></div>
                <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm"><p class="text-sm text-slate-500">Pendentes</p><p class="mt-2 text-3xl font-bold text-amber-600">{{ $stats['pending'] }}</p></div>
                <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm"><p class="text-sm text-slate-500">Unidade com mais chamados</p><p class="mt-2 text-base font-semibold">{{ $stats['top_unit'] }}</p><p class="mt-1 text-2xl font-bold">{{ $stats['top_unit_total'] }}</p></div>
            </div>

            <section class="overflow-x-auto rounded-lg border border-slate-200 bg-white shadow-sm">
                <table class="w-full min-w-[760px] text-sm">
                    <thead class="bg-slate-900 text-left text-xs font-semibold uppercase text-white">
                        <tr>
                            <th class="px-4 py-3">Unidade</th>
                            <th class="px-4 py-3">Total</th>
                            <th class="px-4 py-3">Resolvidos</th>
                            <th class="px-4 py-3">Pendentes</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200">
                        @forelse($byUnit as $row)
                            <tr>
                                <td class="px-4 py-3">{{ $row->unidade }}</td>
                                <td class="px-4 py-3">{{ (int) $row->total }}</td>
                                <td class="px-4 py-3">{{ (int) $row->resolvidos }}</td>
                                <td class="px-4 py-3">{{ max(0, (int) $row->total - (int) $row->resolvidos) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="px-4 py-6 text-center text-slate-500">Sem incidentes no período.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </section>
        </div>
    </div>
</x-app-layout>
