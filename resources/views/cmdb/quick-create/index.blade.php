<x-app-layout>
    <div class="mx-auto max-w-7xl space-y-6">
        @include('cmdb.partials._nav', ['title' => 'Cadastro Rápido', 'description' => 'Atalho para criação, importação e duplicação de ativos.'])

        <section class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
            <a href="{{ route('cmdb.quick-create.create') }}" class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm transition hover:border-[#033151] hover:shadow">
                <p class="text-sm font-semibold uppercase tracking-wide text-slate-500">Novo ativo individual</p>
                <p class="mt-2 text-sm text-slate-600">Cadastro simplificado com campos mínimos e tipos condicionais.</p>
            </a>
            <a href="{{ route('cmdb.import.index') }}" class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm transition hover:border-[#033151] hover:shadow">
                <p class="text-sm font-semibold uppercase tracking-wide text-slate-500">Importar por planilha</p>
                <p class="mt-2 text-sm text-slate-600">Pré-validate CSV/XLSX, revisar alertas e confirmar em lote.</p>
            </a>
            <a href="{{ route('cmdb.assets.index') }}" class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm transition hover:border-[#033151] hover:shadow">
                <p class="text-sm font-semibold uppercase tracking-wide text-slate-500">Duplicar ativo existente</p>
                <p class="mt-2 text-sm text-slate-600">Abra um ativo cadastrado e use a ação de duplicação.</p>
            </a>
            <a href="{{ route('cmdb.discovery.index') }}" class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm transition hover:border-[#033151] hover:shadow">
                <p class="text-sm font-semibold uppercase tracking-wide text-slate-500">Criar a partir de descoberta</p>
                <p class="mt-2 text-sm text-slate-600">Atalho para logs e descobertas SNMP já existentes.</p>
            </a>
        </section>
    </div>
</x-app-layout>
