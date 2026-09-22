<x-app-layout>
    <div class="mx-auto max-w-6xl space-y-6">

        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <div class="flex items-center gap-3">
                    <h1 class="text-xl font-bold text-slate-950">{{ $case->codigo }}</h1>
                    <span class="inline-flex rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-semibold text-slate-700">{{ $case->statusLabel() }}</span>
                </div>
                <p class="text-sm text-slate-500">{{ $case->nome }} — {{ $case->cargo_nome ?: 'cargo não informado' }} @ {{ $case->unidade_nome ?: 'unidade não informada' }}</p>
            </div>

            <div class="flex flex-wrap gap-2">
                @can('update', $case)
                    <a href="{{ route('admissao.cases.edit', $case) }}" class="btn-coreti-secondary text-sm">Editar</a>
                @endcan

                @can('submit', $case)
                    <form method="POST" action="{{ route('admissao.cases.submit', $case) }}" data-confirm-message="Enviar o caso e abrir os chamados para as áreas responsáveis?">
                        @csrf
                        <button type="submit" class="btn-coreti-primary text-sm">Enviar para provisionamento</button>
                    </form>
                @endcan

                @can('redispatch', $case)
                    @if($case->tasks->whereNull('dispatched_at')->whereNotIn('status', \App\Models\AdmissionTask::STATUS_FINALIZADOS)->isNotEmpty())
                        <form method="POST" action="{{ route('admissao.cases.redispatch', $case) }}">
                            @csrf
                            <button type="submit" class="btn-coreti-secondary text-sm">Reenviar chamados</button>
                        </form>
                    @endif
                @endcan
            </div>
        </div>

        @include('admissao.partials.flash')

        @if($case->isCancelado())
            <div class="rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">
                <p class="font-semibold">Admissão cancelada.</p>
                <p>{{ $case->cancel_reason }}</p>
                <p class="mt-1 text-xs">Itens já provisionados precisam ser revertidos manualmente — veja o histórico abaixo.</p>
            </div>
        @endif

        @if($preview['sem_perfil'])
            <div class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                Nenhum perfil de acesso corresponde a este cargo/área. Cadastre um perfil específico ou um perfil padrão em
                <a href="{{ route('admissao.profiles.index') }}" class="font-semibold underline">Perfis de acesso</a>.
            </div>
        @endif

        <div class="grid gap-6 lg:grid-cols-3">

            <section class="lg:col-span-2 space-y-6">
                @foreach($tasksPorArea as $area => $tasks)
                    <div class="rounded-lg border border-slate-200 bg-white shadow-sm">
                        <div class="flex items-center justify-between border-b border-slate-200 px-5 py-3">
                            <h2 class="text-sm font-semibold text-slate-900">{{ config("admissao.areas.{$area}.label", $area) }}</h2>
                            @if($tasks->first()?->jira_issue_key)
                                <span class="text-xs text-slate-500">
                                    Chamado:
                                    @if($tasks->first()->jira_issue_url)
                                        <a href="{{ $tasks->first()->jira_issue_url }}" target="_blank" rel="noopener" class="font-semibold text-slate-700 hover:underline">{{ $tasks->first()->jira_issue_key }}</a>
                                    @else
                                        <span class="font-semibold text-slate-700">{{ $tasks->first()->jira_issue_key }}</span>
                                    @endif
                                </span>
                            @endif
                        </div>

                        <div class="divide-y divide-slate-100">
                            @foreach($tasks as $task)
                                <div class="px-5 py-3">
                                    <div class="flex flex-wrap items-start justify-between gap-2">
                                        <div>
                                            <p class="text-sm font-medium text-slate-800">
                                                {{ $task->label() }}
                                                @unless($task->obrigatorio)<span class="text-xs text-slate-400">(opcional)</span>@endunless
                                            </p>
                                            <p class="text-xs {{ $task->estaAtrasado() ? 'text-rose-600 font-semibold' : 'text-slate-400' }}">
                                                {{ $task->statusLabel() }}
                                                @if($task->due_at) · prazo {{ $task->due_at->format('d/m/Y') }} @endif
                                                @if($task->estaAtrasado()) · atrasada @endif
                                            </p>
                                            @if($task->bloqueio_motivo)
                                                <p class="mt-1 text-xs text-rose-700">Bloqueio: {{ $task->bloqueio_motivo }}</p>
                                            @endif
                                            @if($task->resultado)
                                                <p class="mt-1 text-xs text-emerald-700">
                                                    @foreach($task->resultado as $chave => $valor)
                                                        <span class="mr-2">{{ $chave }}: <strong>{{ $valor }}</strong></span>
                                                    @endforeach
                                                </p>
                                            @endif
                                        </div>

                                        @can('executeTasks', $case)
                                            @unless($task->isFinalizado())
                                                <div class="flex flex-wrap items-center gap-2">
                                                    <form method="POST" action="{{ route('admissao.tasks.complete', $task) }}" class="flex items-center gap-1">
                                                        @csrf
                                                        <input type="text" name="resultado[registro]" placeholder="Resultado (e-mail, patrimônio...)" class="w-52 rounded-md border-slate-300 text-xs">
                                                        <button type="submit" class="btn-coreti-secondary text-xs">Concluir</button>
                                                    </form>
                                                    <form method="POST" action="{{ route('admissao.tasks.skip', $task) }}" data-confirm-message="Marcar como não aplicável?">
                                                        @csrf
                                                        <button type="submit" class="text-xs text-slate-500 hover:underline">N/A</button>
                                                    </form>
                                                </div>
                                            @endunless
                                        @endcan
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endforeach

                @if($tasksPorArea->isEmpty())
                    <div class="rounded-lg border border-slate-200 bg-white p-8 text-center text-sm text-slate-500 shadow-sm">
                        Nenhuma tarefa gerada ainda.
                    </div>
                @endif
            </section>

            <aside class="space-y-6">
                <div class="rounded-lg border border-slate-200 bg-white shadow-sm">
                    <div class="border-b border-slate-200 px-5 py-3">
                        <h2 class="text-sm font-semibold text-slate-900">Dados do colaborador</h2>
                    </div>
                    <dl class="space-y-2 px-5 py-4 text-sm">
                        @foreach([
                            'Matrícula' => $case->matricula,
                            'CPF' => $case->cpf,
                            'Centro de custo' => $case->centro_custo,
                            'Gestor' => $case->gestor_nome,
                            'Admissão' => optional($case->data_admissao)->format('d/m/Y'),
                            'Início' => optional($case->data_inicio)->format('d/m/Y'),
                            'Uniforme' => $case->tamanho_uniforme,
                            'Calçado' => $case->tamanho_calcado,
                            'Substitui' => $case->substitui_nome,
                        ] as $rotulo => $valor)
                            <div class="flex justify-between gap-3">
                                <dt class="text-slate-500">{{ $rotulo }}</dt>
                                <dd class="text-right font-medium text-slate-800">{{ $valor ?: '-' }}</dd>
                            </div>
                        @endforeach
                    </dl>
                </div>

                @if($preview['perfis'])
                    <div class="rounded-lg border border-slate-200 bg-white shadow-sm">
                        <div class="border-b border-slate-200 px-5 py-3">
                            <h2 class="text-sm font-semibold text-slate-900">Perfis aplicados</h2>
                        </div>
                        <ul class="space-y-1 px-5 py-4 text-sm text-slate-700">
                            @foreach($preview['perfis'] as $perfil)<li>{{ $perfil }}</li>@endforeach
                        </ul>
                    </div>
                @endif

                @can('cancel', $case)
                    <div class="rounded-lg border border-rose-200 bg-white shadow-sm">
                        <div class="border-b border-rose-100 px-5 py-3">
                            <h2 class="text-sm font-semibold text-rose-800">Cancelar admissão</h2>
                            <p class="text-xs text-slate-500">Use quando o colaborador desistir ou não comparecer.</p>
                        </div>
                        <form method="POST" action="{{ route('admissao.cases.cancel', $case) }}" class="space-y-2 px-5 py-4" data-confirm-message="Cancelar esta admissão?">
                            @csrf
                            <input type="text" name="cancel_reason" required placeholder="Motivo" class="w-full rounded-md border-slate-300 text-sm">
                            <button type="submit" class="btn-coreti-secondary text-xs">Cancelar admissão</button>
                        </form>
                    </div>
                @endcan

                <div class="rounded-lg border border-slate-200 bg-white shadow-sm">
                    <div class="border-b border-slate-200 px-5 py-3">
                        <h2 class="text-sm font-semibold text-slate-900">Histórico</h2>
                    </div>
                    <ul class="max-h-96 space-y-3 overflow-y-auto px-5 py-4 text-xs">
                        @forelse($case->events as $event)
                            <li>
                                <p class="font-medium text-slate-700">{{ $event->descricao ?: $event->event }}</p>
                                <p class="text-slate-400">
                                    {{ $event->created_at->format('d/m/Y H:i') }}
                                    @if($event->user) · {{ $event->user->name }} @endif
                                </p>
                            </li>
                        @empty
                            <li class="text-slate-500">Sem eventos.</li>
                        @endforelse
                    </ul>
                </div>
            </aside>
        </div>
    </div>
</x-app-layout>
