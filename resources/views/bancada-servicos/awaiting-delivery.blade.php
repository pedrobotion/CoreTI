<x-app-layout>
    @include('bancada-servicos.partials.label-printer')
    <div class="mx-auto max-w-7xl space-y-6">
        <div>
            <p class="text-sm font-semibold uppercase tracking-wide text-slate-500">Bancada de Serviços</p>
            <h1 class="mt-1 text-2xl font-bold text-slate-950">Prontos para Entrega</h1>
            <p class="mt-2 text-sm text-slate-600">Equipamentos liberados para entrega (Sede em Pronto para entrega e Unidade com Nota Fiscal Emitida).</p>
        </div>
        <section class="rounded-lg border border-slate-200 bg-white p-5">
            <form method="GET" class="flex flex-wrap items-center gap-2 lg:flex-nowrap">
                <input name="q" value="{{ $search ?? '' }}" placeholder="Buscar plaqueta, tipo, unidade, TIC..." class="h-10 w-full min-w-[240px] rounded-md border-slate-300 text-sm md:flex-1 md:w-auto">
                <input name="tipo" value="{{ $tipo ?? '' }}" placeholder="Tipo" class="h-10 w-full min-w-[120px] rounded-md border-slate-300 text-sm md:w-32">
                <input name="unidade" value="{{ $unidade ?? '' }}" placeholder="Unidade/Setor" class="h-10 w-full min-w-[170px] rounded-md border-slate-300 text-sm md:w-48">
                <div class="flex h-10 w-full gap-2 md:w-auto">
                    <button type="submit" style="background-color: #033151; border-color: #033151;" class="inline-flex h-10 items-center justify-center rounded-md px-4 text-sm font-semibold text-white hover:opacity-90">Buscar</button>
                    <a href="{{ route('bancada-servicos.awaiting-delivery') }}" class="inline-flex h-10 items-center justify-center rounded-md border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-700 hover:bg-slate-50">Limpar</a>
                </div>
            </form>
        </section>
        <section x-data="bancadaLabelPrinter()" class="rounded-lg border border-emerald-300 bg-emerald-50 p-5">
            <h2 class="text-base font-bold text-emerald-900">Sede (entrega direta)</h2>
            <div class="mt-3 w-full max-w-full overflow-x-auto">
                <table class="w-full min-w-[980px] text-sm">
                    <thead style="background-color: #033151;" class="text-left text-xs font-semibold uppercase text-white">
                    <tr><th class="px-3 py-2">Tipo</th><th class="px-3 py-2">Plaqueta</th><th class="px-3 py-2">Origem</th><th class="px-3 py-2">Unidade/Setor</th><th class="px-3 py-2">Chegada</th><th class="px-3 py-2">TIC</th><th class="px-3 py-2">Obs</th><th class="px-3 py-2">Status</th><th class="px-3 py-2">Ações</th></tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200">
                    @forelse($sedeItems as $item)
                        <tr>
                            <td class="px-3 py-2">{{ $item->tipo_equipamento }}</td><td class="px-3 py-2">{{ $item->plaqueta }}</td><td class="px-3 py-2">Sede</td><td class="px-3 py-2">{{ $item->unidade_setor }}</td><td class="px-3 py-2">{{ optional($item->data_chegada)->format('d/m/Y') }}</td><td class="px-3 py-2">{{ $item->tic ?: '-' }}</td><td class="px-3 py-2">{{ $item->observacao ?: '-' }}</td><td class="px-3 py-2">{{ $item->status }}</td>
                                <td class="px-3 py-2">
                                <form method="POST" action="{{ route('bancada-servicos.assets.status', $item) }}" class="inline">
                                    @csrf @method('PATCH')
                                    <input type="hidden" name="status" value="Entregue">
                                    <button class="rounded-md border border-emerald-700 bg-emerald-700 px-2 py-1 text-xs font-semibold text-white">Marcar entregue</button>
                                </form>
                                <a href="{{ route('bancada-servicos.assets.history', $item) }}" class="ml-1 text-indigo-600 text-xs">Histórico</a>
                                <button type="button" class="ml-1 text-slate-700 text-xs" @click="window.dispatchEvent(new CustomEvent('open-documents',{detail:{url: @js(route('bancada-servicos.assets.documents', $item)), plaqueta: @js($item->plaqueta)}}))">Documentos</button>
                                <button type="button" @click="print(@js($item->unidade_setor), @js(optional($item->data_chegada)->format('d/m/Y')), @js($item->observacao))" class="ml-1 text-emerald-700 text-xs">Imprimir</button>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="9" class="px-3 py-4 text-center text-slate-500">Nenhum equipamento da sede aguardando entrega.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </section>
            @include('bancada-servicos.partials.documents-modal')
        @forelse($grouped as $bucket)
            <section x-data="bancadaLabelPrinter()" class="rounded-lg border border-slate-200 bg-white p-5">
                <div class="flex items-center justify-between">
                    <h2 class="text-base font-bold text-slate-900">{{ $bucket['route']->nome }}</h2>
                    <p class="text-xs text-slate-600">Separa: {{ $bucket['route']->dia_separa ?: '-' }} | Carrega: {{ $bucket['route']->dia_carrega ?: '-' }} | Entrega: {{ $bucket['route']->dia_entrega ?: '-' }}</p>
                </div>
                <div class="mt-3 w-full max-w-full overflow-x-auto">
                    <table class="w-full min-w-[1280px] text-sm">
                        <thead style="background-color: #033151;" class="text-left text-xs font-semibold uppercase text-white">
                        <tr>
                            <th class="px-3 py-2">Tipo</th>
                            <th class="px-3 py-2">Plaqueta</th>
                            <th class="px-3 py-2">Unidade/Setor</th>
                            <th class="px-3 py-2">Status</th>
                            <th class="px-3 py-2">Rota/Malote</th>
                            <th class="px-3 py-2">Próx. separação</th>
                            <th class="px-3 py-2">Próx. carregamento</th>
                            <th class="px-3 py-2">Entrega prevista</th>
                            <th class="px-3 py-2">Ações</th>
                        </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200">
                        @foreach($bucket['items'] as $row)
                            @php($item = $row['equipment'])
                            @php($schedule = $row['schedule'])
                            <tr>
                                <td class="px-3 py-2">{{ $item->tipo_equipamento }}</td>
                                <td class="px-3 py-2">{{ $item->plaqueta }}</td>
                                <td class="px-3 py-2">{{ $item->unidade_setor }}</td>
                                <td class="px-3 py-2">{{ $item->status }}</td>
                                <td class="px-3 py-2">{{ $bucket['route']->nome }}</td>
                                <td class="px-3 py-2">{{ $schedule ? $schedule['separation']->format('d/m/Y') : '-' }}</td>
                                <td class="px-3 py-2">{{ $schedule ? $schedule['loading']->format('d/m/Y') : '-' }}</td>
                                <td class="px-3 py-2">{{ $schedule ? $schedule['delivery']->format('d/m/Y') : '-' }}</td>
                                <td class="px-3 py-2">
                                    @if($item->sent_to_cd_at)
                                        <p class="mb-1 text-xs font-semibold text-emerald-700">Enviado ao CD em {{ optional($item->sent_to_cd_at)->format('d/m/Y H:i') }}</p>
                                    @else
                                        <form method="POST" action="{{ route('bancada-servicos.assets.send-to-cd', $item) }}" class="mb-1 inline">
                                            @csrf @method('PATCH')
                                            <button class="rounded-md border border-sky-700 bg-sky-700 px-2 py-1 text-xs font-semibold text-white">Marcar enviado ao CD</button>
                                        </form>
                                    @endif
                                    <form method="POST" action="{{ route('bancada-servicos.assets.status', $item) }}" class="inline">
                                        @csrf @method('PATCH')
                                        <input type="hidden" name="status" value="Entregue">
                                        <button class="rounded-md border border-emerald-700 bg-emerald-700 px-2 py-1 text-xs font-semibold text-white">Marcar entregue</button>
                                    </form>
                                    <a href="{{ route('bancada-servicos.assets.history', $item) }}" class="ml-1 text-indigo-600 text-xs">Histórico</a>
                                    <button type="button" @click="print(@js($item->unidade_setor), @js(optional($item->data_chegada)->format('d/m/Y')), @js($item->observacao))" class="ml-1 text-emerald-700 text-xs">Imprimir</button>
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            </section>
        @empty
            <section class="rounded-lg border border-slate-200 bg-white p-6 text-center text-slate-500">Nenhum equipamento em rotas no momento.</section>
        @endforelse
        <section class="rounded-lg border border-amber-300 bg-amber-50 p-5">
            <h2 class="text-base font-bold text-amber-900">Sem rota mapeada</h2>
            <div class="mt-3 w-full max-w-full overflow-x-auto">
                <table class="w-full min-w-[900px] text-sm">
                    <thead style="background-color: #033151;" class="text-left text-xs font-semibold uppercase text-white">
                        <tr><th class="px-3 py-2">Tipo</th><th class="px-3 py-2">Plaqueta</th><th class="px-3 py-2">Unidade/Setor</th><th class="px-3 py-2">Status</th><th class="px-3 py-2">Rota/Malote</th><th class="px-3 py-2">Ações</th></tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200">
                        @forelse($unassignedItems as $row)
                            @php($item = $row['equipment'])
                            <tr>
                                <td class="px-3 py-2">{{ $item->tipo_equipamento }}</td>
                                <td class="px-3 py-2">{{ $item->plaqueta }}</td>
                                <td class="px-3 py-2">{{ $item->unidade_setor }}</td>
                                <td class="px-3 py-2">{{ $item->status }}</td>
                                <td class="px-3 py-2"><span class="text-amber-700 font-semibold">Rota não cadastrada</span></td>
                                <td class="px-3 py-2"><a href="{{ route('bancada-servicos.assets.history', $item) }}" class="text-indigo-600 text-xs">Histórico</a></td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="px-3 py-4 text-center text-slate-500">Todos os equipamentos estão em rota ou sede.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
        <section class="rounded-lg border border-slate-200 bg-white p-5">
            <h2 class="text-base font-bold text-slate-900">Equipamentos aguardando envio ao CD</h2>
            <div class="mt-4">
                <h3 class="text-sm font-semibold text-slate-800">Separar hoje</h3>
                <div class="mt-2 w-full max-w-full overflow-x-auto">
                    <table class="w-full min-w-[900px] text-sm">
                        <thead style="background-color: #033151;" class="text-left text-xs font-semibold uppercase text-white">
                            <tr><th class="px-3 py-2">Plaqueta</th><th class="px-3 py-2">Tipo</th><th class="px-3 py-2">Unidade/Setor</th><th class="px-3 py-2">Rota</th><th class="px-3 py-2">Separação</th><th class="px-3 py-2">Carregamento</th><th class="px-3 py-2">Entrega prevista</th><th class="px-3 py-2">Status envio CD</th></tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200">
                            @forelse($pendingCd['today'] as $row)
                                <tr>
                                    <td class="px-3 py-2">{{ $row['equipment']->plaqueta }}</td>
                                    <td class="px-3 py-2">{{ $row['equipment']->tipo_equipamento }}</td>
                                    <td class="px-3 py-2">{{ $row['equipment']->unidade_setor }}</td>
                                    <td class="px-3 py-2">{{ $row['route']->nome }}</td>
                                    <td class="px-3 py-2">{{ $row['schedule']['separation']->format('d/m/Y') }}</td>
                                    <td class="px-3 py-2">{{ $row['schedule']['loading']->format('d/m/Y') }}</td>
                                    <td class="px-3 py-2">{{ $row['schedule']['delivery']->format('d/m/Y') }}</td>
                                    <td class="px-3 py-2">{{ $row['equipment']->sent_to_cd_at ? 'Enviado ao CD' : 'Pendente' }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="8" class="px-3 py-3 text-center text-slate-500">Nenhum equipamento para separar hoje.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="mt-6">
                <h3 class="text-sm font-semibold text-slate-800">Próximas separações</h3>
                <div class="mt-2 w-full max-w-full overflow-x-auto">
                    <table class="w-full min-w-[900px] text-sm">
                        <thead style="background-color: #033151;" class="text-left text-xs font-semibold uppercase text-white">
                            <tr><th class="px-3 py-2">Plaqueta</th><th class="px-3 py-2">Tipo</th><th class="px-3 py-2">Unidade/Setor</th><th class="px-3 py-2">Rota</th><th class="px-3 py-2">Separação</th><th class="px-3 py-2">Carregamento</th><th class="px-3 py-2">Entrega prevista</th><th class="px-3 py-2">Status envio CD</th></tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200">
                            @forelse($pendingCd['upcoming'] as $row)
                                <tr>
                                    <td class="px-3 py-2">{{ $row['equipment']->plaqueta }}</td>
                                    <td class="px-3 py-2">{{ $row['equipment']->tipo_equipamento }}</td>
                                    <td class="px-3 py-2">{{ $row['equipment']->unidade_setor }}</td>
                                    <td class="px-3 py-2">{{ $row['route']->nome }}</td>
                                    <td class="px-3 py-2">{{ $row['schedule']['separation']->format('d/m/Y') }}</td>
                                    <td class="px-3 py-2">{{ $row['schedule']['loading']->format('d/m/Y') }}</td>
                                    <td class="px-3 py-2">{{ $row['schedule']['delivery']->format('d/m/Y') }}</td>
                                    <td class="px-3 py-2">{{ $row['equipment']->sent_to_cd_at ? 'Enviado ao CD' : 'Pendente' }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="8" class="px-3 py-3 text-center text-slate-500">Nenhum equipamento pendente nas próximas separações.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="mt-6">
                <h3 class="text-sm font-semibold text-slate-800">Sem rota cadastrada</h3>
                <div class="mt-2 w-full max-w-full overflow-x-auto">
                    <table class="w-full min-w-[700px] text-sm">
                        <thead style="background-color: #033151;" class="text-left text-xs font-semibold uppercase text-white">
                            <tr><th class="px-3 py-2">Plaqueta</th><th class="px-3 py-2">Tipo</th><th class="px-3 py-2">Unidade/Setor</th><th class="px-3 py-2">Status</th></tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200">
                            @forelse($pendingCd['unmapped'] as $row)
                                <tr>
                                    <td class="px-3 py-2">{{ $row['equipment']->plaqueta }}</td>
                                    <td class="px-3 py-2">{{ $row['equipment']->tipo_equipamento }}</td>
                                    <td class="px-3 py-2">{{ $row['equipment']->unidade_setor }}</td>
                                    <td class="px-3 py-2"><span class="font-semibold text-amber-700">Rota não cadastrada</span></td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="px-3 py-3 text-center text-slate-500">Todos os equipamentos possuem rota cadastrada.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </section>
    </div>
</x-app-layout>
