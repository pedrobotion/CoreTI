<x-app-layout>
    <div class="mx-auto max-w-7xl space-y-6">
        @include('cmdb.partials._nav', ['title' => 'Lotes de Importação', 'description' => 'Auditoria, exportação e reprocessamento das importações em massa.'])

        @if(session('success'))
            <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('success') }}</div>
        @endif

        <section class="grid gap-4 md:grid-cols-2 xl:grid-cols-5">
            <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Total de lotes</p>
                <p class="mt-2 text-3xl font-bold text-slate-950">{{ $totals['batches'] }}</p>
            </div>
            <div class="rounded-lg border border-emerald-200 bg-emerald-50 p-5 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wide text-emerald-700">Lotes concluídos</p>
                <p class="mt-2 text-3xl font-bold text-emerald-900">{{ $totals['completed'] }}</p>
            </div>
            <div class="rounded-lg border border-rose-200 bg-rose-50 p-5 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wide text-rose-700">Lotes com erro</p>
                <p class="mt-2 text-3xl font-bold text-rose-900">{{ $totals['failed'] }}</p>
            </div>
            <div class="rounded-lg border border-blue-200 bg-blue-50 p-5 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wide text-blue-700">Linhas importadas</p>
                <p class="mt-2 text-3xl font-bold text-blue-900">{{ $totals['imported_rows'] }}</p>
            </div>
            <div class="rounded-lg border border-amber-200 bg-amber-50 p-5 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wide text-amber-700">Linhas com erro</p>
                <p class="mt-2 text-3xl font-bold text-amber-900">{{ $totals['error_rows'] }}</p>
            </div>
        </section>

        <section class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
            <form method="GET" class="grid gap-4 md:grid-cols-3 xl:grid-cols-4">
                <div>
                    <label class="text-sm font-medium text-slate-700">Pesquisar</label>
                    <input name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Arquivo ou ID do lote" class="mt-1 h-10 w-full rounded-md border-slate-300 text-sm">
                </div>
                <div>
                    <label class="text-sm font-medium text-slate-700">Status</label>
                    <select name="status" class="mt-1 h-10 w-full rounded-md border-slate-300 text-sm">
                        <option value="">Todos</option>
                        @foreach($batchStatusLabels as $key => $label)
                            <option value="{{ $key }}" @selected(($filters['status'] ?? '') === $key)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="text-sm font-medium text-slate-700">Por página</label>
                    <select name="per_page" class="mt-1 h-10 w-full rounded-md border-slate-300 text-sm">
                        @foreach([10, 15, 25, 50] as $perPage)
                            <option value="{{ $perPage }}" @selected((int) ($filters['per_page'] ?? 15) === $perPage)>{{ $perPage }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="flex items-end gap-2 md:justify-end">
                    <button type="submit" class="inline-flex min-h-10 items-center justify-center rounded-md bg-[#033151] px-4 text-sm font-semibold text-white hover:opacity-90">Filtrar</button>
                    <a href="{{ route('cmdb.import.batches.index') }}" class="inline-flex min-h-10 items-center justify-center rounded-md border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-700 hover:bg-slate-50">Limpar</a>
                </div>
            </form>
        </section>

        <section class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2 class="text-base font-semibold text-slate-900">Lotes registrados</h2>
                    <p class="mt-1 text-sm text-slate-500">Acompanhe a evolução, erros e resultados de cada importação.</p>
                </div>
                <a href="{{ route('cmdb.import.index') }}" class="inline-flex min-h-10 items-center justify-center rounded-md border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-700 hover:bg-slate-50">Nova importação</a>
            </div>

            <div class="mt-4 overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="px-4 py-3">ID</th>
                            <th class="px-4 py-3">Arquivo</th>
                            <th class="px-4 py-3">Usuário</th>
                            <th class="px-4 py-3">Status</th>
                            <th class="px-4 py-3">Total</th>
                            <th class="px-4 py-3">Válidas</th>
                            <th class="px-4 py-3">Alertas</th>
                            <th class="px-4 py-3">Erros</th>
                            <th class="px-4 py-3">Importadas</th>
                            <th class="px-4 py-3">Atualizadas</th>
                            <th class="px-4 py-3">Falhas</th>
                            <th class="px-4 py-3">Início</th>
                            <th class="px-4 py-3">Fim</th>
                            <th class="px-4 py-3">Ações</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 bg-white">
                        @forelse($batches as $batch)
                            <tr>
                                <td class="px-4 py-3 font-semibold text-slate-900">#{{ $batch->id }}</td>
                                <td class="px-4 py-3">{{ $batch->original_name }}</td>
                                <td class="px-4 py-3">{{ $batch->user?->name ?? '-' }}</td>
                                <td class="px-4 py-3">
                                    <span class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-semibold {{ $batch->status === 'completed' ? 'bg-emerald-100 text-emerald-800' : ($batch->status === 'failed' ? 'bg-rose-100 text-rose-800' : 'bg-slate-100 text-slate-700') }}">
                                        {{ $batchStatusLabels[$batch->status] ?? $batch->status }}
                                    </span>
                                </td>
                                <td class="px-4 py-3">{{ $batch->total_rows }}</td>
                                <td class="px-4 py-3">{{ $batch->valid_rows }}</td>
                                <td class="px-4 py-3">{{ $batch->warning_rows }}</td>
                                <td class="px-4 py-3">{{ $batch->error_rows }}</td>
                                <td class="px-4 py-3">{{ $batch->imported_rows }}</td>
                                <td class="px-4 py-3">{{ $batch->updated_rows }}</td>
                                <td class="px-4 py-3">{{ $batch->failed_rows }}</td>
                                <td class="px-4 py-3">{{ $batch->started_at?->format('d/m/Y H:i') ?? '-' }}</td>
                                <td class="px-4 py-3">{{ $batch->finished_at?->format('d/m/Y H:i') ?? '-' }}</td>
                                <td class="px-4 py-3">
                                    <div class="flex flex-wrap gap-2">
                                        <a href="{{ route('cmdb.import.batches.show', $batch) }}" class="inline-flex min-h-9 items-center justify-center rounded-md border border-slate-300 bg-white px-3 text-xs font-semibold text-slate-700 hover:bg-slate-50">Ver detalhes</a>
                                        <a href="{{ route('cmdb.import.batches.result', $batch) }}" class="inline-flex min-h-9 items-center justify-center rounded-md border border-slate-300 bg-white px-3 text-xs font-semibold text-slate-700 hover:bg-slate-50">Resultado</a>
                                        <form method="POST" action="{{ route('cmdb.import.batches.reprocess', $batch) }}">
                                            @csrf
                                            <button type="submit" class="inline-flex min-h-9 items-center justify-center rounded-md bg-[#033151] px-3 text-xs font-semibold text-white hover:opacity-90">Reprocessar</button>
                                        </form>
                                        <a href="{{ route('cmdb.import.batches.export', ['batch' => $batch, 'type' => 'errors']) }}" class="inline-flex min-h-9 items-center justify-center rounded-md border border-rose-300 bg-white px-3 text-xs font-semibold text-rose-700 hover:bg-rose-50">Exportar erros</a>
                                        <a href="{{ route('cmdb.import.batches.export', ['batch' => $batch, 'type' => 'warnings']) }}" class="inline-flex min-h-9 items-center justify-center rounded-md border border-amber-300 bg-white px-3 text-xs font-semibold text-amber-700 hover:bg-amber-50">Exportar alertas</a>
                                        <a href="{{ route('cmdb.import.batches.export', ['batch' => $batch, 'type' => 'all']) }}" class="inline-flex min-h-9 items-center justify-center rounded-md border border-slate-300 bg-white px-3 text-xs font-semibold text-slate-700 hover:bg-slate-50">Exportar completo</a>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="14" class="px-4 py-8 text-center text-sm text-slate-500">Nenhum lote encontrado.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-4">
                {{ $batches->links() }}
            </div>
        </section>
    </div>
</x-app-layout>
