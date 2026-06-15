<section class="rounded-lg border border-emerald-200 bg-emerald-50 p-5 shadow-sm">
    <div class="mb-3 flex items-center justify-between">
        <h2 class="text-base font-bold text-emerald-900">Reposição Estoque Interno da TI</h2>
        <span class="rounded-md bg-emerald-200 px-2 py-1 text-xs font-semibold text-emerald-900">{{ ($internalStockReplenishments ?? collect())->count() }} registros</span>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full min-w-[900px] text-sm">
            <thead class="bg-slate-900 text-left text-xs font-semibold uppercase text-white">
                <tr><th class="px-3 py-2">Plaqueta</th><th class="px-3 py-2">Unidade/Departamento</th><th class="px-3 py-2">Tipo</th><th class="px-3 py-2">Data chegada</th><th class="px-3 py-2">Peça usada</th><th class="px-3 py-2">Quantidade</th></tr>
            </thead>
            <tbody class="divide-y divide-slate-200">
                @forelse(($internalStockReplenishments ?? collect()) as $item)
                    <tr><td class="px-3 py-2">{{ $item->plaqueta }}</td><td class="px-3 py-2">{{ $item->unidade_setor }}</td><td class="px-3 py-2">{{ $item->tipo_equipamento }}</td><td class="px-3 py-2">{{ optional($item->data_chegada)->format('d/m/Y') ?: '-' }}</td><td class="px-3 py-2">{{ $item->peca_nome ?: '-' }}</td><td class="px-3 py-2">{{ $item->peca_quantidade ?: '-' }}</td></tr>
                @empty
                    <tr><td colspan="6" class="px-3 py-4 text-center text-slate-500">Sem consumo de estoque interno pendente.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</section>
