<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Adicionar Circuito') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">
                    <form method="POST" action="{{ route('circuits.units.store') }}" class="space-y-4"
                        x-data="{
                            lookupUfUrl: @js(route('circuits.units.lookup.uf')),
                            async fillUfFromUnit() {
                                const unitSelect = this.$refs.unitSelect;
                                const enderecoInput = this.$refs.enderecoInput;
                                if (!unitSelect || !unitSelect.value) return;
                                try {
                                    const response = await fetch(`${this.lookupUfUrl}?id_unidades=${encodeURIComponent(unitSelect.value)}`, { headers: { Accept: 'application/json' } });
                                    const payload = await response.json();
                                    if (response.ok && payload.found) {
                                        if (enderecoInput && payload.endereco) {
                                            enderecoInput.value = payload.endereco;
                                            enderecoInput.dispatchEvent(new Event('input', { bubbles: true }));
                                        }
                                    }
                                } catch (e) {}
                            }
                        }"
                    >
                        @csrf

                        <div>
                            <label class="block text-sm font-medium text-gray-700">Operadora</label>
                            <select name="operadora" class="mt-1 block w-full rounded-md border-gray-200" required>
                                <option value="">Selecione a operadora</option>
                                @foreach ($operadorasCadastro as $operadora)
                                    <option value="{{ $operadora }}" @selected(old('operadora') === $operadora)>{{ $operadora }}</option>
                                @endforeach
                            </select>
                            @error('operadora')<p class="text-sm text-red-600 mt-1">{{ $message }}</p>@enderror
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700">Unidade</label>
                            <select name="id_unidades" x-ref="unitSelect" @change="fillUfFromUnit" class="mt-1 block w-full rounded-md border-gray-200" required>
                                <option value="">Selecione uma unidade</option>
                                @foreach ($unidades as $unidade)
                                    <option value="{{ $unidade->id_unidades }}" @selected(old('id_unidades') == $unidade->id_unidades)>
                                        {{ $unidade->unidade }}
                                    </option>
                                @endforeach
                            </select>
                            @error('id_unidades')<p class="text-sm text-red-600 mt-1">{{ $message }}</p>@enderror
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700">Serviço</label>
                            <input type="text" name="servico" value="{{ old('servico') }}" class="mt-1 block w-full rounded-md border-gray-200" required />
                            @error('servico')<p class="text-sm text-red-600 mt-1">{{ $message }}</p>@enderror
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700">Endereço</label>
                            <input type="text" name="endereco" x-ref="enderecoInput" value="{{ old('endereco') }}" class="mt-1 block w-full rounded-md border-gray-200" required />
                            @error('endereco')<p class="text-sm text-red-600 mt-1">{{ $message }}</p>@enderror
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700">Contato</label>
                            <input type="text" name="contato" value="{{ old('contato') }}" class="mt-1 block w-full rounded-md border-gray-200" required />
                            @error('contato')<p class="text-sm text-red-600 mt-1">{{ $message }}</p>@enderror
                        </div>

                        <label class="inline-flex items-center gap-2 text-sm text-gray-700">
                            <input type="checkbox" name="contato_whatsapp" value="1" @checked(old('contato_whatsapp')) class="rounded border-gray-300 text-slate-900 focus:ring-slate-900">
                            Contato via WhatsApp
                        </label>

                        <div>
                            <label class="block text-sm font-medium text-gray-700">Informações Adicionais</label>
                            <textarea name="informacoes_adicionais" rows="4" class="mt-1 block w-full rounded-md border-gray-200">{{ old('informacoes_adicionais') }}</textarea>
                            @error('informacoes_adicionais')<p class="text-sm text-red-600 mt-1">{{ $message }}</p>@enderror
                        </div>

                        <div class="flex items-center gap-3 pt-2">
                            <button type="submit" class="btn-coreti-primary">
                                Salvar
                            </button>
                            <a href="{{ route('circuits.units') }}" class="inline-flex items-center px-4 py-2 rounded-md border border-gray-200 text-gray-700 hover:bg-gray-50">
                                Cancelar
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
