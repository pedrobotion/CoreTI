<x-app-layout>
    <div class="mx-auto max-w-7xl space-y-6">
        <div>
            <a href="{{ route('impressoras.index') }}" class="text-xs font-semibold uppercase tracking-wide text-slate-500 underline">← Impressoras</a>
            <h1 class="mt-2 text-2xl font-bold text-slate-950 dark:text-white">Conciliação com a Simpress</h1>
            <p class="mt-1 text-sm text-slate-500">
                Serial lido por SNMP direto na impressora, cruzado com a base do portal.
                O serial não muda; onde o IP diverge, é o portal que precisa ser corrigido.
            </p>
        </div>

        <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
            <div class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <p class="text-xs uppercase text-slate-500">Filas conhecidas</p>
                <p class="mt-2 text-2xl font-bold text-slate-950 dark:text-white">{{ number_format($resumo['total'], 0, ',', '.') }}</p>
            </div>
            <a href="{{ route('impressoras.conciliacao', ['situacao' => 'divergentes']) }}" class="rounded-lg border border-amber-300 bg-amber-50 p-4 shadow-sm transition hover:border-amber-500 dark:border-slate-800 dark:bg-slate-900">
                <p class="text-xs uppercase text-amber-700">IP divergente</p>
                <p class="mt-2 text-2xl font-bold text-amber-800">{{ number_format($resumo['divergentes'], 0, ',', '.') }}</p>
                <p class="mt-1 text-xs text-slate-500">corrigir no portal</p>
            </a>
            <a href="{{ route('impressoras.conciliacao', ['situacao' => 'casadas']) }}" class="rounded-lg border border-emerald-200 bg-emerald-50 p-4 shadow-sm transition hover:border-emerald-400 dark:border-slate-800 dark:bg-slate-900">
                <p class="text-xs uppercase text-emerald-700">Casadas</p>
                <p class="mt-2 text-2xl font-bold text-emerald-800">{{ number_format($resumo['casadas'], 0, ',', '.') }}</p>
            </a>
            <a href="{{ route('impressoras.conciliacao', ['situacao' => 'sem_simpress']) }}" class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm transition hover:border-slate-400 dark:border-slate-800 dark:bg-slate-900">
                <p class="text-xs uppercase text-slate-500">Fora da Simpress</p>
                <p class="mt-2 text-2xl font-bold text-slate-900 dark:text-white">{{ number_format($resumo['sem_simpress'], 0, ',', '.') }}</p>
            </a>
            <a href="{{ route('impressoras.conciliacao', ['situacao' => 'sem_resposta']) }}" class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm transition hover:border-slate-400 dark:border-slate-800 dark:bg-slate-900">
                <p class="text-xs uppercase text-slate-500">Sem resposta SNMP</p>
                <p class="mt-2 text-2xl font-bold text-slate-900 dark:text-white">{{ number_format($resumo['sem_resposta'], 0, ',', '.') }}</p>
            </a>
        </section>

        <section class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <form method="GET" class="flex flex-wrap items-end gap-3">
                <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">
                    Servidor
                    <select name="servidor" class="mt-1 block h-10 rounded-md border-slate-300 text-sm">
                        <option value="">Todos</option>
                        @foreach ($servidores as $s)
                            <option value="{{ $s }}" @selected(($filtros['servidor'] ?? '') === $s)>{{ $s }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">
                    Situação
                    <select name="situacao" class="mt-1 block h-10 rounded-md border-slate-300 text-sm">
                        <option value="">Todas</option>
                        @foreach ($situacoes as $chave => $rotulo)
                            <option value="{{ $chave }}" @selected(($filtros['situacao'] ?? '') === $chave)>{{ $rotulo }}</option>
                        @endforeach
                    </select>
                </label>
                <button class="h-10 rounded-md bg-slate-950 px-4 text-sm font-semibold text-white">Filtrar</button>
                @if (($filtros['servidor'] ?? '') || ($filtros['situacao'] ?? ''))
                    <a href="{{ route('impressoras.conciliacao') }}" class="text-xs font-semibold text-slate-500 underline">Limpar</a>
                @endif
            </form>
        </section>

        <section class="overflow-x-auto rounded-lg border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <table class="w-full min-w-[960px] text-sm">
                <thead class="bg-slate-900 text-left text-xs font-semibold uppercase text-white">
                    <tr>
                        <th class="px-4 py-3">Servidor / fila</th>
                        <th class="px-4 py-3">Serial</th>
                        <th class="px-4 py-3">Modelo</th>
                        <th class="px-4 py-3">IP real (SNMP)</th>
                        <th class="px-4 py-3">IP na Simpress</th>
                        <th class="px-4 py-3">Situação</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200 dark:divide-slate-800">
                    @forelse ($registros as $r)
                        <tr @class(['bg-amber-50/60 dark:bg-amber-900/10' => $r->ip_divergente])>
                            <td class="px-4 py-3">
                                <span class="font-semibold text-slate-900 dark:text-white">{{ $r->fila }}</span>
                                <div class="text-xs text-slate-400">{{ $r->servidor }}</div>
                            </td>
                            <td class="px-4 py-3 font-mono text-xs">{{ $r->serial_number ?: '—' }}</td>
                            <td class="px-4 py-3 text-xs">
                                @if ($r->printer_id)
                                    <a href="{{ route('impressoras.show', $r->printer_id) }}" class="text-slate-700 underline decoration-slate-300 dark:text-slate-200">{{ $r->modelo_snmp ?: $r->printer->model }}</a>
                                @else
                                    {{ $r->modelo_snmp ?: '—' }}
                                @endif
                            </td>
                            <td class="px-4 py-3 font-mono text-xs">{{ $r->ip_porta ?: '—' }}</td>
                            <td class="px-4 py-3 font-mono text-xs {{ $r->ip_divergente ? 'font-bold text-amber-700' : 'text-slate-500' }}">
                                {{ $r->printer_id ? ($r->ip_simpress ?: 'vazio') : '—' }}
                            </td>
                            <td class="px-4 py-3">
                                @if ($r->ip_divergente)
                                    <span class="rounded bg-amber-100 px-2 py-0.5 text-xs font-semibold text-amber-800">Corrigir no portal</span>
                                @elseif ($r->snmp_status === \App\Models\PrintQueueDiscovery::SNMP_SEM_RESPOSTA)
                                    <span class="rounded bg-slate-100 px-2 py-0.5 text-xs text-slate-600 dark:bg-slate-800">Sem SNMP</span>
                                @elseif (! $r->printer_id && $r->serial_number)
                                    <span class="rounded bg-slate-100 px-2 py-0.5 text-xs text-slate-600 dark:bg-slate-800">Fora da Simpress</span>
                                @elseif ($r->printer_id)
                                    <span class="rounded bg-emerald-100 px-2 py-0.5 text-xs font-semibold text-emerald-700">OK</span>
                                @else
                                    <span class="text-xs text-slate-400">pendente</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-4 py-10 text-center text-slate-500">Nenhuma fila com esses filtros.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </section>

        @if ($registros->hasPages())
            <div>{{ $registros->onEachSide(1)->links() }}</div>
        @endif
    </div>
</x-app-layout>
