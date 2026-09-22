<x-app-layout>
    <div class="mx-auto max-w-3xl space-y-6">
        <div class="flex items-center justify-between gap-3">
            <div>
                <p class="text-sm font-semibold uppercase tracking-wide text-slate-500">Administrativo</p>
                <h1 class="mt-1 text-2xl font-bold text-slate-950">{{ $title }}</h1>
                <p class="mt-2 text-sm text-slate-600">Cadastre apenas o nome do cartão e os quatro últimos dígitos visíveis.</p>
            </div>
            <a href="{{ route('administrativo.cartao-ti.index') }}" class="inline-flex h-10 items-center justify-center rounded-md border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50">Voltar</a>
        </div>

        <section class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
            <form method="POST" action="{{ $action }}" class="grid gap-4">
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
                        <label class="text-sm font-medium text-slate-700">Nome do cartão</label>
                        <input name="name" value="{{ old('name', $card->name ?? '') }}" class="mt-1 h-10 w-full rounded-md border-slate-300 text-sm" placeholder="Cartão TI principal">
                    </div>
                    <div>
                        <label class="text-sm font-medium text-slate-700">4 últimos dígitos</label>
                        <input name="last4_digits" value="{{ old('last4_digits', $card->last4_digits ?? '') }}" maxlength="4" inputmode="numeric" class="mt-1 h-10 w-full rounded-md border-slate-300 text-sm" placeholder="1234">
                    </div>
                </div>

                <div>
                    <label class="text-sm font-medium text-slate-700">Observação</label>
                    <textarea name="observacao" rows="4" class="mt-1 w-full rounded-md border-slate-300 text-sm" placeholder="Observações internas">{{ old('observacao', $card->observacao ?? '') }}</textarea>
                </div>

                <label class="inline-flex items-center gap-2 text-sm font-medium text-slate-700">
                    <input type="checkbox" name="active" value="1" class="rounded border-slate-300 text-[#033151]" @checked(old('active', $card?->active ?? true))>
                    Cartão ativo
                </label>

                <div class="flex justify-end gap-2 border-t border-slate-200 pt-4">
                    <a href="{{ route('administrativo.cartao-ti.index') }}" class="inline-flex h-10 items-center justify-center rounded-md border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-700 hover:bg-slate-50">Cancelar</a>
                    <button type="submit" class="inline-flex h-10 items-center justify-center rounded-md border border-[#033151] bg-[#033151] px-4 text-sm font-semibold text-white shadow-sm transition hover:opacity-90">Salvar</button>
                </div>
            </form>
        </section>
    </div>
</x-app-layout>
