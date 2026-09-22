<x-app-layout>
    <div class="mx-auto max-w-7xl space-y-6">

        <div>
            <h1 class="text-xl font-bold text-slate-950">Exclusão de contas do Active Directory</h1>
            <p class="text-sm text-slate-500">
                Todo dia 1º o servidor do AD envia a lista de contas desativadas há mais de
                {{ config('ad.expurgo.dias_padrao', 30) }} dias. Nenhuma é excluída antes da conferência aqui.
            </p>
        </div>

        @if (session('success'))
            <div class="rounded-lg border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-800">{{ session('success') }}</div>
        @endif
        @if (session('error'))
            <div class="rounded-lg border border-rose-200 bg-rose-50 p-4 text-sm text-rose-800">{{ session('error') }}</div>
        @endif

        <div class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50">
                        <tr>
                            <th class="px-4 py-3 text-left font-semibold text-slate-600">Competência</th>
                            <th class="px-4 py-3 text-left font-semibold text-slate-600">Situação</th>
                            <th class="px-4 py-3 text-right font-semibold text-slate-600">Candidatas</th>
                            <th class="px-4 py-3 text-right font-semibold text-slate-600">Aprovadas</th>
                            <th class="px-4 py-3 text-right font-semibold text-slate-600">Excluídas</th>
                            <th class="px-4 py-3 text-left font-semibold text-slate-600">Decisão</th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($lotes as $lote)
                            <tr class="{{ $lote->pendente() ? 'bg-amber-50/50' : '' }}">
                                <td class="px-4 py-3 font-semibold text-slate-900">{{ $lote->referencia }}</td>
                                <td class="px-4 py-3">
                                    <span class="inline-flex items-center rounded-full px-2 py-1 text-xs font-medium ring-1 ring-inset {{ $lote->classesBadge() }}">
                                        {{ $lote->rotulo() }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-right text-slate-700">{{ $lote->total_candidatos }}</td>
                                <td class="px-4 py-3 text-right text-slate-700">{{ $lote->total_aprovados }}</td>
                                <td class="px-4 py-3 text-right font-semibold text-slate-900">{{ $lote->excluidos_count }}</td>
                                <td class="px-4 py-3 text-slate-500">
                                    @if ($lote->aprovado_em)
                                        {{ $lote->aprovador?->name ?? '—' }}
                                        <span class="block text-xs">{{ $lote->aprovado_em->local()->format('d/m/Y H:i') }}</span>
                                    @elseif ($lote->rejeitado_em)
                                        {{ $lote->rejeitador?->name ?? '—' }}
                                        <span class="block text-xs">rejeitado em {{ $lote->rejeitado_em->local()->format('d/m/Y H:i') }}</span>
                                    @else
                                        —
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <a href="{{ route('ad.exclusao.show', $lote) }}" class="btn-coreti-secondary text-xs">
                                        {{ $lote->pendente() ? 'Conferir' : 'Ver' }}
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-4 py-10 text-center text-slate-500">
                                    Nenhum lote recebido ainda. O servidor do AD envia a primeira prévia no próximo dia 1º.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{ $lotes->links() }}
    </div>
</x-app-layout>
