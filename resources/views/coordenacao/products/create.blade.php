<x-app-layout>
    <div class="mx-auto max-w-5xl space-y-6">
        @include('coordenacao.partials.nav',['active'=>'products'])
        <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <h2 class="text-base font-bold text-slate-900">Novo produto de TI</h2>
            <form method="POST" action="{{ route('coordenacao.products.store') }}" class="mt-5">@include('coordenacao.products._form')</form>
        </section>
    </div>
</x-app-layout>
