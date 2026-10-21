<x-app-layout>
    <div class="mx-auto max-w-6xl space-y-6 px-4 py-8 sm:px-6">
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Telefonia Corporativa</p>
                <h1 class="mt-1 text-2xl font-bold tracking-tight text-slate-900 sm:text-3xl">{{ $device->descricao }}</h1>
                <p class="mt-1 font-mono text-sm text-slate-500">{{ $device->imei_1_formatado ? 'IMEI '.$device->imei_1_formatado : 'Sem IMEI cadastrado' }} · {{ $device->scope_label }}</p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <a href="{{ route('administrativo.telefonia.devices.index') }}" class="inline-flex min-h-10 items-center justify-center rounded-md border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-700 hover:bg-slate-50">Voltar</a>
                @can('module.telefonia.manage')
                    <a href="{{ route('administrativo.telefonia.devices.edit', $device) }}" style="background-color: #033151; border-color: #033151;" class="inline-flex min-h-10 items-center justify-center rounded-lg px-4 text-sm font-semibold text-white hover:opacity-90">Editar</a>
                @endcan
            </div>
        </div>

        @include('partials.modulo-flash')

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
            <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm lg:col-span-2">
                <h2 class="text-sm font-semibold uppercase tracking-wide text-slate-500">Dados do aparelho</h2>
                <dl class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div><dt class="text-xs text-slate-500">Marca</dt><dd class="text-sm font-medium text-slate-900">{{ $device->marca }}</dd></div>
                    <div><dt class="text-xs text-slate-500">Modelo</dt><dd class="text-sm font-medium text-slate-900">{{ $device->modelo }}</dd></div>
                    <div><dt class="text-xs text-slate-500">IMEI 1</dt><dd class="font-mono text-sm font-medium text-slate-900">{{ $device->imei_1_formatado ?: '—' }}</dd></div>
                    <div><dt class="text-xs text-slate-500">IMEI 2</dt><dd class="font-mono text-sm font-medium text-slate-900">{{ $device->imei_2_formatado ?: '—' }}</dd></div>
                    <div><dt class="text-xs text-slate-500">Número de série</dt><dd class="text-sm font-medium text-slate-900">{{ $device->numero_serie ?: '—' }}</dd></div>
                    <div>
                        <dt class="text-xs text-slate-500">Estado</dt>
                        <dd><span class="inline-flex items-center rounded-full px-2 py-1 text-xs font-semibold ring-1 ring-inset {{ $device->estado_aparelho === \App\Models\CorpDevice::ESTADO_NOVO ? 'bg-indigo-50 text-indigo-700 ring-indigo-200' : 'bg-slate-100 text-slate-600 ring-slate-200' }}">{{ $device->estado_aparelho }}</span></dd>
                    </div>
                    <div>
                        <dt class="text-xs text-slate-500">Situação</dt>
                        <dd>
                            @php
                                $cor = match ($device->status) {
                                    \App\Models\CorpDevice::STATUS_DISPONIVEL => 'bg-emerald-50 text-emerald-700 ring-emerald-200',
                                    \App\Models\CorpDevice::STATUS_EM_USO => 'bg-sky-50 text-sky-700 ring-sky-200',
                                    default => 'bg-slate-100 text-slate-600 ring-slate-200',
                                };
                            @endphp
                            <span class="inline-flex items-center rounded-full px-2 py-1 text-xs font-semibold ring-1 ring-inset {{ $cor }}">{{ $device->status }}</span>
                        </dd>
                    </div>
                    <div><dt class="text-xs text-slate-500">Operação</dt><dd class="text-sm font-medium text-slate-900">{{ $device->scope_label }}</dd></div>
                </dl>

                @if ($device->observacao)
                    <div class="mt-4 border-t border-slate-100 pt-4">
                        <dt class="text-xs text-slate-500">Observação</dt>
                        <dd class="mt-1 text-sm text-slate-700">{{ $device->observacao }}</dd>
                    </div>
                @endif

                <div class="mt-4 border-t border-slate-100 pt-4 text-xs text-slate-500">
                    Cadastrado em {{ $device->created_at?->format('d/m/Y H:i') }}{{ $device->createdBy ? ' por '.$device->createdBy->name : '' }}
                    @if ($device->updated_at && $device->updated_at->ne($device->created_at))
                        · alterado em {{ $device->updated_at->format('d/m/Y H:i') }}{{ $device->updatedBy ? ' por '.$device->updatedBy->name : '' }}
                    @endif
                    @if ($device->origem_gestaoti_id)
                        <span class="ml-1 rounded bg-slate-100 px-1.5 py-0.5">importado do gestaoTI · registro #{{ $device->origem_gestaoti_id }}</span>
                    @endif
                </div>
            </section>

            <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <h2 class="text-sm font-semibold uppercase tracking-wide text-slate-500">Com quem está</h2>
                @if ($vinculoAtivo)
                    <p class="mt-3 text-sm font-semibold text-slate-900">{{ $vinculoAtivo->collaborator?->name }}</p>
                    <p class="text-xs text-slate-500">Matrícula {{ $vinculoAtivo->collaborator_matricula }}</p>
                    <p class="mt-2 text-xs text-slate-500">Desde {{ $vinculoAtivo->data_vinculo?->format('d/m/Y') }}</p>
                    @php $linhaDoKit = $vinculoAtivo->items->firstWhere('corp_line_id', '!=', null); @endphp
                    @if ($linhaDoKit?->line)
                        <p class="mt-2 font-mono text-xs text-slate-600">Com a linha {{ $linhaDoKit->line->numero_formatado }}</p>
                    @endif
                    <a href="{{ route('administrativo.telefonia.assignments.show', $vinculoAtivo) }}" class="mt-3 inline-flex items-center justify-center rounded-md border border-slate-300 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50">Abrir vínculo</a>
                @else
                    <p class="mt-3 text-sm text-slate-500">Nenhum vínculo ativo.</p>
                    @if ($device->status === \App\Models\CorpDevice::STATUS_EM_USO)
                        <p class="mt-2 rounded-md bg-amber-50 px-3 py-2 text-xs text-amber-900">
                            O aparelho está marcado como "Em Uso" mas não tem vínculo ativo — ele não aparece como disponível para novo vínculo.
                        </p>
                    @endif
                @endif
            </section>
        </div>

        <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="text-sm font-semibold uppercase tracking-wide text-slate-500">Histórico de vínculos</h2>
            @if ($vinculos->isEmpty())
                <p class="mt-3 text-sm text-slate-500">Este aparelho nunca foi vinculado a um colaborador.</p>
            @else
                <div class="mt-3 overflow-x-auto">
                    <table class="w-full min-w-[720px] text-left text-sm">
                        <thead>
                            <tr class="border-b border-slate-100 text-xs font-semibold uppercase tracking-wide text-slate-400"><th class="px-3 py-2">Colaborador</th><th class="px-3 py-2">Período</th><th class="px-3 py-2">Situação</th><th class="px-3 py-2">Motivo da devolução</th><th class="px-3 py-2"></th></tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach ($vinculos as $vinculo)
                                <tr class="transition hover:bg-slate-50">
                                    <td class="px-3 py-2">
                                        <p class="text-slate-900">{{ $vinculo->collaborator?->name ?? '—' }}</p>
                                        <p class="text-xs text-slate-500">Matrícula {{ $vinculo->collaborator_matricula }}</p>
                                    </td>
                                    <td class="px-3 py-2 text-slate-600">{{ $vinculo->data_vinculo?->format('d/m/Y') }} → {{ $vinculo->data_desvinculo?->format('d/m/Y') ?? 'hoje' }}</td>
                                    <td class="px-3 py-2"><span class="inline-flex items-center rounded-full px-2 py-1 text-xs font-semibold ring-1 ring-inset {{ $vinculo->ativo ? 'bg-sky-50 text-sky-700 ring-sky-200' : 'bg-slate-100 text-slate-600 ring-slate-200' }}">{{ $vinculo->ativo ? 'Ativo' : 'Encerrado' }}</span></td>
                                    <td class="px-3 py-2 text-xs text-slate-600">{{ $vinculo->motivo_desvinculo ?: '—' }}</td>
                                    <td class="px-3 py-2 text-right"><a href="{{ route('administrativo.telefonia.assignments.show', $vinculo) }}" class="inline-flex items-center justify-center rounded-md border border-slate-300 bg-white px-3 py-1 text-xs font-semibold text-slate-700 hover:bg-slate-50">Abrir</a></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>
    </div>
</x-app-layout>
