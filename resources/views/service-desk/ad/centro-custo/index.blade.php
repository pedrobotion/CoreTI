<x-app-layout>
    <div class="mx-auto max-w-7xl space-y-6">

        <div>
            <h1 class="text-xl font-bold text-slate-950">Centro de custo no AD</h1>
            <p class="text-sm text-slate-500">
                Define o campo <strong>Escritório</strong> de cada conta a partir da OU do setor.
                O Active Directory não tem campo calculado — a regra vive aqui e um script aplica no domínio.
                Empresa afiliada recebe o CNPJ no lugar do código.
            </p>
        </div>

        {{-- O escopo precisa ser visível: sem ele, "N contas mudariam" não
             explica o que acontece com as outras. --}}
        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <div class="flex flex-wrap items-baseline gap-x-6 gap-y-2">
                <p class="text-sm text-slate-700">
                    A regra alcança <strong class="text-slate-950">{{ $escopo['alcancadas'] }}</strong>
                    contas ativas do domínio.
                </p>
                @if ($escopo['fora'] > 0)
                    <p class="text-sm text-slate-500">
                        {{ $escopo['fora'] }} ficam de fora:
                        @foreach ($escopo['motivos'] as $motivo => $quantas)
                            <span class="whitespace-nowrap">{{ $quantas }} {{ mb_strtolower($motivo) }}@if (! $loop->last),@endif</span>
                        @endforeach
                    </p>
                @endif
            </div>
            <p class="mt-2 text-xs text-slate-500">
                Scanner, GED, conta de sistema e conta modelo não consomem centro de custo.
                Exceções individuais são mantidas na tela de Cadastro incompleto e abaixo.
            </p>
        </div>

        @if (session('success'))
            <div class="rounded-lg border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-800">{{ session('success') }}</div>
        @endif
        @if (session('error'))
            <div class="rounded-lg border border-rose-200 bg-rose-50 p-4 text-sm text-rose-800">{{ session('error') }}</div>
        @endif

        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">OUs mapeadas</p>
                <p class="mt-2 text-3xl font-bold text-slate-950">{{ $resumo['total'] }}</p>
            </div>
            <div class="rounded-lg border border-red-200 bg-red-50 p-5 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wide text-red-700">Falta definir</p>
                <p class="mt-2 text-3xl font-bold text-red-900">{{ $resumo['sem_definicao'] }}</p>
                <p class="mt-1 text-xs text-red-700">nenhuma conta delas será alterada</p>
            </div>
            <div class="rounded-lg border border-emerald-200 bg-emerald-50 p-5 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wide text-emerald-700">Confirmadas</p>
                <p class="mt-2 text-3xl font-bold text-emerald-900">{{ $resumo['confirmadas'] }}</p>
            </div>
            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Não mexer</p>
                <p class="mt-2 text-3xl font-bold text-slate-950">{{ $resumo['ignoradas'] }}</p>
            </div>
        </div>

        <div class="rounded-lg border border-amber-200 bg-amber-50 p-5 shadow-sm">
            <p class="text-sm font-semibold text-amber-900">
                Aplicando as regras hoje, {{ $resumo['contas_afetadas'] }} contas mudariam:
                {{ $resumo['preenchendo'] }} preenchendo campo vazio e
                <strong>{{ $resumo['trocando'] }} trocando de código</strong>.
            </p>
            <p class="mt-1 text-xs text-amber-800">
                Preencher vazio é seguro. Trocar código merece conferência: pode ser correção de erro,
                ou pode ser gente cujo centro de custo segue a função e não o lugar — alguém da TI
                lotado numa unidade, por exemplo.
            </p>
        </div>

        @if ($trocas->isNotEmpty())
            <details class="rounded-2xl border border-slate-200 bg-white shadow-sm">
                <summary class="cursor-pointer px-5 py-3 text-sm font-semibold text-slate-800">
                    Ver as {{ $trocas->count() }} trocas de código
                </summary>
                <div class="overflow-x-auto border-t border-slate-200">
                    <table class="min-w-full divide-y divide-slate-200 text-sm">
                        <thead class="bg-slate-50">
                            <tr>
                                <th class="px-5 py-3 text-left font-semibold text-slate-600">Conta</th>
                                <th class="px-5 py-3 text-left font-semibold text-slate-600">Nome</th>
                                <th class="px-5 py-3 text-left font-semibold text-slate-600">OU</th>
                                <th class="px-5 py-3 text-left font-semibold text-slate-600">De</th>
                                <th class="px-5 py-3 text-left font-semibold text-slate-600">Para</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach ($trocas as $t)
                                <tr>
                                    <td class="px-5 py-3 font-medium text-slate-900">{{ $t['nome_usuario'] }}</td>
                                    <td class="px-5 py-3 text-slate-600">{{ $t['nome_completo'] }}</td>
                                    <td class="px-5 py-3 text-slate-500">{{ $t['ou_caminho'] }}</td>
                                    <td class="px-5 py-3 font-semibold text-rose-700">{{ $t['de'] }}</td>
                                    <td class="px-5 py-3 font-semibold text-emerald-700">{{ $t['para'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </details>
        @endif

        <div class="rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-200 px-5 py-3">
                <h2 class="text-sm font-semibold text-slate-800">Exceções por conta</h2>
                <p class="text-xs text-slate-500">
                    Contas que a regra da OU não toca. Para quem tem centro de custo ligado à função
                    e não ao lugar, ou para conta tratada caso a caso.
                </p>
            </div>

            @if ($excecoes->isNotEmpty())
                <ul class="divide-y divide-slate-100">
                    @foreach ($excecoes as $e)
                        <li class="flex flex-wrap items-center justify-between gap-3 px-5 py-3 text-sm">
                            <div>
                                <span class="font-medium text-slate-900">{{ $e->nome_usuario }}</span>
                                <span class="ml-2 text-slate-500">{{ $e->motivo }}</span>
                                @if ($e->autor)
                                    <span class="block text-xs text-slate-400">{{ $e->autor->name }} · {{ $e->created_at->local()->format('d/m/Y H:i') }}</span>
                                @endif
                            </div>
                            @can('module.servicedesk.manage')
                                <form method="POST" action="{{ route('service-desk.ad.centro-custo.excecoes.destroy', $e) }}"
                                      onsubmit="return confirm('Remover a exceção? {{ $e->nome_usuario }} volta a seguir a regra da OU.')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn-coreti-secondary text-xs">Remover</button>
                                </form>
                            @endcan
                        </li>
                    @endforeach
                </ul>
            @else
                <p class="px-5 py-4 text-sm text-slate-500">Nenhuma exceção cadastrada.</p>
            @endif

            @can('module.servicedesk.manage')
                <form method="POST" action="{{ route('service-desk.ad.centro-custo.excecoes.store') }}"
                      class="flex flex-wrap items-end gap-3 border-t border-slate-200 bg-slate-50 px-5 py-3">
                    @csrf
                    <div>
                        <label class="block text-xs font-semibold text-slate-600">Conta</label>
                        <input type="text" name="nome_usuario" required placeholder="jose.candido"
                               class="mt-1 w-56 rounded-md border-slate-300 text-sm">
                    </div>
                    <div class="flex-1">
                        <label class="block text-xs font-semibold text-slate-600">Motivo</label>
                        <input type="text" name="motivo" required placeholder="Tratado à parte"
                               class="mt-1 w-full rounded-md border-slate-300 text-sm">
                    </div>
                    <button type="submit" class="btn-coreti-secondary text-sm">Adicionar exceção</button>
                </form>
            @endcan
        </div>

        <form method="GET" class="flex flex-wrap items-end gap-3 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
            <div>
                <label class="block text-xs font-semibold text-slate-600">Situação</label>
                <select name="situacao" class="mt-1 rounded-md border-slate-300 text-sm">
                    <option value="todas" @selected($situacao === 'todas')>Todas</option>
                    <option value="sem_definicao" @selected($situacao === 'sem_definicao')>Falta definir</option>
                    <option value="sugerida" @selected($situacao === 'sugerida')>Sugestão a confirmar</option>
                    <option value="confirmada" @selected($situacao === 'confirmada')>Confirmada</option>
                    <option value="ignorada" @selected($situacao === 'ignorada')>Não mexer</option>
                </select>
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-600">OU</label>
                <input type="text" name="q" value="{{ $busca }}" placeholder="Sede / E-Commerce"
                       class="mt-1 w-64 rounded-md border-slate-300 text-sm">
            </div>
            <button type="submit" class="btn-coreti-secondary text-sm">Filtrar</button>
        </form>

        {{--
            Um formulário para a tabela inteira.

            Salvar linha a linha recarregava a página a cada clique e devolvia
            a rolagem ao topo — inviável para revisar 80 sugestões. O contador
            do rodapé conta os campos tocados, e o controller grava só as
            linhas que realmente mudaram.
        --}}
        <form method="POST" action="{{ route('service-desk.ad.centro-custo.atualizar-lote') }}"
              x-data="{ mexidos: new Set(), marcar(id) { this.mexidos.add(id) } }">
            @csrf
            @method('PATCH')
            {{-- Serve para o controller detectar corte por max_input_vars. --}}
            <input type="hidden" name="total_enviado" value="{{ $regras->count() }}">

        <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50">
                        <tr>
                            <th class="px-5 py-3 text-left font-semibold text-slate-600">OU</th>
                            <th class="px-5 py-3 text-right font-semibold text-slate-600">Contas</th>
                            <th class="px-5 py-3 text-left font-semibold text-slate-600">Hoje no AD</th>
                            <th class="px-5 py-3 text-left font-semibold text-slate-600">Centro de custo</th>
                            <th class="px-5 py-3 text-left font-semibold text-slate-600">Situação</th>
                            <th class="px-5 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($regras as $regra)
                            <tr class="{{ $regra->situacao() === 'sem_definicao' ? 'bg-red-50/40' : '' }}">
                                <td class="px-5 py-3 font-medium text-slate-900">{{ $regra->ou_caminho }}</td>
                                <td class="px-5 py-3 text-right text-slate-700">{{ $regra->contas_na_ou }}</td>
                                <td class="px-5 py-3 text-slate-500">
                                    {{ $regra->sugestao_code ?? '—' }}
                                    @if ($regra->contas_divergentes > 0)
                                        <span class="ml-1 inline-flex rounded bg-amber-100 px-1.5 py-0.5 text-[10px] font-semibold text-amber-800"
                                              title="Contas desta OU com código diferente do dominante">
                                            {{ $regra->contas_divergentes }} divergentes
                                        </span>
                                    @endif
                                </td>
                                <td class="px-5 py-3">
                                    <select name="regras[{{ $regra->id }}][tipo_valor]"
                                            @change="marcar({{ $regra->id }})"
                                            class="mb-1 w-64 rounded-md border-slate-300 text-xs">
                                        <option value="centro_custo" @selected($regra->tipo_valor !== 'cnpj')>Centro de custo</option>
                                        <option value="cnpj" @selected($regra->tipo_valor === 'cnpj')>CNPJ (empresa terceira)</option>
                                    </select>

                                    <select name="regras[{{ $regra->id }}][centro_custo_code]"
                                            @change="marcar({{ $regra->id }})"
                                            class="w-64 rounded-md border-slate-300 text-sm">
                                        <option value="">— não definido —</option>
                                        @foreach ($centros as $c)
                                            <option value="{{ $c->code }}" @selected($regra->centro_custo_code === $c->code)>
                                                {{ $c->code }} · {{ $c->name }}
                                            </option>
                                        @endforeach
                                    </select>

                                    <input type="text" name="regras[{{ $regra->id }}][valor_livre]"
                                           value="{{ $regra->valor_livre }}"
                                           @input="marcar({{ $regra->id }})"
                                           placeholder="00.000.000/0000-00"
                                           class="mt-1 w-64 rounded-md border-slate-300 text-sm">
                                </td>
                                <td class="px-5 py-3">
                                    <span class="inline-flex items-center rounded-full px-2 py-1 text-xs font-medium {{ $regra->classesBadge() }}">
                                        {{ $regra->rotulo() }}
                                    </span>
                                    @if ($regra->ignorar && $regra->motivo_ignorar)
                                        <span class="block text-xs text-slate-500">{{ $regra->motivo_ignorar }}</span>
                                    @elseif ($regra->confirmado_em)
                                        <span class="block text-xs text-slate-500">
                                            {{ $regra->confirmador?->name }} · {{ $regra->confirmado_em->local()->format('d/m/Y H:i') }}
                                        </span>
                                    @endif
                                </td>
                                <td class="px-5 py-3 text-right">
                                    @can('module.servicedesk.manage')
                                        {{-- Sem o hidden, checkbox desmarcada não é enviada e o
                                             controller não distingue "desmarcou" de "não mexeu". --}}
                                        <input type="hidden" name="regras[{{ $regra->id }}][ignorar]" value="0">
                                        <label class="inline-flex items-center gap-1 text-xs text-slate-600">
                                            <input type="checkbox" name="regras[{{ $regra->id }}][ignorar]" value="1"
                                                   @change="marcar({{ $regra->id }})"
                                                   class="rounded border-slate-300" @checked($regra->ignorar)>
                                            não mexer
                                        </label>
                                    @endcan
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-5 py-10 text-center text-slate-500">
                                    Nenhuma OU nesse filtro. Rode <code>php artisan ad:semear-regras-escritorio</code> para mapear.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        @can('module.servicedesk.manage')
            {{-- Barra fixa: o botão fica alcançável em qualquer ponto da rolagem. --}}
            <div class="sticky bottom-4 mt-4 flex flex-wrap items-center justify-between gap-3 rounded-lg border border-slate-300 bg-white/95 px-5 py-3 shadow-lg backdrop-blur">
                <p class="text-sm text-slate-600">
                    <span x-text="mexidos.size"></span> linha(s) alterada(s) nesta tela.
                    <span class="text-slate-400">Só o que mudou é gravado.</span>
                </p>
                <button type="submit" class="btn-coreti-primary text-sm">Salvar alterações</button>
            </div>
        @endcan
        </form>
    </div>
</x-app-layout>
