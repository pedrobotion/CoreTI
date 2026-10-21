@php
    $statusLabels = $statusLabels ?? [];
    $actionLabels = $actionLabels ?? [];
    $currentFilters = request()->query();
@endphp

<x-app-layout>
    <div class="mx-auto max-w-7xl space-y-6">
        @include('cmdb.partials._nav', ['title' => 'Detalhe do lote #' . $batch->id, 'description' => 'Linhas importadas, alertas, erros e ativos vinculados.'])

        @if(session('success'))
            <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('success') }}</div>
        @endif

        <section class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <h2 class="text-lg font-semibold text-slate-950">#{{ $batch->id }} - {{ $batch->original_name }}</h2>
                    <p class="mt-1 text-sm text-slate-500">
                        Usuário: {{ $batch->user?->name ?? '-' }}
                        · Status: {{ \Illuminate\Support\Arr::get(['previewed' => 'Prévia', 'processing' => 'Processando', 'completed' => 'Concluído', 'failed' => 'Falhou'], $batch->status, $batch->status) }}
                    </p>
                    <p class="mt-1 text-sm text-slate-500">
                        Início: {{ $batch->started_at?->format('d/m/Y H:i') ?? '-' }}
                        · Fim: {{ $batch->finished_at?->format('d/m/Y H:i') ?? '-' }}
                    </p>
                    <p class="mt-1 text-sm text-slate-500">Opções usadas: <span class="font-medium">{{ json_encode($batch->options ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</span></p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <a href="{{ route('cmdb.import.batches.result', $batch) }}" class="inline-flex min-h-10 items-center justify-center rounded-md bg-[#033151] px-4 text-sm font-semibold text-white hover:opacity-90">Resultado</a>
                    <form method="POST" action="{{ route('cmdb.import.batches.reprocess', $batch) }}">
                        @csrf
                        <button type="submit" class="inline-flex min-h-10 items-center justify-center rounded-md border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-700 hover:bg-slate-50">Reprocessar lote</button>
                    </form>
                    <a href="{{ route('cmdb.import.batches.export', array_merge(request()->query(), ['batch' => $batch, 'type' => 'errors'])) }}" class="inline-flex min-h-10 items-center justify-center rounded-md border border-rose-300 bg-white px-4 text-sm font-semibold text-rose-700 hover:bg-rose-50">Exportar erros</a>
                    <a href="{{ route('cmdb.import.batches.export', array_merge(request()->query(), ['batch' => $batch, 'type' => 'warnings'])) }}" class="inline-flex min-h-10 items-center justify-center rounded-md border border-amber-300 bg-white px-4 text-sm font-semibold text-amber-700 hover:bg-amber-50">Exportar alertas</a>
                    <a href="{{ route('cmdb.import.batches.export', array_merge(request()->query(), ['batch' => $batch, 'type' => 'all'])) }}" class="inline-flex min-h-10 items-center justify-center rounded-md border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-700 hover:bg-slate-50">Exportar completo</a>
                </div>
            </div>
        </section>

        <section class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
            <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Total de linhas</p>
                <p class="mt-2 text-3xl font-bold text-slate-950">{{ $summary['total_rows'] }}</p>
            </div>
            <div class="rounded-lg border border-emerald-200 bg-emerald-50 p-5 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wide text-emerald-700">Válidas</p>
                <p class="mt-2 text-3xl font-bold text-emerald-900">{{ $summary['valid_rows'] }}</p>
            </div>
            <div class="rounded-lg border border-amber-200 bg-amber-50 p-5 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wide text-amber-700">Alertas</p>
                <p class="mt-2 text-3xl font-bold text-amber-900">{{ $summary['warning_rows'] }}</p>
            </div>
            <div class="rounded-lg border border-rose-200 bg-rose-50 p-5 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wide text-rose-700">Erros</p>
                <p class="mt-2 text-3xl font-bold text-rose-900">{{ $summary['error_rows'] }}</p>
            </div>
            <div class="rounded-lg border border-blue-200 bg-blue-50 p-5 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wide text-blue-700">Importadas</p>
                <p class="mt-2 text-3xl font-bold text-blue-900">{{ $summary['imported_rows'] }}</p>
            </div>
            <div class="rounded-lg border border-violet-200 bg-violet-50 p-5 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wide text-violet-700">Atualizadas</p>
                <p class="mt-2 text-3xl font-bold text-violet-900">{{ $summary['updated_rows'] }}</p>
            </div>
        </section>

        <section class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
            <form method="GET" class="grid gap-4 md:grid-cols-3 xl:grid-cols-6">
                <div>
                    <label class="text-sm font-medium text-slate-700">Status</label>
                    <select name="status" class="mt-1 h-10 w-full rounded-md border-slate-300 text-sm">
                        <option value="">Todos</option>
                        @foreach($statusLabels as $key => $label)
                            <option value="{{ $key }}" @selected(($filters['status'] ?? '') === $key)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="text-sm font-medium text-slate-700">Ação</label>
                    <select name="action" class="mt-1 h-10 w-full rounded-md border-slate-300 text-sm">
                        <option value="">Todas</option>
                        @foreach($actionLabels as $key => $label)
                            <option value="{{ $key }}" @selected(($filters['action'] ?? '') === $key)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="text-sm font-medium text-slate-700">Linha</label>
                    <input name="row_number" value="{{ $filters['row_number'] ?? '' }}" class="mt-1 h-10 w-full rounded-md border-slate-300 text-sm" placeholder="Ex.: 12">
                </div>
                <div>
                    <label class="text-sm font-medium text-slate-700">Nome</label>
                    <input name="name" value="{{ $filters['name'] ?? '' }}" class="mt-1 h-10 w-full rounded-md border-slate-300 text-sm" placeholder="Nome do ativo">
                </div>
                <div>
                    <label class="text-sm font-medium text-slate-700">Hostname</label>
                    <input name="hostname" value="{{ $filters['hostname'] ?? '' }}" class="mt-1 h-10 w-full rounded-md border-slate-300 text-sm" placeholder="Hostname">
                </div>
                <div>
                    <label class="text-sm font-medium text-slate-700">Tipo</label>
                    <input name="asset_type" value="{{ $filters['asset_type'] ?? '' }}" class="mt-1 h-10 w-full rounded-md border-slate-300 text-sm" placeholder="server, switch...">
                </div>
                <div class="xl:col-span-2">
                    <label class="text-sm font-medium text-slate-700">Mensagens</label>
                    <input name="message" value="{{ $filters['message'] ?? '' }}" class="mt-1 h-10 w-full rounded-md border-slate-300 text-sm" placeholder="Texto dentro das mensagens">
                </div>
                <div>
                    <label class="text-sm font-medium text-slate-700">Por página</label>
                    <select name="per_page" class="mt-1 h-10 w-full rounded-md border-slate-300 text-sm">
                        @foreach([10, 15, 25, 50] as $perPage)
                            <option value="{{ $perPage }}" @selected((int) ($filters['per_page'] ?? 15) === $perPage)>{{ $perPage }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="flex items-end gap-2 xl:col-span-1">
                    <button type="submit" class="inline-flex min-h-10 items-center justify-center rounded-md bg-[#033151] px-4 text-sm font-semibold text-white hover:opacity-90">Filtrar</button>
                    <a href="{{ route('cmdb.import.batches.show', $batch) }}" class="inline-flex min-h-10 items-center justify-center rounded-md border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-700 hover:bg-slate-50">Limpar</a>
                </div>
            </form>
        </section>

        <section class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-100 text-sm">
                    <thead class="border-b border-slate-100 text-left text-xs font-semibold uppercase tracking-wide text-slate-400">
                        <tr>
                            <th class="px-4 py-3">Linha</th>
                            <th class="px-4 py-3">Status</th>
                            <th class="px-4 py-3">Ação</th>
                            <th class="px-4 py-3">Tipo</th>
                            <th class="px-4 py-3">Nome</th>
                            <th class="px-4 py-3">Hostname</th>
                            <th class="px-4 py-3">IP</th>
                            <th class="px-4 py-3">Fabricante</th>
                            <th class="px-4 py-3">Modelo</th>
                            <th class="px-4 py-3">Mensagens</th>
                            <th class="px-4 py-3">Ativo gerado</th>
                            <th class="px-4 py-3">Criado em</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 bg-white">
                        @forelse($rows as $row)
                            @php $normalized = data_get($row->data, 'normalized', []); @endphp
                            <tr>
                                <td class="px-4 py-3 font-semibold text-slate-900">{{ $row->row_number }}</td>
                                <td class="px-4 py-3">
                                    <span class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-semibold {{ $row->status === 'valid' || $row->status === 'imported' || $row->status === 'updated' ? 'bg-emerald-50 text-emerald-700 ring-1 ring-inset ring-emerald-200' : ($row->status === 'warning' ? 'bg-amber-50 text-amber-700 ring-1 ring-inset ring-amber-200' : 'bg-rose-50 text-rose-700 ring-1 ring-inset ring-rose-200') }}">
                                        {{ $statusLabels[$row->status] ?? $row->status }}
                                    </span>
                                </td>
                                <td class="px-4 py-3">{{ $actionLabels[$row->action] ?? ($row->action ?: '-') }}</td>
                                <td class="px-4 py-3">{{ data_get($normalized, 'asset_type', '-') }}</td>
                                <td class="px-4 py-3">{{ data_get($normalized, 'name', '-') }}</td>
                                <td class="px-4 py-3">{{ data_get($normalized, 'hostname', '-') }}</td>
                                <td class="px-4 py-3">{{ data_get($normalized, 'main_ip', '-') }}</td>
                                <td class="px-4 py-3">{{ data_get($normalized, 'manufacturer', '-') }}</td>
                                <td class="px-4 py-3">{{ data_get($normalized, 'model', '-') }}</td>
                                <td class="px-4 py-3">
                                    @if(!empty($row->messages))
                                        <ul class="list-disc space-y-1 pl-5 text-slate-600">
                                            @foreach($row->messages as $message)
                                                <li>{{ $message }}</li>
                                            @endforeach
                                        </ul>
                                    @else
                                        -
                                    @endif
                                </td>
                                <td class="px-4 py-3">
                                    @if($row->asset)
                                        <a href="{{ route('cmdb.assets.show', $row->asset) }}" class="text-[#033151] hover:underline">#{{ $row->asset->id }} {{ $row->asset->name }}</a>
                                    @else
                                        -
                                    @endif
                                </td>
                                <td class="px-4 py-3">{{ $row->created_at?->format('d/m/Y H:i') ?? '-' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="12" class="px-4 py-8 text-center text-sm text-slate-500">Nenhuma linha encontrada para os filtros selecionados.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-4">
                {{ $rows->links() }}
            </div>
        </section>
    </div>
</x-app-layout>
