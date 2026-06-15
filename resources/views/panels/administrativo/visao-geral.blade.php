<x-app-layout>
    <div class="mx-auto max-w-7xl space-y-6">
        <div>
            <p class="text-sm font-semibold uppercase tracking-wide text-slate-500">Administrativo</p>
            <h1 class="mt-1 text-2xl font-bold text-slate-950">Consulta de Equipamento</h1>
            <p class="mt-2 text-sm text-slate-600">Localize um equipamento por plaqueta e veja dados completos, anexos e histórico.</p>
        </div>

        <section class="grid gap-4 lg:grid-cols-[360px_minmax(0,1fr)]">
            <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <h2 class="text-base font-bold text-slate-900">Consulta de Equipamento</h2>
                        <p class="mt-1 text-sm text-slate-600">Digite a plaqueta para localizar o equipamento em qualquer status.</p>
                    </div>
                    <span class="rounded-md bg-slate-100 px-2 py-1 text-xs font-semibold text-slate-700">Busca global</span>
                </div>

                <form method="GET" action="{{ route('administrativo.visao-geral') }}" class="mt-4 space-y-3">
                    <input
                        type="text"
                        name="equipment_plaqueta"
                        value="{{ $equipmentSearchPlaqueta ?? request('equipment_plaqueta', '') }}"
                        placeholder="Digite a plaqueta do equipamento"
                        class="h-10 w-full rounded-md border-slate-300 text-sm"
                    >
                    <div class="flex gap-2">
                        <button type="submit" class="inline-flex h-10 flex-1 items-center justify-center rounded-md border border-[#033151] bg-[#033151] px-4 text-sm font-semibold text-white shadow-sm transition hover:opacity-90">Buscar equipamento</button>
                        <a href="{{ route('administrativo.visao-geral') }}" class="inline-flex h-10 items-center justify-center rounded-md border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50">Limpar</a>
                    </div>
                </form>
            </div>

            <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                @if(filled($equipmentSearchPlaqueta ?? ''))
                    @if(($equipmentSearchResults ?? collect())->count() > 1 && !($equipmentSearchExactMatch ?? false))
                        <div class="rounded-lg border border-slate-200 bg-slate-50 p-4">
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <div>
                                    <h3 class="text-sm font-bold uppercase tracking-wide text-slate-700">Mais de um equipamento encontrado</h3>
                                    <p class="mt-1 text-sm text-slate-600">Selecione um registro para abrir a consulta completa.</p>
                                </div>
                                <span class="rounded-md bg-slate-100 px-2 py-1 text-xs font-semibold text-slate-700">{{ ($equipmentSearchResults ?? collect())->count() }} resultados</span>
                            </div>
                            <div class="mt-4 overflow-x-auto">
                                <table class="w-full min-w-[720px] text-sm">
                                    <thead class="bg-slate-900 text-left text-xs font-semibold uppercase text-white">
                                        <tr>
                                            <th class="px-3 py-2">Plaqueta</th>
                                            <th class="px-3 py-2">Tipo</th>
                                            <th class="px-3 py-2">Status</th>
                                            <th class="px-3 py-2">Unidade/Setor</th>
                                            <th class="px-3 py-2">Chegada</th>
                                            <th class="px-3 py-2">Ação</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-200">
                                        @foreach($equipmentSearchResults as $candidate)
                                            <tr class="odd:bg-white even:bg-slate-50">
                                                <td class="px-3 py-2">{{ $candidate->plaqueta }}</td>
                                                <td class="px-3 py-2">{{ $candidate->tipo_equipamento ?: '-' }}</td>
                                                <td class="px-3 py-2">{{ $candidate->status ?: '-' }}</td>
                                                <td class="px-3 py-2">{{ $candidate->unidade_setor ?: '-' }}</td>
                                                <td class="px-3 py-2">{{ optional($candidate->data_chegada)->format('d/m/Y') ?: '-' }}</td>
                                                <td class="px-3 py-2">
                                                    <a href="{{ route('administrativo.visao-geral', ['equipment_plaqueta' => $candidate->plaqueta]) }}" class="inline-flex rounded-md border border-[#033151] bg-white px-3 py-1.5 text-xs font-semibold text-[#033151] hover:bg-slate-50">Ver detalhes</a>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    @elseif($equipmentSearchRecord)
                        <div class="grid gap-4 lg:grid-cols-2">
                            <div class="rounded-lg border border-slate-200 bg-slate-50 p-4">
                                <h3 class="text-sm font-bold uppercase tracking-wide text-slate-700">Dados principais</h3>
                                <div class="mt-3 grid gap-3 sm:grid-cols-2">
                                    <div><p class="text-xs font-semibold uppercase text-slate-500">ID</p><p class="mt-1 text-sm text-slate-900">{{ $equipmentSearchRecord->id }}</p></div>
                                    <div><p class="text-xs font-semibold uppercase text-slate-500">Plaqueta</p><p class="mt-1 text-sm text-slate-900">{{ $equipmentSearchRecord->plaqueta }}</p></div>
                                    <div><p class="text-xs font-semibold uppercase text-slate-500">Tipo de equipamento</p><p class="mt-1 text-sm text-slate-900">{{ $equipmentSearchRecord->tipo_equipamento ?: '-' }}</p></div>
                                    <div><p class="text-xs font-semibold uppercase text-slate-500">Status atual</p><p class="mt-1 text-sm text-slate-900">{{ $equipmentSearchRecord->status ?: '-' }}</p></div>
                                    <div><p class="text-xs font-semibold uppercase text-slate-500">Origem</p><p class="mt-1 text-sm text-slate-900">{{ ($equipmentSearchRecord->origem_tipo ?? '') === 'sede' ? 'Sede' : 'Unidade' }}</p></div>
                                    <div><p class="text-xs font-semibold uppercase text-slate-500">Unidade/Setor</p><p class="mt-1 text-sm text-slate-900">{{ $equipmentSearchRecord->unidade_setor ?: '-' }}</p></div>
                                    <div><p class="text-xs font-semibold uppercase text-slate-500">Data de chegada</p><p class="mt-1 text-sm text-slate-900">{{ optional($equipmentSearchRecord->data_chegada)->format('d/m/Y') ?: '-' }}</p></div>
                                    <div><p class="text-xs font-semibold uppercase text-slate-500">Data de saída</p><p class="mt-1 text-sm text-slate-900">{{ optional($equipmentSearchRecord->data_saida)->format('d/m/Y') ?: '-' }}</p></div>
                                    <div><p class="text-xs font-semibold uppercase text-slate-500">TIC / Jira</p><p class="mt-1 text-sm text-slate-900">{{ $equipmentSearchRecord->tic ?: '-' }}</p></div>
                                    <div><p class="text-xs font-semibold uppercase text-slate-500">Service Tag</p><p class="mt-1 text-sm text-slate-900">{{ $equipmentSearchRecord->service_tag ?: '-' }}</p></div>
                                    <div class="sm:col-span-2">
                                        <p class="text-xs font-semibold uppercase text-slate-500">Observação</p>
                                        <p class="mt-1 whitespace-pre-wrap rounded-md border border-slate-200 bg-white px-3 py-2 text-sm text-slate-900">{{ $equipmentSearchRecord->observacao ?: '-' }}</p>
                                    </div>
                                </div>
                            </div>

                            <div class="rounded-lg border border-slate-200 bg-slate-50 p-4">
                                <h3 class="text-sm font-bold uppercase tracking-wide text-slate-700">Fiscal e administrativos</h3>
                                <div class="mt-3 space-y-3 text-sm text-slate-900">
                                    <div class="rounded-md border border-slate-200 bg-white px-3 py-2">
                                        <p class="text-xs font-semibold uppercase text-slate-500">Entrada fiscal</p>
                                        <p class="mt-1">Docto: {{ $equipmentSearchRecord->nota_documento_entrada ?: '-' }}</p>
                                        <p>Número: {{ $equipmentSearchRecord->nota_numero_entrada ?: '-' }}</p>
                                        <p>Emissão: {{ optional($equipmentSearchRecord->data_emissao_entrada)->format('d/m/Y') ?: '-' }}</p>
                                        <p>Valor: {{ $equipmentSearchRecord->nota_valor_entrada !== null ? 'R$ ' . number_format((float) $equipmentSearchRecord->nota_valor_entrada, 2, ',', '.') : '-' }}</p>
                                        <p>Registrada em: {{ optional($equipmentSearchRecord->entrada_realizada_em)->format('d/m/Y H:i') ?: '-' }}</p>
                                    </div>
                                    <div class="rounded-md border border-slate-200 bg-white px-3 py-2">
                                        <p class="text-xs font-semibold uppercase text-slate-500">Nota de saída</p>
                                        <p class="mt-1">Número: {{ $equipmentSearchRecord->nota_numero_saida ?: '-' }}</p>
                                        <p>Registrada em: {{ optional($equipmentSearchRecord->nota_saida_emitida_em)->format('d/m/Y H:i') ?: '-' }}</p>
                                    </div>
                                    <div class="rounded-md border border-slate-200 bg-white px-3 py-2">
                                        <p class="text-xs font-semibold uppercase text-slate-500">Terceiros</p>
                                        <p class="mt-1">Empresa: {{ $equipmentSearchRecord->terceiros_empresa ?: '-' }}</p>
                                        <p>CNPJ: {{ $equipmentSearchRecord->terceiros_cnpj ?: '-' }}</p>
                                        <p>Remessa: {{ $equipmentSearchRecord->terceiros_nota_remessa ?: '-' }}</p>
                                        <p>OS: {{ $equipmentSearchRecord->terceiros_os_numero ?: '-' }}</p>
                                        <p>Valor reparo: {{ $equipmentSearchRecord->terceiros_valor_reparo !== null ? 'R$ ' . number_format((float) $equipmentSearchRecord->terceiros_valor_reparo, 2, ',', '.') : '-' }}</p>
                                        <p>Resultado: {{ $equipmentSearchRecord->terceiros_resultado ?: '-' }}</p>
                                        <p>Enviado em: {{ optional($equipmentSearchRecord->terceiros_enviado_em)->format('d/m/Y H:i') ?: '-' }}</p>
                                        <p>Retorno info: {{ optional($equipmentSearchRecord->terceiros_retorno_informado_em)->format('d/m/Y H:i') ?: '-' }}</p>
                                        <p>Retorno físico: {{ optional($equipmentSearchRecord->terceiros_retorno_fisico_em)->format('d/m/Y H:i') ?: '-' }}</p>
                                    </div>
                                    <div class="rounded-md border border-slate-200 bg-white px-3 py-2">
                                        <p class="text-xs font-semibold uppercase text-slate-500">Peças</p>
                                        <p class="mt-1">Peça: {{ $equipmentSearchRecord->peca_nome ?: '-' }}</p>
                                        <p>Quantidade: {{ $equipmentSearchRecord->peca_quantidade ?: '-' }}</p>
                                        <p>Origem: {{ $equipmentSearchRecord->peca_origem ?: '-' }}</p>
                                        <p>Link compra: {{ $equipmentSearchRecord->peca_link_compra ?: '-' }}</p>
                                        <p>Status fluxo: {{ $equipmentSearchRecord->peca_fluxo_status ?: '-' }}</p>
                                        <p>Solicitação ADM: {{ optional($equipmentSearchRecord->peca_admin_realizado_em)->format('d/m/Y H:i') ?: '-' }}</p>
                                        <p>Confirmação peça: {{ optional($equipmentSearchRecord->peca_recebida_confirmada_em)->format('d/m/Y H:i') ?: '-' }}</p>
                                    </div>
                                </div>
                            </div>

                            <div class="rounded-lg border border-slate-200 bg-slate-50 p-4 lg:col-span-2">
                                <h3 class="text-sm font-bold uppercase tracking-wide text-slate-700">Anexos</h3>
                                <div class="mt-3 overflow-x-auto">
                                    <table class="w-full min-w-[760px] text-sm">
                                        <thead class="bg-slate-900 text-left text-xs font-semibold uppercase text-white">
                                            <tr>
                                                <th class="px-3 py-2">Tipo</th>
                                                <th class="px-3 py-2">Nome</th>
                                                <th class="px-3 py-2">Upload</th>
                                                <th class="px-3 py-2">Usuário</th>
                                                <th class="px-3 py-2">Ação</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-slate-200">
                                            @forelse($equipmentSearchRecord->attachments ?? collect() as $attachment)
                                                <tr>
                                                    <td class="px-3 py-2">{{ $attachment->attachment_type }}</td>
                                                    <td class="px-3 py-2">{{ $attachment->original_name }}</td>
                                                    <td class="px-3 py-2">{{ optional($attachment->uploaded_at)->format('d/m/Y H:i') ?: '-' }}</td>
                                                    <td class="px-3 py-2">{{ $attachment->uploader?->name ?? 'Sistema' }}</td>
                                                    <td class="px-3 py-2">
                                                        <a href="{{ route('bancada-servicos.attachments.download', $attachment) }}" target="_blank" rel="noopener noreferrer" class="inline-flex rounded-md border border-[#033151] bg-white px-3 py-1.5 text-xs font-semibold text-[#033151] hover:bg-slate-50">Baixar</a>
                                                    </td>
                                                </tr>
                                            @empty
                                                <tr><td colspan="5" class="px-3 py-4 text-center text-slate-500">Nenhum anexo encontrado.</td></tr>
                                            @endforelse
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            <div class="rounded-lg border border-slate-200 bg-slate-50 p-4 lg:col-span-2">
                                <h3 class="text-sm font-bold uppercase tracking-wide text-slate-700">Histórico</h3>
                                <div class="mt-3 overflow-x-auto">
                                    <table class="w-full min-w-[980px] text-sm">
                                        <thead class="bg-slate-900 text-left text-xs font-semibold uppercase text-white">
                                            <tr>
                                                <th class="px-3 py-2">Data/Hora</th>
                                                <th class="px-3 py-2">Módulo</th>
                                                <th class="px-3 py-2">Ação</th>
                                                <th class="px-3 py-2">De</th>
                                                <th class="px-3 py-2">Para</th>
                                                <th class="px-3 py-2">Usuário</th>
                                                <th class="px-3 py-2">Obs.</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-slate-200">
                                            @forelse(($equipmentSearchRecord->events ?? collect())->take(12) as $event)
                                                <tr>
                                                    <td class="px-3 py-2">{{ optional($event->created_at)->format('d/m/Y H:i') ?: '-' }}</td>
                                                    <td class="px-3 py-2">{{ $event->module ?: '-' }}</td>
                                                    <td class="px-3 py-2">{{ $event->action }}</td>
                                                    <td class="px-3 py-2">{{ $event->previous_status ?: '-' }}</td>
                                                    <td class="px-3 py-2">{{ $event->new_status ?: '-' }}</td>
                                                    <td class="px-3 py-2">{{ $event->performer?->name ?: 'Sistema' }}</td>
                                                    <td class="px-3 py-2">{{ $event->observation ?: '-' }}</td>
                                                </tr>
                                            @empty
                                                <tr><td colspan="7" class="px-3 py-4 text-center text-slate-500">Nenhum evento encontrado.</td></tr>
                                            @endforelse
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    @else
                        <p class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">Nenhum equipamento encontrado para a plaqueta informada.</p>
                    @endif
                @else
                    <p class="rounded-lg border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-600">Digite uma plaqueta e clique em buscar para consultar os dados completos do equipamento.</p>
                @endif
            </div>
        </section>
    </div>
</x-app-layout>
