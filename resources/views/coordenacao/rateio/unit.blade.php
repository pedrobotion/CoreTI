<x-app-layout>
    <div class="mx-auto max-w-5xl space-y-6">
        @include('coordenacao.partials.nav',['active'=>'rateio'])
        <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:px-6">
            <a class="inline-flex items-center gap-1 text-sm font-semibold text-[#033151] hover:underline" href="{{ route('coordenacao.rateio.index',['month'=>$month->format('Y-m')]) }}">← Voltar ao rateio</a>
            <h2 class="mt-3 text-xl font-bold tracking-tight text-slate-900">Unidade: {{ $unitKey }}</h2>
            <p class="mt-1 text-sm text-slate-500">Competência {{ $month->format('m/Y') }}</p>
        </section>
        <section class="rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-slate-100 text-left text-xs font-semibold uppercase tracking-wide text-slate-400">
                            <th class="px-5 py-3 sm:px-6">Produto</th><th class="px-5 py-3">Participantes</th><th class="px-5 py-3">Total</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse($allocations as $row)
                            <tr class="transition hover:bg-slate-50">
                                <td class="px-5 py-3 font-medium text-slate-800 sm:px-6">{{ $row->product->name }}</td>
                                <td class="px-5 py-3 text-slate-600">{{ $row->eligible_users }}</td>
                                <td class="px-5 py-3 font-mono text-slate-700">R$ {{ number_format($row->individual_amount + $row->shared_amount,2,',','.') }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="px-6 py-10 text-center text-sm text-slate-400">Sem dados para esta unidade.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</x-app-layout>
