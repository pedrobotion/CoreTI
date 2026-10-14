<x-app-layout>
    @php
        $maxOperadora = max(0.01, $custoPorOperadora->max('custo'));
        $maxUnidade = max(0.01, $custoPorUnidade->max('custo'));
        $maxStatusLinha = max(1, $linhasPorStatus->max('quantidade'));
        $maxMarca = max(1, $aparelhosPorMarca->max('quantidade'));
        $maxStatusAp = max(1, $aparelhosPorStatus->max('quantidade'));
        $brl = fn ($v) => 'R$ '.number_format($v, 2, ',', '.');
    @endphp

    <div class="mx-auto max-w-7xl space-y-6">
        <div>
            <a href="{{ route('home') }}" class="text-xs font-semibold uppercase tracking-wide text-slate-500 underline">← Dashboards</a>
            <h1 class="mt-2 text-2xl font-bold text-slate-950 dark:text-white">Telefonia</h1>
            <p class="mt-1 text-sm text-slate-500">
                Foto atual do parque de linhas e aparelhos. O custo é o valor mensal das linhas não canceladas.
            </p>
        </div>

        <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            @foreach ([
                ['Custo mensal', $brl($resumo['custo_mensal']), $resumo['linhas_total'].' linhas no total'],
                ['Linhas em uso', number_format($resumo['linhas_em_uso'], 0, ',', '.'), $resumo['linhas_disponiveis'].' disponíveis · '.$resumo['linhas_canceladas'].' canceladas'],
                ['Aparelhos', number_format($resumo['aparelhos_total'], 0, ',', '.'), $resumo['aparelhos_em_uso'].' em uso'],
                ['Vínculos ativos', number_format($resumo['vinculos_ativos'], 0, ',', '.'), 'termos de responsabilidade vigentes'],
            ] as [$rotulo, $valor, $nota])
                <div class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    <p class="text-xs uppercase text-slate-500">{{ $rotulo }}</p>
                    <p class="mt-2 text-2xl font-bold text-slate-950 dark:text-white">{{ $valor }}</p>
                    <p class="mt-1 text-xs text-slate-500">{{ $nota }}</p>
                </div>
            @endforeach
        </section>

        <section class="grid gap-5 xl:grid-cols-2">
            <div class="rounded-lg border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <h2 class="text-lg font-bold text-slate-950 dark:text-white">Custo por operadora</h2>
                <div class="mt-5 space-y-4">
                    @forelse ($custoPorOperadora as $o)
                        <div>
                            <div class="flex items-center justify-between gap-3 text-sm">
                                <span class="font-medium text-slate-800 dark:text-slate-100">{{ $o->operadora }} <span class="text-xs font-normal text-slate-400">· {{ $o->quantidade }} linhas</span></span>
                                <strong class="text-slate-900 dark:text-white">{{ $brl($o->custo) }}</strong>
                            </div>
                            <div class="mt-1.5 h-2 overflow-hidden rounded-full bg-slate-100 dark:bg-slate-800">
                                <div class="h-full rounded-full bg-violet-500" style="width: {{ round($o->custo / $maxOperadora * 100) }}%"></div>
                            </div>
                        </div>
                    @empty
                        <p class="text-sm text-slate-500">Sem linhas com custo cadastrado.</p>
                    @endforelse
                </div>
            </div>

            <div class="rounded-lg border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <h2 class="text-lg font-bold text-slate-950 dark:text-white">Linhas por situação</h2>
                <div class="mt-5 space-y-4">
                    @foreach ($linhasPorStatus as $s)
                        <div>
                            <div class="flex items-center justify-between gap-3 text-sm">
                                <span class="font-medium text-slate-800 dark:text-slate-100">{{ $s->status }}</span>
                                <strong class="text-slate-900 dark:text-white">{{ number_format($s->quantidade, 0, ',', '.') }}</strong>
                            </div>
                            <div class="mt-1.5 h-2 overflow-hidden rounded-full bg-slate-100 dark:bg-slate-800">
                                <div class="h-full rounded-full bg-sky-500" style="width: {{ round($s->quantidade / $maxStatusLinha * 100) }}%"></div>
                            </div>
                        </div>
                    @endforeach
                </div>

                <h3 class="mt-6 text-sm font-semibold uppercase tracking-wide text-slate-500">Custo por operação</h3>
                <div class="mt-3 space-y-2">
                    @foreach ($custoPorOperacao as $op)
                        <div class="flex items-center justify-between gap-3 text-sm">
                            <span class="text-slate-700 dark:text-slate-200">{{ $op->operacao }} <span class="text-xs text-slate-400">· {{ $op->quantidade }} linhas</span></span>
                            <strong class="text-slate-900 dark:text-white">{{ $brl($op->custo) }}</strong>
                        </div>
                    @endforeach
                </div>
            </div>
        </section>

        <section class="rounded-lg border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <div class="border-b border-slate-200 px-6 py-4 dark:border-slate-800">
                <h2 class="text-lg font-bold text-slate-950 dark:text-white">Custo por unidade</h2>
                <p class="mt-1 text-sm text-slate-500">Rateio pela unidade de quem está com a linha (vínculo ativo mais recente).</p>
            </div>
            <div class="divide-y divide-slate-100 px-6 dark:divide-slate-800">
                @forelse ($custoPorUnidade as $u)
                    <div class="py-3">
                        <div class="flex items-center justify-between gap-3 text-sm">
                            <span class="min-w-0 truncate font-medium text-slate-800 dark:text-slate-100">{{ $u->unidade }} <span class="text-xs font-normal text-slate-400">· {{ $u->quantidade }} linhas</span></span>
                            <strong class="shrink-0 text-slate-900 dark:text-white">{{ $brl($u->custo) }}</strong>
                        </div>
                        <div class="mt-1.5 h-1.5 overflow-hidden rounded-full bg-slate-100 dark:bg-slate-800">
                            <div class="h-full rounded-full bg-violet-500" style="width: {{ round($u->custo / $maxUnidade * 100) }}%"></div>
                        </div>
                    </div>
                @empty
                    <p class="py-6 text-sm text-slate-500">Nenhuma linha com vínculo ativo a uma unidade.</p>
                @endforelse
            </div>
        </section>

        <section class="grid gap-5 xl:grid-cols-2">
            <div class="rounded-lg border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <h2 class="text-lg font-bold text-slate-950 dark:text-white">Aparelhos por marca</h2>
                <div class="mt-5 space-y-3">
                    @foreach ($aparelhosPorMarca as $m)
                        <div>
                            <div class="flex items-center justify-between gap-3 text-sm">
                                <span class="font-medium text-slate-800 dark:text-slate-100">{{ $m->marca }}</span>
                                <strong class="text-slate-900 dark:text-white">{{ number_format($m->quantidade, 0, ',', '.') }}</strong>
                            </div>
                            <div class="mt-1.5 h-2 overflow-hidden rounded-full bg-slate-100 dark:bg-slate-800">
                                <div class="h-full rounded-full bg-indigo-500" style="width: {{ round($m->quantidade / $maxMarca * 100) }}%"></div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="rounded-lg border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <h2 class="text-lg font-bold text-slate-950 dark:text-white">Aparelhos por situação</h2>
                <div class="mt-5 space-y-3">
                    @foreach ($aparelhosPorStatus as $s)
                        <div>
                            <div class="flex items-center justify-between gap-3 text-sm">
                                <span class="font-medium text-slate-800 dark:text-slate-100">{{ $s->status }}</span>
                                <strong class="text-slate-900 dark:text-white">{{ number_format($s->quantidade, 0, ',', '.') }}</strong>
                            </div>
                            <div class="mt-1.5 h-2 overflow-hidden rounded-full bg-slate-100 dark:bg-slate-800">
                                <div class="h-full rounded-full bg-emerald-500" style="width: {{ round($s->quantidade / $maxStatusAp * 100) }}%"></div>
                            </div>
                        </div>
                    @endforeach
                </div>

                <h3 class="mt-6 text-sm font-semibold uppercase tracking-wide text-slate-500">Pendências</h3>
                <div class="mt-3 grid grid-cols-3 gap-3">
                    @foreach ([
                        ['Linhas em uso sem vínculo', $qualidade['linhas_sem_vinculo']],
                        ['Vínculos vencidos', $qualidade['vinculos_vencidos']],
                        ['Com colaborador inativo', $qualidade['colaborador_inativo']],
                    ] as [$rotulo, $valor])
                        <div class="rounded-lg border p-3 {{ $valor > 0 ? 'border-amber-300 bg-amber-50 dark:border-amber-900/50 dark:bg-amber-500/10' : 'border-slate-200 dark:border-slate-700' }}">
                            <p class="text-xl font-bold text-slate-950 dark:text-white">{{ number_format($valor, 0, ',', '.') }}</p>
                            <p class="mt-1 text-[11px] leading-tight text-slate-500">{{ $rotulo }}</p>
                        </div>
                    @endforeach
                </div>
            </div>
        </section>
    </div>
</x-app-layout>
