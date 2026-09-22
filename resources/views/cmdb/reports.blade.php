<x-app-layout>
    <div class="mx-auto max-w-7xl space-y-6">
        @include('cmdb.partials._nav', ['title' => 'Relatórios', 'description' => 'Resumo rápido da base do CMDB.'])

        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
            @foreach($summary as $label => $value)
                <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ str_replace('_', ' ', $label) }}</p>
                    <p class="mt-2 text-3xl font-bold text-slate-950">{{ $value }}</p>
                </div>
            @endforeach
        </div>
    </div>
</x-app-layout>
