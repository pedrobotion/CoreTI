<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-gray-800">Aparelhos celulares</h2>
    </x-slot>

    <div class="mx-auto max-w-7xl space-y-6 px-6 py-8">
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <p class="text-xs uppercase tracking-widest text-slate-500">Telefonia Corporativa</p>
                <h1 class="text-3xl font-semibold text-slate-900">Aparelhos celulares</h1>
                <p class="mt-1 text-sm text-slate-500">Parque de aparelhos: marca, modelo, IMEI, estado e situação.</p>
            </div>
            <div class="flex gap-2">
                <a href="{{ route('administrativo.telefonia.devices.export.excel', request()->query()) }}" class="rounded-md border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Excel</a>
                <a href="{{ route('administrativo.telefonia.devices.export.pdf', request()->query()) }}" class="rounded-md border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">PDF</a>
                @can('module.telefonia.manage')
                    <a href="{{ route('administrativo.telefonia.devices.create') }}" class="rounded-md bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:opacity-90">Novo aparelho</a>
                @endcan
            </div>
        </div>

        @include('partials.modulo-flash')

        @include('partials.modulo-resumo', ['cards' => [
            ['titulo' => 'Total', 'valor' => $resumo['total']],
            ['titulo' => 'Disponíveis', 'valor' => $resumo['disponiveis']],
            ['titulo' => 'Em uso', 'valor' => $resumo['em_uso']],
            ['titulo' => 'Novos', 'valor' => $resumo['novos'], 'nota' => 'Nunca usados'],
            ['titulo' => 'Em uso sem vínculo', 'valor' => $resumo['em_uso_sem_vinculo'], 'alerta' => true, 'nota' => 'Conferir'],
        ]])

        @if ($resumo['em_uso_sem_vinculo'] > 0)
            <div class="rounded-md border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
                <span class="font-semibold">{{ $resumo['em_uso_sem_vinculo'] }} aparelho(s) marcado(s) como "Em Uso" sem nenhum vínculo registrado.</span>
                Enquanto estiverem assim, não aparecem como disponíveis para um novo vínculo.
            </div>
        @endif

        <section class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
            <form method="GET" action="{{ route('administrativo.telefonia.devices.index') }}" class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-7">
                <input name="busca" value="{{ $filters['busca'] }}" placeholder="IMEI, modelo ou nº de série" class="rounded-md border-slate-300 text-sm lg:col-span-2">

                @if (count($scopeOptions) > 1)
                    <select name="scope" class="rounded-md border-slate-300 text-sm">
                        <option value="">Todas as operações</option>
                        @foreach ($scopeOptions as $valor => $rotulo)
                            <option value="{{ $valor }}" @selected($filters['scope'] === $valor)>{{ $rotulo }}</option>
                        @endforeach
                    </select>
                @endif

                <select name="marca" class="rounded-md border-slate-300 text-sm">
                    <option value="">Todas as marcas</option>
                    @foreach ($marcas as $marca)
                        <option value="{{ $marca }}" @selected($filters['marca'] === $marca)>{{ $marca }}</option>
                    @endforeach
                </select>

                <select name="status" class="rounded-md border-slate-300 text-sm">
                    <option value="">Todas as situações</option>
                    @foreach ($statuses as $status)
                        <option value="{{ $status }}" @selected($filters['status'] === $status)>{{ $status }}</option>
                    @endforeach
                </select>

                <select name="estado" class="rounded-md border-slate-300 text-sm">
                    <option value="">Novo e usado</option>
                    @foreach ($estados as $estado)
                        <option value="{{ $estado }}" @selected($filters['estado'] === $estado)>{{ $estado }}</option>
                    @endforeach
                </select>

                @include('partials.modulo-por-pagina')

                <div class="flex gap-2">
                    <button class="flex-1 rounded-md border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Filtrar</button>
                    <a href="{{ route('administrativo.telefonia.devices.index') }}" class="rounded-md border border-slate-300 px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Limpar</a>
                </div>
            </form>
        </section>

        <section class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
            <div class="mb-4 flex items-center justify-between gap-3">
                <h2 class="text-lg font-semibold text-slate-900">Aparelhos cadastrados</h2>
                <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-600">{{ number_format($devices->total(), 0, ',', '.') }} registro(s)</span>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full min-w-[960px] text-left text-sm">
                    <thead class="border-b border-slate-200 text-xs uppercase text-slate-500">
                        <tr>
                            <th class="px-3 py-3">Aparelho</th>
                            <th class="px-3 py-3">IMEI 1</th>
                            <th class="px-3 py-3">IMEI 2</th>
                            <th class="px-3 py-3">Estado</th>
                            <th class="px-3 py-3">Operação</th>
                            <th class="px-3 py-3">Situação</th>
                            <th class="px-3 py-3 text-right">Ações</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($devices as $device)
                            @php
                                $semVinculo = $device->status === \App\Models\CorpDevice::STATUS_EM_USO && $device->vinculos_ativos_count === 0;
                            @endphp
                            <tr class="{{ $semVinculo ? 'bg-amber-50/60' : '' }}">
                                <td class="px-3 py-3">
                                    <p class="font-semibold text-slate-900">{{ $device->descricao }}</p>
                                    @if ($device->numero_serie)
                                        <p class="text-xs text-slate-500">Série {{ $device->numero_serie }}</p>
                                    @endif
                                </td>
                                <td class="px-3 py-3 font-mono text-xs text-slate-700">{{ $device->imei_1_formatado ?: '—' }}</td>
                                <td class="px-3 py-3 font-mono text-xs text-slate-500">{{ $device->imei_2_formatado ?: '—' }}</td>
                                <td class="px-3 py-3">
                                    <span class="rounded-full px-2 py-1 text-xs font-medium {{ $device->estado_aparelho === \App\Models\CorpDevice::ESTADO_NOVO ? 'bg-indigo-100 text-indigo-700' : 'bg-slate-100 text-slate-600' }}">{{ $device->estado_aparelho }}</span>
                                </td>
                                <td class="px-3 py-3">
                                    <span class="rounded-full bg-slate-100 px-2 py-1 text-xs font-medium text-slate-600">{{ $device->scope_label }}</span>
                                </td>
                                <td class="px-3 py-3">
                                    @php
                                        $cor = match ($device->status) {
                                            \App\Models\CorpDevice::STATUS_DISPONIVEL => 'bg-emerald-100 text-emerald-700',
                                            \App\Models\CorpDevice::STATUS_EM_USO => 'bg-sky-100 text-sky-700',
                                            default => 'bg-slate-200 text-slate-600',
                                        };
                                    @endphp
                                    <span class="rounded-full px-2 py-1 text-xs font-medium {{ $cor }}">{{ $device->status }}</span>
                                    @if ($semVinculo)
                                        <span class="ml-1 rounded-full bg-amber-200 px-2 py-1 text-xs font-semibold text-amber-900" title="Marcado como em uso, mas sem vínculo registrado">sem vínculo</span>
                                    @endif
                                </td>
                                <td class="px-3 py-3">
                                    <div class="flex justify-end gap-2">
                                        <a href="{{ route('administrativo.telefonia.devices.show', $device) }}" class="rounded-md border border-slate-300 px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50">Abrir</a>
                                        @can('module.telefonia.manage')
                                            <a href="{{ route('administrativo.telefonia.devices.edit', $device) }}" class="rounded-md border border-slate-300 px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50">Editar</a>
                                            @if ($device->vinculos_ativos_count === 0)
                                                <form method="POST" action="{{ route('administrativo.telefonia.devices.destroy', $device) }}" onsubmit="return confirm('Excluir o aparelho {{ $device->descricao }}?')">
                                                    @csrf @method('DELETE')
                                                    <button class="rounded-md border border-rose-300 px-3 py-1.5 text-xs font-medium text-rose-700 hover:bg-rose-50">Excluir</button>
                                                </form>
                                            @endif
                                        @endcan
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-3 py-10 text-center text-sm text-slate-500">
                                    Nenhum aparelho encontrado para os filtros atuais.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-4">{{ $devices->links() }}</div>
        </section>
    </div>
</x-app-layout>
