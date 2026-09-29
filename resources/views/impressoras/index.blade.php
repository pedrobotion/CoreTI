<x-app-layout>
    <div class="mx-auto max-w-7xl space-y-6">
        <div class="flex flex-wrap items-end justify-between gap-3">
            <div>
                <p class="text-sm font-semibold uppercase tracking-wide text-slate-500">Operação</p>
                <h1 class="mt-1 text-2xl font-bold text-slate-950 dark:text-white">Impressoras</h1>
                <p class="mt-1 text-sm text-slate-500">Parque monitorado pelo portal da Simpress. Consulta apenas — o cadastro é de lá.</p>
            </div>
        </div>

        <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <div class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <p class="text-xs uppercase text-slate-500">Impressoras</p>
                <p class="mt-2 text-2xl font-bold text-slate-950 dark:text-white">{{ number_format($resumo['total'], 0, ',', '.') }}</p>
            </div>
            <a href="{{ route('impressoras.index', ['situacao' => 'suprimento_critico']) }}" class="rounded-lg border border-rose-200 bg-white p-4 shadow-sm transition hover:border-rose-400 dark:border-slate-800 dark:bg-slate-900">
                <p class="text-xs uppercase text-rose-600">Suprimento crítico</p>
                <p class="mt-2 text-2xl font-bold text-rose-700">{{ number_format($resumo['suprimento_critico'], 0, ',', '.') }}</p>
                <p class="mt-1 text-xs text-slate-500">abaixo de 10%</p>
            </a>
            <a href="{{ route('impressoras.index', ['situacao' => 'sem_contato']) }}" class="rounded-lg border border-amber-200 bg-white p-4 shadow-sm transition hover:border-amber-400 dark:border-slate-800 dark:bg-slate-900">
                <p class="text-xs uppercase text-amber-600">Sem contato recente</p>
                <p class="mt-2 text-2xl font-bold text-amber-700">{{ number_format($resumo['sem_contato'], 0, ',', '.') }}</p>
                <p class="mt-1 text-xs text-slate-500">há {{ \App\Models\Printer::DIAS_SEM_CONTATO_ALERTA }} dias ou mais</p>
            </a>
            <a href="{{ route('impressoras.index', ['situacao' => 'sem_unidade']) }}" class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm transition hover:border-slate-400 dark:border-slate-800 dark:bg-slate-900">
                <p class="text-xs uppercase text-slate-500">Sem unidade</p>
                <p class="mt-2 text-2xl font-bold text-slate-900 dark:text-white">{{ number_format($resumo['sem_unidade'], 0, ',', '.') }}</p>
                <p class="mt-1 text-xs text-slate-500">localização do portal não casou</p>
            </a>
        </section>

        <section class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <form method="GET" class="grid gap-3 lg:grid-cols-12 lg:items-end">
                <label class="block text-sm font-medium text-slate-700 lg:col-span-3 dark:text-slate-200">
                    Pesquisar
                    <input name="q" value="{{ $busca }}" placeholder="Serial, modelo, IP, hostname…"
                           class="mt-1 h-10 w-full rounded-md border-slate-300 text-sm">
                </label>
                <label class="block text-sm font-medium text-slate-700 lg:col-span-2 dark:text-slate-200">
                    Unidade
                    <select name="unidade" class="mt-1 h-10 w-full rounded-md border-slate-300 text-sm">
                        <option value="">Todas</option>
                        @foreach ($unidades as $unidade)
                            <option value="{{ $unidade->id }}" @selected((int) ($filtros['unidade'] ?? 0) === $unidade->id)>{{ $unidade->name }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="block text-sm font-medium text-slate-700 lg:col-span-2 dark:text-slate-200">
                    Modelo
                    <select name="modelo" class="mt-1 h-10 w-full rounded-md border-slate-300 text-sm">
                        <option value="">Todos</option>
                        @foreach ($modelos as $modelo)
                            <option value="{{ $modelo }}" @selected(($filtros['modelo'] ?? '') === $modelo)>{{ $modelo }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="block text-sm font-medium text-slate-700 lg:col-span-2 dark:text-slate-200">
                    Consumível
                    <select name="consumivel" class="mt-1 h-10 w-full rounded-md border-slate-300 text-sm">
                        <option value="">Todos</option>
                        @foreach ($consumiveis as $chave => $rotulo)
                            <option value="{{ $chave }}" @selected($consumivelSelecionado === $chave)>{{ $rotulo }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="block text-sm font-medium text-slate-700 lg:col-span-2 dark:text-slate-200">
                    Situação
                    <select name="situacao" class="mt-1 h-10 w-full rounded-md border-slate-300 text-sm">
                        <option value="">Todas</option>
                        @foreach ($situacoes as $chave => $rotulo)
                            <option value="{{ $chave }}" @selected(($filtros['situacao'] ?? '') === $chave)>{{ $rotulo }}</option>
                        @endforeach
                    </select>
                </label>
                <div class="flex gap-2 lg:col-span-1">
                    <button class="h-10 flex-1 rounded-md bg-slate-950 px-4 text-sm font-semibold text-white">Filtrar</button>
                </div>
                <p class="col-span-full text-xs text-slate-500">
                    <strong>Consumível</strong> e <strong>Situação</strong> se combinam — por exemplo,
                    <em>Fusor</em> + <em>crítico</em> lista só os fusores abaixo de {{ \App\Models\Printer::NIVEL_CRITICO }}%.
                </p>
            </form>
            @if ($busca !== '' || ($filtros['unidade'] ?? null) || ($filtros['modelo'] ?? null) || ($filtros['situacao'] ?? null) || $consumivelSelecionado !== '')
                <a href="{{ route('impressoras.index') }}" class="mt-3 inline-block text-xs font-semibold text-slate-500 underline">Limpar filtros</a>
            @endif
        </section>

        <section class="overflow-x-auto rounded-lg border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <table class="w-full min-w-[1000px] text-sm">
                <thead class="bg-slate-900 text-left text-xs font-semibold uppercase text-white">
                    <tr>
                        <th class="px-4 py-3">Modelo</th>
                        <th class="px-4 py-3">Serial</th>
                        <th class="px-4 py-3">Unidade / local</th>
                        <th class="px-4 py-3">Rede</th>
                        <th class="px-4 py-3">{{ $consumivelSelecionado !== '' ? ($consumiveis[$consumivelSelecionado] ?? 'Consumível') : 'Consumível mais baixo' }}</th>
                        <th class="px-4 py-3">Último contato</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200 dark:divide-slate-800">
                    @forelse ($impressoras as $impressora)
                        @php($pior = $piorSuprimento->get($impressora->id))
                        <tr class="transition hover:bg-slate-50 dark:hover:bg-slate-800/60">
                            <td class="px-4 py-3">
                                <a href="{{ route('impressoras.show', $impressora) }}" class="font-semibold text-slate-900 underline decoration-slate-300 dark:text-white">
                                    {{ $impressora->model ?: '—' }}
                                </a>
                                <div class="mt-0.5 text-xs text-slate-400">{{ $impressora->manufacturer ?: '' }}</div>
                            </td>
                            <td class="px-4 py-3 font-mono text-xs">{{ $impressora->serial_number ?: '—' }}</td>
                            <td class="px-4 py-3">
                                {{ $impressora->local }}
                                @unless ($impressora->organization_unit_id)
                                    <span class="ml-1 rounded bg-slate-100 px-1.5 py-0.5 text-[11px] font-semibold text-slate-600 dark:bg-slate-800">sem unidade</span>
                                @endunless
                            </td>
                            <td class="px-4 py-3 text-xs">
                                {{ $impressora->ip_address ?: '—' }}
                                <div class="text-slate-400">{{ $impressora->hostname ?: '' }}</div>
                            </td>
                            <td class="px-4 py-3">
                                @if ($pior)
                                    <x-impressoras.nivel :supply="$pior" />
                                @else
                                    <span class="text-xs text-slate-400">sem leitura</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-xs">
                                @if ($impressora->last_contact)
                                    <span class="{{ $impressora->semContatoRecente() ? 'font-semibold text-amber-700' : 'text-slate-600 dark:text-slate-300' }}">
                                        {{ $impressora->last_contact->local()->diffForHumans() }}
                                    </span>
                                    <div class="text-slate-400">{{ $impressora->last_contact->local()->format('d/m/Y H:i') }}</div>
                                @else
                                    <span class="font-semibold text-amber-700">nunca</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-4 py-10 text-center text-slate-500">Nenhuma impressora encontrada com esses filtros.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </section>

        @if ($impressoras->hasPages())
            <div>{{ $impressoras->onEachSide(1)->links() }}</div>
        @endif
    </div>
</x-app-layout>
