<x-app-layout>
    @php
        $statusBadgeClass = fn (string $status) => match ($status) {
            'manual' => 'bg-slate-100 text-slate-700',
            'hourly' => 'bg-blue-50 text-blue-700 ring-1 ring-inset ring-blue-200',
            'daily' => 'bg-emerald-50 text-emerald-700 ring-1 ring-inset ring-emerald-200',
            'weekly' => 'bg-violet-50 text-violet-700 ring-1 ring-inset ring-violet-200',
            'custom' => 'bg-amber-50 text-amber-700 ring-1 ring-inset ring-amber-200',
            default => 'bg-slate-100 text-slate-700',
        };
    @endphp

    <div class="mx-auto max-w-7xl space-y-6">
        @include('cmdb.partials._nav', ['title' => 'Jobs de Sincronização', 'description' => 'Execuções manuais e agendamentos preparados para o CMDB.'])

        @if(!empty($setupPending))
            <div class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                Estrutura de jobs ainda não foi criada no banco. Execute as migrations para liberar o cadastro.
            </div>
        @endif

        <section class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
            <form method="GET" class="grid gap-3 md:grid-cols-4">
                <div class="md:col-span-2">
                    <label class="text-sm font-medium text-slate-700">Busca</label>
                    <input name="q" value="{{ $filters['q'] ?? '' }}" class="mt-1 w-full rounded-md border-slate-300 text-sm" placeholder="Nome do job">
                </div>
                <div>
                    <label class="text-sm font-medium text-slate-700">Conector</label>
                    <input name="connector_id" value="{{ $filters['connector_id'] ?? '' }}" class="mt-1 w-full rounded-md border-slate-300 text-sm" placeholder="ID do conector">
                </div>
                <div>
                    <label class="text-sm font-medium text-slate-700">Ativo</label>
                    <select name="is_active" class="mt-1 w-full rounded-md border-slate-300 text-sm">
                        <option value="">Todos</option>
                        <option value="1" @selected(($filters['is_active'] ?? '') === '1')>Sim</option>
                        <option value="0" @selected(($filters['is_active'] ?? '') === '0')>Não</option>
                    </select>
                </div>
                <div class="md:col-span-4 flex flex-wrap justify-end gap-2">
                    <a href="{{ route('cmdb.sync-jobs.index') }}" class="inline-flex min-h-10 items-center justify-center rounded-md border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-700 hover:bg-slate-50">Limpar</a>
                    <button class="inline-flex min-h-10 items-center justify-center rounded-md bg-[#033151] px-4 text-sm font-semibold text-white hover:opacity-90">Filtrar</button>
                    <a href="{{ route('cmdb.sync-jobs.create') }}" class="inline-flex min-h-10 items-center justify-center rounded-md border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-700 hover:bg-slate-50">Novo job</a>
                </div>
            </form>
        </section>

        <section class="rounded-lg border border-slate-200 bg-white shadow-sm">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-100 text-sm">
                    <thead class="border-b border-slate-100 text-left text-xs font-semibold uppercase tracking-wide text-slate-400">
                        <tr>
                            <th class="px-5 py-3">Nome</th>
                            <th class="px-5 py-3">Conector</th>
                            <th class="px-5 py-3">Agendamento</th>
                            <th class="px-5 py-3">Ativo</th>
                            <th class="px-5 py-3">Última execução</th>
                            <th class="px-5 py-3">Último resultado</th>
                            <th class="px-5 py-3">Logs</th>
                            <th class="px-5 py-3">Ações</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse($items as $item)
                            <tr>
                                <td class="px-5 py-3 font-semibold text-slate-900">{{ $item->name }}</td>
                                <td class="px-5 py-3 text-slate-700">{{ $item->connector?->name ?: '-' }}</td>
                                <td class="px-5 py-3"><span class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-semibold {{ $statusBadgeClass($item->schedule_type) }}">{{ $scheduleTypes[$item->schedule_type] ?? $item->schedule_type }}</span></td>
                                <td class="px-5 py-3">{{ $item->is_active ? 'Sim' : 'Não' }}</td>
                                <td class="px-5 py-3 text-slate-700">{{ $item->last_run_at ? $item->last_run_at->format('d/m/Y H:i') : '-' }}</td>
                                <td class="px-5 py-3 text-slate-700">{{ $item->last_status ?: '-' }}</td>
                                <td class="px-5 py-3 text-slate-700">{{ $item->sync_logs_count ?? 0 }}</td>
                                <td class="px-5 py-3">
                                    <div class="flex flex-wrap gap-2">
                                        <a href="{{ route('cmdb.sync-jobs.show', $item) }}" class="inline-flex rounded-md border border-slate-300 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50">Ver</a>
                                        <a href="{{ route('cmdb.sync-jobs.edit', $item) }}" class="inline-flex rounded-md border border-[#033151] bg-white px-3 py-1.5 text-xs font-semibold text-[#033151] hover:bg-slate-50">Editar</a>
                                        <form method="POST" action="{{ route('cmdb.sync-jobs.run-now', $item) }}">@csrf<button class="inline-flex rounded-md border border-emerald-200 bg-white px-3 py-1.5 text-xs font-semibold text-emerald-700 hover:bg-emerald-50">Executar</button></form>
                                        <form method="POST" action="{{ route('cmdb.sync-jobs.destroy', $item) }}" onsubmit="return confirm('Excluir este job?')">@csrf @method('DELETE')<button class="inline-flex rounded-md border border-rose-200 bg-white px-3 py-1.5 text-xs font-semibold text-rose-700 hover:bg-rose-50">Excluir</button></form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="px-5 py-6 text-center text-slate-500">Nenhum job cadastrado.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        <div>{{ $items->links() }}</div>
    </div>
</x-app-layout>
