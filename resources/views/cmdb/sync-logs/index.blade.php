<x-app-layout>
    @php
        $statusBadgeClass = fn (string $status) => match ($status) {
            'running' => 'bg-blue-100 text-blue-800',
            'success' => 'bg-emerald-100 text-emerald-800',
            'warning' => 'bg-amber-100 text-amber-800',
            'failed' => 'bg-rose-100 text-rose-800',
            'simulated' => 'bg-slate-100 text-slate-700',
            default => 'bg-slate-100 text-slate-700',
        };
    @endphp

    <div class="mx-auto max-w-7xl space-y-6">
        @include('cmdb.partials._nav', ['title' => 'Logs de Sincronização', 'description' => 'Registro técnico das execuções simuladas do CMDB.'])

        <section class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
            <form method="GET" class="grid gap-3 md:grid-cols-4">
                <div class="md:col-span-2">
                    <label class="text-sm font-medium text-slate-700">Busca</label>
                    <input name="q" value="{{ $filters['q'] ?? '' }}" class="mt-1 w-full rounded-md border-slate-300 text-sm" placeholder="Mensagem ou erro">
                </div>
                <div>
                    <label class="text-sm font-medium text-slate-700">Status</label>
                    <select name="status" class="mt-1 w-full rounded-md border-slate-300 text-sm">
                        <option value="">Todos</option>
                        @foreach($statuses as $key => $label)
                            <option value="{{ $key }}" @selected(($filters['status'] ?? '') === $key)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="text-sm font-medium text-slate-700">Conector</label>
                    <select name="connector_id" class="mt-1 w-full rounded-md border-slate-300 text-sm">
                        <option value="">Todos</option>
                        @foreach($connectors as $connector)
                            <option value="{{ $connector->id }}" @selected((string) ($filters['connector_id'] ?? '') === (string) $connector->id)>{{ $connector->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="text-sm font-medium text-slate-700">Job</label>
                    <select name="sync_job_id" class="mt-1 w-full rounded-md border-slate-300 text-sm">
                        <option value="">Todos</option>
                        @foreach($jobs as $job)
                            <option value="{{ $job->id }}" @selected((string) ($filters['sync_job_id'] ?? '') === (string) $job->id)>{{ $job->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="md:col-span-4 flex flex-wrap justify-end gap-2">
                    <a href="{{ route('cmdb.sync-logs.index') }}" class="inline-flex min-h-10 items-center justify-center rounded-md border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-700 hover:bg-slate-50">Limpar</a>
                    <button class="inline-flex min-h-10 items-center justify-center rounded-md bg-[#033151] px-4 text-sm font-semibold text-white hover:opacity-90">Filtrar</button>
                </div>
            </form>
        </section>

        <section class="rounded-lg border border-slate-200 bg-white shadow-sm">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="px-5 py-3">Data início</th>
                            <th class="px-5 py-3">Data fim</th>
                            <th class="px-5 py-3">Conector</th>
                            <th class="px-5 py-3">Job</th>
                            <th class="px-5 py-3">Status</th>
                            <th class="px-5 py-3">Encontrados</th>
                            <th class="px-5 py-3">Criados</th>
                            <th class="px-5 py-3">Atualizados</th>
                            <th class="px-5 py-3">Sem alteração</th>
                            <th class="px-5 py-3">Para revisão</th>
                            <th class="px-5 py-3">Sem correspond.</th>
                            <th class="px-5 py-3">Falhas</th>
                            <th class="px-5 py-3">Duração</th>
                            <th class="px-5 py-3">Ações</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200">
                        @forelse($items as $item)
                            <tr>
                                <td class="px-5 py-3 text-slate-700">{{ $item->started_at?->format('d/m/Y H:i') ?: '-' }}</td>
                                <td class="px-5 py-3 text-slate-700">{{ $item->finished_at?->format('d/m/Y H:i') ?: '-' }}</td>
                                <td class="px-5 py-3 text-slate-700">{{ $item->connector?->name ?: '-' }}</td>
                                <td class="px-5 py-3 text-slate-700">{{ $item->syncJob?->name ?: '-' }}</td>
                                <td class="px-5 py-3"><span class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-semibold {{ $statusBadgeClass($item->status) }}">{{ $statuses[$item->status] ?? $item->status }}</span></td>
                                <td class="px-5 py-3 text-slate-700">{{ $item->assets_found }}</td>
                                <td class="px-5 py-3 text-slate-700">{{ $item->assets_created }}</td>
                                <td class="px-5 py-3 text-slate-700">{{ $item->assets_updated }}</td>
                                <td class="px-5 py-3 text-slate-700">{{ $item->assets_unchanged }}</td>
                                <td class="px-5 py-3 text-slate-700">{{ $item->assets_review }}</td>
                                <td class="px-5 py-3 {{ $item->assets_unmatched > 0 ? 'font-semibold text-amber-700' : 'text-slate-700' }}">{{ $item->assets_unmatched }}</td>
                                <td class="px-5 py-3 text-slate-700">{{ $item->assets_failed }}</td>
                                <td class="px-5 py-3 text-slate-700">{{ $item->duration_seconds ? $item->duration_seconds . 's' : '-' }}</td>
                                <td class="px-5 py-3"><a href="{{ route('cmdb.sync-logs.show', $item) }}" class="inline-flex rounded-md border border-[#033151] bg-white px-3 py-1.5 text-xs font-semibold text-[#033151] hover:bg-slate-50">Ver</a></td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="14" class="px-5 py-6 text-center text-slate-500">Nenhum log encontrado.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        <div>{{ $items->links() }}</div>
    </div>
</x-app-layout>
