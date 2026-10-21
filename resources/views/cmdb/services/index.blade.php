<x-app-layout>
    <div class="mx-auto max-w-7xl space-y-6">
        @include('cmdb.partials._nav', ['title' => 'Catálogo de serviços', 'description' => 'Nomes padronizados para os serviços executados ou suportados pelo CMDB.'])

        <section class="grid gap-6 lg:grid-cols-[minmax(260px,0.8fr)_minmax(0,1.8fr)]">
            <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                <h2 class="font-semibold text-slate-900">Novo serviço</h2>
                <form method="POST" action="{{ route('cmdb.services.store') }}" class="mt-4 space-y-3">
                    @csrf
                    <input name="name" required placeholder="Ex.: SAP, Citrix, DNS" class="w-full rounded-md border-slate-300 text-sm">
                    <input name="category" placeholder="Categoria" class="w-full rounded-md border-slate-300 text-sm">
                    <textarea name="description" rows="3" placeholder="Descrição complementar" class="w-full rounded-md border-slate-300 text-sm"></textarea>
                    <button class="w-full rounded-md bg-[#033151] px-4 py-2 text-sm font-semibold text-white">Cadastrar serviço</button>
                </form>
            </div>
            <div class="rounded-lg border border-slate-200 bg-white shadow-sm">
                <div class="border-b border-slate-200 px-5 py-4"><h2 class="font-semibold text-slate-900">Serviços cadastrados</h2></div>
                <div class="divide-y divide-slate-100">
                    @forelse($services as $service)
                        <div class="flex flex-wrap items-center justify-between gap-3 px-5 py-4">
                            <div><div class="font-semibold text-slate-900">{{ $service->name }}</div><div class="text-xs text-slate-500">{{ $service->servers_count }} ativo(s) • {{ $service->category ?: 'Sem categoria' }}</div></div>
                            <div class="flex items-center gap-2"><span class="rounded-full px-2.5 py-1 text-xs font-semibold {{ $service->active ? 'bg-emerald-50 text-emerald-700 ring-1 ring-inset ring-emerald-200' : 'bg-slate-100 text-slate-600' }}">{{ $service->active ? 'Ativo' : 'Inativo' }}</span><form method="POST" action="{{ route('cmdb.services.toggle', $service) }}">@csrf @method('PATCH')<button class="rounded-md border border-slate-300 px-3 py-1.5 text-xs font-semibold">{{ $service->active ? 'Desativar' : 'Ativar' }}</button></form></div>
                        </div>
                    @empty
                        <p class="px-5 py-8 text-sm text-slate-500">Nenhum serviço cadastrado.</p>
                    @endforelse
                </div>
            </div>
        </section>
    </div>
</x-app-layout>
