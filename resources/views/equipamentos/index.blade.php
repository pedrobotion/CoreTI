<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-gray-800">Equipamentos</h2>
    </x-slot>

    <div class="mx-auto max-w-7xl space-y-6 px-6 py-8">
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <p class="text-xs uppercase tracking-widest text-slate-500">ServiceDesk</p>
                <h1 class="text-3xl font-semibold text-slate-900">Inventário de equipamentos</h1>
                <p class="mt-1 text-sm text-slate-500">Parque de TI distribuído pelas unidades: tipo, patrimônio, localização e situação.</p>
            </div>
            <div class="flex gap-2">
                <a href="{{ route('service-desk.equipamentos.export', request()->query()) }}" class="rounded-md border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Exportar CSV</a>
                @can('module.equipamentos.manage')
                    <a href="{{ route('service-desk.equipamentos.create') }}" class="rounded-md bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:opacity-90">Novo equipamento</a>
                @endcan
            </div>
        </div>

        @include('partials.modulo-flash')

        @include('partials.modulo-resumo', ['cards' => [
            ['titulo' => 'Total', 'valor' => $resumo['total']],
            ['titulo' => 'Disponíveis', 'valor' => $resumo['disponiveis']],
            ['titulo' => 'Em uso', 'valor' => $resumo['em_uso']],
            ['titulo' => 'Em manutenção', 'valor' => $resumo['manutencao']],
            ['titulo' => 'Em uso sem entrega', 'valor' => $resumo['em_uso_sem_entrega'], 'alerta' => true, 'nota' => 'Notebook, PC ou monitor'],
        ]])

        @if ($resumo['em_uso_sem_entrega'] > 0)
            <div class="rounded-md border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
                <span class="font-semibold">{{ $resumo['em_uso_sem_entrega'] }} equipamento(s) dos tipos entregues a colaborador estão "Em Uso" sem entrega registrada.</span>
                Eles não aparecem como disponíveis para entrega enquanto estiverem assim.
            </div>
        @endif

        <section class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
            <form method="GET" action="{{ route('service-desk.equipamentos.index') }}" class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-6">
                <input name="busca" value="{{ $filters['busca'] }}" placeholder="Patrimônio, nome, série ou IP" class="rounded-md border-slate-300 text-sm lg:col-span-2">

                @if (count($scopeOptions) > 1)
                    <select name="scope" class="rounded-md border-slate-300 text-sm">
                        <option value="">Todas as operações</option>
                        @foreach ($scopeOptions as $valor => $rotulo)
                            <option value="{{ $valor }}" @selected($filters['scope'] === $valor)>{{ $rotulo }}</option>
                        @endforeach
                    </select>
                @endif

                <select name="tipo" class="rounded-md border-slate-300 text-sm">
                    <option value="">Todos os tipos</option>
                    @foreach ($tipos as $tipo)
                        <option value="{{ $tipo }}" @selected($filters['tipo'] === $tipo)>{{ $tipo }}</option>
                    @endforeach
                </select>

                <select name="status" class="rounded-md border-slate-300 text-sm">
                    <option value="">Todas as situações</option>
                    @foreach ($statuses as $status)
                        <option value="{{ $status }}" @selected($filters['status'] === $status)>{{ $status }}</option>
                    @endforeach
                </select>

                <select name="unidade" class="rounded-md border-slate-300 text-sm">
                    <option value="">Todas as unidades</option>
                    @foreach ($unidades as $unidade)
                        <option value="{{ $unidade->id }}" @selected($filters['unidade'] === (string) $unidade->id)>{{ $unidade->name }}</option>
                    @endforeach
                </select>

                <div class="flex gap-2">
                    <button class="flex-1 rounded-md border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Filtrar</button>
                    <a href="{{ route('service-desk.equipamentos.index') }}" class="rounded-md border border-slate-300 px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Limpar</a>
                </div>
            </form>
        </section>

        <section class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
            <div class="mb-4 flex items-center justify-between gap-3">
                <h2 class="text-lg font-semibold text-slate-900">Equipamentos cadastrados</h2>
                <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-600">{{ number_format($equipments->total(), 0, ',', '.') }} registro(s)</span>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full min-w-[1000px] text-left text-sm">
                    <thead class="border-b border-slate-200 text-xs uppercase text-slate-500">
                        <tr>
                            <th class="px-3 py-3">Patrimônio</th>
                            <th class="px-3 py-3">Equipamento</th>
                            <th class="px-3 py-3">Tipo</th>
                            <th class="px-3 py-3">Onde está</th>
                            <th class="px-3 py-3">Operação</th>
                            <th class="px-3 py-3">Situação</th>
                            <th class="px-3 py-3 text-right">Ações</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($equipments as $equipment)
                            @php
                                $semEntrega = $equipment->status === \App\Models\Equipment::STATUS_EM_USO && $equipment->vinculos_ativos_count === 0;
                            @endphp
                            <tr class="{{ $semEntrega ? 'bg-amber-50/60' : '' }}">
                                <td class="px-3 py-3 font-mono font-semibold text-slate-900">{{ $equipment->patrimonio }}</td>
                                <td class="px-3 py-3">
                                    <p class="text-slate-900">{{ $equipment->nome }}</p>
                                    @if ($equipment->numero_serie || $equipment->endereco_ip)
                                        <p class="text-xs text-slate-500">
                                            {{ $equipment->numero_serie ? 'Série '.$equipment->numero_serie : '' }}
                                            {{ $equipment->numero_serie && $equipment->endereco_ip ? '·' : '' }}
                                            {{ $equipment->endereco_ip }}
                                        </p>
                                    @endif
                                </td>
                                <td class="px-3 py-3 text-slate-600">{{ $equipment->tipo }}</td>
                                <td class="px-3 py-3">
                                    <p class="text-slate-700">{{ $equipment->local }}</p>
                                    @if ($equipment->department)
                                        <p class="text-xs text-slate-500">{{ $equipment->department->name }}</p>
                                    @endif
                                </td>
                                <td class="px-3 py-3">
                                    <span class="rounded-full bg-slate-100 px-2 py-1 text-xs font-medium text-slate-600">{{ $equipment->scope_label }}</span>
                                </td>
                                <td class="px-3 py-3">
                                    @php
                                        $cor = match ($equipment->status) {
                                            \App\Models\Equipment::STATUS_DISPONIVEL, \App\Models\Equipment::STATUS_NOVO => 'bg-emerald-100 text-emerald-700',
                                            \App\Models\Equipment::STATUS_EM_USO => 'bg-sky-100 text-sky-700',
                                            \App\Models\Equipment::STATUS_EM_MANUTENCAO, \App\Models\Equipment::STATUS_EM_CONSERTO => 'bg-amber-100 text-amber-800',
                                            \App\Models\Equipment::STATUS_EMPRESTADO => 'bg-violet-100 text-violet-700',
                                            default => 'bg-slate-200 text-slate-600',
                                        };
                                    @endphp
                                    <div class="flex flex-wrap items-center gap-1">
                                        <span class="inline-flex items-center whitespace-nowrap rounded-full px-2 py-1 text-xs font-medium {{ $cor }}">{{ $equipment->status }}</span>
                                        @if ($semEntrega)
                                            <span class="inline-flex items-center whitespace-nowrap rounded-full bg-amber-100 px-2 py-1 text-xs font-semibold text-amber-800 ring-1 ring-inset ring-amber-200" title="Marcado como em uso, mas sem entrega registrada">sem entrega</span>
                                        @endif
                                    </div>
                                </td>
                                <td class="px-3 py-3">
                                    <div class="flex justify-end gap-2">
                                        @can('module.equipamentos.manage')
                                            <a href="{{ route('service-desk.equipamentos.edit', $equipment) }}" class="rounded-md border border-slate-300 px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50">Editar</a>
                                            @if ($equipment->vinculos_ativos_count === 0)
                                                <form method="POST" action="{{ route('service-desk.equipamentos.destroy', $equipment) }}" onsubmit="return confirm('Excluir o equipamento {{ $equipment->patrimonio }}?')">
                                                    @csrf @method('DELETE')
                                                    <button class="rounded-md border border-rose-300 px-3 py-1.5 text-xs font-medium text-rose-700 hover:bg-rose-50">Excluir</button>
                                                </form>
                                            @endif
                                        @else
                                            <span class="text-xs text-slate-400">Somente leitura</span>
                                        @endcan
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-3 py-10 text-center text-sm text-slate-500">
                                    Nenhum equipamento encontrado para os filtros atuais.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-4">{{ $equipments->links() }}</div>
        </section>
    </div>
</x-app-layout>
