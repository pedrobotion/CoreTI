<x-app-layout>
    @php
        $maxDia = max(1, $porDia->max('paginas'));
        $maxHora = max(1, $porHorario->max('paginas'));
        $maxUsuario = max(1, $porUsuario->max('paginas'));
    @endphp

    <div class="mx-auto max-w-7xl space-y-6">
        <div class="flex flex-wrap items-end justify-between gap-3">
            <div>
                <a href="{{ route('dashboard') }}" class="text-xs font-semibold uppercase tracking-wide text-slate-500 underline">← Dashboards</a>
                <h1 class="mt-2 text-2xl font-bold text-slate-950 dark:text-white">Impressões</h1>
                <p class="mt-1 text-sm text-slate-500">
                    Log dos servidores de impressão, de {{ $de->local()->format('d/m/Y') }} a {{ $ate->local()->format('d/m/Y') }}.
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

        @if ($resumo['trabalhos'] === 0)
            <div class="rounded-lg border border-slate-200 bg-white p-10 text-center shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <p class="text-sm font-semibold text-slate-700 dark:text-slate-200">Nenhuma impressão registrada neste período.</p>
                <p class="mt-2 text-sm text-slate-500">
                    Os servidores enviam o log por tarefa agendada. Se o período deveria ter movimento,
                    confira a tabela de servidores no fim desta página.
                </p>
            </div>
        @else
            <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                @foreach ([
                    ['Páginas impressas', number_format($resumo['paginas'], 0, ',', '.'), null],
                    ['Trabalhos', number_format($resumo['trabalhos'], 0, ',', '.'), 'média de '.number_format($resumo['media_paginas'], 2, ',', '.').' pág. por trabalho'],
                    ['Usuários', number_format($resumo['usuarios'], 0, ',', '.'), 'em '.number_format($resumo['impressoras'], 0, ',', '.').' impressoras'],
                    ['Maior trabalho', number_format($resumo['maior_trabalho'], 0, ',', '.').' pág.', $resumo['sem_pagina'].' trabalhos sem render folha'],
                ] as [$rotulo, $valor, $nota])
                    <div class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                        <p class="text-xs uppercase text-slate-500">{{ $rotulo }}</p>
                        <p class="mt-2 text-2xl font-bold text-slate-950 dark:text-white">{{ $valor }}</p>
                        @if ($nota)<p class="mt-1 text-xs text-slate-500">{{ $nota }}</p>@endif
                    </div>
                @endforeach
            </section>

            <section class="grid gap-5 xl:grid-cols-2">
                <div class="rounded-lg border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    <h2 class="text-lg font-bold text-slate-950 dark:text-white">Páginas por dia</h2>
                    <div class="mt-5 space-y-2">
                        @foreach ($porDia as $dia)
                            <div class="flex items-center gap-3">
                                <span class="w-28 shrink-0 text-xs text-slate-500">
                                    {{ $dia->dia->format('d/m') }}
                                    <span class="text-slate-400">{{ $dia->dia->locale('pt_BR')->isoFormat('ddd') }}</span>
                                </span>
                                <div class="h-5 flex-1 overflow-hidden rounded bg-slate-100 dark:bg-slate-800">
                                    <div class="h-full rounded bg-sky-500" style="width: {{ round($dia->paginas / $maxDia * 100) }}%"></div>
                                </div>
                                <span class="w-16 shrink-0 text-right text-xs font-semibold text-slate-700 dark:text-slate-200">{{ number_format($dia->paginas, 0, ',', '.') }}</span>
                            </div>
                        @endforeach
                    </div>
                </div>

                <div class="rounded-lg border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    <h2 class="text-lg font-bold text-slate-950 dark:text-white">Páginas por horário</h2>
                    <p class="mt-1 text-xs text-slate-500">Horário de Brasília.</p>
                    {{--
                        A barra precisa de um pai com altura DEFINIDA.

                        Antes a coluna não tinha altura: com "items-end" o
                        filho é dimensionado pelo conteúdo, e "height: X%"
                        sobre pai indefinido resolve em zero — o gráfico
                        sumia inteiro, sem erro nenhum. Agora a coluna ocupa
                        a altura toda e a área da barra é um flex-1, que o
                        próprio flex torna definida.
                    --}}
                    <div class="mt-5 flex h-48 items-stretch gap-1">
                        @foreach ($porHorario as $hora)
                            <div class="group flex h-full flex-1 flex-col"
                                 title="{{ sprintf('%02d:00 — %s páginas em %s trabalhos', $hora->hora, number_format($hora->paginas, 0, ',', '.'), number_format($hora->trabalhos, 0, ',', '.')) }}">
                                <div class="flex flex-1 items-end">
                                    <div class="w-full rounded-t bg-indigo-500 transition group-hover:bg-indigo-600"
                                         style="height: {{ $hora->paginas > 0 ? max(round($hora->paginas / $maxHora * 100), 2) : 0 }}%"></div>
                                </div>
                                <span class="mt-1 h-4 text-center text-[10px] leading-4 text-slate-400">
                                    {{ $hora->hora % 3 === 0 ? $hora->hora : '' }}
                                </span>
                            </div>
                        @endforeach
                    </div>
                </div>
            </section>

            <section class="grid gap-5 xl:grid-cols-2">
                <div class="rounded-lg border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    <div class="border-b border-slate-200 px-6 py-4 dark:border-slate-800">
                        <h2 class="text-lg font-bold text-slate-950 dark:text-white">Quem mais imprime</h2>
                    </div>
                    <div class="divide-y divide-slate-100 dark:divide-slate-800">
                        @foreach ($porUsuario as $u)
                            <div class="px-6 py-3">
                                <div class="flex items-center justify-between gap-3 text-sm">
                                    <span class="min-w-0 truncate font-medium text-slate-800 dark:text-slate-100">
                                        {{ $u->usuario }}
                                        @if ($u->lotacao ?? null)
                                            <span class="ml-1 text-xs font-normal text-slate-500">· {{ $u->lotacao }}</span>
                                        @endif
                                    </span>
                                    <span class="shrink-0 text-xs text-slate-500">{{ number_format($u->trabalhos, 0, ',', '.') }} trab. · <strong class="text-slate-900 dark:text-white">{{ number_format($u->paginas, 0, ',', '.') }} pág.</strong></span>
                                </div>
                                @if (($u->nome_completo ?? null) || ($u->cargo ?? null))
                                    <p class="mt-0.5 truncate text-xs text-slate-400">
                                        {{ $u->nome_completo ?? $u->usuario }}@if ($u->cargo ?? null) — {{ $u->cargo }}@endif
                                    </p>
                                @endif
                                <div class="mt-1.5 h-1.5 overflow-hidden rounded-full bg-slate-100 dark:bg-slate-800">
                                    <div class="h-full rounded-full bg-emerald-500" style="width: {{ round($u->paginas / $maxUsuario * 100) }}%"></div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>

                <div class="rounded-lg border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    <div class="border-b border-slate-200 px-6 py-4 dark:border-slate-800">
                        <h2 class="text-lg font-bold text-slate-950 dark:text-white">Impressoras mais usadas</h2>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead class="bg-slate-50 text-left text-xs font-semibold uppercase text-slate-500 dark:bg-slate-800/60">
                                <tr>
                                    <th class="px-6 py-2">Impressora</th>
                                    <th class="px-6 py-2">Páginas</th>
                                    <th class="px-6 py-2">%</th>
                                    <th class="px-6 py-2">Usuários</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                                @foreach ($porImpressora as $i)
                                    <tr>
                                        <td class="px-6 py-2">
                                            @if ($i->printer_id)
                                                <a href="{{ route('impressoras.show', $i->printer_id) }}" class="font-medium text-slate-900 underline decoration-slate-300 dark:text-white">{{ $i->impressora }}</a>
                                            @else
                                                <span class="font-medium text-slate-800 dark:text-slate-100">{{ $i->impressora }}</span>
                                                <span class="ml-1 rounded bg-slate-100 px-1.5 py-0.5 text-[11px] text-slate-500 dark:bg-slate-800">fora do portal</span>
                                            @endif
                                        </td>
                                        <td class="px-6 py-2 font-semibold">{{ number_format($i->paginas, 0, ',', '.') }}</td>
                                        <td class="px-6 py-2 text-xs text-slate-500">{{ number_format($i->percentual, 1, ',', '.') }}%</td>
                                        <td class="px-6 py-2 text-xs text-slate-500">{{ $i->usuarios }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </section>

            @if ($porLotacao->isNotEmpty())
                @php($maxLotacao = max(1, $porLotacao->max('paginas')))
                <section class="rounded-lg border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    <h2 class="text-lg font-bold text-slate-950 dark:text-white">Páginas por lotação</h2>
                    <p class="mt-1 text-sm text-slate-500">Unidade ou setor de cada pessoa, conforme o Active Directory.</p>
                    <div class="mt-5 grid gap-x-8 gap-y-3 lg:grid-cols-2">
                        @foreach ($porLotacao as $l)
                            <div>
                                <div class="flex items-center justify-between gap-3 text-sm">
                                    <span class="min-w-0 truncate font-medium text-slate-800 dark:text-slate-100">{{ $l->lotacao }}</span>
                                    <span class="shrink-0 text-xs text-slate-500">
                                        {{ $l->pessoas }} {{ $l->pessoas == 1 ? 'pessoa' : 'pessoas' }} ·
                                        <strong class="text-slate-900 dark:text-white">{{ number_format($l->paginas, 0, ',', '.') }} pág.</strong>
                                    </span>
                                </div>
                                <div class="mt-1.5 h-1.5 overflow-hidden rounded-full bg-slate-100 dark:bg-slate-800">
                                    <div class="h-full rounded-full bg-amber-500" style="width: {{ round($l->paginas / $maxLotacao * 100) }}%"></div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </section>
            @endif

            <section class="grid gap-5 xl:grid-cols-[1fr_1.3fr]">
                <div class="rounded-lg border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    <h2 class="text-lg font-bold text-slate-950 dark:text-white">Perfil dos trabalhos</h2>
                    <p class="mt-1 text-sm text-slate-500">
                        O log não guarda o nome do documento — o driver universal descarta o título.
                        O peso por página é o que resta para distinguir texto de material gráfico.
                    </p>
                    <div class="mt-5 space-y-4">
                        @foreach ($perfil as $faixa)
                            <div>
                                <div class="flex items-center justify-between gap-3 text-sm">
                                    <span class="font-medium text-slate-800 dark:text-slate-100">{{ $faixa->rotulo }}</span>
                                    <span class="text-xs text-slate-500">
                                        {{ number_format($faixa->paginas, 0, ',', '.') }} pág. ·
                                        <strong class="text-slate-900 dark:text-white">{{ number_format($faixa->percentual, 1, ',', '.') }}%</strong>
                                    </span>
                                </div>
                                <div class="mt-1.5 h-2 overflow-hidden rounded-full bg-slate-100 dark:bg-slate-800">
                                    <div class="h-full rounded-full" style="width: {{ $faixa->percentual }}%; background: {{ $faixa->cor }}"></div>
                                </div>
                                <p class="mt-1 text-xs text-slate-400">{{ number_format($faixa->trabalhos, 0, ',', '.') }} trabalhos</p>
                            </div>
                        @endforeach
                    </div>
                </div>

                <div class="rounded-lg border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    <div class="border-b border-slate-200 px-6 py-4 dark:border-slate-800">
                        <h2 class="text-lg font-bold text-slate-950 dark:text-white">Mais pesados por página</h2>
                        <p class="mt-1 text-sm text-slate-500">Uma página de 20 MB costuma ser foto ou digitalização em resolução alta demais.</p>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead class="bg-slate-50 text-left text-xs font-semibold uppercase text-slate-500 dark:bg-slate-800/60">
                                <tr>
                                    <th class="px-6 py-2">Usuário</th>
                                    <th class="px-6 py-2">Lotação</th>
                                    <th class="px-6 py-2">Impressora</th>
                                    <th class="px-6 py-2">Peso</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                                @foreach ($maisPesados as $t)
                                    <tr>
                                        <td class="px-6 py-2">{{ $t->usuario }}</td>
                                        <td class="px-6 py-2 text-xs text-slate-500">{{ $t->lotacao ?? '—' }}</td>
                                        <td class="px-6 py-2 text-xs">{{ $t->impressora }}</td>
                                        <td class="px-6 py-2 font-semibold">{{ number_format($t->kb_por_pagina / 1024, 1, ',', '.') }} MB<span class="font-normal text-xs text-slate-400">/pág.</span></td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </section>

            <section class="rounded-lg border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <div class="border-b border-slate-200 px-6 py-4 dark:border-slate-800">
                    <h2 class="text-lg font-bold text-slate-950 dark:text-white">Maiores trabalhos</h2>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[1000px] text-sm">
                        <thead class="bg-slate-50 text-left text-xs font-semibold uppercase text-slate-500 dark:bg-slate-800/60">
                            <tr>
                                <th class="px-6 py-2">Quando</th>
                                <th class="px-6 py-2">Usuário</th>
                                <th class="px-6 py-2">Cargo</th>
                                <th class="px-6 py-2">Lotação</th>
                                <th class="px-6 py-2">Estação</th>
                                <th class="px-6 py-2">Impressora</th>
                                <th class="px-6 py-2">Páginas</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                            @foreach ($maiores as $t)
                                <tr>
                                    <td class="px-6 py-2 text-xs">{{ $t->impresso_em->local()->format('d/m/Y H:i') }}</td>
                                    <td class="px-6 py-2">{{ $t->usuario }}</td>
                                    <td class="px-6 py-2 text-xs text-slate-500">{{ $t->cargo ?? '—' }}</td>
                                    <td class="px-6 py-2 text-xs text-slate-500">{{ $t->lotacao ?? '—' }}</td>
                                    <td class="px-6 py-2 text-xs text-slate-500">{{ $t->estacao ?: '—' }}</td>
                                    <td class="px-6 py-2 text-xs">{{ $t->impressora }}</td>
                                    <td class="px-6 py-2 font-bold">{{ number_format($t->paginas, 0, ',', '.') }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </section>
        @endif

        <section class="rounded-lg border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <h2 class="text-lg font-bold text-slate-950 dark:text-white">Servidores de impressão</h2>
            <p class="mt-1 text-sm text-slate-500">Total acumulado, independente do período escolhido acima.</p>
            <div class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                @foreach ($servidores as $s)
                    <div class="rounded-lg border p-4 {{ $s->trabalhos === 0 ? 'border-amber-300 bg-amber-50' : 'border-slate-200 dark:border-slate-700' }}">
                        <p class="text-sm font-bold text-slate-900 dark:text-white">{{ $s->servidor }}</p>
                        <p class="mt-1 text-2xl font-bold text-slate-950 dark:text-white">{{ number_format($s->trabalhos, 0, ',', '.') }}</p>
                        @if ($s->ultimo)
                            <p class="mt-1 text-xs text-slate-500">último em {{ $s->ultimo->local()->format('d/m/Y H:i') }}</p>
                        @else
                            <p class="mt-1 text-xs font-semibold text-amber-700">nunca enviou</p>
                        @endif
                        @unless ($s->esperado)
                            <p class="mt-1 text-[11px] text-slate-400">origem não listada no config</p>
                        @endunless
                    </div>
                @endforeach
            </div>
        </section>
    </div>
</x-app-layout>
