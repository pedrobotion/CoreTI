<x-app-layout>
    <div class="mx-auto max-w-3xl space-y-6 px-4 py-8 sm:px-6">
        <div>
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Telefonia Corporativa</p>
            <h1 class="mt-1 text-2xl font-bold tracking-tight text-slate-900 sm:text-3xl">Importar fatura</h1>
            <p class="mt-1 text-sm text-slate-500">O PDF é lido e você confere o resultado antes de qualquer gravação.</p>
        </div>

        @include('partials.modulo-flash')

        <form method="POST" action="{{ route('administrativo.telefonia.faturas.preview') }}" enctype="multipart/form-data" class="space-y-5 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            @csrf

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                @if (count($scopeOptions) > 1)
                    <div>
                        <label for="scope" class="mb-1 block text-sm font-medium text-slate-700">Operação <span class="text-rose-600">*</span></label>
                        <select id="scope" name="scope" required class="w-full rounded-md border-slate-300 text-sm">
                            @foreach ($scopeOptions as $valor => $rotulo)
                                <option value="{{ $valor }}" @selected(old('scope') === $valor)>{{ $rotulo }}</option>
                            @endforeach
                        </select>
                        <p class="mt-1 text-xs text-slate-500">A fatura será conferida contra as linhas desta operação.</p>
                        <x-input-error :messages="$errors->get('scope')" class="mt-1" />
                    </div>
                @else
                    <input type="hidden" name="scope" value="{{ array_key_first($scopeOptions) }}">
                @endif

                <div>
                    <label for="operadora" class="mb-1 block text-sm font-medium text-slate-700">Operadora <span class="text-rose-600">*</span></label>
                    <select id="operadora" name="operadora" required class="w-full rounded-md border-slate-300 text-sm">
                        @foreach ($operadoras as $operadora)
                            <option value="{{ $operadora }}" @selected(old('operadora') === $operadora)>{{ $operadora }}</option>
                        @endforeach
                    </select>
                    <p class="mt-1 text-xs text-slate-500">Cada operadora emite num layout diferente, então cada uma precisa do seu leitor.</p>
                    <x-input-error :messages="$errors->get('operadora')" class="mt-1" />
                </div>
            </div>

            <div>
                <label for="arquivo" class="mb-1 block text-sm font-medium text-slate-700">PDF da fatura <span class="text-rose-600">*</span></label>
                <input id="arquivo" name="arquivo" type="file" accept="application/pdf" required class="w-full rounded-md border border-slate-300 p-2 text-sm">
                <p class="mt-1 text-xs text-slate-500">Até 50 MB. O mesmo arquivo não pode ser importado duas vezes.</p>
                <x-input-error :messages="$errors->get('arquivo')" class="mt-1" />
            </div>

            <div class="rounded-xl border border-sky-200 bg-sky-50 px-4 py-3 text-sm text-sky-900">
                Depois de ler o PDF, o sistema mostra o que bate com o cadastro, que números são novos e quais linhas cadastradas
                não vieram na conta. Nada é gravado até você confirmar.
            </div>

            <div class="flex justify-end gap-2 border-t border-slate-100 pt-5">
                <a href="{{ route('administrativo.telefonia.faturas.index') }}" class="inline-flex min-h-10 items-center justify-center rounded-md border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-700 hover:bg-slate-50">Cancelar</a>
                <button style="background-color: #033151; border-color: #033151;" class="inline-flex min-h-10 items-center justify-center rounded-lg px-4 text-sm font-semibold text-white hover:opacity-90">Ler fatura</button>
            </div>
        </form>
    </div>
</x-app-layout>
