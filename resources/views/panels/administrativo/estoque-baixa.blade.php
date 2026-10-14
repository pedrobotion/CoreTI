<x-app-layout>
    <div class="mx-auto max-w-2xl space-y-6">
        <div>
            <a href="{{ route('administrativo.estoque-interno') }}" class="text-xs font-semibold uppercase tracking-wide text-slate-500 underline">← Estoque interno</a>
            <h1 class="mt-2 text-2xl font-bold text-slate-950 dark:text-white">Baixa — {{ $product->nome }}</h1>
            <p class="mt-1 text-sm text-slate-500">{{ $product->categoria }} · saldo atual <strong>{{ $product->saldo }}</strong></p>
        </div>

        @if ($errors->any())
            <div class="rounded-md border border-rose-300 bg-rose-50 px-4 py-3 text-sm text-rose-800">
                @foreach ($errors->all() as $erro)<p>{{ $erro }}</p>@endforeach
            </div>
        @endif

        @if ($product->saldo === 0)
            <div class="rounded-md border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-800">Sem saldo para dar baixa.</div>
        @endif

        <form method="POST" action="{{ route('administrativo.estoque-interno.baixa.store', $product) }}" class="space-y-4 rounded-lg border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
            @csrf
            @if ($product->ehSerial())
                <label class="block text-xs font-semibold uppercase tracking-wide text-slate-500">Unidade
                    <select name="internal_stock_unit_id" required class="mt-1 block h-10 w-full rounded-md border-slate-300 text-sm">
                        @foreach ($product->units as $u)
                            <option value="{{ $u->id }}">{{ $u->serial_number ?: 'sem serial' }}@if($u->plaqueta) · plaq {{ $u->plaqueta }}@endif @if($u->modelo) · {{ $u->modelo }}@endif ({{ $u->estado }})</option>
                        @endforeach
                    </select>
                </label>
            @else
                <label class="block text-xs font-semibold uppercase tracking-wide text-slate-500">Quantidade a baixar
                    <input type="number" name="quantidade" min="1" max="{{ $product->saldo }}" value="{{ old('quantidade', 1) }}" required class="mt-1 block h-10 w-40 rounded-md border-slate-300 text-sm">
                </label>
            @endif

            <div class="grid gap-4 sm:grid-cols-2">
                <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">Motivo
                    <select name="motivo" required class="mt-1 block h-10 w-full rounded-md border-slate-300 text-sm">
                        <option value="entrega_colaborador">Entrega a colaborador</option>
                        <option value="uso_bancada">Uso na bancada</option>
                        <option value="devolucao">Devolução / troca</option>
                        <option value="descarte">Descarte</option>
                        <option value="ajuste">Ajuste</option>
                    </select>
                </label>
                <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">Destino
                    <select name="destino_tipo" class="mt-1 block h-10 w-full rounded-md border-slate-300 text-sm">
                        <option value="colaborador">Colaborador</option>
                        <option value="unidade">Unidade</option>
                        <option value="bancada">Bancada</option>
                        <option value="outro">Outro</option>
                    </select>
                </label>
            </div>

            <label class="block text-xs font-semibold uppercase tracking-wide text-slate-500">Quem recebeu / destino
                <input type="text" name="destino_descricao" value="{{ old('destino_descricao') }}" maxlength="255" placeholder="nome do colaborador, unidade, chamado..." class="mt-1 block h-10 w-full rounded-md border-slate-300 text-sm">
            </label>
            <label class="block text-xs font-semibold uppercase tracking-wide text-slate-500">Observação
                <input type="text" name="observacao" value="{{ old('observacao') }}" maxlength="1000" class="mt-1 block h-10 w-full rounded-md border-slate-300 text-sm">
            </label>

            <div class="flex justify-end gap-2 pt-2">
                <a href="{{ route('administrativo.estoque-interno') }}" class="h-10 rounded-md border border-slate-300 bg-white px-4 text-sm font-semibold leading-10 text-slate-700 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200">Cancelar</a>
                <button @disabled($product->saldo === 0) class="h-10 rounded-md bg-slate-900 px-4 text-sm font-semibold text-white hover:bg-slate-800 disabled:opacity-40">Registrar baixa</button>
            </div>
        </form>
    </div>
</x-app-layout>
