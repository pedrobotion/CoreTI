<x-app-layout>
    <div class="mx-auto max-w-6xl space-y-6 px-4 py-8 sm:px-6">
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Telefonia Corporativa</p>
                <h1 class="mt-1 font-mono text-2xl font-bold tracking-tight text-slate-900 sm:text-3xl">{{ $line->numero_formatado }}</h1>
                <p class="mt-1 text-sm text-slate-500">{{ $line->operadora }} · {{ $line->scope_label }}</p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <a href="{{ route('administrativo.telefonia.lines.index') }}" class="inline-flex min-h-10 items-center justify-center rounded-md border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-700 hover:bg-slate-50">Voltar</a>
                @can('module.telefonia.manage')
                    <a href="{{ route('administrativo.telefonia.lines.edit', $line) }}" style="background-color: #033151; border-color: #033151;" class="inline-flex min-h-10 items-center justify-center rounded-lg px-4 text-sm font-semibold text-white hover:opacity-90">Editar</a>
                @endcan
            </div>
        </div>

        @include('partials.modulo-flash')

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
            <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm lg:col-span-2">
                <h2 class="text-sm font-semibold uppercase tracking-wide text-slate-500">Dados da linha</h2>
                <dl class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div><dt class="text-xs text-slate-500">Operadora</dt><dd class="text-sm font-medium text-slate-900">{{ $line->operadora }}</dd></div>
                    <div><dt class="text-xs text-slate-500">Plano</dt><dd class="text-sm font-medium text-slate-900">{{ $line->nome_plano ?: '—' }}</dd></div>
                    <div>
                        <dt class="text-xs text-slate-500">Valor mensal</dt>
                        <dd class="text-sm font-medium text-slate-900">{{ $line->valor_mensal !== null ? 'R$ '.number_format((float) $line->valor_mensal, 2, ',', '.') : '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-slate-500">Situação</dt>
                        <dd>
                            @php
                                $cor = match ($line->status) {
                                    \App\Models\CorpLine::STATUS_DISPONIVEL => 'bg-emerald-100 text-emerald-700',
                                    \App\Models\CorpLine::STATUS_EM_USO => 'bg-sky-100 text-sky-700',
                                    default => 'bg-slate-200 text-slate-600',
                                };
                            @endphp
                            <span class="rounded-full px-2 py-1 text-xs font-medium {{ $cor }}">{{ $line->status }}</span>
                        </dd>
                    </div>
                    @if ($line->data_cancelamento)
                        <div><dt class="text-xs text-slate-500">Cancelada em</dt><dd class="text-sm font-medium text-slate-900">{{ $line->data_cancelamento->format('d/m/Y') }}</dd></div>
                    @endif
                    <div><dt class="text-xs text-slate-500">Operação</dt><dd class="text-sm font-medium text-slate-900">{{ $line->scope_label }}</dd></div>
                </dl>

                @if ($line->observacao)
                    <div class="mt-4 border-t border-slate-100 pt-4">
                        <dt class="text-xs text-slate-500">Observação</dt>
                        <dd class="mt-1 text-sm text-slate-700">{{ $line->observacao }}</dd>
                    </div>
                @endif

                <div class="mt-4 border-t border-slate-100 pt-4 text-xs text-slate-500">
                    Cadastrada em {{ $line->created_at?->format('d/m/Y H:i') }}{{ $line->createdBy ? ' por '.$line->createdBy->name : '' }}
                    @if ($line->updated_at && $line->updated_at->ne($line->created_at))
                        · alterada em {{ $line->updated_at->format('d/m/Y H:i') }}{{ $line->updatedBy ? ' por '.$line->updatedBy->name : '' }}
                    @endif
                    @if ($line->origem_gestaoti_id)
                        <span class="ml-1 rounded bg-slate-100 px-1.5 py-0.5">importada do gestaoTI · registro #{{ $line->origem_gestaoti_id }}</span>
                    @endif
                </div>
            </section>

            <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <h2 class="text-sm font-semibold uppercase tracking-wide text-slate-500">Com quem está</h2>
                @if ($vinculoAtivo)
                    <p class="mt-3 text-sm font-semibold text-slate-900">{{ $vinculoAtivo->collaborator?->name }}</p>
                    <p class="text-xs text-slate-500">Matrícula {{ $vinculoAtivo->collaborator_matricula }}</p>
                    <p class="mt-2 text-xs text-slate-500">Desde {{ $vinculoAtivo->data_vinculo?->format('d/m/Y') }} · vence {{ $vinculoAtivo->data_vencimento?->format('d/m/Y') }}</p>
                    <a href="{{ route('administrativo.telefonia.assignments.show', $vinculoAtivo) }}" class="mt-3 inline-block rounded-md border border-slate-300 px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50">Abrir vínculo</a>
                @else
                    <p class="mt-3 text-sm text-slate-500">Nenhum vínculo ativo.</p>
                    @if ($line->status === \App\Models\CorpLine::STATUS_EM_USO)
                        <p class="mt-2 rounded-md bg-amber-50 px-3 py-2 text-xs text-amber-900">
                            A linha está marcada como "Em Uso" mas não tem vínculo ativo — ela não aparece como disponível para novo vínculo.
                        </p>
                    @endif
                @endif
            </section>
        </div>

        <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="text-sm font-semibold uppercase tracking-wide text-slate-500">Histórico de vínculos</h2>
            @if ($vinculos->isEmpty())
                <p class="mt-3 text-sm text-slate-500">Esta linha nunca foi vinculada a um colaborador.</p>
            @else
                <div class="mt-3 overflow-x-auto">
                    <table class="w-full min-w-[720px] text-left text-sm">
                        <thead class="border-b border-slate-100 text-xs font-semibold uppercase tracking-wide text-slate-400">
                            <tr><th class="px-3 py-2">Colaborador</th><th class="px-3 py-2">Período</th><th class="px-3 py-2">Situação</th><th class="px-3 py-2">Motivo da devolução</th><th class="px-3 py-2"></th></tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach ($vinculos as $vinculo)
                                <tr>
                                    <td class="px-3 py-2">
                                        <p class="text-slate-900">{{ $vinculo->collaborator?->name ?? '—' }}</p>
                                        <p class="text-xs text-slate-500">Matrícula {{ $vinculo->collaborator_matricula }}</p>
                                    </td>
                                    <td class="px-3 py-2 text-slate-600">
                                        {{ $vinculo->data_vinculo?->format('d/m/Y') }}
                                        → {{ $vinculo->data_desvinculo?->format('d/m/Y') ?? 'hoje' }}
                                    </td>
                                    <td class="px-3 py-2">
                                        <span class="rounded-full px-2 py-1 text-xs font-medium {{ $vinculo->ativo ? 'bg-sky-100 text-sky-700' : 'bg-slate-200 text-slate-600' }}">{{ $vinculo->ativo ? 'Ativo' : 'Encerrado' }}</span>
                                    </td>
                                    <td class="px-3 py-2 text-xs text-slate-600">{{ $vinculo->motivo_desvinculo ?: '—' }}</td>
                                    <td class="px-3 py-2 text-right">
                                        <a href="{{ route('administrativo.telefonia.assignments.show', $vinculo) }}" class="rounded-md border border-slate-300 px-3 py-1 text-xs font-medium text-slate-700 hover:bg-slate-50">Abrir</a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>

        <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="text-sm font-semibold uppercase tracking-wide text-slate-500">Histórico de cobrança</h2>
            @if ($faturas->isEmpty())
                <p class="mt-3 text-sm text-slate-500">Nenhuma fatura importada com esta linha ainda.</p>
            @else
                <div class="mt-3 overflow-x-auto">
                    <table class="w-full min-w-[620px] text-left text-sm">
                        <thead class="border-b border-slate-100 text-xs font-semibold uppercase tracking-wide text-slate-400">
                            <tr><th class="px-3 py-2">Competência</th><th class="px-3 py-2">Plano na fatura</th><th class="px-3 py-2 text-right">Valor</th><th class="px-3 py-2 text-right">Variação</th><th class="px-3 py-2"></th></tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach ($faturas as $item)
                                <tr>
                                    <td class="px-3 py-2 text-slate-700">{{ $item->competencia?->format('m/Y') }}</td>
                                    <td class="px-3 py-2 text-slate-600">{{ $item->nome_plano ?: '—' }}</td>
                                    <td class="px-3 py-2 text-right font-mono text-slate-900">R$ {{ number_format((float) $item->valor, 2, ',', '.') }}</td>
                                    <td class="px-3 py-2 text-right">
                                        @if ($item->variacao_percentual !== null)
                                            <span class="font-medium {{ $item->variacao_percentual > 0 ? 'text-rose-700' : ($item->variacao_percentual < 0 ? 'text-emerald-700' : 'text-slate-500') }}">
                                                {{ $item->variacao_percentual > 0 ? '+' : '' }}{{ number_format((float) $item->variacao_percentual, 2, ',', '.') }}%
                                            </span>
                                        @else
                                            <span class="text-slate-400">—</span>
                                        @endif
                                    </td>
                                    <td class="px-3 py-2 text-right">
                                        @if ($item->fatura)
                                            <a href="{{ route('administrativo.telefonia.faturas.show', $item->fatura) }}" class="rounded-md border border-slate-300 px-3 py-1 text-xs font-medium text-slate-700 hover:bg-slate-50">Fatura</a>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>
    </div>
</x-app-layout>
