<x-app-layout>
    <div class="mx-auto max-w-7xl space-y-6">
        @include('cmdb.partials._nav', ['title' => $manufacturer->name, 'description' => 'Detalhes do fabricante'])

        <div class="flex flex-wrap gap-2">
            <a href="{{ route('cmdb.manufacturers.edit', $manufacturer) }}" class="inline-flex min-h-10 items-center justify-center rounded-md bg-[#033151] px-4 text-sm font-semibold text-white hover:opacity-90">Editar</a>
            <a href="{{ route('cmdb.manufacturers.index') }}" class="inline-flex min-h-10 items-center justify-center rounded-md border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-700 hover:bg-slate-50">Voltar</a>
        </div>

        <section class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
            <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                <p class="text-xs font-semibold uppercase text-slate-500">Nome</p>
                <p class="mt-2 text-base font-semibold text-slate-950">{{ $manufacturer->name }}</p>
            </div>
            <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                <p class="text-xs font-semibold uppercase text-slate-500">Slug</p>
                <p class="mt-2 text-base font-semibold text-slate-950">{{ $manufacturer->slug }}</p>
            </div>
            <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                <p class="text-xs font-semibold uppercase text-slate-500">Ativo</p>
                <p class="mt-2 text-base font-semibold text-slate-950">{{ $manufacturer->is_active ? 'Sim' : 'Não' }}</p>
            </div>
            <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                <p class="text-xs font-semibold uppercase text-slate-500">Website</p>
                <p class="mt-2 text-base font-semibold text-slate-950">{{ $manufacturer->website ?: '-' }}</p>
            </div>
            <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                <p class="text-xs font-semibold uppercase text-slate-500">Suporte</p>
                <p class="mt-2 text-base font-semibold text-slate-950">{{ $manufacturer->support_url ?: '-' }}</p>
            </div>
            <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                <p class="text-xs font-semibold uppercase text-slate-500">Modelos</p>
                <p class="mt-2 text-2xl font-semibold text-slate-950">{{ $manufacturer->models->count() }}</p>
            </div>
        </section>

        <section class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="text-base font-semibold text-slate-900">Modelos vinculados</h2>
            <div class="mt-4 space-y-2">
                @forelse($manufacturer->models as $item)
                    <div class="flex items-center justify-between gap-2 border-b border-slate-100 py-2 last:border-0">
                        <a href="{{ route('cmdb.models.show', $item) }}" class="font-semibold text-[#033151] hover:underline">{{ $item->name }}</a>
                        <span class="text-xs text-slate-500">{{ $item->asset_type ?: 'Qualquer tipo' }}</span>
                    </div>
                @empty
                    <p class="text-sm text-slate-500">Nenhum modelo vinculado.</p>
                @endforelse
            </div>
        </section>

        @if($manufacturer->notes)
            <section class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                <h2 class="text-base font-semibold text-slate-900">Observações</h2>
                <p class="mt-2 whitespace-pre-line text-sm text-slate-700">{{ $manufacturer->notes }}</p>
            </section>
        @endif
    </div>
</x-app-layout>
