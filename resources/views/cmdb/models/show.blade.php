<x-app-layout>
    <div class="mx-auto max-w-7xl space-y-6">
        @include('cmdb.partials._nav', ['title' => $model->name, 'description' => 'Detalhes do modelo'])

        <div class="flex flex-wrap gap-2">
            <a href="{{ route('cmdb.models.edit', $model) }}" class="inline-flex min-h-10 items-center justify-center rounded-md bg-[#033151] px-4 text-sm font-semibold text-white hover:opacity-90">Editar</a>
            <a href="{{ route('cmdb.models.index') }}" class="inline-flex min-h-10 items-center justify-center rounded-md border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-700 hover:bg-slate-50">Voltar</a>
        </div>

        <section class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
            <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                <p class="text-xs font-semibold uppercase text-slate-500">Nome</p>
                <p class="mt-2 text-base font-semibold text-slate-950">{{ $model->name }}</p>
            </div>
            <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                <p class="text-xs font-semibold uppercase text-slate-500">Fabricante</p>
                <p class="mt-2 text-base font-semibold text-slate-950">{{ $model->manufacturer?->name ?: '-' }}</p>
            </div>
            <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                <p class="text-xs font-semibold uppercase text-slate-500">Tipo de ativo</p>
                <p class="mt-2 text-base font-semibold text-slate-950">{{ $model->asset_type ?: '-' }}</p>
            </div>
            <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                <p class="text-xs font-semibold uppercase text-slate-500">Part number</p>
                <p class="mt-2 text-base font-semibold text-slate-950">{{ $model->part_number ?: '-' }}</p>
            </div>
            <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                <p class="text-xs font-semibold uppercase text-slate-500">Rack units</p>
                <p class="mt-2 text-base font-semibold text-slate-950">{{ $model->rack_units ?: '-' }}</p>
            </div>
            <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                <p class="text-xs font-semibold uppercase text-slate-500">Ativo</p>
                <p class="mt-2 text-base font-semibold text-slate-950">{{ $model->is_active ? 'Sim' : 'Não' }}</p>
            </div>
        </section>

        <section class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="text-base font-semibold text-slate-900">Ciclo de vida</h2>
            <div class="mt-4 grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                <div><p class="text-xs font-semibold uppercase text-slate-500">EOL</p><p class="mt-1 text-sm text-slate-950">{{ $model->eol_date?->format('d/m/Y') ?: '-' }}</p></div>
                <div><p class="text-xs font-semibold uppercase text-slate-500">EOS</p><p class="mt-1 text-sm text-slate-950">{{ $model->eos_date?->format('d/m/Y') ?: '-' }}</p></div>
                <div><p class="text-xs font-semibold uppercase text-slate-500">Fim do suporte</p><p class="mt-1 text-sm text-slate-950">{{ $model->support_end_date?->format('d/m/Y') ?: '-' }}</p></div>
                <div><p class="text-xs font-semibold uppercase text-slate-500">Consumo</p><p class="mt-1 text-sm text-slate-950">{{ $model->power_consumption_watts ?: '-' }} W</p></div>
                <div><p class="text-xs font-semibold uppercase text-slate-500">Manual</p><p class="mt-1 text-sm text-slate-950">{{ $model->manual_url ?: '-' }}</p></div>
                <div><p class="text-xs font-semibold uppercase text-slate-500">Imagem</p><p class="mt-1 text-sm text-slate-950">{{ $model->image_url ?: '-' }}</p></div>
            </div>
        </section>

        @if($model->notes)
            <section class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                <h2 class="text-base font-semibold text-slate-900">Observações</h2>
                <p class="mt-2 whitespace-pre-line text-sm text-slate-700">{{ $model->notes }}</p>
            </section>
        @endif
    </div>
</x-app-layout>
