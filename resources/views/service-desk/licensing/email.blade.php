<x-app-layout>
    <div class="mx-auto max-w-7xl space-y-6">
        <div>
            <p class="text-sm font-semibold uppercase tracking-wide text-slate-500">Licenciamento</p>
            <h1 class="mt-1 text-2xl font-bold text-slate-950">E-mail</h1>
        </div>

        <section class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm" x-data="emailRateioForm(@js((float) ($totals['cost'] ?? 0)))">
            <p class="mb-3 text-sm text-slate-600">
                Informe o <strong>valor total da fatura de e-mail</strong>. A quantidade de <strong>licenças não utilizadas</strong> é automática.
            </p>
            @if(!empty($totals['workspace_warning']))
                <div class="mb-3 rounded-md border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-800">
                    {{ $totals['workspace_warning'] }}
                </div>
            @endif
            <form method="GET" class="flex flex-wrap items-end gap-3 lg:flex-nowrap" @submit="beforeSubmit()">
                <label class="flex min-w-[260px] flex-[1_1_360px] flex-col gap-1 text-xs font-semibold uppercase tracking-wide text-slate-500">
                    <span>Custo da fatura de e-mail (R$)</span>
                    <input id="valor_fatura" x-model="valorFaturaDisplay" @input="formatMoney()" type="text" inputmode="decimal" placeholder="Ex.: 42.475,04" class="toolbar-input h-10 w-full rounded-md border-slate-300 text-sm">
                    <input type="hidden" name="valor_fatura" :value="valorFaturaRaw">
                </label>
                <label class="flex min-w-[240px] flex-[0_0_260px] flex-col gap-1 text-xs font-semibold uppercase tracking-wide text-slate-500">
                    <span>Licenças não utilizadas (automático)</span>
                    <input type="text" value="{{ $totals['extra'] }}" readonly class="toolbar-input h-10 w-full rounded-md border-slate-300 bg-slate-100 text-sm text-slate-700">
                </label>
                <div class="flex items-center gap-2 lg:ml-auto">
                    <button class="toolbar-btn-primary inline-flex h-10 items-center justify-center rounded-md border border-slate-900 bg-slate-900 px-4 text-sm font-semibold text-white shadow-sm transition hover:opacity-90">Calcular</button>
                    <button name="export" value="csv" class="toolbar-btn inline-flex h-10 items-center justify-center rounded-md border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50">Exportar CSV</button>
                </div>
            </form>
            <p class="mt-3 text-xs text-slate-500">
                Fórmula: <strong>custo unitário = valor da fatura / (e-mails ativos + licenças não utilizadas)</strong>.
                O custo das licenças não utilizadas é repartido proporcionalmente entre todas as unidades.
            </p>
        </section>

        <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <div class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm"><p class="text-xs uppercase text-slate-500">E-mails ativos</p><p class="mt-2 text-2xl font-bold">{{ $totals['emails'] }}</p></div>
            <div class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm"><p class="text-xs uppercase text-slate-500">E-mails inativos</p><p class="mt-2 text-2xl font-bold">{{ $totals['emails_inactive'] ?? 0 }}</p></div>
            <div class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm"><p class="text-xs uppercase text-slate-500">Licenças disponíveis</p><p class="mt-2 text-2xl font-bold">{{ $totals['extra'] }}</p></div>
            <div class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm"><p class="text-xs uppercase text-slate-500">Licenças em uso (Workspace)</p><p class="mt-2 text-2xl font-bold">{{ $totals['workspace_used'] }}</p></div>
            <div class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm"><p class="text-xs uppercase text-slate-500">Licenças totais (Workspace)</p><p class="mt-2 text-2xl font-bold">{{ $totals['workspace_total'] ?? '-' }}</p></div>
            <div class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm"><p class="text-xs uppercase text-slate-500">Valor fatura</p><p class="mt-2 text-2xl font-bold">R$ {{ number_format($totals['cost'], 2, ',', '.') }}</p></div>
            <div class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm"><p class="text-xs uppercase text-slate-500">Custo unitário</p><p class="mt-2 text-2xl font-bold">R$ {{ number_format($totals['unit_cost'], 2, ',', '.') }}</p></div>
        </section>

        <section class="overflow-x-auto rounded-lg border border-slate-200 bg-white shadow-sm">
            <table class="w-full min-w-[960px] text-sm">
                <thead class="bg-slate-900 text-left text-xs font-semibold uppercase text-white">
                    <tr>
                        <th class="px-4 py-3">Unidade</th>
                        <th class="px-4 py-3">Unicoop</th>
                        <th class="px-4 py-3">Área</th>
                        <th class="px-4 py-3">Qtd. E-mails</th>
                        <th class="px-4 py-3">Rateio Licenças Não Utilizadas</th>
                        <th class="px-4 py-3">Custo Total</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200">
                    @foreach ($rows as $row)
                        @php
                            $qtd = (int) $row->total;
                            $extraShare = ($totals['emails'] ?? 0) > 0
                                ? ($qtd / $totals['emails']) * ($totals['extra_cost_pool'] ?? 0)
                                : 0;
                            $baseCost = ($totals['unit_cost'] ?? 0) * $qtd;
                        @endphp
                        <tr>
                            <td class="px-4 py-3">{{ $row->unidade }}</td>
                            <td class="px-4 py-3">{{ $row->unicoop }}</td>
                            <td class="px-4 py-3">{{ $row->area }}</td>
                            <td class="px-4 py-3">{{ $qtd }}</td>
                            <td class="px-4 py-3">R$ {{ number_format($extraShare, 2, ',', '.') }}</td>
                            <td class="px-4 py-3">R$ {{ number_format($baseCost + $extraShare, 2, ',', '.') }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </section>
    </div>

    <script>
        function emailRateioForm(initialValue) {
            return {
                valorFaturaDisplay: '',
                valorFaturaRaw: 0,
                init() {
                    const value = Number(initialValue || 0);
                    this.valorFaturaRaw = value.toFixed(2);
                    this.valorFaturaDisplay = value.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                },
                formatMoney() {
                    const digits = (this.valorFaturaDisplay || '').replace(/\D/g, '');
                    if (!digits) {
                        this.valorFaturaDisplay = '';
                        this.valorFaturaRaw = 0;
                        return;
                    }
                    const cents = Number(digits) / 100;
                    this.valorFaturaRaw = cents.toFixed(2);
                    this.valorFaturaDisplay = cents.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                },
                beforeSubmit() {
                    if (!this.valorFaturaRaw || Number(this.valorFaturaRaw) < 0) {
                        this.valorFaturaRaw = 0;
                    }
                }
            };
        }
    </script>
</x-app-layout>
