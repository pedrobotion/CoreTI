<x-app-layout>
    <div class="mx-auto max-w-7xl space-y-6">
        @include('coordenacao.partials.nav',['active'=>'rateio'])

        <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:px-6">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div class="flex items-center gap-3">
                    <span class="grid h-9 w-9 shrink-0 place-items-center rounded-lg bg-teal-50 text-teal-600">
                        <svg viewBox="0 0 24 24" class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 1v22M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
                    </span>
                    <div>
                        <h2 class="text-base font-bold text-slate-900">Rateio mensal</h2>
                        <p class="mt-0.5 text-sm text-slate-500">Competência {{ $month->format('m/Y') }} · Status: {{ $closing->status }}</p>
                    </div>
                </div>
                <form class="flex gap-2">
                    <input type="month" name="month" value="{{ $month->format('Y-m') }}" class="rounded-md border-slate-300 text-sm">
                    <input type="hidden" name="calculate" value="1">
                    <button style="background-color: #033151; border-color: #033151;" class="inline-flex min-h-10 items-center justify-center rounded-lg px-4 text-sm font-semibold text-white hover:opacity-90">Calcular / atualizar</button>
                </form>
            </div>
            <div class="mt-5 grid gap-4 sm:grid-cols-3">
                <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm"><span class="text-xs font-semibold uppercase tracking-wide text-slate-500">Total</span><strong class="mt-1 block text-2xl font-bold text-slate-900">R$ {{ number_format($closing->total_amount,2,',','.') }}</strong></div>
                <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm"><span class="text-xs font-semibold uppercase tracking-wide text-slate-500">Individual</span><strong class="mt-1 block text-2xl font-bold text-slate-900">R$ {{ number_format($closing->total_individual_amount,2,',','.') }}</strong></div>
                <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm"><span class="text-xs font-semibold uppercase tracking-wide text-slate-500">Compartilhado</span><strong class="mt-1 block text-2xl font-bold text-slate-900">R$ {{ number_format($closing->total_shared_amount,2,',','.') }}</strong></div>
            </div>
        </section>

        <section class="rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="flex items-center justify-between gap-3 border-b border-slate-100 p-5 sm:px-6">
                <h2 class="text-base font-bold text-slate-900">Apropriação por unidade</h2>
                <span class="inline-flex items-center rounded-full bg-teal-50 px-2.5 py-1 text-xs font-semibold text-teal-700 ring-1 ring-inset ring-teal-200">{{ number_format($allocations->count(), 0, ',', '.') }} linha(s)</span>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full min-w-[900px] text-sm">
                    <thead>
                        <tr class="border-b border-slate-100 text-left text-xs font-semibold uppercase tracking-wide text-slate-400">
                            <th class="px-5 py-3 sm:px-6">Unidade</th><th class="px-5 py-3">Produto</th><th class="px-5 py-3">Participantes</th><th class="px-5 py-3">Individual</th><th class="px-5 py-3">Compartilhado</th><th class="px-5 py-3">Total</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse($allocations as $allocation)
                            <tr class="transition hover:bg-slate-50">
                                <td class="px-5 py-3 sm:px-6"><a class="font-semibold text-[#033151] hover:underline" href="{{ route('coordenacao.rateio.unit',['unitKey'=>$allocation->unit_key,'month'=>$month->format('Y-m')]) }}">{{ $allocation->unit_key }}</a></td>
                                <td class="px-5 py-3 text-slate-700">{{ $allocation->product->name }}</td>
                                <td class="px-5 py-3 text-slate-600">{{ $allocation->eligible_users }}</td>
                                <td class="px-5 py-3 font-mono text-slate-700">R$ {{ number_format($allocation->individual_amount,2,',','.') }}</td>
                                <td class="px-5 py-3 font-mono text-slate-700">R$ {{ number_format($allocation->shared_amount,2,',','.') }}</td>
                                <td class="px-5 py-3 font-mono font-semibold text-slate-900">R$ {{ number_format($allocation->individual_amount + $allocation->shared_amount,2,',','.') }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="px-6 py-10 text-center text-sm text-slate-400">Nenhum cálculo realizado para a competência.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</x-app-layout>
