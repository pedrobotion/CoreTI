<x-app-layout>
    <div class="mx-auto max-w-7xl space-y-6">
        <div>
            <a href="{{ route('administrativo.estoque-interno') }}" class="text-xs font-semibold uppercase tracking-wide text-slate-500 underline">← Estoque interno</a>
            <h1 class="mt-2 text-2xl font-bold text-slate-950 dark:text-white">Histórico de movimentações</h1>
        </div>

        <section class="overflow-x-auto rounded-lg border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <table class="w-full min-w-[900px] text-sm">
                <thead class="bg-slate-900 text-left text-xs font-semibold uppercase text-white">
                    <tr>
                        <th class="px-4 py-3">Quando</th>
                        <th class="px-4 py-3">Tipo</th>
                        <th class="px-4 py-3">Produto</th>
                        <th class="px-4 py-3">Qtd</th>
                        <th class="px-4 py-3">Item / serial</th>
                        <th class="px-4 py-3">Motivo</th>
                        <th class="px-4 py-3">Destino</th>
                        <th class="px-4 py-3">Saldo</th>
                        <th class="px-4 py-3">Por</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    @forelse ($movimentos as $m)
                        <tr>
                            <td class="px-4 py-2 text-xs text-slate-500">{{ $m->created_at?->format('d/m/Y H:i') }}</td>
                            <td class="px-4 py-2">
                                <span class="rounded px-1.5 py-0.5 text-[11px] font-semibold {{ $m->tipo === 'entrada' ? 'bg-emerald-100 text-emerald-800' : ($m->tipo === 'baixa' ? 'bg-slate-100 text-slate-700' : 'bg-amber-100 text-amber-800') }}">{{ ucfirst($m->tipo) }}</span>
                            </td>
                            <td class="px-4 py-2 font-medium text-slate-900 dark:text-white">{{ $m->product?->nome }}</td>
                            <td class="px-4 py-2 font-semibold">{{ $m->quantidade }}</td>
                            <td class="px-4 py-2 text-xs text-slate-500">{{ $m->unit?->serial_number ?: ($m->unit?->plaqueta ?: '—') }}</td>
                            <td class="px-4 py-2 text-xs text-slate-500">{{ $m->motivo ? str_replace('_', ' ', $m->motivo) : '—' }}</td>
                            <td class="px-4 py-2 text-xs text-slate-500">{{ $m->colaborador?->name ?? $m->destino_descricao ?? '—' }}</td>
                            <td class="px-4 py-2 text-xs text-slate-500">{{ $m->saldo_apos }}</td>
                            <td class="px-4 py-2 text-xs text-slate-400">{{ $m->autor?->name ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="9" class="px-4 py-10 text-center text-sm text-slate-500">Sem movimentações registradas.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </section>

        <div>{{ $movimentos->links() }}</div>
    </div>
</x-app-layout>
