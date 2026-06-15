<x-app-layout>
    <div class="mx-auto max-w-7xl space-y-6">
        <section class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
            <p class="text-sm font-semibold uppercase tracking-wide text-slate-500">Licenciamento</p>
            <h1 class="mt-1 text-2xl font-bold text-slate-950">Rateio Office</h1>
            <p class="mt-1 text-sm text-slate-600">Cálculo de custo por unidade e exportação da planilha.</p>
        </section>

        <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-6">
            <div class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
                <p class="text-xs uppercase text-slate-500">Total de licenças ativas</p>
                <p class="mt-2 text-2xl font-bold">{{ $stats['total'] }}</p>
            </div>
            <div class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
                <p class="text-xs uppercase text-slate-500">Total Office Apps</p>
                <p class="mt-2 text-2xl font-bold">{{ $stats['total_office_apps'] }}</p>
            </div>
            <div class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
                <p class="text-xs uppercase text-slate-500">Total Office Business</p>
                <p class="mt-2 text-2xl font-bold">{{ $stats['total_office_business'] }}</p>
            </div>
            <div class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
                <p class="text-xs uppercase text-slate-500">Total Power BI Pro</p>
                <p class="mt-2 text-2xl font-bold">{{ $stats['total_powerbi_pro'] }}</p>
            </div>
            <div class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
                <p class="text-xs uppercase text-slate-500">Total Visio Plan</p>
                <p class="mt-2 text-2xl font-bold">{{ $stats['total_visio_plan'] }}</p>
            </div>
            <div class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
                <p class="text-xs uppercase text-slate-500">Custo total calculado</p>
                <p class="mt-2 text-2xl font-bold">R$ {{ number_format($stats['total_cost'], 2, ',', '.') }}</p>
            </div>
        </section>

        <section class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
            <form method="GET" class="flex flex-nowrap items-end gap-3 overflow-x-auto pb-1">
                <label class="flex min-w-[260px] items-center gap-2 text-xs font-semibold text-slate-600">
                    <span class="whitespace-nowrap">Office Apps</span>
                    <input name="custo_office_apps" type="text" inputmode="decimal" value="{{ $costs['office_apps'] }}" placeholder="R$ 0,00" class="toolbar-input js-money-br w-full rounded-md border-slate-300 text-sm font-normal">
                </label>
                <label class="flex min-w-[260px] items-center gap-2 text-xs font-semibold text-slate-600">
                    <span class="whitespace-nowrap">Office Business</span>
                    <input name="custo_office_business" type="text" inputmode="decimal" value="{{ $costs['office_business'] }}" placeholder="R$ 0,00" class="toolbar-input js-money-br w-full rounded-md border-slate-300 text-sm font-normal">
                </label>
                <label class="flex min-w-[240px] items-center gap-2 text-xs font-semibold text-slate-600">
                    <span class="whitespace-nowrap">Power BI Pro</span>
                    <input name="custo_powerbi_pro" type="text" inputmode="decimal" value="{{ $costs['powerbi_pro'] }}" placeholder="R$ 0,00" class="toolbar-input js-money-br w-full rounded-md border-slate-300 text-sm font-normal">
                </label>
                <label class="flex min-w-[220px] items-center gap-2 text-xs font-semibold text-slate-600">
                    <span class="whitespace-nowrap">Visio Plan</span>
                    <input name="custo_visio_plan" type="text" inputmode="decimal" value="{{ $costs['visio_plan'] }}" placeholder="R$ 0,00" class="toolbar-input js-money-br w-full rounded-md border-slate-300 text-sm font-normal">
                </label>
                <button class="toolbar-btn-primary inline-flex min-h-10 shrink-0 items-center justify-center rounded-md border border-slate-900 bg-slate-900 px-4 text-sm font-semibold text-white">Calcular</button>
                <button name="export" value="xlsx" class="toolbar-btn inline-flex min-h-10 shrink-0 items-center justify-center rounded-md border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-700">Exportar XLSX</button>
            </form>
        </section>

        <section class="overflow-x-auto rounded-lg border border-slate-200 bg-white shadow-sm">
            <table class="w-full min-w-[1300px] text-sm">
                <thead class="bg-slate-900 text-left text-xs font-semibold uppercase text-white">
                    <tr>
                        <th class="px-4 py-3">Unidade</th>
                        <th class="px-4 py-3">Unicoop</th>
                        <th class="px-4 py-3">Área</th>
                        <th class="px-4 py-3">Office Apps</th>
                        <th class="px-4 py-3">Office Business</th>
                        <th class="px-4 py-3">Power BI Pro</th>
                        <th class="px-4 py-3">Visio Plan</th>
                        <th class="px-4 py-3">Total Licenças</th>
                        <th class="px-4 py-3">Custo Total</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200">
                    @forelse ($grouped as $row)
                        <tr>
                            <td class="px-4 py-3">{{ $row->unidade }}</td>
                            <td class="px-4 py-3">{{ $row->unicoop }}</td>
                            <td class="px-4 py-3">{{ $row->area }}</td>
                            <td class="px-4 py-3">{{ $row->total_office_apps }}</td>
                            <td class="px-4 py-3">{{ $row->total_office_business }}</td>
                            <td class="px-4 py-3">{{ $row->total_powerbi_pro }}</td>
                            <td class="px-4 py-3">{{ $row->total_visio_plan }}</td>
                            <td class="px-4 py-3">{{ $row->total_licencas }}</td>
                            <td class="px-4 py-3">R$ {{ number_format($row->custo_total, 2, ',', '.') }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="px-4 py-6 text-center text-slate-500">Nenhum registro ativo encontrado.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </section>
    </div>

    <script>
        (() => {
            const formatMoneyBr = (value) => {
                const digits = value.replace(/\D/g, '');
                if (!digits) return '';
                const number = Number(digits) / 100;
                return number.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            };

            document.querySelectorAll('.js-money-br').forEach((input) => {
                input.addEventListener('input', () => {
                    input.value = formatMoneyBr(input.value);
                });
            });
        })();
    </script>
</x-app-layout>
