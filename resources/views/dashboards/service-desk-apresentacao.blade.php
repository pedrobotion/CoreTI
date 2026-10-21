<x-app-layout>
    @php
        $circ = 2 * M_PI * 54;
        $fmt = fn ($v, $d = 0) => number_format($v, $d, ',', '.');
        $tabs = [
            'atendidos' => 'Chamados Atendidos',
            'backlog' => 'Backlog',
            'internet' => 'Internet',
            'top5' => 'Top 5 Incidentes',
        ];
    @endphp

    <style>
        /* Seleção de texto no dashboard: azul suave, nunca a cor de destaque do SO. */
        #sd-content ::selection { background-color: rgba(56, 189, 248, 0.35); color: #ffffff; }
        #sd-content ::-moz-selection { background-color: rgba(56, 189, 248, 0.35); color: #ffffff; }
    </style>

    <div class="mx-auto max-w-[1400px]"
         x-data="{
            tab: 'atendidos',
            fs: false,
            showTab(t) {
                // Troca de aba robusta: além do x-show, força o display direto —
                // assim continua funcionando mesmo depois do reload() trocar o HTML
                // (quando o re-bind do Alpine às vezes falha e empilha as 4 abas).
                this.tab = t;
                document.querySelectorAll('#sd-content [data-sd-tab]').forEach(el => {
                    el.style.display = (el.getAttribute('data-sd-tab') === t) ? '' : 'none';
                });
            },
            toggleFs() {
                const el = this.$refs.deck;
                const req = el.requestFullscreen || el.webkitRequestFullscreen;
                const exit = document.exitFullscreen || document.webkitExitFullscreen;
                const emNativa = document.fullscreenElement || document.webkitFullscreenElement;
                if (!this.fs) {
                    // Tenta a tela cheia NATIVA; se o navegador não suportar em <div>
                    // (iOS/Safari), cai na tela cheia por CSS (fixed inset-0).
                    if (req) {
                        try {
                            const p = req.call(el);
                            if (p && p.catch) { p.catch(() => { this.fs = true; }); }
                        } catch (e) { this.fs = true; }
                    } else {
                        this.fs = true;
                    }
                } else {
                    if (emNativa && exit) { exit.call(document); }
                    else { this.fs = false; }
                }
            },
            reload(form) {
                // Troca os dados sem recarregar a página — assim NÃO sai da tela cheia.
                const base = '{{ route('dashboards.service-desk-apresentacao') }}';
                const params = new URLSearchParams(new FormData(form)).toString();
                const url = base + (params ? '?' + params : '');
                const cur = document.getElementById('sd-content');
                cur.style.opacity = '0.4';
                fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                    .then(r => r.text())
                    .then(html => {
                        const fresh = new DOMParser().parseFromString(html, 'text/html').getElementById('sd-content');
                        if (!fresh) { window.location = url; return; }
                        cur.innerHTML = fresh.innerHTML;
                        if (window.Alpine) window.Alpine.initTree(cur);
                        this.showTab(this.tab); // re-afirma a aba ativa no HTML recém-trocado
                        cur.style.opacity = '';
                        try { window.getSelection()?.removeAllRanges(); } catch (e) {}
                        history.replaceState(null, '', url);
                    })
                    .catch(() => { window.location = url; });
            },
            aplicarPeriodo(form) {
                // Só recarrega quando as DUAS datas estão escolhidas — assim não muda no meio da seleção.
                if (!form.inicio.value || !form.fim.value) return;
                this.reload(form);
            }
         }"
         x-init="document.addEventListener('fullscreenchange', () => fs = !!document.fullscreenElement); $nextTick(() => showTab(tab))">
        <a href="{{ route('home') }}" class="text-xs font-semibold uppercase tracking-wide text-slate-500 underline">← Dashboards</a>

        <div x-ref="deck"
             class="mt-2 overflow-hidden bg-[#070c17] text-slate-200 shadow-xl ring-1 ring-white/10"
             :class="fs ? 'fixed inset-0 z-[9999] overflow-y-auto sm:flex sm:flex-col sm:overflow-hidden' : 'rounded-2xl'"
             style="background-image: radial-gradient(1200px 500px at 85% -10%, rgba(37,99,235,.25), transparent 60%);">

          <div id="sd-content" class="flex min-h-0 flex-1 flex-col">
            {{-- Cabeçalho --}}
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-white/10 px-5 py-3">
                <h1 class="text-lg font-extrabold text-white sm:text-xl">Apresentação {{ $modo === 'mes' ? 'Mensal' : 'por Período' }} — Infraestrutura TI <span class="text-sky-300/80">· {{ $empresaLabel }}</span></h1>
                <div class="flex w-full flex-wrap items-center gap-2 lg:w-auto">
                    <form method="GET" id="sd-filtros" class="flex flex-wrap items-center gap-2">
                        {{-- Empresa é escolhida pelos botões acima das abas; aqui só
                             preserva o valor nos recarregamentos dos demais filtros. --}}
                        <input type="hidden" name="empresa" value="{{ $empresaSelecionada }}">
                        <span class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Time</span>
                        <select name="time" @change="reload($event.target.form)" style="color-scheme: dark;"
                                class="h-8 max-w-[190px] rounded-lg border-white/15 bg-white/10 text-sm font-semibold text-slate-100 focus:border-sky-400 focus:ring-sky-400">
                            <option value="" @selected($timeSelecionado === null) style="background-color:#0f172a;color:#e2e8f0;">Todos os times</option>
                            @foreach ($times as $t)
                                <option value="{{ $t }}" @selected($t === $timeSelecionado)
                                        style="background-color:#0f172a;color:#e2e8f0;">{{ $t }}</option>
                            @endforeach
                        </select>
                        {{-- Mês inteiro: ao escolher um mês, limpa Início/Fim para o período ser o mês todo. --}}
                        <span class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Mês</span>
                        <select name="mes" @change="$event.target.form.inicio.value=''; $event.target.form.fim.value=''; reload($event.target.form)" style="color-scheme: dark;"
                                class="h-8 max-w-[160px] rounded-lg border-white/15 bg-white/10 text-sm font-semibold text-slate-100 focus:border-sky-400 focus:ring-sky-400">
                            @foreach ($meses as $m)
                                <option value="{{ $m['valor'] }}" @selected($m['valor'] === $mesSelecionado)
                                        style="background-color:#0f172a;color:#e2e8f0;">{{ $m['rotulo'] }}</option>
                            @endforeach
                        </select>
                        {{-- Período livre: Início + Fim. Só aplica quando as duas datas estão escolhidas (botão). --}}
                        <span class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Período</span>
                        <input type="date" name="inicio" value="{{ $inicioSel }}"
                               max="{{ \Illuminate\Support\Carbon::now()->format('Y-m-d') }}" title="Data inicial"
                               style="color-scheme: dark;"
                               class="h-8 rounded-lg border-white/15 bg-white/10 px-2 text-sm font-semibold text-slate-100 focus:border-sky-400 focus:ring-sky-400">
                        <span class="text-slate-500">–</span>
                        <input type="date" name="fim" value="{{ $fimSel }}"
                               max="{{ \Illuminate\Support\Carbon::now()->format('Y-m-d') }}" title="Data final"
                               style="color-scheme: dark;"
                               class="h-8 rounded-lg border-white/15 bg-white/10 px-2 text-sm font-semibold text-slate-100 focus:border-sky-400 focus:ring-sky-400">
                        <button type="button" @click="$event.target.form.mes.value=''; aplicarPeriodo($event.target.form)"
                                class="h-8 rounded-lg border border-sky-400/40 bg-sky-500/15 px-3 text-sm font-semibold text-sky-200 transition hover:bg-sky-500/25">Aplicar</button>
                    </form>
                    <span class="hidden items-center gap-2 rounded-full border border-white/15 bg-white/5 px-3 py-1.5 text-sm sm:inline-flex">
                        <span class="font-semibold text-sky-400">Infraestrutura de TI</span>
                    </span>
                    <button type="button" @click="toggleFs()"
                            class="inline-flex h-8 w-8 items-center justify-center rounded-lg border border-white/15 bg-white/5 text-slate-300 transition hover:border-sky-400/40 hover:text-sky-300"
                            :title="fs ? 'Sair da tela cheia' : 'Tela cheia'">
                        <svg x-show="!fs" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 9V4h5M20 9V4h-5M4 15v5h5M20 15v5h-5"/></svg>
                        <svg x-show="fs" x-cloak class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 4v5H4M15 4v5h5M9 20v-5H4M15 20v-5h5"/></svg>
                    </button>
                </div>
            </div>

            {{-- Empresa (filtro de dados — recarrega o conteúdo) --}}
            <div class="flex flex-wrap items-center gap-1 border-b border-white/10 px-3 py-1.5">
                @foreach ($empresas as $valor => $label)
                    <button type="button"
                            @click="const f = document.getElementById('sd-filtros'); f.empresa.value = '{{ $valor }}'; reload(f)"
                            class="rounded-lg px-3 py-1.5 text-sm font-semibold transition {{ $valor === $empresaSelecionada ? 'bg-sky-500/15 text-sky-300 ring-1 ring-sky-400/40' : 'text-slate-400 hover:text-slate-200 hover:bg-white/5' }}">{{ $label }}</button>
                @endforeach
            </div>

            {{-- Abas --}}
            <div class="flex flex-wrap gap-1 border-b border-white/10 px-3 py-1.5">
                @foreach ($tabs as $k => $label)
                    <button type="button" @click="showTab('{{ $k }}')"
                            :class="tab === '{{ $k }}' ? 'bg-sky-500/15 text-sky-300 ring-1 ring-sky-400/40' : 'text-slate-400 hover:text-slate-200 hover:bg-white/5'"
                            class="rounded-lg px-3 py-1.5 text-sm font-semibold transition">{{ $label }}</button>
                @endforeach
            </div>

            <div class="flex min-h-0 flex-1 flex-col">

            {{-- =================== 1) CHAMADOS ATENDIDOS =================== --}}
            <div x-show="tab === 'atendidos'" data-sd-tab="atendidos" x-cloak class="flex min-h-0 flex-1 flex-col overflow-hidden p-4">
                <h2 class="text-lg font-extrabold text-white">Chamados Atendidos <span class="text-sm font-medium text-sky-300/80">· {{ $rotuloSemana }}</span></h2>
                <p class="mt-0.5 text-[11px] text-slate-500">Não contabiliza os catálogos de automação/robô: {{ collect($catalogosExcluidos ?? [])->map(fn($c) => '“'.$c.'”')->implode(' e ') }}.</p>

                <div class="mt-3 grid gap-2.5 grid-cols-2 sm:grid-cols-3 xl:grid-cols-7">
                    @include('dashboards.partials.sd-kpi', ['titulo'=>'Chamados Criados','valor'=>$fmt($atendidos['criados']),'nota'=>null])
                    @include('dashboards.partials.sd-kpi', ['titulo'=>'Chamados Atendidos','valor'=>$fmt($atendidos['atendidos']),'nota'=>null])
                    @include('dashboards.partials.sd-kpi', ['titulo'=>'Requisições','valor'=>$fmt($atendidos['requisicoes']),'nota'=>$fmt($atendidos['pctReq'],1).'% do total'])
                    @include('dashboards.partials.sd-kpi', ['titulo'=>'Incidentes','valor'=>$fmt($atendidos['incidentes']),'nota'=>$fmt($atendidos['pctInc'],1).'% do total','cor'=>'text-rose-400'])
                    @include('dashboards.partials.sd-kpi', ['titulo'=>'Performance','valor'=>$fmt($atendidos['perf'],1).'%','nota'=>null])
                    @include('dashboards.partials.sd-kpi', ['titulo'=>'Criados no ano','valor'=>$fmt($acumuladoAno['criados']),'nota'=>$acumuladoAno['periodo']])
                    @include('dashboards.partials.sd-kpi', ['titulo'=>'Atendidos no ano','valor'=>$fmt($acumuladoAno['atendidos']),'nota'=>$acumuladoAno['periodo']])
                </div>

                <div class="mt-3 grid gap-3 lg:grid-cols-2">
                    <div class="rounded-xl border border-white/10 bg-white/[0.03] p-4">
                        <div class="mb-3 flex items-center gap-3 text-xs font-bold uppercase tracking-wide text-slate-200"><span class="h-px w-6 bg-sky-400/60"></span>Composição dos Chamados</div>
                        @php $lReq = $circ * ($atendidos['pctReq']/100); $lInc = $circ * ($atendidos['pctInc']/100); @endphp
                        <div class="flex items-center gap-5">
                            <svg viewBox="0 0 120 120" class="h-32 w-32 shrink-0">
                                <circle cx="60" cy="60" r="54" fill="none" stroke="#13203a" stroke-width="13"/>
                                <circle cx="60" cy="60" r="54" fill="none" stroke="#38bdf8" stroke-width="13" stroke-dasharray="{{ $lReq }} {{ $circ - $lReq }}" transform="rotate(-90 60 60)"/>
                                <circle cx="60" cy="60" r="54" fill="none" stroke="#f43f5e" stroke-width="13" stroke-dasharray="{{ $lInc }} {{ $circ - $lInc }}" stroke-dashoffset="{{ -$lReq }}" transform="rotate(-90 60 60)"/>
                                <text x="60" y="57" text-anchor="middle" class="fill-white" style="font-size:22px;font-weight:800">{{ $fmt($atendidos['criados']) }}</text>
                                <text x="60" y="72" text-anchor="middle" class="fill-slate-400" style="font-size:9px;font-weight:700">chamados</text>
                            </svg>
                            <div class="space-y-2.5 text-sm">
                                <div class="flex items-center gap-2"><span class="h-3 w-3 rounded-full bg-sky-400"></span>Requisições <span class="font-bold text-white">{{ $fmt($atendidos['requisicoes']) }}</span> <span class="text-slate-400">({{ $fmt($atendidos['pctReq'],1) }}%)</span></div>
                                <div class="flex items-center gap-2"><span class="h-3 w-3 rounded-full bg-rose-500"></span>Incidentes <span class="font-bold text-white">{{ $fmt($atendidos['incidentes']) }}</span> <span class="text-slate-400">({{ $fmt($atendidos['pctInc'],1) }}%)</span></div>
                            </div>
                        </div>
                    </div>
                    <div class="rounded-xl border border-white/10 bg-white/[0.03] p-4">
                        <div class="mb-3 flex items-center gap-3 text-xs font-bold uppercase tracking-wide text-slate-200"><span class="h-px w-6 bg-sky-400/60"></span>Índice de Atendimento</div>
                        <div class="flex items-center justify-between text-sm"><span class="text-slate-300">Atendidos no período</span><span class="font-bold text-sky-300">{{ $fmt($atendidos['atendidos']) }} de {{ $fmt($atendidos['criados']) }}</span></div>
                        <div class="mt-2 h-3 w-full overflow-hidden rounded-full bg-white/10"><div class="h-full rounded-full bg-gradient-to-r from-blue-700 to-sky-400" style="width: {{ min(100,$atendidos['perf']) }}%"></div></div>
                        <div class="mt-3 flex items-center justify-between text-sm"><span class="text-slate-300">Atendidos no ano</span><span class="font-bold text-sky-300">{{ $fmt($acumuladoAno['atendidos']) }} de {{ $fmt($acumuladoAno['criados']) }}</span></div>
                        <div class="mt-2 h-3 w-full overflow-hidden rounded-full bg-white/10"><div class="h-full rounded-full bg-gradient-to-r from-blue-800 to-sky-500" style="width: {{ min(100,$acumuladoAno['perf']) }}%"></div></div>
                        <div class="mt-4 grid grid-cols-2 gap-3 text-center">
                            <div>
                                <div class="text-3xl font-extrabold text-sky-400 sm:text-4xl">{{ $fmt($atendidos['perf'],1) }}%</div>
                                <div class="mt-0.5 text-xs text-slate-400">no período</div>
                            </div>
                            <div class="border-l border-white/10">
                                <div class="text-3xl font-extrabold text-sky-300 sm:text-4xl">{{ $fmt($acumuladoAno['perf'],1) }}%</div>
                                <div class="mt-0.5 text-xs text-slate-400">no ano ({{ $acumuladoAno['ano'] }})</div>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Comparativo dos últimos 3 meses --}}
                <div class="mt-3 flex min-h-[160px] flex-1 flex-col overflow-hidden rounded-xl border border-white/10 bg-white/[0.03] p-4">
                    <div class="mb-2 flex items-center justify-between">
                        <div class="flex items-center gap-3 text-xs font-bold uppercase tracking-wide text-slate-200"><span class="h-px w-6 bg-sky-400/60"></span>Incidentes — Criados × Resolvidos (3 meses)</div>
                        <div class="flex gap-4 text-[11px] text-slate-300">
                            <span class="flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-full" style="background-color:#f43f5e"></span>Criados</span>
                            <span class="flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-full" style="background-color:#38bdf8"></span>Resolvidos</span>
                        </div>
                    </div>
                    <div class="flex min-h-0 w-full flex-1">
                        {{-- Desktop/tablet: gráfico largo (em tela cheia preenche a altura) --}}
                        <div class="hidden min-h-0 w-full items-center justify-center sm:flex">
                            @include('dashboards.partials.sd-chart-3m', ['comparativoMensal' => $comparativoMensal, 'fmt' => $fmt, 'W' => 1000, 'H' => 200, 'padL' => 56, 'padR' => 66, 'padT' => 18, 'padB' => 40, 'fsFill' => true, 'maxH' => 'max-h-[40vh]'])
                        </div>
                        {{-- Celular: gráfico mais alto e legível --}}
                        <div class="w-full self-center sm:hidden">
                            @include('dashboards.partials.sd-chart-3m', ['comparativoMensal' => $comparativoMensal, 'fmt' => $fmt, 'W' => 300, 'H' => 200, 'padL' => 38, 'padR' => 34, 'padT' => 16, 'padB' => 42, 'fsFill' => false, 'maxH' => 'max-h-[46vh]'])
                        </div>
                    </div>
                </div>

                @include('dashboards.partials.sd-rodape', ['texto' => 'No período de '.$rotuloSemana.', o índice de atendimento foi de '.$fmt($atendidos['perf'],1).'%, com '.$fmt($atendidos['atendidos']).' atendidos de '.$fmt($atendidos['criados']).' criados — '.$fmt($atendidos['requisicoes']).' requisições e '.$fmt($atendidos['incidentes']).' incidentes.'])
            </div>

            {{-- =================== 2) BACKLOG =================== --}}
            <div x-show="tab === 'backlog'" data-sd-tab="backlog" x-cloak class="flex min-h-0 flex-1 flex-col overflow-hidden p-4">
                <h2 class="text-lg font-extrabold text-white">Backlog de Atendimentos <span class="text-sm font-medium text-sky-300/80">· {{ $rotuloSemana }}</span></h2>

                @php $varPos = $backlog['variacao'] > 0; $corVar = $varPos ? 'text-rose-400' : 'text-emerald-400'; @endphp
                <div class="mt-3 grid gap-2.5 grid-cols-2 sm:grid-cols-3 xl:grid-cols-5">
                    @include('dashboards.partials.sd-kpi', ['titulo'=>'Backlog início','valor'=>$fmt($backlog['backlogInicial']),'nota'=>'início do período'])
                    @include('dashboards.partials.sd-kpi', ['titulo'=>'Criados no período','valor'=>$fmt($backlog['criados']),'nota'=>null])
                    @include('dashboards.partials.sd-kpi', ['titulo'=>'Resolvidos no período','valor'=>$fmt($backlog['resolvidos']),'nota'=>null])
                    @include('dashboards.partials.sd-kpi', ['titulo'=>'Backlog fim','valor'=>$fmt($backlog['backlogFinal']),'nota'=>'fim do período'])
                    @include('dashboards.partials.sd-kpi', ['titulo'=>'Variação','valor'=>($varPos?'+':'').$fmt($backlog['variacao']),'nota'=>$fmt($backlog['variacaoPct'],1).'%','cor'=>$corVar])
                </div>

                <div class="mt-3 flex min-h-[220px] flex-1 flex-col gap-3 lg:flex-row">
                    <div class="flex flex-col rounded-xl border border-white/10 bg-white/[0.03] p-4 lg:flex-[2]">
                        <div class="mb-3 flex items-center gap-3 text-xs font-bold uppercase tracking-wide text-slate-200"><span class="h-px w-6 bg-sky-400/60"></span>Evolução do Backlog ({{ ['diaria' => 'dia a dia', 'semanal' => 'semanal', 'mensal' => 'mensal'][$backlog['granularidade'] ?? ''] ?? 'período' }})</div>
                        @php $maxBL = max(1, collect($backlog['serie'])->max('valor')); @endphp
                        <div class="flex min-h-0 flex-1 items-stretch justify-between gap-2">
                            @foreach ($backlog['serie'] as $b)
                                @php $ult = $loop->last; @endphp
                                <div class="group flex min-h-0 flex-1 flex-col items-center gap-1.5">
                                    <span class="text-xs font-bold {{ $ult ? 'text-emerald-400' : 'text-slate-200' }}">{{ $b['valor'] }}</span>
                                    <div class="flex w-full min-h-0 flex-1 items-end justify-center">
                                        <div class="w-full max-w-[44px] rounded-t-md transition group-hover:brightness-125 {{ $ult ? 'bg-gradient-to-t from-emerald-700 to-emerald-400' : 'bg-gradient-to-t from-blue-800 to-sky-500' }}"
                                             style="height: {{ max(3, round($b['valor']/$maxBL*100)) }}%"></div>
                                    </div>
                                    <span class="text-[11px] {{ $ult ? 'font-bold text-emerald-400' : 'text-slate-400' }}">{{ $b['rotulo'] }}</span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                    <div class="flex flex-col rounded-xl border border-white/10 bg-white/[0.03] p-4 lg:flex-1">
                        <div class="mb-2 flex items-center gap-3 text-xs font-bold uppercase tracking-wide text-slate-200"><span class="h-px w-6 bg-sky-400/60"></span>Resumo do período</div>
                        <dl class="divide-y divide-white/10 text-sm">
                            <div class="flex items-center justify-between py-2"><dt class="text-slate-300">Backlog início</dt><dd class="font-bold text-sky-300">{{ $fmt($backlog['backlogInicial']) }}</dd></div>
                            <div class="flex items-center justify-between py-2"><dt class="text-slate-300">Criados</dt><dd class="font-bold text-sky-300">{{ $fmt($backlog['criados']) }}</dd></div>
                            <div class="flex items-center justify-between py-2"><dt class="text-slate-300">Resolvidos</dt><dd class="font-bold text-sky-300">{{ $fmt($backlog['resolvidos']) }}</dd></div>
                            <div class="flex items-center justify-between py-2"><dt class="text-slate-300">Backlog fim</dt><dd class="font-bold text-sky-300">{{ $fmt($backlog['backlogFinal']) }}</dd></div>
                        </dl>
                        <div class="mt-auto rounded-lg border {{ $varPos ? 'border-rose-500/30 bg-rose-500/5' : 'border-emerald-500/30 bg-emerald-500/5' }} p-3 text-center">
                            <div class="text-xl font-extrabold {{ $corVar }}">{{ ($varPos?'+':'').$fmt($backlog['variacao']) }} chamados</div>
                            <div class="text-[11px] text-slate-400">variação de {{ $fmt($backlog['variacaoPct'],1) }}% no período</div>
                        </div>
                    </div>
                </div>

                @include('dashboards.partials.sd-rodape', ['texto' => 'O período encerrou com backlog de '.$fmt($backlog['backlogFinal']).' chamados ('.($varPos?'+':'').$fmt($backlog['variacao']).', '.$fmt($backlog['variacaoPct'],1).'%), com '.$fmt($backlog['criados']).' criados e '.$fmt($backlog['resolvidos']).' resolvidos.'])
            </div>

            {{-- =================== 3) INTERNET =================== --}}
            <div x-show="tab === 'internet'" data-sd-tab="internet" x-cloak class="flex min-h-0 flex-1 flex-col overflow-hidden p-4">
                <h2 class="text-lg font-extrabold text-white">Incidentes de Indisponibilidade de Internet <span class="text-sm font-medium text-sky-300/80">· {{ $rotuloSemana }}</span></h2>

                <div class="mt-3 grid gap-2.5 grid-cols-2 xl:grid-cols-4">
                    @include('dashboards.partials.sd-kpi', ['titulo'=>'Total de Incidentes','valor'=>$fmt($internet['total']),'nota'=>null,'cor'=>'text-rose-400'])
                    @include('dashboards.partials.sd-kpi', ['titulo'=>'Unidade Mais Afetada','valor'=>$internet['unidadeMaisAfetada'],'nota'=>$internet['ocorrenciasTop'].' ocorrências','small'=>true])
                    @include('dashboards.partials.sd-kpi', ['titulo'=>'% do Total','valor'=>$fmt($internet['pctTop'],1).'%','nota'=>$internet['unidadeMaisAfetada']])
                    @include('dashboards.partials.sd-kpi', ['titulo'=>'Unidades Distintas','valor'=>$fmt($internet['unidadesDistintas']),'nota'=>'afetadas'])
                </div>

                <div class="mt-3 flex min-h-[220px] flex-1 flex-col gap-3 lg:flex-row">
                    <div class="flex flex-col rounded-xl border border-white/10 bg-white/[0.03] p-4 lg:flex-1">
                        <div class="mb-3 flex items-center gap-3 text-xs font-bold uppercase tracking-wide text-slate-200"><span class="h-px w-6 bg-sky-400/60"></span>Top Unidades com Mais Incidentes</div>
                        @php $maxU = max(1, $internet['porUnidade']->max('n') ?? 1); @endphp
                        <div class="flex min-h-0 flex-1 flex-col gap-2">
                            @forelse ($internet['porUnidade'] as $u)
                                <div class="flex items-center gap-3 text-sm">
                                    <span class="w-36 shrink-0 truncate text-slate-200">{{ $loop->iteration <= 3 ? ['🥇','🥈','🥉'][$loop->index].' ' : '' }}{{ $u->u }}</span>
                                    <div class="h-4 flex-1 overflow-hidden rounded bg-white/5"><div class="h-full rounded bg-gradient-to-r from-rose-800 to-rose-500" style="width: {{ max(6, round($u->n/$maxU*100)) }}%"></div></div>
                                    <span class="w-10 text-right font-bold text-white">{{ $u->n }}</span>
                                </div>
                            @empty
                                <p class="text-sm text-slate-400">Sem incidentes de internet neste período.</p>
                            @endforelse
                        </div>
                    </div>
                    <div class="flex flex-col rounded-xl border border-white/10 bg-white/[0.03] p-4 lg:flex-1">
                        <div class="mb-3 flex items-center gap-3 text-xs font-bold uppercase tracking-wide text-slate-200"><span class="h-px w-6 bg-sky-400/60"></span>Recorrência em {{ $internet['unidadeMaisAfetada'] }}</div>
                        <div class="min-h-0 flex-1 space-y-1.5 overflow-auto pr-1">
                            @forelse ($internet['recorrencia'] as $r)
                                <div class="flex items-center gap-4 rounded-lg border border-white/10 bg-white/[0.02] px-3 py-2 text-sm">
                                    <span class="font-semibold text-sky-300">{{ \Illuminate\Support\Carbon::parse($r->data_hora_criacao)->format('d/m') }}</span>
                                    <span class="font-mono text-slate-200">{{ $r->chave }}</span>
                                </div>
                            @empty
                                <p class="text-sm text-slate-400">Sem recorrência no período.</p>
                            @endforelse
                        </div>
                    </div>
                </div>

                <div class="mt-3 rounded-lg border border-amber-500/20 bg-amber-500/5 px-4 py-2 text-[11px] text-amber-200/80">
                    A unidade é a cadastrada no chamado (solicitante); parte dos incidentes fica como “Sede”, então a distribuição pode diferir da classificação manual do deck.
                </div>
            </div>

            {{-- =================== 4) TOP 5 =================== --}}
            <div x-show="tab === 'top5'" data-sd-tab="top5" x-cloak class="flex min-h-0 flex-1 flex-col overflow-hidden p-4">
                <h2 class="text-lg font-extrabold text-white">Top 5 Incidentes (Catálogos) <span class="text-sm font-medium text-sky-300/80">· {{ $rotuloSemana }}</span></h2>
                <p class="mt-0.5 text-[11px] text-slate-500">Não contabiliza os catálogos de automação/robô: {{ collect($catalogosExcluidos ?? [])->map(fn($c) => '“'.$c.'”')->implode(' e ') }}.</p>

                <div class="mt-3 grid gap-2.5 grid-cols-2 sm:grid-cols-3 xl:grid-cols-5">
                    @include('dashboards.partials.sd-kpi', ['titulo'=>'Total de Incidentes','valor'=>$fmt($top5['total']),'nota'=>null,'cor'=>'text-rose-400'])
                    @include('dashboards.partials.sd-kpi', ['titulo'=>'Catálogo Líder','valor'=>$top5['lider']['catalogo'] ?? '—','nota'=>($top5['lider']['atual'] ?? 0).' casos ('.$fmt($top5['pctLider'],1).'%)','small'=>true])
                    @include('dashboards.partials.sd-kpi', ['titulo'=>'Concentração Top 5','valor'=>$fmt($top5['concentracao'],1).'%','nota'=>$top5['concentracaoAbs'].' de '.$top5['total']])
                    @include('dashboards.partials.sd-kpi', ['titulo'=>'Variação vs sem. ant.','valor'=>($top5['variacaoTotal']>=0?'+':'').$fmt($top5['variacaoTotal']),'nota'=>$fmt($top5['variacaoTotalPct'],1).'%','cor'=>$top5['variacaoTotal']>=0?'text-amber-400':'text-emerald-400'])
                    @include('dashboards.partials.sd-kpi', ['titulo'=>'Maior Alta','valor'=>$top5['maiorAlta']['catalogo'] ?? '—','nota'=>'+'.($top5['maiorAlta']['variacao'] ?? 0).'%','small'=>true])
                </div>

                <div class="mt-3 flex min-h-[220px] flex-1 flex-col gap-3 lg:flex-row">
                    <div class="flex flex-col rounded-xl border border-white/10 bg-white/[0.03] p-4 lg:flex-[2]">
                        <div class="mb-3 flex items-center justify-between">
                            <div class="flex items-center gap-3 text-xs font-bold uppercase tracking-wide text-slate-200"><span class="h-px w-6 bg-sky-400/60"></span>Comparativo (3 {{ $modo === 'mes' ? 'meses' : 'períodos' }})</div>
                            <div class="flex gap-3 text-[11px] text-slate-400">
                                <span class="flex items-center gap-1"><span class="h-2 w-3 rounded-sm bg-rose-900"></span>{{ $top5['rotuloS2'] }}</span>
                                <span class="flex items-center gap-1"><span class="h-2 w-3 rounded-sm bg-rose-600"></span>{{ $top5['rotuloS1'] }}</span>
                                <span class="flex items-center gap-1"><span class="h-2 w-3 rounded-sm bg-rose-400"></span>{{ $top5['rotuloAtual'] }}</span>
                            </div>
                        </div>
                        @php $maxT = max(1, collect($top5['linhas'])->flatMap(fn($l)=>[$l['s2'],$l['s1'],$l['atual']])->max() ?: 1); @endphp
                        <div class="flex min-h-0 flex-1 flex-col gap-2">
                            @forelse ($top5['linhas'] as $l)
                                <div>
                                    <div class="mb-0.5 text-sm font-semibold text-slate-200">{{ $l['catalogo'] }}</div>
                                    @foreach (['s2'=>'bg-rose-900','s1'=>'bg-rose-600','atual'=>'bg-rose-400'] as $per=>$barCor)
                                        <div class="mb-0.5 flex items-center gap-2">
                                            <div class="h-2.5 flex-1 overflow-hidden rounded bg-white/5"><div class="h-full rounded {{ $barCor }}" style="width: {{ max(2, round($l[$per]/$maxT*100)) }}%"></div></div>
                                            <span class="w-7 text-right text-[11px] font-bold {{ $per==='atual'?'text-rose-300':'text-slate-400' }}">{{ $l[$per] }}</span>
                                        </div>
                                    @endforeach
                                </div>
                            @empty
                                <p class="text-sm text-slate-400">Sem incidentes neste período.</p>
                            @endforelse
                        </div>
                    </div>
                    <div class="flex flex-col rounded-xl border border-white/10 bg-white/[0.03] p-4 lg:flex-1">
                        <div class="mb-3 flex items-center gap-3 text-xs font-bold uppercase tracking-wide text-slate-200"><span class="h-px w-6 bg-sky-400/60"></span>Variação (per. ant. → atual)</div>
                        <div class="flex min-h-0 flex-1 flex-col gap-1.5">
                            @foreach ($top5['linhas'] as $l)
                                @php $alta = $l['variacao'] >= 0; @endphp
                                <div class="flex items-center justify-between rounded-lg border border-white/10 bg-white/[0.02] px-3 py-2 text-sm">
                                    <span class="truncate pr-2 text-slate-200">{{ $l['catalogo'] }}</span>
                                    <span class="shrink-0 font-bold {{ $alta ? 'text-amber-400' : 'text-emerald-400' }}">{{ $alta ? '▲' : '▼' }} {{ ($alta?'+':'').$l['variacao'] }}%</span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>

                @include('dashboards.partials.sd-rodape', ['texto' => 'No período foram '.$fmt($top5['total']).' incidentes (sem catálogos de automação). O Top 5 concentra '.$fmt($top5['concentracao'],1).'%, liderado por “'.($top5['lider']['catalogo'] ?? '—').'” ('.($top5['lider']['atual'] ?? 0).' casos).'])
            </div>


            </div>{{-- /flex-1 --}}
          </div>{{-- /#sd-content --}}
        </div>{{-- /deck --}}
    </div>
</x-app-layout>
