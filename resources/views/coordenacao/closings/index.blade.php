<x-app-layout>
    <div class="mx-auto max-w-7xl space-y-6">
        @include('coordenacao.partials.nav',['active'=>'closings'])

        <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:px-6">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div class="flex items-center gap-3">
                    <span class="grid h-9 w-9 shrink-0 place-items-center rounded-lg bg-teal-50 text-teal-600">
                        <svg viewBox="0 0 24 24" class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>
                    </span>
                    <div>
                        <h2 class="text-base font-bold text-slate-900">Fechamentos mensais</h2>
                        <p class="mt-0.5 text-sm text-slate-500">O fechamento registra um snapshot operacional da competência.</p>
                    </div>
                </div>
                <form method="POST" action="{{ route('coordenacao.closings.store') }}" class="flex gap-2">
                    @csrf
                    <input type="month" name="reference_month" value="{{ now()->format('Y-m') }}" class="rounded-md border-slate-300 text-sm">
                    <button style="background-color: #033151; border-color: #033151;" class="inline-flex min-h-10 items-center justify-center rounded-lg px-4 text-sm font-semibold text-white hover:opacity-90">Criar competência</button>
                </form>
            </div>
        </section>

        <section class="rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="flex items-center justify-between gap-3 border-b border-slate-100 p-5 sm:px-6">
                <h2 class="text-base font-bold text-slate-900">Competências</h2>
                <span class="inline-flex items-center rounded-full bg-teal-50 px-2.5 py-1 text-xs font-semibold text-teal-700 ring-1 ring-inset ring-teal-200">{{ number_format($closings->total(), 0, ',', '.') }} registro(s)</span>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-slate-100 text-left text-xs font-semibold uppercase tracking-wide text-slate-400">
                            <th class="px-5 py-3 sm:px-6">Competência</th><th class="px-5 py-3">Status</th><th class="px-5 py-3">Total</th><th class="px-5 py-3 text-right">Ações</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse($closings as $closing)
                            <tr class="transition hover:bg-slate-50">
                                <td class="px-5 py-3 font-medium text-slate-800 sm:px-6">{{ $closing->reference_month->format('m/Y') }}</td>
                                <td class="px-5 py-3">
                                    <span class="inline-flex items-center rounded-full bg-slate-100 px-2 py-1 text-xs font-semibold text-slate-600 ring-1 ring-inset ring-slate-200">{{ $closing->status }}</span>
                                </td>
                                <td class="px-5 py-3 font-mono text-slate-700">R$ {{ number_format($closing->total_amount,2,',','.') }}</td>
                                <td class="px-5 py-3 text-right">
                                    <a class="inline-flex items-center justify-center rounded-md border border-slate-300 bg-white px-3 py-1.5 text-xs font-semibold text-[#033151] hover:bg-slate-50" href="{{ route('coordenacao.closings.show',$closing) }}">Abrir</a>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="px-6 py-10 text-center text-sm text-slate-400">Nenhuma competência criada.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="border-t border-slate-100 p-5 sm:px-6">{{ $closings->links() }}</div>
        </section>
    </div>
</x-app-layout>
