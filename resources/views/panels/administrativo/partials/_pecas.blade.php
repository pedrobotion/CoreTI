<section class="rounded-lg border-2 border-blue-200 bg-blue-50 p-5 shadow-sm">
    <div class="mb-3 flex items-center justify-between">
        <h2 class="text-base font-bold text-blue-900">Pendências Peças (CD / Internet / Dell)</h2>
        <span class="rounded-md bg-blue-200 px-2 py-1 text-xs font-semibold text-blue-900">{{ ($pendingParts ?? collect())->count() }} pendências</span>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full min-w-[900px] text-sm">
            <thead class="bg-slate-900 text-left text-xs font-semibold uppercase text-white">
                <tr><th class="px-3 py-2">Plaqueta</th><th class="px-3 py-2">Tipo</th><th class="px-3 py-2">Unidade/Setor</th><th class="px-3 py-2">Peça</th><th class="px-3 py-2">Qtd</th><th class="px-3 py-2">Origem</th><th class="px-3 py-2">Status fluxo</th></tr>
            </thead>
            <tbody class="divide-y divide-slate-200">
                @forelse(($pendingParts ?? collect()) as $item)
                    <tr><td class="px-3 py-2">{{ $item->plaqueta }}</td><td class="px-3 py-2">{{ $item->tipo_equipamento }}</td><td class="px-3 py-2">{{ $item->unidade_setor }}</td><td class="px-3 py-2">{{ $item->peca_nome ?: '-' }}</td><td class="px-3 py-2">{{ $item->peca_quantidade ?: '-' }}</td><td class="px-3 py-2">{{ $item->peca_origem ?: '-' }}</td><td class="px-3 py-2">{{ $item->peca_fluxo_status ?: '-' }}</td></tr>
                @empty
                    <tr><td colspan="7" class="px-3 py-4 text-center text-slate-500">Sem pendências de peças.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</section>
