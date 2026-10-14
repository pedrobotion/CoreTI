<x-app-layout>
    @php
        $maxDia = max(1, $porDia->max('execucoes'));
        $maxTipo = max(1, $porTipo->max('execucoes'));
        $maxHost = max(1, $porHost->max('execucoes'));
        $okPares = $saude->where('situacao', 'ok')->count();
        $silencioPares = $saude->where('situacao', 'silencio')->count();
        $falhaPares = $saude->where('situacao', 'falha')->count();
        $inativoPares = $saude->where('situacao', 'inativo')->count();
        $tomSituacao = [
            'falha' => ['Com falha', 'bg-rose-100 text-rose-800 dark:bg-rose-500/15 dark:text-rose-200'],
            'silencio' => ['Em silêncio', 'bg-amber-100 text-amber-800 dark:bg-amber-500/15 dark:text-amber-200'],
            'inativo' => ['Descontinuado', 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300'],
            'ok' => ['Saudável', 'bg-emerald-100 text-emerald-800 dark:bg-emerald-500/15 dark:text-emerald-200'],
        ];
    @endphp

    <div class="mx-auto max-w-7xl space-y-6">
        <div class="flex flex-wrap items-end justify-between gap-3">
            <div>
                <a href="{{ route('home') }}" class="text-xs font-semibold uppercase tracking-wide text-slate-500 underline">← Dashboards</a>
                <h1 class="mt-2 text-2xl font-bold text-slate-950 dark:text-white">Auditoria de Backups</h1>
                <p class="mt-1 text-sm text-slate-500">
                    Log de execução dos backups, de {{ $de->local()->format('d/m/Y') }} a {{ $ate->local()->format('d/m/Y') }}.
                </p>
            </div>
            <form method="GET" class="flex flex-wrap items-end gap-2">
                <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">
                    De
                    <input type="date" name="de" value="{{ $de->local()->format('Y-m-d') }}" class="mt-1 block h-10 rounded-md border-slate-300 text-sm">
                </label>
                <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">
                    Até
                    <input type="date" name="ate" value="{{ $ate->local()->format('Y-m-d') }}" class="mt-1 block h-10 rounded-md border-slate-300 text-sm">
                </label>
                <button class="h-10 rounded-md bg-slate-950 px-4 text-sm font-semibold text-white">Aplicar</button>
            </form>
        </div>

        <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            @foreach ([
                ['Execuções', number_format($resumo['execucoes'], 0, ',', '.'), $resumo['pares'].' pares (servidor + banco)'],
                ['Taxa de sucesso', number_format($resumo['sucesso_pct'], 1, ',', '.').'%', number_format($resumo['falhas'], 0, ',', '.').' falhas no período'],
                ['Em silêncio', number_format($resumo['em_silencio'], 0, ',', '.'), 'sem reportar há mais de '.config('backups.stale_after_hours', 36).'h'],
                ['Volume total', number_format($resumo['tamanho_gb'], 1, ',', '.').' GB', 'duração média de '.number_format($resumo['duracao_media'], 0, ',', '.').'s'],
            ] as [$rotulo, $valor, $nota])
                <div class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    <p class="text-xs uppercase text-slate-500">{{ $rotulo }}</p>
                    <p class="mt-2 text-2xl font-bold text-slate-950 dark:text-white">{{ $valor }}</p>
                    <p class="mt-1 text-xs text-slate-500">{{ $nota }}</p>
                </div>
            @endforeach
        </section>

        @if ($resumo['execucoes'] === 0)
            <div class="rounded-lg border border-slate-200 bg-white p-10 text-center shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <p class="text-sm font-semibold text-slate-700 dark:text-slate-200">Nenhum backup registrado neste período.</p>
                <p class="mt-2 text-sm text-slate-500">Os agentes enviam o log por webhook. Ajuste o período acima se precisar olhar mais atrás.</p>
            </div>
        @else
            <section class="grid gap-5 xl:grid-cols-2">
                <div class="rounded-lg border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    <h2 class="text-lg font-bold text-slate-950 dark:text-white">Execuções por dia</h2>
                    <p class="mt-1 text-xs text-slate-500">A faixa vermelha é a parcela que falhou.</p>
                    <div class="mt-5 flex h-48 items-stretch gap-1">
                        @foreach ($porDia as $d)
                            <div class="group flex h-full flex-1 flex-col"
                                 title="{{ $d->dia->format('d/m') }} — {{ number_format($d->execucoes, 0, ',', '.') }} execuções, {{ number_format($d->falhas, 0, ',', '.') }} falhas">
                                <div class="flex flex-1 flex-col justify-end">
                                    <div class="w-full rounded-t bg-rose-500" style="height: {{ $d->execucoes > 0 ? round($d->falhas / $maxDia * 100) : 0 }}%"></div>
                                    <div class="w-full bg-emerald-500" style="height: {{ round(($d->execucoes - $d->falhas) / $maxDia * 100) }}%"></div>
                                </div>
                                <span class="mt-1 h-4 text-center text-[10px] leading-4 text-slate-400">{{ $loop->index % 5 === 0 ? $d->dia->format('d/m') : '' }}</span>
                            </div>
                        @endforeach
                    </div>
                </div>

                <div class="rounded-lg border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    <h2 class="text-lg font-bold text-slate-950 dark:text-white">Por tipo de backup</h2>
                    <div class="mt-5 space-y-4">
                        @foreach ($porTipo as $t)
                            <div>
                                <div class="flex items-center justify-between gap-3 text-sm">
                                    <span class="font-medium text-slate-800 dark:text-slate-100">{{ $t->tipo }}</span>
                                    <span class="text-xs text-slate-500">{{ number_format($t->execucoes, 0, ',', '.') }} · <strong class="text-slate-900 dark:text-white">{{ number_format($t->percentual, 1, ',', '.') }}%</strong></span>
                                </div>
                                <div class="mt-1.5 h-2 overflow-hidden rounded-full bg-slate-100 dark:bg-slate-800">
                                    <div class="h-full rounded-full bg-sky-500" style="width: {{ round($t->execucoes / $maxTipo * 100) }}%"></div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </section>

            <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                @foreach ([
                    ['Saudáveis', $okPares, 'bg-emerald-500'],
                    ['Com falha', $falhaPares, 'bg-rose-500'],
                    ['Em silêncio', $silencioPares, 'bg-amber-500'],
                    ['Descontinuados', $inativoPares, 'bg-slate-400'],
                ] as [$rotulo, $valor, $cor])
                    <div class="flex items-center gap-3 rounded-lg border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                        <span class="h-10 w-1.5 shrink-0 rounded-full {{ $cor }}"></span>
                        <div>
                            <p class="text-2xl font-bold text-slate-950 dark:text-white">{{ number_format($valor, 0, ',', '.') }}</p>
                            <p class="text-xs uppercase text-slate-500">{{ $rotulo }}</p>
                        </div>
                    </div>
                @endforeach
            </section>

            <section class="rounded-lg border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <div class="border-b border-slate-200 px-6 py-4 dark:border-slate-800">
                    <h2 class="text-lg font-bold text-slate-950 dark:text-white">Servidores com mais movimento</h2>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-slate-50 text-left text-xs font-semibold uppercase text-slate-500 dark:bg-slate-800/60">
                            <tr>
                                <th class="px-6 py-2">Servidor</th>
                                <th class="px-6 py-2">Bancos</th>
                                <th class="px-6 py-2">Execuções</th>
                                <th class="px-6 py-2">Falhas</th>
                                <th class="px-6 py-2">Volume</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                            @foreach ($porHost as $h)
                                <tr>
                                    <td class="px-6 py-2 font-medium text-slate-900 dark:text-white">{{ $h->host }}</td>
                                    <td class="px-6 py-2 text-xs text-slate-500">{{ $h->bancos }}</td>
                                    <td class="px-6 py-2">
                                        <div class="flex items-center gap-2">
                                            <div class="h-1.5 w-24 overflow-hidden rounded-full bg-slate-100 dark:bg-slate-800">
                                                <div class="h-full rounded-full bg-sky-500" style="width: {{ round($h->execucoes / $maxHost * 100) }}%"></div>
                                            </div>
                                            <span class="font-semibold">{{ number_format($h->execucoes, 0, ',', '.') }}</span>
                                        </div>
                                    </td>
                                    <td class="px-6 py-2 {{ $h->falhas > 0 ? 'font-semibold text-rose-600 dark:text-rose-400' : 'text-slate-400' }}">{{ number_format($h->falhas, 0, ',', '.') }}</td>
                                    <td class="px-6 py-2 text-xs text-slate-500">{{ number_format($h->tamanho, 1, ',', '.') }} GB</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </section>

            @if ($comProblema->isNotEmpty())
                <section class="rounded-lg border border-rose-200 bg-white shadow-sm dark:border-rose-900/50 dark:bg-slate-900">
                    <div class="border-b border-slate-200 px-6 py-4 dark:border-slate-800">
                        <h2 class="text-lg font-bold text-slate-950 dark:text-white">Pares que precisam de atenção</h2>
                        <p class="mt-1 text-sm text-slate-500">Último backup conhecido de cada servidor + banco (fora do filtro de período).</p>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead class="bg-slate-50 text-left text-xs font-semibold uppercase text-slate-500 dark:bg-slate-800/60">
                                <tr>
                                    <th class="px-6 py-2">Servidor / Banco</th>
                                    <th class="px-6 py-2">Situação</th>
                                    <th class="px-6 py-2">Último backup</th>
                                    <th class="px-6 py-2">Chamado</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                                @foreach ($comProblema as $p)
                                    @php([$rotulo, $classe] = $tomSituacao[$p->situacao])
                                    <tr>
                                        <td class="px-6 py-2">
                                            <span class="font-medium text-slate-900 dark:text-white">{{ $p->host }}</span>
                                            <span class="text-slate-400">/ {{ $p->banco }}</span>
                                        </td>
                                        <td class="px-6 py-2"><span class="rounded-full px-2 py-0.5 text-xs font-semibold {{ $classe }}">{{ $rotulo }}</span></td>
                                        <td class="px-6 py-2 text-xs text-slate-500">{{ $p->ultimo->local()->format('d/m/Y H:i') }} <span class="text-slate-400">({{ $p->horas }}h)</span></td>
                                        <td class="px-6 py-2 text-xs">
                                            @if ($p->jira_issue_url)
                                                <a href="{{ $p->jira_issue_url }}" target="_blank" rel="noopener" class="font-semibold text-sky-600 underline dark:text-sky-400">{{ $p->jira_issue_key }}</a>
                                            @else
                                                <span class="text-slate-400">—</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </section>
            @endif

            @if ($falhasRecentes->isNotEmpty())
                <section class="rounded-lg border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    <div class="border-b border-slate-200 px-6 py-4 dark:border-slate-800">
                        <h2 class="text-lg font-bold text-slate-950 dark:text-white">Falhas recentes</h2>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full min-w-[800px] text-sm">
                            <thead class="bg-slate-50 text-left text-xs font-semibold uppercase text-slate-500 dark:bg-slate-800/60">
                                <tr>
                                    <th class="px-6 py-2">Quando</th>
                                    <th class="px-6 py-2">Servidor / Banco</th>
                                    <th class="px-6 py-2">Observação</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                                @foreach ($falhasRecentes as $f)
                                    <tr>
                                        <td class="px-6 py-2 text-xs text-slate-500">{{ $f->quando->local()->format('d/m/Y H:i') }}</td>
                                        <td class="px-6 py-2"><span class="font-medium text-slate-900 dark:text-white">{{ $f->host }}</span> <span class="text-slate-400">/ {{ $f->banco }}</span></td>
                                        <td class="px-6 py-2 text-xs text-slate-500">{{ \Illuminate\Support\Str::limit($f->observacao, 90) ?: '—' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </section>
            @endif
        @endif

        <section class="rounded-lg border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <h2 class="text-lg font-bold text-slate-950 dark:text-white">Auditoria autônoma (incidentes)</h2>
            <p class="mt-1 text-sm text-slate-500">
                Verificação por SSH com abertura de chamado controlada. Começa a popular quando o agendador roda.
            </p>
            <div class="mt-4 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                @foreach ([
                    ['Incidentes abertos', $incidentes['abertos']],
                    ['Recuperados', $incidentes['recuperados']],
                    ['Com chamado', $incidentes['com_chamado']],
                    ['MTTR médio', $incidentes['mttr_horas'] !== null ? number_format($incidentes['mttr_horas'], 1, ',', '.').'h' : '—'],
                ] as [$rotulo, $valor])
                    <div class="rounded-lg border border-slate-200 p-4 dark:border-slate-700">
                        <p class="text-xs uppercase text-slate-500">{{ $rotulo }}</p>
                        <p class="mt-1 text-2xl font-bold text-slate-950 dark:text-white">{{ is_string($valor) ? $valor : number_format($valor, 0, ',', '.') }}</p>
                    </div>
                @endforeach
            </div>
            @if ($incidentes['total'] === 0)
                <p class="mt-4 text-xs text-slate-400">Sem incidentes registrados ainda — o fluxo autônomo não começou a verificar.</p>
            @endif
        </section>
    </div>
</x-app-layout>
