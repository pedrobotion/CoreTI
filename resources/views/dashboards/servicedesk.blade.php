<x-app-layout>
    @php
        $maxMov = max(1, $movimentoPorDia->max(fn ($d) => max($d->criados, $d->resolvidos)));
        $maxStatus = max(1, $chamadosPorStatus->max('quantidade'));
        $maxSquad = max(1, $chamadosPorSquad->max('quantidade'));
        $maxLotacao = max(1, $adPorLotacao->max('quantidade'));
        $maxOffice = max(1, $officePorTipo->max('quantidade'));
        $rankings = [
            ['Catálogo', $porCatalogo],
            ['Tipo de requisição', $porTipo],
            ['Unidade', $porUnidade],
        ];
    @endphp

    <div class="mx-auto max-w-7xl space-y-6">
        <div class="flex flex-wrap items-end justify-between gap-3">
            <div>
                <a href="{{ route('home') }}" class="text-xs font-semibold uppercase tracking-wide text-slate-500 underline">← Dashboards</a>
                <h1 class="mt-2 text-2xl font-bold text-slate-950 dark:text-white">ServiceDesk</h1>
                <p class="mt-1 text-sm text-slate-500">
                    Chamados, e-mails corporativos, Active Directory e licenças Office. Movimento de {{ $de->local()->format('d/m/Y') }} a {{ $ate->local()->format('d/m/Y') }}.
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
                ['Chamados abertos', number_format($resumo['chamados_abertos'], 0, ',', '.'), $resumo['aguardando_solicitante'].' aguardando solicitante'],
                ['SLA em atenção', number_format($resumo['sla_risco'], 0, ',', '.'), 'abertos com prazo estourado'],
                ['Criados no período', number_format($resumo['criados_periodo'], 0, ',', '.'), number_format($resumo['resolvidos_periodo'], 0, ',', '.').' resolvidos no período'],
                ['Contas AD ativas', number_format($resumo['ad_ativos'], 0, ',', '.'), $resumo['emails_ativos'].' e-mails · '.$resumo['office_ativos'].' licenças Office'],
            ] as [$rotulo, $valor, $nota])
                <div class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    <p class="text-xs uppercase text-slate-500">{{ $rotulo }}</p>
                    <p class="mt-2 text-2xl font-bold text-slate-950 dark:text-white">{{ $valor }}</p>
                    <p class="mt-1 text-xs text-slate-500">{{ $nota }}</p>
                </div>
            @endforeach
        </section>

        <section class="rounded-lg border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <h2 class="text-lg font-bold text-slate-950 dark:text-white">Abertos x resolvidos por dia</h2>
                <div class="flex items-center gap-4 text-xs text-slate-500">
                    <span class="flex items-center gap-1"><span class="h-2.5 w-2.5 rounded-sm bg-sky-500"></span> Criados</span>
                    <span class="flex items-center gap-1"><span class="h-2.5 w-2.5 rounded-sm bg-emerald-500"></span> Resolvidos</span>
                </div>
            </div>
            <div class="mt-5 flex h-48 items-stretch gap-1">
                @foreach ($movimentoPorDia as $d)
                    <div class="group flex h-full flex-1 flex-col"
                         title="{{ $d->dia->format('d/m') }} — {{ $d->criados }} criados, {{ $d->resolvidos }} resolvidos">
                        <div class="flex flex-1 items-end justify-center gap-px">
                            <div class="w-1/2 rounded-t bg-sky-500" style="height: {{ $d->criados > 0 ? max(round($d->criados / $maxMov * 100), 2) : 0 }}%"></div>
                            <div class="w-1/2 rounded-t bg-emerald-500" style="height: {{ $d->resolvidos > 0 ? max(round($d->resolvidos / $maxMov * 100), 2) : 0 }}%"></div>
                        </div>
                        <span class="mt-1 h-4 text-center text-[10px] leading-4 text-slate-400">{{ $loop->index % 5 === 0 ? $d->dia->format('d/m') : '' }}</span>
                    </div>
                @endforeach
            </div>
        </section>

        <section class="grid gap-5 xl:grid-cols-2">
            <div class="rounded-lg border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <h2 class="text-lg font-bold text-slate-950 dark:text-white">Abertos por status</h2>
                <div class="mt-5 space-y-3">
                    @foreach ($chamadosPorStatus as $s)
                        <div>
                            <div class="flex items-center justify-between gap-3 text-sm">
                                <span class="font-medium text-slate-800 dark:text-slate-100">{{ $s->status }}</span>
                                <strong class="text-slate-900 dark:text-white">{{ number_format($s->quantidade, 0, ',', '.') }}</strong>
                            </div>
                            <div class="mt-1.5 h-2 overflow-hidden rounded-full bg-slate-100 dark:bg-slate-800">
                                <div class="h-full rounded-full bg-amber-500" style="width: {{ round($s->quantidade / $maxStatus * 100) }}%"></div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="rounded-lg border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <h2 class="text-lg font-bold text-slate-950 dark:text-white">Abertos por time</h2>
                <div class="mt-5 space-y-3">
                    @foreach ($chamadosPorSquad as $s)
                        <div>
                            <div class="flex items-center justify-between gap-3 text-sm">
                                <span class="font-medium text-slate-800 dark:text-slate-100">{{ $s->squad }}</span>
                                <strong class="text-slate-900 dark:text-white">{{ number_format($s->quantidade, 0, ',', '.') }}</strong>
                            </div>
                            <div class="mt-1.5 h-2 overflow-hidden rounded-full bg-slate-100 dark:bg-slate-800">
                                <div class="h-full rounded-full bg-indigo-500" style="width: {{ round($s->quantidade / $maxSquad * 100) }}%"></div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </section>

        <section class="grid gap-5 lg:grid-cols-3">
            @foreach ($rankings as [$titulo, $lista])
                <div class="rounded-lg border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    <div class="border-b border-slate-200 px-5 py-3 dark:border-slate-800">
                        <h2 class="text-base font-bold text-slate-950 dark:text-white">Top {{ $titulo }}</h2>
                        <p class="text-xs text-slate-500">Chamados criados no período.</p>
                    </div>
                    <div class="divide-y divide-slate-100 dark:divide-slate-800">
                        @forelse ($lista as $item)
                            <div class="flex items-center justify-between gap-3 px-5 py-2.5 text-sm">
                                <span class="min-w-0 truncate text-slate-700 dark:text-slate-200">{{ $item->rotulo }}</span>
                                <strong class="shrink-0 text-slate-900 dark:text-white">{{ number_format($item->quantidade, 0, ',', '.') }}</strong>
                            </div>
                        @empty
                            <p class="px-5 py-6 text-sm text-slate-500">Sem dados no período.</p>
                        @endforelse
                    </div>
                </div>
            @endforeach
        </section>

        <section class="grid gap-5 xl:grid-cols-3">
            <div class="rounded-lg border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <h2 class="text-lg font-bold text-slate-950 dark:text-white">E-mails corporativos</h2>
                <p class="mt-1 text-xs text-slate-500">Ativos por escopo.</p>
                <div class="mt-4 space-y-3">
                    @foreach ($emailsPorScope as $e)
                        <div class="flex items-center justify-between gap-3 text-sm">
                            <span class="text-slate-700 dark:text-slate-200">{{ $e->scope }}</span>
                            <span class="text-xs text-slate-500"><strong class="text-slate-900 dark:text-white">{{ number_format($e->ativos, 0, ',', '.') }}</strong> / {{ number_format($e->total, 0, ',', '.') }}</span>
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="rounded-lg border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <h2 class="text-lg font-bold text-slate-950 dark:text-white">Active Directory</h2>
                <div class="mt-4 grid grid-cols-2 gap-3">
                    @foreach ([
                        ['Ativas', $adResumo['ativos'], 'text-emerald-600 dark:text-emerald-400'],
                        ['Inativas', $adResumo['inativos'], 'text-amber-600 dark:text-amber-400'],
                        ['Excluídas', $adResumo['excluidos'], 'text-slate-500'],
                        ['Não colaboradores', $adResumo['nao_colaboradores'], 'text-rose-600 dark:text-rose-400'],
                    ] as [$rotulo, $valor, $cor])
                        <div class="rounded-lg border border-slate-200 p-3 dark:border-slate-700">
                            <p class="text-xl font-bold {{ $cor }}">{{ number_format($valor, 0, ',', '.') }}</p>
                            <p class="mt-0.5 text-[11px] text-slate-500">{{ $rotulo }}</p>
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="rounded-lg border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <h2 class="text-lg font-bold text-slate-950 dark:text-white">Licenças Office ativas</h2>
                <div class="mt-4 space-y-3">
                    @foreach ($officePorTipo as $o)
                        <div>
                            <div class="flex items-center justify-between gap-3 text-sm">
                                <span class="text-slate-700 dark:text-slate-200">{{ $o->tipo }}</span>
                                <strong class="text-slate-900 dark:text-white">{{ number_format($o->quantidade, 0, ',', '.') }}</strong>
                            </div>
                            <div class="mt-1.5 h-1.5 overflow-hidden rounded-full bg-slate-100 dark:bg-slate-800">
                                <div class="h-full rounded-full bg-violet-500" style="width: {{ round($o->quantidade / $maxOffice * 100) }}%"></div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </section>

        <section class="rounded-lg border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <h2 class="text-lg font-bold text-slate-950 dark:text-white">Contas AD ativas por lotação</h2>
            <div class="mt-5 grid gap-x-8 gap-y-3 lg:grid-cols-2">
                @foreach ($adPorLotacao as $l)
                    <div>
                        <div class="flex items-center justify-between gap-3 text-sm">
                            <span class="min-w-0 truncate font-medium text-slate-800 dark:text-slate-100">{{ $l->lotacao }}</span>
                            <strong class="shrink-0 text-slate-900 dark:text-white">{{ number_format($l->quantidade, 0, ',', '.') }}</strong>
                        </div>
                        <div class="mt-1.5 h-1.5 overflow-hidden rounded-full bg-slate-100 dark:bg-slate-800">
                            <div class="h-full rounded-full bg-sky-500" style="width: {{ round($l->quantidade / $maxLotacao * 100) }}%"></div>
                        </div>
                    </div>
                @endforeach
            </div>
        </section>
    </div>
</x-app-layout>
