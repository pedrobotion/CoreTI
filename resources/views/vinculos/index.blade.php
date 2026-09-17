<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-gray-800">{{ $ctx->itemLabel }}s</h2>
    </x-slot>

    <div class="mx-auto max-w-7xl space-y-6 px-6 py-8">
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <p class="text-xs uppercase tracking-widest text-slate-500">{{ $ctx->label }}</p>
                <h1 class="text-3xl font-semibold text-slate-900">{{ $ctx->itemLabel }}s</h1>
                <p class="mt-1 text-sm text-slate-500">
                    @if ($ctx->key === \App\Support\AssignmentContext::TELEFONIA)
                        Linhas e aparelhos entregues a colaboradores, com termo de responsabilidade.
                    @else
                        Equipamentos entregues a colaboradores, com termo de responsabilidade.
                    @endif
                </p>
            </div>
            <div class="flex flex-wrap gap-2">
                <a href="{{ $ctx->route('export.excel', request()->query()) }}" class="rounded-md border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Excel</a>
                <a href="{{ $ctx->route('export.pdf', request()->query()) }}" class="rounded-md border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">PDF</a>
                @if ($ctx->podeGerenciar())
                    <a href="{{ $ctx->route('create') }}" class="rounded-md bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:opacity-90">Nov{{ $ctx->itemLabel === 'Entrega' ? 'a' : 'o' }} {{ mb_strtolower($ctx->itemLabel) }}</a>
                @endif
            </div>
        </div>

        @include('partials.modulo-flash')

        @include('partials.modulo-resumo', ['cards' => [
            ['titulo' => 'Ativos', 'valor' => $resumo['ativos']],
            ['titulo' => 'A vencer (30d)', 'valor' => $resumo['a_vencer']],
            ['titulo' => 'Vencidos', 'valor' => $resumo['vencidos'], 'alerta' => true],
            ['titulo' => 'Sem termo assinado', 'valor' => $resumo['sem_termo'], 'alerta' => true],
            ['titulo' => 'Colaborador inativo', 'valor' => $resumo['inconsistentes'], 'alerta' => true, 'nota' => 'Com item ainda entregue'],
        ]])

        @if ($resumo['inconsistentes'] > 0)
            <div class="rounded-md border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
                <span class="font-semibold">{{ $resumo['inconsistentes'] }} registro(s) ativo(s) de colaborador que já não está ativo no RH.</span>
                É o caminho mais comum para item sumir do controle: a pessoa sai e o item não volta.
                <a href="{{ $ctx->route('index', ['inconsistentes' => 1]) }}" class="font-semibold underline">Ver só esses</a>.
            </div>
        @endif

        <section class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
            <form method="GET" action="{{ $ctx->route('index') }}" class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-6">
                <input name="colaborador" value="{{ $filters['colaborador'] }}" placeholder="Colaborador ou matrícula" class="rounded-md border-slate-300 text-sm lg:col-span-2">
                <input name="busca" value="{{ $filters['busca'] }}" placeholder="{{ $ctx->key === 'telefonia' ? 'Número ou IMEI' : 'Patrimônio ou nome' }}" class="rounded-md border-slate-300 text-sm">

                @if (count($scopeOptions) > 1)
                    <select name="scope" class="rounded-md border-slate-300 text-sm">
                        <option value="">Todas as operações</option>
                        @foreach ($scopeOptions as $valor => $rotulo)
                            <option value="{{ $valor }}" @selected($filters['scope'] === $valor)>{{ $rotulo }}</option>
                        @endforeach
                    </select>
                @endif

                <select name="situacao" class="rounded-md border-slate-300 text-sm">
                    <option value="ativos" @selected($filters['situacao'] === 'ativos')>Ativos</option>
                    <option value="encerrados" @selected($filters['situacao'] === 'encerrados')>Encerrados</option>
                    <option value="todos" @selected($filters['situacao'] === 'todos')>Todos</option>
                </select>

                <select name="vencimento" class="rounded-md border-slate-300 text-sm">
                    <option value="">Qualquer vencimento</option>
                    <option value="vencidos" @selected($filters['vencimento'] === 'vencidos')>Vencidos</option>
                    <option value="30" @selected($filters['vencimento'] === '30')>Vencem em 30 dias</option>
                    <option value="90" @selected($filters['vencimento'] === '90')>Vencem em 90 dias</option>
                </select>

                <select name="termo" class="rounded-md border-slate-300 text-sm">
                    <option value="">Termo: todos</option>
                    <option value="pendente" @selected($filters['termo'] === 'pendente')>Sem termo assinado</option>
                    <option value="assinado" @selected($filters['termo'] === 'assinado')>Com termo assinado</option>
                </select>

                <select name="cargo" class="rounded-md border-slate-300 text-sm">
                    <option value="">Todos os cargos</option>
                    @foreach ($cargos as $cargo)
                        <option value="{{ $cargo->source_id }}" @selected($filters['cargo'] === (string) $cargo->source_id)>{{ $cargo->name }}</option>
                    @endforeach
                </select>

                <select name="unidade" class="rounded-md border-slate-300 text-sm">
                    <option value="">Todas as unidades</option>
                    @foreach ($unidades as $unidade)
                        <option value="{{ $unidade->source_id }}" @selected($filters['unidade'] === (string) $unidade->source_id)>{{ $unidade->name }}</option>
                    @endforeach
                </select>

                @if (count($tipos) > 1)
                    <select name="tipo" class="rounded-md border-slate-300 text-sm">
                        <option value="">Todos os tipos</option>
                        @foreach ($tipos as $valor => $rotulo)
                            <option value="{{ $valor }}" @selected($filters['tipo'] === $valor)>{{ $rotulo }}</option>
                        @endforeach
                    </select>
                @endif

                @include('partials.modulo-por-pagina')

                <div class="flex gap-2 lg:col-span-2">
                    <label class="inline-flex items-center gap-2 text-sm text-slate-700">
                        <input type="checkbox" name="inconsistentes" value="1" @checked($filters['inconsistentes'] === '1') class="rounded border-slate-300">
                        Só colaborador inativo
                    </label>
                </div>

                <div class="flex gap-2">
                    <button class="flex-1 rounded-md border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Filtrar</button>
                    <a href="{{ $ctx->route('index') }}" class="rounded-md border border-slate-300 px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Limpar</a>
                </div>
            </form>
        </section>

        <section class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
            <div class="mb-4 flex items-center justify-between gap-3">
                <h2 class="text-lg font-semibold text-slate-900">Registros</h2>
                <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-600">{{ number_format($assignments->total(), 0, ',', '.') }} registro(s)</span>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full min-w-[1000px] text-left text-sm">
                    <thead class="border-b border-slate-200 text-xs uppercase text-slate-500">
                        <tr>
                            <th class="px-3 py-3">Colaborador</th>
                            <th class="px-3 py-3">Itens</th>
                            <th class="px-3 py-3">Entrega</th>
                            <th class="px-3 py-3">Vencimento</th>
                            <th class="px-3 py-3">Termo</th>
                            <th class="px-3 py-3">Situação</th>
                            <th class="px-3 py-3 text-right">Ações</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($assignments as $assignment)
                            @php
                                $colaboradorInativo = $assignment->ativo && $assignment->collaborator
                                    && (! $assignment->collaborator->active || $assignment->collaborator->terminated);
                            @endphp
                            <tr class="{{ $colaboradorInativo ? 'bg-amber-50/60' : '' }}">
                                <td class="px-3 py-3">
                                    <p class="font-medium text-slate-900">{{ $assignment->collaborator?->name ?? '—' }}</p>
                                    <p class="text-xs text-slate-500">
                                        Matrícula {{ $assignment->collaborator_matricula ?? '—' }}
                                        @if ($unidadesPorSourceId[$assignment->collaborator?->unit_source_id] ?? null)
                                            · {{ $unidadesPorSourceId[$assignment->collaborator->unit_source_id] }}
                                        @endif
                                    </p>
                                    @if ($colaboradorInativo)
                                        <span class="mt-1 inline-block rounded-full bg-amber-200 px-2 py-0.5 text-xs font-semibold text-amber-900">inativo no RH</span>
                                    @endif
                                </td>
                                <td class="px-3 py-3">
                                    <ul class="space-y-0.5">
                                        @foreach ($assignment->items as $item)
                                            <li class="text-xs text-slate-700">{{ $item->descricao() }}</li>
                                        @endforeach
                                    </ul>
                                    <p class="mt-1 text-xs text-slate-400">{{ $assignment->tipo_descricao }}</p>
                                </td>
                                <td class="px-3 py-3 text-slate-600">{{ $assignment->data_vinculo?->format('d/m/Y') }}</td>
                                <td class="px-3 py-3">
                                    <span class="{{ $assignment->isVencido() ? 'font-semibold text-rose-700' : ($assignment->isProximoVencimento() ? 'font-semibold text-amber-700' : 'text-slate-600') }}">
                                        {{ $assignment->data_vencimento?->format('d/m/Y') }}
                                    </span>
                                </td>
                                <td class="px-3 py-3">
                                    @if ($assignment->temTermoAssinado())
                                        <span class="rounded-full bg-emerald-100 px-2 py-1 text-xs font-medium text-emerald-700">Assinado</span>
                                    @elseif ($assignment->ativo)
                                        <span class="rounded-full bg-amber-100 px-2 py-1 text-xs font-medium text-amber-800">Pendente</span>
                                    @else
                                        <span class="text-xs text-slate-400">—</span>
                                    @endif
                                </td>
                                <td class="px-3 py-3">
                                    @if ($assignment->ativo)
                                        <span class="rounded-full bg-sky-100 px-2 py-1 text-xs font-medium text-sky-700">Ativo</span>
                                    @else
                                        <span class="rounded-full bg-slate-200 px-2 py-1 text-xs font-medium text-slate-600">Encerrado</span>
                                    @endif
                                </td>
                                <td class="px-3 py-3">
                                    <div class="flex justify-end gap-2">
                                        <a href="{{ $ctx->route('show', $assignment) }}" class="rounded-md border border-slate-300 px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50">Abrir</a>
                                        @if ($ctx->podeGerenciar())
                                            @if ($assignment->ativo)
                                                <a href="{{ $ctx->route('desvinculo', $assignment) }}" class="rounded-md border border-rose-300 px-3 py-1.5 text-xs font-medium text-rose-700 hover:bg-rose-50">Devolver</a>
                                            @endif
                                            {{-- Excluir apaga o registro e libera os itens. Para encerrar
                                                 preservando o histórico, o caminho é "Devolver". --}}
                                            <form method="POST" action="{{ $ctx->route('destroy', $assignment) }}"
                                                  onsubmit="return confirm('Excluir este registro de {{ mb_strtolower($ctx->itemLabel) }}? O histórico será perdido e os itens voltam ao estoque. Para encerrar preservando o histórico, use Devolver.')">
                                                @csrf @method('DELETE')
                                                <button class="rounded-md border border-slate-300 px-3 py-1.5 text-xs font-medium text-slate-500 hover:bg-slate-50">Excluir</button>
                                            </form>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-3 py-10 text-center text-sm text-slate-500">
                                    Nenhum registro encontrado para os filtros atuais.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-4">{{ $assignments->links() }}</div>
        </section>
    </div>
</x-app-layout>
