<x-app-layout>
    <div class="mx-auto max-w-7xl space-y-6 px-4 py-8 sm:px-6">
        <div class="flex flex-wrap items-end justify-between gap-3">
            <div>
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Telefonia Corporativa</p>
                <h1 class="mt-1 text-2xl font-bold tracking-tight text-slate-900 sm:text-3xl">Aparelhos celulares</h1>
                <p class="mt-1 text-sm text-slate-500">Parque de aparelhos: marca, modelo, IMEI, estado e situação.</p>
            </div>
            <div class="flex items-center gap-2">
                <a href="{{ route('administrativo.telefonia.devices.export.excel', request()->query()) }}" class="inline-flex min-h-10 items-center justify-center rounded-md border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-700 hover:bg-slate-50">Excel</a>
                <a href="{{ route('administrativo.telefonia.devices.export.pdf', request()->query()) }}" class="inline-flex min-h-10 items-center justify-center rounded-md border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-700 hover:bg-slate-50">PDF</a>
                @can('module.telefonia.manage')
                    <a href="{{ route('administrativo.telefonia.devices.create') }}" style="background-color: #033151; border-color: #033151;" class="inline-flex min-h-10 items-center justify-center rounded-lg px-4 text-sm font-semibold text-white hover:opacity-90">Novo aparelho</a>
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
            <div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
                <span class="font-semibold">{{ $resumo['em_uso_sem_vinculo'] }} aparelho(s) marcado(s) como "Em Uso" sem nenhum vínculo registrado.</span>
                Enquanto estiverem assim, não aparecem como disponíveis para um novo vínculo.
            </div>
        @endif

        <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:px-6">
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
                    <button style="background-color: #033151; border-color: #033151;" class="inline-flex flex-1 items-center justify-center rounded-lg px-4 py-2 text-sm font-semibold text-white hover:opacity-90">Filtrar</button>
                    <a href="{{ route('administrativo.telefonia.devices.index') }}" class="inline-flex items-center justify-center rounded-md border border-slate-300 bg-white px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Limpar</a>
                </div>
            </form>
        </section>

        <section class="rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 p-5 sm:px-6">
                <div class="flex items-center gap-3">
                    <span class="grid h-9 w-9 shrink-0 place-items-center rounded-lg bg-indigo-50 text-indigo-600">
                        <svg viewBox="0 0 24 24" class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="6" y="2" width="12" height="20" rx="2"/><path d="M11 18h2"/></svg>
                    </span>
                    <div>
                        <h2 class="text-base font-bold text-slate-900">Aparelhos cadastrados</h2>
                        <p class="mt-0.5 text-sm text-slate-500">Resultado dos filtros aplicados.</p>
                    </div>
                </div>
                <span class="inline-flex items-center rounded-full bg-indigo-50 px-2.5 py-1 text-xs font-semibold text-indigo-700 ring-1 ring-inset ring-indigo-200">{{ number_format($devices->total(), 0, ',', '.') }} registro(s)</span>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full min-w-[960px] text-left text-sm">
                    <thead>
                        <tr class="border-b border-slate-100 text-xs font-semibold uppercase tracking-wide text-slate-400">
                            <th class="px-5 py-3 sm:px-6">Aparelho</th>
                            <th class="px-5 py-3">IMEI 1</th>
                            <th class="px-5 py-3">IMEI 2</th>
                            <th class="px-5 py-3">Estado</th>
                            <th class="px-5 py-3">Operação</th>
                            <th class="px-5 py-3">Situação</th>
                            <th class="px-5 py-3 text-right">Ações</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($devices as $device)
                            @php
                                $semVinculo = $device->status === \App\Models\CorpDevice::STATUS_EM_USO && $device->vinculos_ativos_count === 0;
                            @endphp
                            <tr class="transition hover:bg-slate-50 {{ $semVinculo ? 'bg-amber-50/60' : '' }}">
                                <td class="px-5 py-3 sm:px-6">
                                    <p class="font-semibold text-slate-900">{{ $device->descricao }}</p>
                                    @if ($device->numero_serie)
                                        <p class="text-xs text-slate-500">Série {{ $device->numero_serie }}</p>
                                    @endif
                                </td>
                                <td class="px-5 py-3 font-mono text-xs text-slate-700">{{ $device->imei_1_formatado ?: '—' }}</td>
                                <td class="px-5 py-3 font-mono text-xs text-slate-500">{{ $device->imei_2_formatado ?: '—' }}</td>
                                <td class="px-5 py-3">
                                    <span class="inline-flex items-center rounded-full px-2 py-1 text-xs font-semibold ring-1 ring-inset {{ $device->estado_aparelho === \App\Models\CorpDevice::ESTADO_NOVO ? 'bg-indigo-50 text-indigo-700 ring-indigo-200' : 'bg-slate-100 text-slate-600 ring-slate-200' }}">{{ $device->estado_aparelho }}</span>
                                </td>
                                <td class="px-5 py-3">
                                    <span class="inline-flex items-center rounded-full bg-slate-100 px-2 py-1 text-xs font-medium text-slate-600">{{ $device->scope_label }}</span>
                                </td>
                                <td class="px-5 py-3">
                                    @php
                                        $cor = match ($device->status) {
                                            \App\Models\CorpDevice::STATUS_DISPONIVEL => 'bg-emerald-50 text-emerald-700 ring-emerald-200',
                                            \App\Models\CorpDevice::STATUS_EM_USO => 'bg-sky-50 text-sky-700 ring-sky-200',
                                            default => 'bg-slate-100 text-slate-600 ring-slate-200',
                                        };
                                    @endphp
                                    <span class="inline-flex items-center rounded-full px-2 py-1 text-xs font-semibold ring-1 ring-inset {{ $cor }}">{{ $device->status }}</span>
                                    @if ($semVinculo)
                                        <span class="ml-1 inline-flex items-center rounded-full bg-amber-100 px-2 py-1 text-xs font-semibold text-amber-800 ring-1 ring-inset ring-amber-200" title="Marcado como em uso, mas sem vínculo registrado">sem vínculo</span>
                                    @endif
                                </td>
                                <td class="px-5 py-3">
                                    <div class="flex justify-end gap-2">
                                        <a href="{{ route('administrativo.telefonia.devices.show', $device) }}" class="inline-flex items-center justify-center rounded-md border border-slate-300 bg-white px-3 py-1.5 text-xs font-semibold text-[#033151] hover:bg-slate-50">Abrir</a>
                                        @can('module.telefonia.manage')
                                            <a href="{{ route('administrativo.telefonia.devices.edit', $device) }}" class="inline-flex items-center justify-center rounded-md border border-slate-300 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50">Editar</a>
                                            @if ($device->vinculos_ativos_count === 0)
                                                <form method="POST" action="{{ route('administrativo.telefonia.devices.destroy', $device) }}" onsubmit="return confirm('Excluir o aparelho {{ $device->descricao }}?')">
                                                    @csrf @method('DELETE')
                                                    <button class="inline-flex items-center justify-center rounded-md border border-rose-300 bg-white px-3 py-1.5 text-xs font-semibold text-rose-700 hover:bg-rose-50">Excluir</button>
                                                </form>
                                            @endif
                                        @endcan
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-6 py-10 text-center text-sm text-slate-400">
                                    Nenhum aparelho encontrado para os filtros atuais.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="border-t border-slate-100 p-5 sm:px-6">{{ $devices->links() }}</div>
        </section>
    </div>
</x-app-layout>
