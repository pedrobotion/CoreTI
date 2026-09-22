<x-app-layout>
    <div class="mx-auto max-w-4xl space-y-6">
        <div class="flex items-center justify-between gap-3">
            <div>
                <p class="text-sm font-semibold uppercase tracking-wide text-slate-500">Administrativo</p>
                <h1 class="mt-1 text-2xl font-bold text-slate-950">{{ $title }}</h1>
                <p class="mt-2 text-sm text-slate-600">Registre o nome da compra, valor pago e anexe a fatura do cartão.</p>
            </div>
            <a href="{{ route('administrativo.cartao-ti.index') }}" class="inline-flex h-10 items-center justify-center rounded-md border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50">Voltar</a>
        </div>

        <section class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
            <form method="POST" action="{{ $action }}" enctype="multipart/form-data" class="grid gap-4">
                @csrf
                @if(($method ?? 'POST') !== 'POST')
                    @method($method)
                @endif

                @if($errors->any())
                    <div class="rounded-md border border-rose-200 bg-rose-50 p-4 text-sm text-rose-900">
                        <p class="font-semibold">Corrija os erros abaixo:</p>
                        <ul class="mt-2 list-disc space-y-1 pl-5">
                            @foreach($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <div class="grid gap-4 md:grid-cols-2">
                    <div>
                        <label class="text-sm font-medium text-slate-700">Cartão</label>
                        <select name="ti_card_id" class="mt-1 h-10 w-full rounded-md border-slate-300 text-sm">
                            <option value="">Selecione</option>
                            @foreach($cards as $cardOption)
                                <option value="{{ $cardOption->id }}" @selected((string) old('ti_card_id', $purchase->ti_card_id ?? '') === (string) $cardOption->id)>{{ $cardOption->name }} •••• {{ $cardOption->last4_digits }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="text-sm font-medium text-slate-700">Data da compra</label>
                        <input type="date" name="purchase_date" value="{{ old('purchase_date', optional($purchase?->purchase_date)->format('Y-m-d')) }}" class="mt-1 h-10 w-full rounded-md border-slate-300 text-sm">
                    </div>
                </div>

                <div class="grid gap-4 md:grid-cols-2">
                    <div>
                        <label class="text-sm font-medium text-slate-700">Nome da compra</label>
                        <input name="purchase_name" value="{{ old('purchase_name', $purchase->purchase_name ?? '') }}" class="mt-1 h-10 w-full rounded-md border-slate-300 text-sm" placeholder="Ex.: Software, licença, café, etc.">
                    </div>
                    <div>
                        <label class="text-sm font-medium text-slate-700">Valor pago</label>
                        <input name="purchase_value" value="{{ old('purchase_value', isset($purchase) ? number_format((float) $purchase->purchase_value, 2, ',', '.') : '') }}" class="mt-1 h-10 w-full rounded-md border-slate-300 text-sm" placeholder="0,00">
                    </div>
                </div>

                <div class="grid gap-4 md:grid-cols-2">
                    <div>
                        <label class="text-sm font-medium text-slate-700">Tipo de cobrança</label>
                        <select name="billing_type" class="mt-1 h-10 w-full rounded-md border-slate-300 text-sm">
                            <option value="">Selecione</option>
                            <option value="mensalidade" @selected(old('billing_type', $purchase->billing_type ?? '') === 'mensalidade')>Mensalidade</option>
                            <option value="anualidade" @selected(old('billing_type', $purchase->billing_type ?? '') === 'anualidade')>Anualidade</option>
                            <option value="avulsa" @selected(old('billing_type', $purchase->billing_type ?? '') === 'avulsa')>Compra avulsa</option>
                        </select>
                    </div>
                    <div>
                        <label class="text-sm font-medium text-slate-700">Moeda</label>
                        <select name="currency" class="mt-1 h-10 w-full rounded-md border-slate-300 text-sm">
                            <option value="">Selecione</option>
                            <option value="BRL" @selected(old('currency', $purchase->currency ?? '') === 'BRL')>Real (BRL)</option>
                            <option value="USD" @selected(old('currency', $purchase->currency ?? '') === 'USD')>Dólar (USD)</option>
                            <option value="EUR" @selected(old('currency', $purchase->currency ?? '') === 'EUR')>Euro (EUR)</option>
                        </select>
                    </div>
                </div>

                <div>
                    <label class="text-sm font-medium text-slate-700">Observação</label>
                    <textarea name="observacao" rows="4" class="mt-1 w-full rounded-md border-slate-300 text-sm" placeholder="Observações opcionais">{{ old('observacao', $purchase->observacao ?? '') }}</textarea>
                </div>

                <div class="grid gap-2">
                    <label class="text-sm font-medium text-slate-700">Fatura</label>
                    <input type="file" name="invoice_file" class="w-full rounded-md border-slate-300 text-sm">
                    @if(! empty($purchase?->invoice_storage_path))
                        <div class="text-sm text-slate-600">
                            Fatura atual:
                            <a href="{{ route('administrativo.cartao-ti.purchases.invoice.download', $purchase) }}" class="font-semibold text-[#033151] hover:underline">{{ $purchase->invoice_original_name }}</a>
                        </div>
                    @endif
                </div>

                <div class="flex justify-end gap-2 border-t border-slate-200 pt-4">
                    <a href="{{ route('administrativo.cartao-ti.index') }}" class="inline-flex h-10 items-center justify-center rounded-md border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-700 hover:bg-slate-50">Cancelar</a>
                    <button type="submit" class="inline-flex h-10 items-center justify-center rounded-md border border-[#033151] bg-[#033151] px-4 text-sm font-semibold text-white shadow-sm transition hover:opacity-90">Salvar</button>
                </div>
            </form>
        </section>
    </div>
</x-app-layout>
