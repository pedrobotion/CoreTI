<x-app-layout>
    <div class="mx-auto max-w-5xl space-y-6">
        @include('coordenacao.partials.nav',['active'=>'closings'])
        <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:px-6">
            <a class="inline-flex items-center gap-1 text-sm font-semibold text-[#033151] hover:underline" href="{{ route('coordenacao.closings.index') }}">← Voltar</a>
            <h2 class="mt-3 text-xl font-bold tracking-tight text-slate-900">Fechamento {{ $closing->reference_month->format('m/Y') }}</h2>
            <p class="mt-1 text-sm text-slate-500">Status: {{ $closing->status }} · Total: R$ {{ number_format($closing->total_amount,2,',','.') }}</p>
            <div class="mt-4 flex flex-wrap gap-2 border-t border-slate-100 pt-4">
                <form method="POST" action="{{ route('coordenacao.closings.calculate',$closing) }}">@csrf<button style="background-color: #033151; border-color: #033151;" class="inline-flex min-h-10 items-center justify-center rounded-lg px-4 text-sm font-semibold text-white hover:opacity-90">Calcular</button></form>
                <form method="POST" action="{{ route('coordenacao.closings.review',$closing) }}">@csrf<button class="inline-flex min-h-10 items-center justify-center rounded-md border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-700 hover:bg-slate-50">Marcar revisado</button></form>
                <form method="POST" action="{{ route('coordenacao.closings.close',$closing) }}">@csrf<button class="inline-flex min-h-10 items-center justify-center rounded-md border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-700 hover:bg-slate-50">Fechar competência</button></form>
            </div>
        </section>
    </div>
</x-app-layout>
