<x-app-layout>
    @php
        // Fila de tratamento: junta as pendências das etapas numa lista só.
        $cor = [
            'Entrada Fiscal' => 'blue', 'Terceiros' => 'emerald', 'Peças' => 'violet',
            'Estoque Interno' => 'amber', 'Nota de Saída' => 'rose',
        ];
        $rota = [
            'Entrada Fiscal' => 'administrativo.entrada-fiscal', 'Terceiros' => 'administrativo.terceiros',
            'Peças' => 'administrativo.pecas', 'Estoque Interno' => 'administrativo.estoque-interno',
            'Nota de Saída' => 'administrativo.nota-saida',
        ];
        $linha = fn ($e, $etapa) => ['etapa' => $etapa, 'eq' => $e];
        $fila = collect()
            ->concat(($pendingEntry ?? collect())->map(fn ($e) => $linha($e, 'Entrada Fiscal')))
            ->concat(($pendingThirdParty ?? collect())->map(fn ($e) => $linha($e, 'Terceiros')))
            ->concat(($pendingParts ?? collect())->map(fn ($e) => $linha($e, 'Peças')))
            ->concat(($internalStockReplenishments ?? collect())->map(fn ($e) => $linha($e, 'Estoque Interno')))
            ->concat(($pendingOutboundNote ?? collect())->map(fn ($e) => $linha($e, 'Nota de Saída')))
            ->sortByDesc(fn ($r) => optional($r['eq']->updated_at)->timestamp ?? 0)
            ->take(8)->values();
        $s = $adminSummary ?? [];

        $tiles = [
            ['Entrada Fiscal', $s['entrada_fiscal'] ?? 0, 'aguardando', 'blue', 'administrativo.entrada-fiscal',
                '<path d="M14 3H6a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9z"/><path d="M14 3v6h6M8 13h8M8 17h5"/>'],
            ['Terceiros', $s['terceiros'] ?? 0, 'pendências', 'emerald', 'administrativo.terceiros',
                '<circle cx="9" cy="8" r="3"/><path d="M3 20a6 6 0 0 1 12 0M16 6.5a3 3 0 0 1 0 5M18 20a6 6 0 0 0-3-5.2"/>'],
            ['Peças', $s['pecas'] ?? 0, 'registros', 'violet', 'administrativo.pecas',
                '<path d="M12 3l8 4.5v9L12 21l-8-4.5v-9z" stroke-linejoin="round"/><path d="M4 7.5l8 4.5 8-4.5M12 12v9"/>'],
            ['Estoque Interno', $s['estoque_interno'] ?? 0, 'itens', 'amber', 'administrativo.estoque-interno',
                '<path d="M3 7l9-4 9 4v10l-9 4-9-4z" stroke-linejoin="round"/><path d="M3 7l9 4 9-4M12 11v10"/>'],
            ['Nota de Saída', $s['nota_saida'] ?? 0, 'aguardando', 'rose', 'administrativo.nota-saida',
                '<path d="M10 3H6a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-8"/><path d="M14 3v6h6M21 3l-7 7"/>'],
            ['Empresas Terceirizadas', $s['empresas_ativas'] ?? 0, 'cadastradas', 'teal', 'administrativo.empresas-terceirizadas',
                '<path d="M3 21h18M5 21V7l6-4v18M19 21V11l-8-4M8 10h.01M8 14h.01"/>'],
        ];
        // Tailwind precisa ver as classes literais p/ compilar:
        $tint = [
            'blue' => ['bg-blue-50','border-blue-100','text-blue-600'],
            'emerald' => ['bg-emerald-50','border-emerald-100','text-emerald-600'],
            'violet' => ['bg-violet-50','border-violet-100','text-violet-600'],
            'amber' => ['bg-amber-50','border-amber-100','text-amber-600'],
            'rose' => ['bg-rose-50','border-rose-100','text-rose-600'],
            'teal' => ['bg-teal-50','border-teal-100','text-teal-600'],
        ];
        $badge = [
            'blue' => 'bg-blue-50 text-blue-700 ring-blue-200',
            'emerald' => 'bg-emerald-50 text-emerald-700 ring-emerald-200',
            'violet' => 'bg-violet-50 text-violet-700 ring-violet-200',
            'amber' => 'bg-amber-50 text-amber-700 ring-amber-200',
            'rose' => 'bg-rose-50 text-rose-700 ring-rose-200',
        ];
    @endphp

    <div class="mx-auto max-w-7xl space-y-6">
        {{-- Cabeçalho --}}
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">CoreTI · Administrativo</p>
                <h1 class="mt-1 text-2xl font-bold tracking-tight text-slate-900 sm:text-3xl">Painel Administrativo</h1>
                <p class="mt-1 max-w-2xl text-sm text-slate-500">Controle dos equipamentos recebidos para manutenção e registros fiscais relacionados.</p>
            </div>
            <a href="{{ route('administrativo.historico') }}" class="inline-flex h-10 items-center gap-2 rounded-xl border border-slate-200 bg-white px-4 text-sm font-semibold text-slate-700 shadow-sm transition hover:border-slate-300">
                <svg viewBox="0 0 24 24" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 8v4l3 2M21 12a9 9 0 1 1-9-9" stroke-linecap="round"/></svg>
                Histórico
            </a>
        </div>

        {{-- Busca + ações --}}
        <form method="GET" action="{{ route('administrativo.visao-geral') }}" class="flex flex-wrap gap-3">
            <label class="flex h-12 flex-1 basis-72 items-center gap-3 rounded-2xl border border-slate-200 bg-white px-4 shadow-sm focus-within:border-[#033151] focus-within:ring-2 focus-within:ring-[#033151]/15">
                <svg viewBox="0 0 24 24" class="h-5 w-5 text-slate-400" fill="none" stroke="currentColor" stroke-width="1.9"><circle cx="11" cy="11" r="7"/><path d="M21 21l-4-4" stroke-linecap="round"/></svg>
                <input type="text" name="equipment_plaqueta" value="{{ request('equipment_plaqueta', '') }}" placeholder="Buscar equipamento pela plaqueta…" class="min-w-0 flex-1 border-0 bg-transparent p-0 text-sm text-slate-800 placeholder:text-slate-400 focus:ring-0">
            </label>
            <button type="submit" class="inline-flex h-12 items-center gap-2 rounded-2xl border border-[#033151] bg-[#033151] px-5 text-sm font-semibold text-white shadow-sm transition hover:opacity-90">
                <svg viewBox="0 0 24 24" class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"/><path d="M21 21l-4-4" stroke-linecap="round"/></svg>
                Consultar
            </button>
            <a href="{{ route('administrativo.estoque-interno') }}" class="inline-flex h-12 items-center gap-2 rounded-2xl border border-slate-200 bg-white px-5 text-sm font-semibold text-slate-700 shadow-sm transition hover:border-slate-300">
                <svg viewBox="0 0 24 24" class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 3l8 4.5v9L12 21l-8-4.5v-9z" stroke-linejoin="round"/><path d="M4 7.5l8 4.5 8-4.5M12 12v9"/></svg>
                Estoque
            </a>
        </form>

        {{-- Pendências por etapa --}}
        <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div class="flex items-start gap-3">
                    <span class="grid h-9 w-9 shrink-0 place-items-center rounded-lg bg-blue-50 text-blue-600">
                        <svg viewBox="0 0 24 24" class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.9"><path d="M5 20V10M12 20V4M19 20v-7"/></svg>
                    </span>
                    <div>
                        <h2 class="text-base font-bold text-slate-900">Pendências por etapa</h2>
                        <p class="mt-0.5 text-sm text-slate-500">Visão geral dos registros que aguardam tratamento na área fiscal.</p>
                    </div>
                </div>
                <span class="text-xs text-slate-400">Atualizado agora · {{ now()->format('d/m/Y H:i') }}</span>
            </div>

            <div class="mt-5 grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-3">
                @foreach ($tiles as [$nome, $num, $sub, $c, $r, $icon])
                    <a href="{{ route($r) }}" class="group flex items-center gap-4 rounded-2xl border {{ $tint[$c][1] }} {{ $tint[$c][0] }} p-4 transition hover:-translate-y-0.5 hover:shadow-md">
                        <span class="grid h-12 w-12 shrink-0 place-items-center rounded-xl bg-white shadow-sm {{ $tint[$c][2] }}">
                            <svg viewBox="0 0 24 24" class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="1.7">{!! $icon !!}</svg>
                        </span>
                        <span class="min-w-0 flex-1">
                            <span class="block text-sm font-semibold text-slate-700">{{ $nome }}</span>
                            <span class="block text-3xl font-extrabold leading-tight {{ $tint[$c][2] }} tabular-nums">{{ $num }}</span>
                            <span class="block text-xs text-slate-500">{{ $sub }}</span>
                        </span>
                        <span class="grid h-7 w-7 shrink-0 place-items-center self-start rounded-full bg-white shadow-sm {{ $tint[$c][2] }} transition group-hover:translate-x-0.5">
                            <svg viewBox="0 0 24 24" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 6l6 6-6 6" stroke-linecap="round" stroke-linejoin="round"/></svg>
                        </span>
                    </a>
                @endforeach
            </div>
        </section>

        {{-- Fila + atalhos --}}
        <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
            <section class="rounded-2xl border border-slate-200 bg-white shadow-sm lg:col-span-2">
                <div class="flex items-center justify-between gap-3 border-b border-slate-100 p-5 sm:px-6">
                    <div class="flex items-center gap-3">
                        <span class="grid h-9 w-9 place-items-center rounded-lg bg-slate-100 text-slate-500">
                            <svg viewBox="0 0 24 24" class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.9"><path d="M8 6h13M8 12h13M8 18h13M3 6h.01M3 12h.01M3 18h.01" stroke-linecap="round"/></svg>
                        </span>
                        <div>
                            <h2 class="text-base font-bold text-slate-900">Fila de tratamento</h2>
                            <p class="mt-0.5 text-sm text-slate-500">Registros mais recentes aguardando ação nas etapas fiscais.</p>
                        </div>
                    </div>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[640px] text-sm">
                        <thead>
                            <tr class="border-b border-slate-100 text-left text-xs font-semibold uppercase tracking-wide text-slate-400">
                                <th class="px-5 py-3 sm:px-6">Etapa</th>
                                <th class="px-5 py-3">Equipamento</th>
                                <th class="px-5 py-3">Unidade/Setor</th>
                                <th class="px-5 py-3">Atualizado</th>
                                <th class="px-5 py-3">Situação</th>
                                <th class="px-5 py-3"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @forelse ($fila as $f)
                                @php
                                    $e = $f['eq']; $c = $cor[$f['etapa']] ?? 'blue';
                                    $dias = $e->updated_at ? (int) floor($e->updated_at->diffInDays(now())) : 0;
                                    [$pLabel, $pCls] = $dias >= 10 ? ['Atrasado', 'rose'] : ($dias >= 5 ? ['Atenção', 'amber'] : ['No prazo', 'emerald']);
                                @endphp
                                <tr class="transition hover:bg-slate-50">
                                    <td class="px-5 py-3 sm:px-6">
                                        <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-semibold ring-1 ring-inset {{ $badge[$c] }}">
                                            <span class="h-1.5 w-1.5 rounded-full bg-current"></span>{{ $f['etapa'] }}
                                        </span>
                                    </td>
                                    <td class="px-5 py-3">
                                        <div class="font-semibold text-slate-800">{{ $e->tipo_equipamento ?: 'Equipamento' }}</div>
                                        <div class="font-mono text-xs text-slate-400">{{ $e->plaqueta ?: '—' }}{{ $e->peca_nome ? ' · '.$e->peca_nome : '' }}</div>
                                    </td>
                                    <td class="px-5 py-3 text-slate-600">{{ $e->unidade_setor ?: '—' }}</td>
                                    <td class="px-5 py-3 text-slate-500">{{ optional($e->updated_at)->format('d/m/Y H:i') ?: '—' }}</td>
                                    <td class="px-5 py-3">
                                        <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-semibold ring-1 ring-inset {{ $badge[$pCls] }}">
                                            <span class="h-1.5 w-1.5 rounded-full bg-current"></span>{{ $pLabel }}
                                        </span>
                                    </td>
                                    <td class="px-5 py-3 text-right">
                                        <a href="{{ route($rota[$f['etapa']] ?? 'administrativo') }}" class="text-slate-300 transition hover:text-[#033151]">
                                            <svg viewBox="0 0 24 24" class="ml-auto h-4 w-4" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 6l6 6-6 6" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                        </a>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="6" class="px-6 py-10 text-center text-sm text-slate-400">Nenhuma pendência nas etapas fiscais. 🎉</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>

            {{-- Atalhos rápidos --}}
            <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
                <div class="flex items-center gap-3">
                    <span class="grid h-9 w-9 place-items-center rounded-lg bg-amber-50 text-amber-600">
                        <svg viewBox="0 0 24 24" class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M13 3L4 14h7l-1 7 9-11h-7z" stroke-linejoin="round"/></svg>
                    </span>
                    <div>
                        <h2 class="text-base font-bold text-slate-900">Atalhos rápidos</h2>
                        <p class="mt-0.5 text-sm text-slate-500">Ações mais utilizadas.</p>
                    </div>
                </div>
                <div class="mt-4 flex flex-col gap-3">
                    @php
                        $atalhos = [
                            ['Dar entrada em NF', 'administrativo.entrada-fiscal', 'blue', '<path d="M14 3H6a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9z"/><path d="M14 3v6h6M12 12v5M9.5 14.5h5"/>'],
                            ['Consultar equipamento', 'administrativo.visao-geral', 'emerald', '<circle cx="11" cy="11" r="7"/><path d="M21 21l-4-4" stroke-linecap="round"/>'],
                            ['Cadastrar peça', 'administrativo.estoque-interno', 'violet', '<path d="M12 3l8 4.5v9L12 21l-8-4.5v-9z" stroke-linejoin="round"/><path d="M4 7.5l8 4.5 8-4.5M12 12v9"/>'],
                            ['Emitir nota de saída', 'administrativo.nota-saida', 'amber', '<path d="M10 3H6a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-8"/><path d="M14 3v6h6M21 3l-7 7"/>'],
                        ];
                    @endphp
                    @foreach ($atalhos as [$nome, $r, $c, $icon])
                        <a href="{{ route($r) }}" class="group flex items-center gap-3 rounded-xl border {{ $tint[$c][1] }} {{ $tint[$c][0] }} px-4 py-3 text-sm font-semibold text-slate-700 transition hover:translate-x-0.5 hover:shadow-sm">
                            <span class="grid h-9 w-9 shrink-0 place-items-center rounded-lg bg-white shadow-sm {{ $tint[$c][2] }}">
                                <svg viewBox="0 0 24 24" class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.7">{!! $icon !!}</svg>
                            </span>
                            {{ $nome }}
                            <svg viewBox="0 0 24 24" class="ml-auto h-4 w-4 text-slate-300 transition group-hover:text-slate-500" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 6l6 6-6 6" stroke-linecap="round" stroke-linejoin="round"/></svg>
                        </a>
                    @endforeach
                </div>
            </section>
        </div>
    </div>
</x-app-layout>
