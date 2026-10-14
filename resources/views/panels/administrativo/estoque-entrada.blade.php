<x-app-layout>
    <div class="mx-auto max-w-2xl space-y-6">
        <div>
            <a href="{{ route('administrativo.estoque-interno') }}" class="text-xs font-semibold uppercase tracking-wide text-slate-500 underline">← Estoque interno</a>
            <h1 class="mt-2 text-2xl font-bold text-slate-950 dark:text-white">Entrada — {{ $product->nome }}</h1>
            <p class="mt-1 text-sm text-slate-500">{{ $product->categoria }} · {{ $product->ehSerial() ? 'uma unidade por serial' : 'por quantidade' }}</p>
        </div>

        @if ($errors->any())
            <div class="rounded-md border border-rose-300 bg-rose-50 px-4 py-3 text-sm text-rose-800">
                @foreach ($errors->all() as $erro)<p>{{ $erro }}</p>@endforeach
            </div>
        @endif

        <form method="POST" action="{{ route('administrativo.estoque-interno.entrada.store', $product) }}" class="space-y-4 rounded-lg border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
            @csrf
            @if ($product->ehSerial())
                <div class="grid gap-4 sm:grid-cols-2">
                    <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">Número de série
                        <input type="text" name="serial_number" value="{{ old('serial_number') }}" maxlength="120" class="mt-1 block h-10 w-full rounded-md border-slate-300 text-sm">
                    </label>
                    <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">Plaqueta
                        <input type="text" name="plaqueta" value="{{ old('plaqueta') }}" maxlength="100" class="mt-1 block h-10 w-full rounded-md border-slate-300 text-sm">
                    </label>
                    <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">Modelo
                        <input type="text" name="modelo" value="{{ old('modelo') }}" maxlength="160" class="mt-1 block h-10 w-full rounded-md border-slate-300 text-sm">
                    </label>
                    <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">Estado
                        <select name="estado" class="mt-1 block h-10 w-full rounded-md border-slate-300 text-sm">
                            @foreach (\App\Models\InternalStockUnit::ESTADOS as $e)<option value="{{ $e }}" @selected(old('estado') === $e)>{{ $e }}</option>@endforeach
                        </select>
                    </label>
                </div>
            @else
                <label class="block text-xs font-semibold uppercase tracking-wide text-slate-500">Quantidade a adicionar
                    <input type="number" name="quantidade" min="1" value="{{ old('quantidade', 1) }}" required class="mt-1 block h-10 w-40 rounded-md border-slate-300 text-sm">
                </label>
                <p class="text-xs text-slate-500">Saldo atual: <strong>{{ $product->saldo }}</strong></p>
            @endif

            <label class="block text-xs font-semibold uppercase tracking-wide text-slate-500">Observação
                <input type="text" name="observacao" value="{{ old('observacao') }}" maxlength="1000" placeholder="nota fiscal, origem da compra, etc." class="mt-1 block h-10 w-full rounded-md border-slate-300 text-sm">
            </label>

            <div class="flex justify-end gap-2 pt-2">
                <a href="{{ route('administrativo.estoque-interno') }}" class="h-10 rounded-md border border-slate-300 bg-white px-4 text-sm font-semibold leading-10 text-slate-700 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200">Cancelar</a>
                <button class="h-10 rounded-md bg-emerald-600 px-4 text-sm font-semibold text-white hover:bg-emerald-700">Registrar entrada</button>
            </div>
        </form>
    </div>
</x-app-layout>
