<x-app-layout>
    <div class="mx-auto max-w-7xl space-y-6">

        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="text-xl font-bold text-slate-950">Fila de execução</h1>
                <p class="text-sm text-slate-500">Tarefas agrupadas por tipo — é aqui que a TI faz as criações em lote.</p>
            </div>
            <a href="{{ route('admissao.cases.index') }}" class="btn-coreti-secondary text-sm">Voltar aos casos</a>
        </div>

        @include('admissao.partials.flash')

        <form method="GET" class="flex flex-wrap items-end gap-3 rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
            <div>
                <label class="block text-xs font-semibold text-slate-600">Área</label>
                <select name="area" class="mt-1 rounded-md border-slate-300 text-sm">
                    <option value="">Todas</option>
                    @foreach(config('admissao.areas') as $chave => $definicao)
                        <option value="{{ $chave }}" @selected($area === $chave)>{{ $definicao['label'] }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-600">Tipo de tarefa</label>
                <select name="tipo" class="mt-1 rounded-md border-slate-300 text-sm">
                    <option value="">Todos</option>
                    @foreach(config('admissao.task_types') as $chave => $definicao)
                        <option value="{{ $chave }}" @selected($tipo === $chave)>{{ $definicao['label'] }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-600">Janela (dias)</label>
                <input type="number" name="janela" value="{{ $janela }}" min="0" max="180" class="mt-1 w-24 rounded-md border-slate-300 text-sm">
            </div>
            <button type="submit" class="btn-coreti-secondary text-sm">Filtrar</button>
        </form>

        @forelse($porTipo as $taskType => $tasks)
            <section class="rounded-lg border border-slate-200 bg-white shadow-sm">
                <div class="flex items-center justify-between border-b border-slate-200 px-5 py-3">
                    <h2 class="text-sm font-semibold text-slate-900">
                        {{ config("admissao.task_types.{$taskType}.label", $taskType) }}
                    </h2>
                    <span class="text-xs font-semibold text-slate-500">{{ $tasks->count() }} pendente(s)</span>
                </div>

                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50">
                        <tr class="text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                            <th class="px-4 py-2">Colaborador</th>
                            <th class="px-4 py-2">Unidade</th>
                            <th class="px-4 py-2">Início</th>
                            <th class="px-4 py-2">Prazo</th>
                            <th class="px-4 py-2">Chamado</th>
                            <th class="px-4 py-2">Registrar conclusão</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach($tasks as $task)
                            <tr class="{{ $task->estaAtrasado() ? 'bg-rose-50' : '' }}">
                                <td class="px-4 py-2">
                                    <a href="{{ route('admissao.cases.show', $task->admissionCase) }}" class="font-medium text-slate-800 hover:underline">
                                        {{ $task->admissionCase->nome }}
                                    </a>
                                    <p class="text-xs text-slate-400">{{ $task->admissionCase->codigo }} · {{ $task->admissionCase->matricula ?: 'sem matrícula' }}</p>
                                </td>
                                <td class="px-4 py-2 text-slate-600">{{ $task->admissionCase->unidade_nome ?: '-' }}</td>
                                <td class="px-4 py-2 text-slate-600">{{ optional($task->admissionCase->data_inicio)->format('d/m/Y') ?: '-' }}</td>
                                <td class="px-4 py-2 {{ $task->estaAtrasado() ? 'font-semibold text-rose-700' : 'text-slate-600' }}">
                                    {{ optional($task->due_at)->format('d/m/Y') ?: '-' }}
                                </td>
                                <td class="px-4 py-2 text-xs text-slate-500">
                                    @if($task->jira_issue_url)
                                        <a href="{{ $task->jira_issue_url }}" target="_blank" rel="noopener" class="hover:underline">{{ $task->jira_issue_key }}</a>
                                    @else
                                        {{ $task->jira_issue_key ?: '-' }}
                                    @endif
                                </td>
                                <td class="px-4 py-2">
                                    @can('executeTasks', $task->admissionCase)
                                        <form method="POST" action="{{ route('admissao.tasks.complete', $task) }}" class="flex items-center gap-1">
                                            @csrf
                                            <input type="text" name="resultado[registro]" placeholder="Resultado" class="w-40 rounded-md border-slate-300 text-xs">
                                            <button type="submit" class="btn-coreti-secondary text-xs">OK</button>
                                        </form>
                                    @else
                                        <span class="text-xs text-slate-400">sem permissão</span>
                                    @endcan
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </section>
        @empty
            <div class="rounded-lg border border-slate-200 bg-white p-8 text-center text-sm text-slate-500 shadow-sm">
                Nenhuma tarefa pendente na janela selecionada.
            </div>
        @endforelse

        {{ $tasks->links() }}
    </div>
</x-app-layout>
