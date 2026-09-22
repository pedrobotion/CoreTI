<x-app-layout>
    <div class="mx-auto max-w-7xl space-y-6">
        @include('cmdb.partials._nav', ['title' => $job->name, 'description' => 'Detalhe do job e histórico de execuções.'])

        <div class="flex flex-wrap gap-2">
            <a href="{{ route('cmdb.sync-jobs.edit', $job) }}" class="inline-flex min-h-10 items-center justify-center rounded-md bg-[#033151] px-4 text-sm font-semibold text-white hover:opacity-90">Editar</a>
            <a href="{{ route('cmdb.sync-jobs.index') }}" class="inline-flex min-h-10 items-center justify-center rounded-md border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-700 hover:bg-slate-50">Voltar</a>
            <form method="POST" action="{{ route('cmdb.sync-jobs.run-now', $job) }}">@csrf<button class="inline-flex min-h-10 items-center justify-center rounded-md border border-emerald-200 bg-white px-4 text-sm font-semibold text-emerald-700 hover:bg-emerald-50">Executar agora</button></form>
        </div>

        <section class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
            <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                <div><p class="text-xs font-semibold uppercase text-slate-500">Conector</p><p class="mt-1 text-sm font-semibold text-slate-950">{{ $job->connector?->name ?: '-' }}</p></div>
                <div><p class="text-xs font-semibold uppercase text-slate-500">Agendamento</p><p class="mt-1 text-sm font-semibold text-slate-950">{{ $scheduleTypes[$job->schedule_type] ?? $job->schedule_type }}</p></div>
                <div><p class="text-xs font-semibold uppercase text-slate-500">Ativo</p><p class="mt-1 text-sm font-semibold text-slate-950">{{ $job->is_active ? 'Sim' : 'Não' }}</p></div>
                <div><p class="text-xs font-semibold uppercase text-slate-500">Frequência</p><p class="mt-1 text-sm font-semibold text-slate-950">{{ $job->frequency_minutes ?: '-' }}</p></div>
                <div><p class="text-xs font-semibold uppercase text-slate-500">Horário</p><p class="mt-1 text-sm font-semibold text-slate-950">{{ $job->run_at ?: '-' }}</p></div>
                <div><p class="text-xs font-semibold uppercase text-slate-500">Última execução</p><p class="mt-1 text-sm font-semibold text-slate-950">{{ $job->last_run_at ? $job->last_run_at->format('d/m/Y H:i') : '-' }}</p></div>
                <div><p class="text-xs font-semibold uppercase text-slate-500">Último resultado</p><p class="mt-1 text-sm font-semibold text-slate-950">{{ $job->last_status ?: '-' }}</p></div>
                <div class="md:col-span-2 xl:col-span-3"><p class="text-xs font-semibold uppercase text-slate-500">Último erro</p><p class="mt-1 whitespace-pre-line text-sm text-rose-700">{{ $job->last_error ?: 'Sem erro registrado.' }}</p></div>
            </div>
        </section>

        <section class="rounded-lg border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-200 px-5 py-4">
                <h2 class="text-base font-semibold text-slate-900">Logs vinculados</h2>
            </div>
            <div class="p-5 space-y-3">
                @forelse($job->syncLogs as $log)
                    <div class="rounded-md border border-slate-200 p-3 text-sm">
                        <div class="flex items-center justify-between gap-2">
                            <div>
                                <a href="{{ route('cmdb.sync-logs.show', $log) }}" class="font-semibold text-[#033151] hover:underline">{{ $log->message ?: 'Execução' }}</a>
                                <div class="text-xs text-slate-500">{{ $log->started_at?->format('d/m/Y H:i') }}</div>
                            </div>
                            <span class="text-xs text-slate-500">{{ $log->status }}</span>
                        </div>
                    </div>
                @empty
                    <p class="text-sm text-slate-500">Nenhum log vinculado.</p>
                @endforelse
            </div>
        </section>
    </div>
</x-app-layout>
