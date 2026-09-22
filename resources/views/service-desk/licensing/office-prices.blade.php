<x-app-layout>
    @php
        $legacyLabels = [
            'OFFICE_APPS' => 'Custo Office Apps',
            'OFFICE_BUSINESS' => 'Custo Office Business',
            'POWER_BI_PRO' => 'Custo Power BI Pro',
            'POWER_BI_PREMIUM' => 'Custo Power BI Premium',
            'VISIO_PLAN' => 'Custo Visio Plan',
        ];
    @endphp

    <div class="mx-auto max-w-5xl space-y-6">
        <section class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                <div>
                    <p class="text-sm font-semibold uppercase tracking-wide text-slate-500">Licenciamento</p>
                    <h1 class="mt-1 text-2xl font-bold text-slate-950">Configurar custos do Office</h1>
                    <p class="mt-1 text-sm text-slate-600">Informe os valores unitários usados no cálculo do Rateio Office.</p>
                </div>
                <a href="{{ route('administrativo.licensing.office') }}" class="inline-flex min-h-10 items-center justify-center rounded-md border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50">Voltar ao rateio</a>
            </div>
        </section>

        <section class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
            <form method="POST" action="{{ route('administrativo.licensing.office-prices.store') }}" class="space-y-5">
                @csrf
                <input type="hidden" name="currency" value="BRL">
                <input type="hidden" name="valid_from" value="{{ now()->startOfMonth()->toDateString() }}">

                <div class="grid gap-4 md:grid-cols-2">
                    @foreach($skuCatalog as $sku)
                        @php
                            $currentPrice = $latestPrices->get($sku['sku_id']);
                            $fieldValue = old("prices.{$sku['sku_id']}", $currentPrice ? number_format((float) $currentPrice->monthly_price, 2, ',', '.') : '');
                        @endphp
                        <label class="block">
                            <span class="text-xs font-semibold uppercase text-slate-500">{{ $legacyLabels[$sku['sku_id']] ?? $sku['name'] }}</span>
                            <div class="mt-1 flex min-h-10 items-center rounded-md border border-slate-300 bg-white shadow-sm focus-within:border-slate-900 focus-within:ring-1 focus-within:ring-slate-900">
                                <span class="border-r border-slate-200 px-3 text-sm font-semibold text-slate-500">R$</span>
                                <input
                                    name="prices[{{ $sku['sku_id'] }}]"
                                    value="{{ $fieldValue }}"
                                    placeholder="0,00"
                                    inputmode="decimal"
                                    class="min-h-10 w-full border-0 bg-transparent text-sm focus:ring-0"
                                >
                            </div>
                        </label>
                    @endforeach
                </div>

                <div class="flex justify-end">
                    <button class="inline-flex min-h-10 items-center justify-center rounded-md border border-slate-900 bg-slate-900 px-4 text-sm font-semibold text-white shadow-sm hover:bg-slate-800">Aplicar custos</button>
                </div>
            </form>
        </section>
    </div>
</x-app-layout>
