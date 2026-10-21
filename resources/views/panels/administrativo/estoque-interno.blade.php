<x-app-layout>
    @php
        $emBancada = request()->routeIs('bancada-servicos.*');
        $eb = $emBancada ? 'bancada-servicos.estoque' : 'administrativo.estoque-interno';
    @endphp
    <div class="mx-auto max-w-7xl space-y-6" x-data="{ novoProduto: false }">
        <div class="flex flex-wrap items-end justify-between gap-3">
            <div>
                <p class="text-sm font-semibold uppercase tracking-wide text-slate-500">{{ $emBancada ? 'Bancada de Serviços' : 'Administrativo' }}</p>
                <h1 class="mt-1 text-2xl font-bold text-slate-950 dark:text-white">Estoque Interno da TI</h1>
                <p class="mt-1 text-sm text-slate-500">Peças de reposição controladas por quantidade (saldo e estoque mínimo para alerta).</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <a href="{{ route($eb.'.movimentos') }}" class="inline-flex h-10 items-center rounded-md border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100">Histórico</a>
                <a href="{{ route($eb.'.exportar', request()->query()) }}" class="inline-flex h-10 items-center rounded-md border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100">Exportar CSV</a>
                <button type="button" @click="novoProduto = true" class="inline-flex h-10 items-center rounded-md border border-slate-900 bg-slate-900 px-4 text-sm font-semibold text-white shadow-sm hover:bg-slate-800">+ Novo produto</button>
            </div>
        </div>

        @if (session('success'))
            <div class="rounded-md border border-emerald-300 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800">{{ session('success') }}</div>
        @endif
        @if ($errors->any())
            <div class="rounded-md border border-rose-300 bg-rose-50 px-4 py-3 text-sm text-rose-800">
                @foreach ($errors->all() as $erro)<p>{{ $erro }}</p>@endforeach
            </div>
        @endif

        <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            @foreach ($cards as $c)
                <div class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    <p class="text-xs uppercase text-slate-500">{{ $c->categoria }}</p>
                    <p class="mt-2 text-2xl font-bold text-slate-950 dark:text-white">{{ number_format($c->saldo, 0, ',', '.') }}</p>
                    <p class="mt-1 text-xs text-slate-500">{{ $c->tipos }} {{ $c->tipos == 1 ? 'tipo' : 'tipos' }} cadastrados</p>
                </div>
            @endforeach
            <div class="rounded-lg border p-4 shadow-sm {{ $abaixoDoMinimo > 0 ? 'border-rose-300 bg-rose-50 dark:border-rose-900/50 dark:bg-rose-500/10' : 'border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900' }}">
                <p class="text-xs uppercase text-slate-500">Abaixo do mínimo</p>
                <p class="mt-2 text-2xl font-bold {{ $abaixoDoMinimo > 0 ? 'text-rose-600 dark:text-rose-400' : 'text-slate-950 dark:text-white' }}">{{ number_format($abaixoDoMinimo, 0, ',', '.') }}</p>
                <p class="mt-1 text-xs text-slate-500">tipos para repor</p>
            </div>
        </section>

        <form method="GET" class="flex flex-wrap items-end gap-2">
            <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">Categoria
                <select name="categoria" class="mt-1 block h-10 rounded-md border-slate-300 text-sm">
                    <option value="">Todas</option>
                    @foreach ($categorias as $cat)
                        <option value="{{ $cat }}" @selected($filtros['categoria'] === $cat)>{{ $cat }}</option>
                    @endforeach
                </select>
            </label>
            <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">Situação
                <select name="situacao" class="mt-1 block h-10 rounded-md border-slate-300 text-sm">
                    <option value="todos" @selected($filtros['situacao'] === 'todos')>Todos</option>
                    <option value="abaixo" @selected($filtros['situacao'] === 'abaixo')>Abaixo do mínimo</option>
                    <option value="disponivel" @selected($filtros['situacao'] === 'disponivel')>Com saldo</option>
                    <option value="zerado" @selected($filtros['situacao'] === 'zerado')>Zerados</option>
                </select>
            </label>
            <label class="flex-1 text-xs font-semibold uppercase tracking-wide text-slate-500">Busca
                <input type="text" name="q" value="{{ $filtros['q'] }}" placeholder="nome, serial ou plaqueta" class="mt-1 block h-10 w-full rounded-md border-slate-300 text-sm">
            </label>
            <button class="h-10 rounded-md bg-slate-950 px-4 text-sm font-semibold text-white">Filtrar</button>
        </form>

        <section class="overflow-x-auto rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <table class="w-full min-w-[800px] text-sm">
                <thead class="border-b border-slate-100 text-left text-xs font-semibold uppercase tracking-wide text-slate-400">
                    <tr>
                        <th class="px-4 py-3">Categoria</th>
                        <th class="px-4 py-3">Produto</th>
                        <th class="px-4 py-3">Controle</th>
                        <th class="px-4 py-3">Saldo</th>
                        <th class="px-4 py-3">Mínimo</th>
                        <th class="px-4 py-3 text-right">Ações</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    @forelse ($produtos as $p)
                        <tr class="{{ $p->abaixo_do_minimo ? 'bg-rose-50/60 dark:bg-rose-500/5' : '' }}">
                            <td class="px-4 py-3 text-slate-600 dark:text-slate-300">{{ $p->categoria }}</td>
                            <td class="px-4 py-3 font-medium text-slate-900 dark:text-white">{{ $p->nome }}</td>
                            <td class="px-4 py-3 text-xs text-slate-500">{{ $p->ehSerial() ? 'Por unidade (serial)' : 'Por quantidade' }}</td>
                            <td class="px-4 py-3">
                                <span class="font-bold {{ $p->abaixo_do_minimo ? 'text-rose-600 dark:text-rose-400' : 'text-slate-900 dark:text-white' }}">{{ number_format($p->saldo, 0, ',', '.') }}</span>
                                @if ($p->abaixo_do_minimo)
                                    <span class="ml-1 rounded bg-rose-100 px-1.5 py-0.5 text-[11px] font-semibold text-rose-800 dark:bg-rose-500/15 dark:text-rose-200">repor</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-xs text-slate-500">{{ $p->quantidade_minima }}</td>
                            <td class="px-4 py-3">
                                <div class="flex justify-end gap-2">
                                    <a href="{{ route($eb.'.entrada', $p) }}" class="rounded-md border border-emerald-300 bg-emerald-50 px-3 py-1.5 text-xs font-semibold text-emerald-700 hover:bg-emerald-100 dark:border-emerald-900/50 dark:bg-emerald-500/10 dark:text-emerald-300">Entrada</a>
                                    <a href="{{ route($eb.'.baixa', $p) }}" class="rounded-md border border-slate-300 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200">Baixa</a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-4 py-10 text-center text-sm text-slate-500">Nenhum produto. Cadastre com "+ Novo produto".</td></tr>
                    @endforelse
                </tbody>
            </table>
        </section>

        <section class="grid gap-5 xl:grid-cols-2">
            <div class="rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <div class="flex items-center justify-between border-b border-slate-200 px-6 py-4 dark:border-slate-800">
                    <h2 class="text-lg font-bold text-slate-950 dark:text-white">Movimentações recentes</h2>
                    <a href="{{ route($eb.'.movimentos') }}" class="text-xs font-semibold text-slate-600 underline dark:text-slate-300">ver tudo</a>
                </div>
                <div class="divide-y divide-slate-100 dark:divide-slate-800">
                    @forelse ($movimentos as $m)
                        <div class="flex items-center justify-between gap-3 px-6 py-2.5 text-sm">
                            <div class="min-w-0">
                                <span class="rounded px-1.5 py-0.5 text-[11px] font-semibold {{ $m->tipo === 'entrada' ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-500/15 dark:text-emerald-200' : 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300' }}">{{ ucfirst($m->tipo) }}</span>
                                <span class="ml-1 font-medium text-slate-800 dark:text-slate-100">{{ $m->product?->nome }}</span>
                                <span class="text-slate-400">× {{ $m->quantidade }}</span>
                                @if ($m->destino_descricao)<span class="text-xs text-slate-500">→ {{ $m->destino_descricao }}</span>@endif
                            </div>
                            <span class="shrink-0 text-xs text-slate-400">{{ $m->created_at?->format('d/m H:i') }}</span>
                        </div>
                    @empty
                        <p class="px-6 py-8 text-center text-sm text-slate-500">Sem movimentações ainda.</p>
                    @endforelse
                </div>
            </div>

            <div class="rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <div class="border-b border-slate-200 px-6 py-4 dark:border-slate-800">
                    <h2 class="text-lg font-bold text-slate-950 dark:text-white">Reposição pela bancada</h2>
                    <p class="mt-1 text-xs text-slate-500">Peças do estoque da TI consumidas em reparos aguardando reposição.</p>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-slate-50 text-left text-xs font-semibold uppercase text-slate-500 dark:bg-slate-800/60">
                            <tr><th class="px-6 py-2">Plaqueta</th><th class="px-6 py-2">Equipamento</th><th class="px-6 py-2">Peça</th><th class="px-6 py-2">Qtd</th></tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                            @forelse ($reposicao as $r)
                                <tr>
                                    <td class="px-6 py-2">{{ $r->plaqueta ?: '—' }}</td>
                                    <td class="px-6 py-2 text-xs text-slate-500">{{ $r->tipo_equipamento }}</td>
                                    <td class="px-6 py-2">{{ $r->peca_nome }}</td>
                                    <td class="px-6 py-2 font-semibold">{{ $r->peca_quantidade }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="px-6 py-8 text-center text-sm text-slate-500">Sem consumo de estoque interno pendente.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </section>

        {{-- Modal: novo produto --}}
        <div x-show="novoProduto" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" @keydown.escape.window="novoProduto = false">
            <div class="w-full max-w-lg rounded-xl bg-white p-6 shadow-xl dark:bg-slate-900" @click.outside="novoProduto = false">
                <h2 class="text-lg font-bold text-slate-950 dark:text-white">Novo produto no estoque</h2>
                <form method="POST" action="{{ route($eb.'.produtos.store') }}" class="mt-4 space-y-4">
                    @csrf
                    <div class="grid gap-4 sm:grid-cols-2">
                        <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">Categoria
                            <select name="categoria" required class="mt-1 block h-10 w-full rounded-md border-slate-300 text-sm">
                                @foreach ($categorias as $cat)<option value="{{ $cat }}">{{ $cat }}</option>@endforeach
                            </select>
                        </label>
                        <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">Controle
                            {{-- Por ora só por quantidade (serial desativado). --}}
                            <input type="hidden" name="controle" value="quantidade">
                            <div class="mt-1 flex h-10 items-center rounded-md border border-slate-200 bg-slate-50 px-3 text-sm text-slate-600 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300">Por quantidade</div>
                        </label>
                    </div>
                    <label class="block text-xs font-semibold uppercase tracking-wide text-slate-500">Nome / descrição
                        <input type="text" name="nome" required maxlength="255" placeholder='Ex.: Nobreak APC 1200VA ou SSD 480GB' class="mt-1 block h-10 w-full rounded-md border-slate-300 text-sm">
                    </label>
                    <label class="block text-xs font-semibold uppercase tracking-wide text-slate-500">Estoque mínimo (alerta)
                        <input type="number" name="quantidade_minima" min="0" value="0" class="mt-1 block h-10 w-32 rounded-md border-slate-300 text-sm">
                    </label>
                    <div class="flex justify-end gap-2 pt-2">
                        <button type="button" @click="novoProduto = false" class="h-10 rounded-md border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-700 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200">Cancelar</button>
                        <button class="h-10 rounded-md bg-slate-900 px-4 text-sm font-semibold text-white">Cadastrar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
