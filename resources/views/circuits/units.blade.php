<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Circuitos Unidades') }}
        </h2>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto w-full max-w-[1500px] px-4 sm:px-6 lg:px-8">
            <div class="panel-surface bg-white shadow-sm sm:rounded-lg border border-gray-100">
                <div class="p-5 lg:p-6 border-b border-gray-100">
                    <div class="filters-toolbar">
                        <form id="filters-form" method="GET" action="{{ route('circuits.units') }}" class="toolbar-search-grid">
                                <select name="operadora" class="toolbar-select status-select text-sm shadow-sm focus:border-slate-900 focus:ring-slate-900">
                                    <option value="">Todos</option>
                                    @foreach ($operadoras as $operadora)
                                        <option value="{{ $operadora }}" @selected(($filters['operadora'] ?? '') === $operadora)>{{ $operadora }}</option>
                                    @endforeach
                                </select>

                                <input
                                    type="text"
                                    name="q"
                                    value="{{ $search }}"
                                    placeholder="Buscar por operadora, unidade, serviço, endereço ou contato"
                                    class="toolbar-input min-w-0 rounded-md border-slate-300 text-sm shadow-sm focus:border-slate-900 focus:ring-slate-900"
                                    style="min-width: 300px;"
                                />

                                <button type="submit" form="filters-form" class="toolbar-btn-primary inline-flex w-auto items-center justify-center rounded-md border border-slate-900 bg-slate-900 px-4 text-sm font-semibold text-white shadow-sm hover:bg-slate-800">
                                    Buscar
                                </button>

                                <a href="{{ route('circuits.units') }}" class="toolbar-btn inline-flex w-auto items-center justify-center rounded-md border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50">
                                    Limpar
                                </a>
                                <select name="per_page" form="filters-form" onchange="document.getElementById('filters-form').submit()" class="toolbar-select text-sm">
                                    <option value="5" @selected($perPage === 5)>5 por página</option>
                                    <option value="10" @selected($perPage === 10)>10 por página</option>
                                    <option value="15" @selected($perPage === 15)>15 por página</option>
                                    <option value="25" @selected($perPage === 25)>25 por página</option>
                                    <option value="50" @selected($perPage === 50)>50 por página</option>
                                </select>
                                <button type="button" onclick="openCircuitModal('create-circuit-modal')" class="btn-coreti-primary whitespace-nowrap">
                                    Adicionar Circuito
                                </button>
                                <button type="button" onclick="openCircuitModal('create-operator-modal')" class="toolbar-btn inline-flex w-auto items-center justify-center rounded-md border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50 whitespace-nowrap">
                                    Adicionar Operadora
                                </button>
                        </form>
                    </div>
                </div>

                <div class="p-5 lg:p-6">
                    <div class="data-table-shell overflow-x-auto border border-gray-200 rounded-lg w-full">
                        <table class="compact-table w-full min-w-[1280px] table-auto divide-y divide-gray-200 text-sm">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-3 text-left font-semibold text-gray-700">Operadora</th>
                                    <th class="px-4 py-3 text-left font-semibold text-gray-700">Unidade</th>
                                    <th class="px-4 py-3 text-left font-semibold text-gray-700">Serviço</th>
                                    <th class="px-4 py-3 text-left font-semibold text-gray-700">Endereço</th>
                                    <th class="px-4 py-3 text-left font-semibold text-gray-700">Contato</th>
                                    <th class="px-4 py-3 text-left font-semibold text-gray-700">Informações Adicionais</th>
                                    <th class="px-4 py-3 text-left font-semibold text-gray-700">Ações</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                @forelse ($units as $unit)
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-4 py-3 text-gray-700">{{ $unit->operadora }}</td>
                                        <td class="px-4 py-3 text-gray-900 font-medium">
                                            <button type="button" onclick="openCircuitModal('circuit-modal-{{ $unit->id_circuitos }}')" class="text-left text-slate-900 hover:text-blue-700">
                                                {{ $unit->unidade->unidade ?? $unit->unidades_circuitos }}
                                            </button>
                                        </td>
                                        <td class="px-4 py-3 text-gray-700">{{ $unit->servico }}</td>
                                        <td class="px-4 py-3 text-gray-700">{{ $unit->endereco }}</td>
                                        <td class="px-4 py-3 text-gray-700">
                                            @if ($unit->contato_whatsapp && $unit->whatsappUrl())
                                                <a href="{{ $unit->whatsappUrl() }}" target="_blank" rel="noopener noreferrer" class="text-emerald-700 hover:text-emerald-800 underline">
                                                    {{ $unit->contato }}
                                                </a>
                                            @else
                                                {{ $unit->contato }}
                                            @endif
                                        </td>
                                        <td class="px-4 py-3 text-gray-700 max-w-xs">
                                            <div class="line-clamp-3 whitespace-pre-line">{{ $unit->informacoes_adicionais ?: '-' }}</div>
                                        </td>
                                        <td class="px-4 py-3">
                                            <div class="flex items-center gap-2">
                                                <a href="{{ route('circuits.units.edit', $unit) }}" class="inline-flex items-center justify-center w-8 h-8 rounded-md text-gray-600 hover:text-gray-900" title="Editar circuito" aria-label="Editar circuito">
                                                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
                                                        <path d="M16.862 4.487a2.1 2.1 0 1 1 2.97 2.97L8.5 18.79 4 20l1.21-4.5 11.652-11.013z" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"/>
                                                    </svg>
                                                    <span class="sr-only">Editar</span>
                                                </a>
                                                <form method="POST" action="{{ route('circuits.units.destroy', $unit) }}" data-confirm-message="Tem certeza que deseja excluir este circuito?">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="inline-flex items-center justify-center w-8 h-8 rounded-md text-gray-600 hover:text-gray-900" title="Excluir circuito" aria-label="Excluir circuito">
                                                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
                                                            <path d="M4 7h16M9 7V5h6v2m-7 0 .7 11.2A2 2 0 0 0 10.7 20h2.6a2 2 0 0 0 1.99-1.8L16 7" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"/>
                                                        </svg>
                                                        <span class="sr-only">Excluir</span>
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="7" class="px-4 py-6 text-center text-gray-500">
                                            Nenhum circuito encontrado.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    <div class="flex items-center justify-end mt-4">
                        {{ $units->links('vendor.pagination.cocari') }}
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div id="create-circuit-modal" class="fixed inset-0 z-50 hidden items-center justify-center bg-slate-900/60 px-4">
        <div class="w-full max-w-6xl rounded-lg border border-slate-200 bg-white p-5 shadow-xl">
            <div class="mb-4">
                <h2 class="text-lg font-semibold text-slate-900">Adicionar Circuito</h2>
                <button type="button" onclick="closeCircuitModal('create-circuit-modal', true)" class="mt-1 text-slate-700 hover:text-slate-900">Fechar</button>
            </div>

            <form id="create-circuit-form" method="POST" action="{{ route('circuits.units.store') }}" class="space-y-4"
                x-data="{
                    lookupUfUrl: @js(route('circuits.units.lookup.uf')),
                    async fillAddressFromUnit() {
                        const unitSelect = this.$refs.unitSelect;
                        const enderecoInput = this.$refs.enderecoInput;
                        if (!unitSelect || !unitSelect.value) return;
                        try {
                            const response = await fetch(`${this.lookupUfUrl}?id_unidades=${encodeURIComponent(unitSelect.value)}`, { headers: { Accept: 'application/json' } });
                            const payload = await response.json();
                            if (response.ok && payload.found && enderecoInput && payload.endereco) {
                                enderecoInput.value = payload.endereco;
                                enderecoInput.dispatchEvent(new Event('input', { bubbles: true }));
                            }
                        } catch (e) {}
                    }
                }"
            >
                @csrf

                <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                    <div>
                        <label class="text-sm font-medium text-slate-700">Operadora</label>
                        <select name="operadora" class="mt-1 w-full rounded-md border-slate-300 text-sm shadow-sm focus:border-slate-900 focus:ring-slate-900" required>
                            <option value="">Selecione a operadora</option>
                            @foreach ($operadorasCadastro as $operadora)
                                <option value="{{ $operadora }}" @selected(old('operadora') === $operadora)>{{ $operadora }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="text-sm font-medium text-slate-700">Unidade</label>
                        <select name="id_unidades" x-ref="unitSelect" @change="fillAddressFromUnit" class="mt-1 w-full rounded-md border-slate-300 text-sm shadow-sm focus:border-slate-900 focus:ring-slate-900" required>
                            <option value="">Selecione uma unidade</option>
                            @foreach ($unidades as $unidade)
                                <option value="{{ $unidade->id_unidades }}" @selected(old('id_unidades') == $unidade->id_unidades)>
                                    {{ $unidade->unidade }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="text-sm font-medium text-slate-700">Serviço</label>
                        <input type="text" name="servico" value="{{ old('servico') }}" class="mt-1 w-full rounded-md border-slate-300 text-sm shadow-sm focus:border-slate-900 focus:ring-slate-900" required />
                    </div>

                    <div class="lg:col-span-2">
                        <label class="text-sm font-medium text-slate-700">Endereço</label>
                        <input type="text" name="endereco" x-ref="enderecoInput" value="{{ old('endereco') }}" readonly class="mt-1 w-full rounded-md border-slate-300 bg-slate-50 text-sm text-slate-700 shadow-sm focus:border-slate-900 focus:ring-slate-900" required />
                    </div>

                    <div>
                        <label class="text-sm font-medium text-slate-700">Contato</label>
                        <input type="text" name="contato" value="{{ old('contato') }}" class="mt-1 w-full rounded-md border-slate-300 text-sm shadow-sm focus:border-slate-900 focus:ring-slate-900" required />
                    </div>

                    <div class="sm:col-span-2 lg:col-span-3">
                        <label class="inline-flex items-center gap-2 text-sm text-slate-700">
                            <input type="checkbox" name="contato_whatsapp" value="1" @checked(old('contato_whatsapp')) class="rounded border-slate-300 text-slate-900 focus:ring-slate-900">
                            Contato via WhatsApp
                        </label>
                    </div>

                    <div class="sm:col-span-2 lg:col-span-3">
                        <label class="text-sm font-medium text-slate-700">Informações Adicionais</label>
                        <textarea name="informacoes_adicionais" rows="3" class="mt-1 w-full rounded-md border-slate-300 text-sm shadow-sm focus:border-slate-900 focus:ring-slate-900">{{ old('informacoes_adicionais') }}</textarea>
                    </div>

                    <div class="sm:col-span-2 lg:col-span-3 flex items-end justify-start gap-2 pt-2">
                        <button type="button" onclick="closeCircuitModal('create-circuit-modal', true)" class="toolbar-btn inline-flex min-h-10 items-center justify-center rounded-md border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50">Cancelar</button>
                        <button type="submit" class="toolbar-btn-primary inline-flex min-h-10 items-center justify-center rounded-md border border-slate-900 bg-slate-900 px-4 text-sm font-semibold text-white shadow-sm hover:bg-slate-800">Adicionar Circuito</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div id="create-operator-modal" class="fixed inset-0 z-50 hidden items-center justify-center bg-slate-900/60 px-4">
        <div class="w-full max-w-xl rounded-lg border border-slate-200 bg-white p-5 shadow-xl">
            <div class="mb-4">
                <h2 class="text-lg font-semibold text-slate-900">Adicionar Operadora</h2>
                <button type="button" onclick="closeCircuitModal('create-operator-modal', true)" class="mt-1 text-slate-700 hover:text-slate-900">Fechar</button>
            </div>

            <form method="POST" action="{{ route('circuits.operators.store') }}" class="space-y-4">
                @csrf

                <div>
                    <label class="text-sm font-medium text-slate-700">Nome da operadora</label>
                    <input type="text" name="nome" value="{{ old('nome') }}" class="mt-1 w-full rounded-md border-slate-300 text-sm shadow-sm focus:border-slate-900 focus:ring-slate-900" required>
                    @error('nome')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>

                <div class="flex items-center justify-end gap-2">
                    <button type="button" onclick="closeCircuitModal('create-operator-modal', true)" class="toolbar-btn inline-flex min-h-10 items-center justify-center rounded-md border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50">Cancelar</button>
                    <button type="submit" class="toolbar-btn-primary inline-flex min-h-10 items-center justify-center rounded-md border border-slate-900 bg-slate-900 px-4 text-sm font-semibold text-white shadow-sm hover:bg-slate-800">Salvar operadora</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openCircuitModal(id) {
            const modal = document.getElementById(id);
            if (!modal) return;
            modal.classList.remove('hidden');
            modal.classList.add('flex');
        }

        function closeCircuitModal(id, reset = false) {
            const modal = document.getElementById(id);
            if (!modal) return;
            if (reset) {
                const form = modal.querySelector('form');
                if (form) form.reset();
            }
            modal.classList.remove('flex');
            modal.classList.add('hidden');
        }

        @if ($errors->any() && old('nome'))
            openCircuitModal('create-operator-modal');
        @elseif ($errors->any() && (old('operadora') || old('id_unidades') || old('servico') || old('endereco') || old('contato') || old('informacoes_adicionais')))
            openCircuitModal('create-circuit-modal');
        @endif
    </script>
</x-app-layout>
