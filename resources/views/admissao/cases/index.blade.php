<x-app-layout>
    <div class="mx-auto max-w-7xl space-y-6">

        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="text-xl font-bold text-slate-950">Admissão de colaboradores</h1>
                <p class="text-sm text-slate-500">Fluxo único: o RH registra uma vez, o sistema distribui para as áreas.</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('admissao.tasks.board') }}" class="btn-coreti-secondary text-sm">Fila de execução</a>
                @can('module.admissao.manage')
                    <a href="{{ route('admissao.profiles.index') }}" class="btn-coreti-secondary text-sm">Perfis de acesso</a>
                @endcan
                @can('module.admissao.request')
                    <a href="{{ route('admissao.cases.create') }}" class="btn-coreti-primary text-sm">Nova admissão</a>
                @endcan
            </div>
        </div>

        @include('admissao.partials.flash')

        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Em andamento</p>
                <p class="mt-2 text-3xl font-bold text-slate-950">{{ $resumo['abertos'] }}</p>
            </div>
            <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Rascunhos</p>
                <p class="mt-2 text-3xl font-bold text-slate-950">{{ $resumo['rascunhos'] }}</p>
            </div>
            <div class="rounded-lg border border-amber-200 bg-amber-50 p-5 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wide text-amber-700">Começam em 7 dias</p>
                <p class="mt-2 text-3xl font-bold text-amber-800">{{ $resumo['proximos_7_dias'] }}</p>
            </div>
            <div class="rounded-lg border border-rose-200 bg-rose-50 p-5 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wide text-rose-700">Com tarefa atrasada</p>
                <p class="mt-2 text-3xl font-bold text-rose-800">{{ $resumo['atrasados'] }}</p>
            </div>
        </div>

        <form method="GET" class="flex flex-wrap items-end gap-3 rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
            <div>
                <label class="block text-xs font-semibold text-slate-600">Situação</label>
                <select name="status" class="mt-1 rounded-md border-slate-300 text-sm">
                    <option value="abertos" @selected($status === 'abertos')>Em andamento</option>
                    <option value="todos" @selected($status === 'todos')>Todos</option>
                    <option value="rascunho" @selected($status === 'rascunho')>Rascunho</option>
                    <option value="pronto" @selected($status === 'pronto')>Pronto</option>
                    <option value="concluido" @selected($status === 'concluido')>Concluído</option>
                    <option value="cancelado" @selected($status === 'cancelado')>Cancelado</option>
                </select>
            </div>
            <div class="min-w-64 flex-1">
                <label class="block text-xs font-semibold text-slate-600">Buscar</label>
                <input type="text" name="q" value="{{ $busca }}" placeholder="Nome, matrícula ou código" class="mt-1 w-full rounded-md border-slate-300 text-sm">
            </div>
            <button type="submit" class="btn-coreti-secondary text-sm">Filtrar</button>
        </form>

        <div class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50">
                    <tr class="text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                        <th class="px-4 py-3">Código</th>
                        <th class="px-4 py-3">Colaborador</th>
                        <th class="px-4 py-3">Cargo / Unidade</th>
                        <th class="px-4 py-3">Início</th>
                        <th class="px-4 py-3">Situação</th>
                        <th class="px-4 py-3">Tarefas</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($cases as $case)
                        <tr class="hover:bg-slate-50">
                            <td class="px-4 py-3">
                                <a href="{{ route('admissao.cases.show', $case) }}" class="font-semibold text-slate-900 hover:underline">{{ $case->codigo }}</a>
                                <p class="text-xs text-slate-400">{{ $case->tipoLabel() }}</p>
                            </td>
                            <td class="px-4 py-3">
                                <p class="font-medium text-slate-800">{{ $case->nome }}</p>
                                <p class="text-xs text-slate-400">{{ $case->matricula ?: 'sem matrícula' }}</p>
                            </td>
                            <td class="px-4 py-3 text-slate-600">
                                <p>{{ $case->cargo_nome ?: '-' }}</p>
                                <p class="text-xs text-slate-400">{{ $case->unidade_nome ?: '-' }}</p>
                            </td>
                            <td class="px-4 py-3 text-slate-600">
                                @if($case->data_inicio)
                                    {{ $case->data_inicio->format('d/m/Y') }}
                                    @php $dias = $case->diasParaInicio(); @endphp
                                    <p class="text-xs {{ $dias !== null && $dias <= 3 ? 'text-rose-600' : 'text-slate-400' }}">
                                        {{ $dias === null ? '' : ($dias >= 0 ? "em {$dias} dia(s)" : 'já iniciou') }}
                                    </p>
                                @else
                                    <span class="text-slate-400">a definir</span>
                                @endif
                            </td>
                            <td class="px-4 py-3">
                                <span class="inline-flex rounded-full bg-slate-100 px-2 py-0.5 text-xs font-semibold text-slate-700">
                                    {{ $case->statusLabel() }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-slate-600">
                                {{ $case->tasks_count - $case->tasks_pendentes_count }}/{{ $case->tasks_count }}
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-4 py-8 text-center text-slate-500">Nenhum caso encontrado.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{ $cases->links() }}
    </div>
</x-app-layout>
