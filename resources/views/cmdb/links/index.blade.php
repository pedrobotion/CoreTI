<x-app-layout>
    <div class="mx-auto max-w-7xl space-y-6">
        @include('cmdb.partials._nav', ['title' => 'Links e Circuitos', 'description' => 'Acesso rápido ao cadastro de conectividade e fichas de importação.'])

        <section class="grid gap-4 md:grid-cols-2">
            <a href="{{ route('network-datacenter.servicos.index') }}" class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm transition hover:border-[#033151] hover:shadow-md">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Conectividade</p>
                <h2 class="mt-2 text-lg font-semibold text-slate-950">Serviços de Conectividade</h2>
                <p class="mt-2 text-sm text-slate-600">Consulta e manutenção dos links/circuitos existentes.</p>
            </a>

            <a href="{{ route('network-datacenter.servicos.create') }}" class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm transition hover:border-[#033151] hover:shadow-md">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Importação</p>
                <h2 class="mt-2 text-lg font-semibold text-slate-950">Fichas / Importação</h2>
                <p class="mt-2 text-sm text-slate-600">Cadastro novo com upload e extração da ficha de ativação.</p>
            </a>
        </section>
    </div>
</x-app-layout>
