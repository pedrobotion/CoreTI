<x-app-layout>
    <div class="mx-auto max-w-5xl space-y-6">
        @include('cmdb.partials._nav', ['title' => 'Resultado da importação', 'description' => 'Resumo final do lote processado.'])

        @if(session('success'))
            <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('success') }}</div>
        @endif

        <section class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <h2 class="text-lg font-semibold text-slate-950">#{{ $batch->id }} - {{ $batch->original_name }}</h2>
                    <p class="mt-1 text-sm text-slate-500">Usuário: {{ $batch->user?->name ?? '-' }} · Status: {{ \Illuminate\Support\Arr::get(['previewed' => 'Prévia', 'processing' => 'Processando', 'completed' => 'Concluído', 'failed' => 'Falhou'], $batch->status, $batch->status) }}</p>
                    <p class="mt-1 text-sm text-slate-500">Início: {{ $batch->started_at?->format('d/m/Y H:i') ?? '-' }} · Fim: {{ $batch->finished_at?->format('d/m/Y H:i') ?? '-' }}</p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <a href="{{ route('cmdb.import.batches.show', $batch) }}" class="inline-flex min-h-10 items-center justify-center rounded-md border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-700 hover:bg-slate-50">Ver detalhes do lote</a>
                    <a href="{{ route('cmdb.import.batches.export', ['batch' => $batch, 'type' => 'errors']) }}" class="inline-flex min-h-10 items-center justify-center rounded-md border border-rose-300 bg-white px-4 text-sm font-semibold text-rose-700 hover:bg-rose-50">Exportar erros</a>
                    <a href="{{ route('cmdb.import.batches.export', ['batch' => $batch, 'type' => 'warnings']) }}" class="inline-flex min-h-10 items-center justify-center rounded-md border border-amber-300 bg-white px-4 text-sm font-semibold text-amber-700 hover:bg-amber-50">Exportar alertas</a>
                    <a href="{{ route('cmdb.import.index') }}" class="inline-flex min-h-10 items-center justify-center rounded-md bg-[#033151] px-4 text-sm font-semibold text-white hover:opacity-90">Nova importação</a>
                </div>
            </div>
        </section>

        <section class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
            <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Total de linhas</p>
                <p class="mt-2 text-3xl font-bold text-slate-950">{{ $summary['total_rows'] }}</p>
            </div>
            <div class="rounded-lg border border-emerald-200 bg-emerald-50 p-5 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wide text-emerald-700">Criadas</p>
                <p class="mt-2 text-3xl font-bold text-emerald-900">{{ $summary['imported_rows'] }}</p>
            </div>
            <div class="rounded-lg border border-blue-200 bg-blue-50 p-5 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wide text-blue-700">Atualizadas</p>
                <p class="mt-2 text-3xl font-bold text-blue-900">{{ $summary['updated_rows'] }}</p>
            </div>
            <div class="rounded-lg border border-rose-200 bg-rose-50 p-5 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wide text-rose-700">Falhas</p>
                <p class="mt-2 text-3xl font-bold text-rose-900">{{ $summary['failed_rows'] }}</p>
            </div>
            <div class="rounded-lg border border-amber-200 bg-amber-50 p-5 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wide text-amber-700">Alertas</p>
                <p class="mt-2 text-3xl font-bold text-amber-900">{{ $summary['warning_rows'] }}</p>
            </div>
            <div class="rounded-lg border border-violet-200 bg-violet-50 p-5 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wide text-violet-700">Linhas processadas</p>
                <p class="mt-2 text-3xl font-bold text-violet-900">{{ $summary['success_rows'] }}</p>
            </div>
        </section>

        <section class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h3 class="text-base font-semibold text-slate-900">Próximas ações</h3>
                    <p class="mt-1 text-sm text-slate-500">Use os atalhos abaixo para revisar o lote ou começar outra importação.</p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <a href="{{ route('cmdb.import.batches.index') }}" class="inline-flex min-h-10 items-center justify-center rounded-md border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-700 hover:bg-slate-50">Lotes de importação</a>
                    <a href="{{ route('cmdb.import.batches.show', $batch) }}" class="inline-flex min-h-10 items-center justify-center rounded-md bg-[#033151] px-4 text-sm font-semibold text-white hover:opacity-90">Abrir lote</a>
                </div>
            </div>
        </section>
    </div>
</x-app-layout>
