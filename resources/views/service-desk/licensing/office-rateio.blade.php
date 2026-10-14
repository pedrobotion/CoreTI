<x-app-layout>
    @php
        $legacyLabels = [
            'OFFICE_APPS' => 'Custo Office Apps',
            'OFFICE_BUSINESS' => 'Custo Office Business',
            'POWER_BI_PRO' => 'Custo Power BI Pro',
            'POWER_BI_PREMIUM' => 'Custo Power BI Premium',
            'VISIO_PLAN' => 'Custo Visio Plan',
        ];

        // Consolidado por centro de custo (unicoop.área), nomes do catálogo
        // atual, já pronto no serviço — tela e Excel compartilham a mesma fonte.
        $legacyRows = collect($consolidado ?? []);

        $totalLicencas = $legacyRows->sum('quantidade');
        $totalCusto = $legacyRows->sum('custo_total');
    @endphp

    <div class="mx-auto max-w-7xl space-y-5">
        <section class="rounded-lg border border-slate-200 bg-white shadow-sm">
            <div class="flex flex-col gap-5 p-5 lg:flex-row lg:items-center lg:justify-between">
                <div class="flex items-start gap-4">
                    <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-md bg-slate-900 text-sm font-bold text-white shadow-sm">OF</div>
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Licenciamento</p>
                        <h1 class="mt-1 text-2xl font-bold text-slate-900">Rateio Office</h1>
                    <p class="mt-1 max-w-2xl text-sm text-slate-500">Cálculo por valor unitário × quantidade de licenças, com detalhamento por colaborador.</p>
                    </div>
                </div>

                <div class="flex flex-col gap-2 sm:flex-row sm:items-center">
                    @if($batch)
                        <span class="inline-flex min-h-10 items-center justify-center rounded-md border border-emerald-200 bg-emerald-50 px-3 text-xs font-semibold uppercase text-emerald-700">Rateio disponível</span>
                        <a href="{{ route('administrativo.licensing.office.allocation.export', $batch) }}" class="inline-flex min-h-10 items-center justify-center rounded-md border border-slate-900 bg-slate-900 px-4 text-sm font-semibold text-white shadow-sm hover:bg-slate-800">Exportar Excel</a>
                    @else
                        <span class="inline-flex min-h-10 items-center justify-center rounded-md border border-amber-200 bg-amber-50 px-3 text-xs font-semibold uppercase text-amber-700">Aguardando geração</span>
                    @endif
                </div>
            </div>

            <div class="grid border-t border-slate-200 sm:grid-cols-4">
                <div class="border-b border-slate-200 px-5 py-4 sm:border-b-0 sm:border-r">
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Competência</p>
                    <p class="mt-1 text-2xl font-semibold text-slate-900">{{ $batch?->competence ?? $competence }}</p>
                </div>
                <div class="border-b border-slate-200 px-5 py-4 sm:border-b-0 sm:border-r">
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Atribuições</p>
                    <p class="mt-1 text-2xl font-semibold text-slate-900">{{ number_format((int) ($batch?->assignments_count ?? $totalLicencas), 0, ',', '.') }}</p>
                </div>
                <div class="px-5 py-4">
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Classificadas / pendentes</p>
                    <p class="mt-1 text-2xl font-semibold text-slate-900">{{ (int) ($batch?->classified_assignments_count ?? 0) }} / {{ (int) ($batch?->pending_assignments_count ?? 0) }}</p>
                </div>
                <div class="px-5 py-4">
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Custo classificado</p>
                    <p class="mt-1 text-2xl font-semibold text-blue-700">R$ {{ number_format($totalCusto, 2, ',', '.') }}</p>
                </div>
            </div>
        </section>

        <section class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
            <div class="flex flex-col gap-4 xl:flex-row xl:items-end xl:justify-between">
                <div>
                    <h2 class="text-base font-semibold text-slate-900">Operação do rateio</h2>
                    <p class="mt-1 text-sm text-slate-500">Aplique os custos, selecione a competência e atualize o consolidado.</p>
                </div>

                <div class="flex flex-col gap-3 lg:flex-row lg:items-end">
                    <form method="POST" action="{{ route('administrativo.licensing.office.generate') }}" class="flex flex-col gap-3 sm:flex-row sm:items-end">
                        @csrf
                        <label class="block">
                            <span class="text-xs font-semibold uppercase text-slate-500">Competência</span>
                            <input type="month" name="competence" value="{{ $competence }}" required class="mt-1 min-h-10 rounded-md border-slate-300 bg-white text-sm shadow-sm focus:border-slate-900 focus:ring-slate-900">
                        </label>
                        <button class="inline-flex min-h-10 items-center justify-center rounded-md border border-slate-900 bg-slate-900 px-4 text-sm font-semibold text-white shadow-sm hover:bg-slate-800">Gerar / atualizar</button>
                    </form>
                    @if($batch)
                        <form method="POST" action="{{ route('administrativo.licensing.office.reprocess', $batch) }}">
                            @csrf
                            <button class="inline-flex min-h-10 items-center justify-center rounded-md border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50">Reprocessar</button>
                        </form>
                    @endif
                </div>
            </div>

            <form method="POST" action="{{ route('administrativo.licensing.office-prices.store') }}" class="mt-5 rounded-md border border-slate-200 bg-slate-50/80 p-3">
                @csrf
                <input type="hidden" name="currency" value="BRL">
                <input type="hidden" name="valid_from" value="{{ $competence }}-01">
                <input type="hidden" name="competence" value="{{ $competence }}">
                <input type="hidden" name="back_to_rateio" value="1">

                <div class="flex gap-2 overflow-x-auto pb-1">
                    @foreach($skuCatalog as $sku)
                        @php
                            $currentPrice = $latestPrices->get($sku['sku_id']);
                            $fieldValue = old("prices.{$sku['sku_id']}", $currentPrice ? number_format((float) $currentPrice->monthly_price, 2, ',', '.') : '');
                        @endphp
                        <label class="block min-w-[180px] flex-1">
                            <span class="block truncate px-1 text-xs font-semibold uppercase text-slate-500">{{ $legacyLabels[$sku['sku_id']] ?? $sku['name'] }}</span>
                            <div class="mt-1 flex min-h-10 items-center rounded-md border border-slate-300 bg-white shadow-sm focus-within:border-slate-900 focus-within:ring-1 focus-within:ring-slate-900">
                                <span class="border-r border-slate-200 px-3 text-sm font-semibold text-slate-400">R$</span>
                                <input
                                    name="prices[{{ $sku['sku_id'] }}]"
                                    value="{{ $fieldValue }}"
                                    placeholder="0,00"
                                    inputmode="decimal"
                                    class="min-h-10 w-full border-0 bg-transparent text-sm font-semibold text-slate-900 focus:ring-0"
                                >
                            </div>
                        </label>
                    @endforeach
                    <div class="flex min-w-[145px] items-end">
                        <button class="inline-flex min-h-10 w-full items-center justify-center rounded-md border border-blue-700 bg-blue-700 px-4 text-sm font-semibold text-white shadow-sm hover:bg-blue-800">Aplicar</button>
                    </div>
                </div>
            </form>
        </section>

        @if(!$batch)
            <section class="rounded-lg border border-amber-200 bg-amber-50 p-5 text-sm text-amber-900 shadow-sm">
                Nenhum rateio gerado para a competência {{ $competence }}. Configure os preços e clique em “Gerar / atualizar rateio”.
            </section>
        @else
            @if((int) $batch->pending_assignments_count > 0)
                <section class="rounded-lg border border-amber-200 bg-amber-50 p-5 text-sm text-amber-900">
                    Existem {{ (int) $batch->pending_assignments_count }} atribuições sem classificação completa. Elas aparecem no detalhamento abaixo, mas não entram no consolidado enquanto não tiverem preço, unidade, colaborador ou centro de custo resolvido.
                </section>
            @endif

            <section class="rounded-lg border border-slate-200 bg-white shadow-sm">
                <div class="border-b border-slate-200 p-5">
                    <h2 class="text-base font-semibold text-slate-900">Resumo por licença</h2>
                    <p class="mt-1 text-sm text-slate-500">Quantidade e custo das quatro licenças faturáveis: valor unitário × quantidade.</p>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[680px] text-sm">
                        <thead class="bg-slate-50 text-left text-xs font-semibold uppercase text-slate-500">
                            <tr><th class="px-4 py-3">Licença</th><th class="px-4 py-3">SKU</th><th class="px-4 py-3 text-right">Quantidade</th><th class="px-4 py-3 text-right">Preço unitário</th><th class="px-4 py-3 text-right">Total</th></tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200">
                            @forelse($skuSummary as $skuRow)
                                <tr>
                                    <td class="px-4 py-3 font-medium text-slate-900">{{ $skuRow->sku_name }}</td>
                                    <td class="px-4 py-3 font-mono text-xs text-slate-600">{{ $skuRow->sku_id }}</td>
                                    <td class="px-4 py-3 text-right">{{ (int) $skuRow->quantity }}</td>
                                    <td class="px-4 py-3 text-right">R$ {{ number_format((float) $skuRow->unit_price, 2, ',', '.') }}</td>
                                    <td class="px-4 py-3 text-right font-semibold">R$ {{ number_format((float) $skuRow->total_amount, 2, ',', '.') }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="px-4 py-6 text-center text-slate-500">Nenhuma licença encontrada.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="rounded-lg border border-slate-200 bg-white shadow-sm">
                <div class="border-b border-slate-200 p-5">
                    <h2 class="text-base font-semibold text-slate-900">Resumo por departamento da Sede</h2>
                    <p class="mt-1 text-sm text-slate-500">Licenças e custo mensal estimado dos departamentos classificados como Sede.</p>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[1080px] text-sm">
                        <thead class="bg-slate-50 text-left text-xs font-semibold uppercase text-slate-500">
                            <tr>
                                <th class="px-4 py-3">Departamento</th>
                                <th class="px-4 py-3">Centro de custo</th>
                                <th class="px-4 py-3 text-right">Usuários</th>
                                <th class="px-4 py-3 text-right">Office Apps</th>
                                <th class="px-4 py-3 text-right">Office Business</th>
                                <th class="px-4 py-3 text-right">Power BI</th>
                                <th class="px-4 py-3 text-right">Visio</th>
                                <th class="px-4 py-3 text-right">Outras</th>
                                <th class="px-4 py-3 text-right">Total</th>
                                <th class="px-4 py-3 text-right">Custo mensal</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200">
                            @forelse($departmentSummary as $departmentRow)
                                @php($departmentTotal = (int) $departmentRow->apps_count + (int) $departmentRow->business_count + (int) $departmentRow->powerbi_count + (int) $departmentRow->visio_count + (int) $departmentRow->other_count)
                                <tr class="hover:bg-blue-50">
                                    <td class="px-4 py-3 font-medium text-slate-900">{{ $departmentRow->department_name }}</td>
                                    <td class="px-4 py-3 font-mono text-xs text-slate-600">{{ $departmentRow->cost_center ?: '-' }}</td>
                                    <td class="px-4 py-3 text-right">{{ (int) $departmentRow->users_count }}</td>
                                    <td class="px-4 py-3 text-right">{{ (int) $departmentRow->apps_count }}</td>
                                    <td class="px-4 py-3 text-right">{{ (int) $departmentRow->business_count }}</td>
                                    <td class="px-4 py-3 text-right">{{ (int) $departmentRow->powerbi_count }}</td>
                                    <td class="px-4 py-3 text-right">{{ (int) $departmentRow->visio_count }}</td>
                                    <td class="px-4 py-3 text-right">{{ (int) $departmentRow->other_count }}</td>
                                    <td class="px-4 py-3 text-right font-semibold">{{ $departmentTotal }}</td>
                                    <td class="px-4 py-3 text-right font-semibold text-blue-700">R$ {{ number_format((float) $departmentRow->total_amount, 2, ',', '.') }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="10" class="px-4 py-6 text-center text-slate-500">Nenhum departamento da Sede classificado para esta competência.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="rounded-lg border border-slate-200 bg-white shadow-sm">
                <div class="border-b border-slate-200 p-5">
                    <h2 class="text-base font-semibold text-slate-900">Resumo por unidade</h2>
                    <p class="mt-1 text-sm text-slate-500">Quantidade de licenças e custo mensal estimado de cada unidade nesta competência.</p>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[980px] text-sm">
                        <thead class="bg-slate-50 text-left text-xs font-semibold uppercase text-slate-500">
                            <tr>
                                <th class="px-4 py-3">Unidade</th>
                                <th class="px-4 py-3 text-right">Usuários</th>
                                <th class="px-4 py-3 text-right">Office Apps</th>
                                <th class="px-4 py-3 text-right">Office Business</th>
                                <th class="px-4 py-3 text-right">Power BI</th>
                                <th class="px-4 py-3 text-right">Visio</th>
                                <th class="px-4 py-3 text-right">Outras</th>
                                <th class="px-4 py-3 text-right">Total</th>
                                <th class="px-4 py-3 text-right">Custo mensal</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200">
                            @forelse($unitSummary as $unitRow)
                                @php($unitTotal = (int) $unitRow->apps_count + (int) $unitRow->business_count + (int) $unitRow->powerbi_count + (int) $unitRow->visio_count + (int) $unitRow->other_count)
                                <tr class="hover:bg-blue-50">
                                    <td class="px-4 py-3 font-medium text-slate-900">{{ $unitRow->unit_name ?: 'Pendente de mapeamento' }}</td>
                                    <td class="px-4 py-3 text-right">{{ (int) $unitRow->users_count }}</td>
                                    <td class="px-4 py-3 text-right">{{ (int) $unitRow->apps_count }}</td>
                                    <td class="px-4 py-3 text-right">{{ (int) $unitRow->business_count }}</td>
                                    <td class="px-4 py-3 text-right">{{ (int) $unitRow->powerbi_count }}</td>
                                    <td class="px-4 py-3 text-right">{{ (int) $unitRow->visio_count }}</td>
                                    <td class="px-4 py-3 text-right">{{ (int) $unitRow->other_count }}</td>
                                    <td class="px-4 py-3 text-right font-semibold">{{ $unitTotal }}</td>
                                    <td class="px-4 py-3 text-right font-semibold text-blue-700">R$ {{ number_format((float) $unitRow->total_amount, 2, ',', '.') }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="9" class="px-4 py-6 text-center text-slate-500">Nenhuma unidade classificada para esta competência.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="rounded-lg border border-slate-200 bg-white shadow-sm">
                <div class="flex flex-col gap-3 border-b border-slate-200 p-5 lg:flex-row lg:items-end lg:justify-between">
                    <div>
                        <h2 class="text-base font-semibold text-slate-900">Detalhamento por colaborador e licença</h2>
                        <p class="mt-1 text-sm text-slate-500">Uma linha por atribuição, incluindo pendências e SKU reais do Microsoft Graph.</p>
                    </div>
                    <form method="GET" class="flex flex-wrap items-end gap-2">
                        <input type="hidden" name="competence" value="{{ $competence }}">
                        <label class="block"><span class="text-xs font-semibold uppercase text-slate-500">Buscar</span><input name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Nome, e-mail, licença ou unidade" class="mt-1 min-h-10 rounded-md border-slate-300 text-sm"></label>
                        <label class="inline-flex min-h-10 items-center gap-2 px-2 text-sm"><input type="checkbox" name="pending" value="1" @checked(request()->boolean('pending'))> Pendentes</label>
                        <button class="inline-flex min-h-10 items-center justify-center rounded-md border border-slate-700 bg-white px-3 text-sm font-semibold text-slate-700">Filtrar</button>
                    </form>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[1500px] text-sm">
                        <thead class="bg-slate-50 text-left text-xs font-semibold uppercase text-slate-500">
                            <tr><th class="px-4 py-3">Colaborador</th><th class="px-4 py-3">E-mail</th><th class="px-4 py-3">Licença</th><th class="px-4 py-3">Unidade</th><th class="px-4 py-3">Centro de custo</th><th class="px-4 py-3">Status</th><th class="px-4 py-3 text-right">Valor</th><th class="px-4 py-3">Mensagem</th></tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200">
                            @forelse($detailRows as $detail)
                                <tr class="{{ $detail->classification_status === 'classified' ? '' : 'bg-amber-50' }}">
                                    <td class="px-4 py-3 font-medium text-slate-900">{{ $detail->user_name }}</td>
                                    <td class="px-4 py-3">{{ $detail->user_email }}</td>
                                    <td class="px-4 py-3">{{ $detail->sku_name }}<div class="font-mono text-xs text-slate-500">{{ $detail->sku_id }}</div></td>
                                    <td class="px-4 py-3">{{ $detail->catalog_cost_center_name ?: ($detail->unit_name_snapshot ?: 'N/D') }}</td>
                                    <td class="px-4 py-3">{{ $detail->cost_center_code_snapshot ?: 'N/D' }}<div class="text-xs text-slate-500">{{ $detail->catalog_cost_center_name ?: $detail->cost_center_name_snapshot }}</div></td>
                                    <td class="px-4 py-3 font-semibold">{{ $classificationLabels[$detail->classification_status] ?? $detail->classification_status }}</td>
                                    <td class="px-4 py-3 text-right">R$ {{ number_format((float) $detail->total_amount, 2, ',', '.') }}</td>
                                    <td class="max-w-md px-4 py-3 text-xs text-slate-600">{{ $detail->classification_message ?: '-' }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="8" class="px-4 py-6 text-center text-slate-500">Nenhuma atribuição encontrada.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                @if($batch && $detailRows->count() >= 200)
                    <p class="border-t border-slate-200 px-5 py-3 text-xs text-slate-500">Exibindo as primeiras 200 linhas. Use a busca para localizar outras atribuições ou exporte o Excel completo.</p>
                @endif
            </section>

            <section class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm">
                <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                    <div class="p-5 pb-0">
                        <h2 class="text-base font-semibold text-slate-900">Consolidado para importação</h2>
                        <p class="text-sm text-slate-500">Mesmo layout do Excel legado: Unidade, Unicoop, Área, Quantidade e Custo Total.</p>
                    </div>
                    @if($batch)
                        <div class="px-5 pt-5 lg:pb-0">
                            <a href="{{ route('administrativo.licensing.office.allocation.export', $batch) }}" class="inline-flex min-h-10 items-center justify-center rounded-md border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50">Exportar Excel</a>
                        </div>
                    @endif
                </div>

                <div class="mt-4 overflow-x-auto">
                    <table class="w-full min-w-[760px] text-sm">
                        <thead class="border-y border-slate-200 bg-slate-50 text-left text-xs font-semibold uppercase text-slate-500">
                            <tr>
                                <th class="px-4 py-3">Unidade</th>
                                <th class="px-4 py-3">Unicoop</th>
                                <th class="px-4 py-3">Área</th>
                                <th class="px-4 py-3">Quantidade de Licenças</th>
                                <th class="px-4 py-3">Custo Total (R$)</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200">
                            @forelse($legacyRows as $row)
                                <tr class="bg-white hover:bg-blue-50">
                                    <td class="px-4 py-3 font-medium text-slate-900">{{ $row['unidade'] }}</td>
                                    <td class="px-4 py-3 font-mono text-xs">{{ $row['unicoop'] }}</td>
                                    <td class="px-4 py-3 font-mono text-xs">{{ $row['area'] }}</td>
                                    <td class="px-4 py-3 text-right">{{ (int) $row['quantidade'] }}</td>
                                    <td class="px-4 py-3 text-right font-semibold">R$ {{ number_format((float) $row['custo_total'], 2, ',', '.') }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="px-4 py-6 text-center text-slate-500">Nenhum centro de custo classificado para esta competência.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>
        @endif
    </div>
</x-app-layout>
