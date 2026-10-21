<x-app-layout>
    <div class="mx-auto max-w-7xl space-y-6">
        <div>
            <p class="text-sm font-semibold uppercase tracking-wide text-slate-500">Administrativo</p>
            <h1 class="mt-1 text-2xl font-bold text-slate-950">Cartão TI</h1>
            <p class="mt-2 text-sm text-slate-600">Controle dos cartões de crédito usados pela TI, com cartões mascarados, lançamentos e faturas anexadas.</p>
        </div>

        @if (! empty($setupPending))
            <section class="rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
                Estrutura do módulo ainda não encontrada. Execute as migrations para liberar o cadastro.
            </section>
        @else
            <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
                <div class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
                    <p class="text-xs uppercase text-slate-500">Cartões cadastrados</p>
                    <p class="mt-2 text-2xl font-bold text-slate-950">{{ number_format($stats['cards_total']) }}</p>
                </div>
                <div class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
                    <p class="text-xs uppercase text-slate-500">Cartões ativos</p>
                    <p class="mt-2 text-2xl font-bold text-slate-950">{{ number_format($stats['cards_active']) }}</p>
                </div>
                <div class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
                    <p class="text-xs uppercase text-slate-500">Lançamentos</p>
                    <p class="mt-2 text-2xl font-bold text-slate-950">{{ number_format($stats['purchases_total']) }}</p>
                </div>
                <div class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
                    <p class="text-xs uppercase text-slate-500">Total gasto</p>
                    <p class="mt-2 text-2xl font-bold text-slate-950">R$ {{ number_format($stats['total_spent'], 2, ',', '.') }}</p>
                </div>
                <div class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
                    <p class="text-xs uppercase text-slate-500">Com fatura anexada</p>
                    <p class="mt-2 text-2xl font-bold text-slate-950">{{ number_format($stats['with_invoice']) }}</p>
                </div>
            </section>

            <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h2 class="text-base font-bold text-slate-900">Lançamentos</h2>
                        <p class="mt-1 text-sm text-slate-600">Nome da compra, valor e anexo da fatura ficam visíveis aqui.</p>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <a href="{{ route('administrativo.cartao-ti.cards.create') }}" class="inline-flex min-h-10 items-center justify-center rounded-md border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50">Novo cartão</a>
                        <a href="{{ route('administrativo.cartao-ti.purchases.create') }}" class="inline-flex min-h-10 items-center justify-center rounded-md border border-[#033151] bg-[#033151] px-4 text-sm font-semibold text-white shadow-sm transition hover:opacity-90">Novo lançamento</a>
                    </div>
                </div>

                <form method="GET" action="{{ route('administrativo.cartao-ti.index') }}" class="mt-4 grid gap-3 md:grid-cols-4">
                    <div class="md:col-span-2">
                        <label class="text-sm font-medium text-slate-700">Buscar</label>
                        <input type="text" name="q" value="{{ $search }}" placeholder="Produto, observação ou cartão" class="mt-1 h-10 w-full rounded-md border-slate-300 text-sm">
                    </div>
                    <div>
                        <label class="text-sm font-medium text-slate-700">Cartão</label>
                        <select name="card_id" class="mt-1 h-10 w-full rounded-md border-slate-300 text-sm">
                            <option value="">Todos</option>
                            @foreach($cards as $card)
                                <option value="{{ $card->id }}" @selected((int) $cardFilter === (int) $card->id)>{{ $card->name }} •••• {{ $card->last4_digits }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="text-sm font-medium text-slate-700">Por página</label>
                        <select name="per_page" class="mt-1 h-10 w-full rounded-md border-slate-300 text-sm">
                            @foreach([10, 15, 25, 50] as $perPage)
                                <option value="{{ $perPage }}" @selected((int) request('per_page', 10) === $perPage)>{{ $perPage }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="md:col-span-4 flex gap-2">
                        <button type="submit" class="inline-flex h-10 items-center justify-center rounded-md border border-[#033151] bg-[#033151] px-4 text-sm font-semibold text-white shadow-sm transition hover:opacity-90">Filtrar</button>
                        <a href="{{ route('administrativo.cartao-ti.index') }}" class="inline-flex h-10 items-center justify-center rounded-md border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50">Limpar</a>
                    </div>
                </form>
            </section>

            <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <h2 class="text-base font-bold text-slate-900">Cartões</h2>
                        <p class="mt-1 text-sm text-slate-600">Somente os 4 últimos dígitos são armazenados.</p>
                    </div>
                </div>

                <div class="mt-4 overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-100 text-sm">
                        <thead class="bg-slate-900 text-left text-xs font-semibold uppercase tracking-wide text-white">
                            <tr>
                                <th class="px-3 py-2">Nome</th>
                                <th class="px-3 py-2">Final</th>
                                <th class="px-3 py-2">Lançamentos</th>
                                <th class="px-3 py-2">Total gasto</th>
                                <th class="px-3 py-2">Status</th>
                                <th class="px-3 py-2">Ações</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 bg-white">
                            @forelse($cards as $card)
                                <tr>
                                    <td class="px-3 py-2 font-medium text-slate-900">{{ $card->name }}</td>
                                    <td class="px-3 py-2 font-mono text-slate-700">•••• {{ $card->last4_digits }}</td>
                                    <td class="px-3 py-2 text-slate-700">{{ number_format($card->purchases_count ?? 0) }}</td>
                                    <td class="px-3 py-2 text-slate-700">R$ {{ number_format((float) ($card->purchases_total_value ?? 0), 2, ',', '.') }}</td>
                                    <td class="px-3 py-2">
                                        <span class="inline-flex rounded-md px-2 py-1 text-xs font-semibold {{ $card->active ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-100 text-slate-700' }}">
                                            {{ $card->active ? 'Ativo' : 'Inativo' }}
                                        </span>
                                    </td>
                                    <td class="px-3 py-2">
                                        <div class="flex flex-wrap gap-2">
                                            <a href="{{ route('administrativo.cartao-ti.cards.edit', $card) }}" class="inline-flex rounded-md border border-slate-300 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50">Editar</a>
                                            <form method="POST" action="{{ route('administrativo.cartao-ti.cards.toggle', $card) }}">
                                                @csrf
                                                @method('PATCH')
                                                <button type="submit" class="inline-flex rounded-md border border-[#033151] bg-white px-3 py-1.5 text-xs font-semibold text-[#033151] hover:bg-slate-50">
                                                    {{ $card->active ? 'Desativar' : 'Ativar' }}
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="px-3 py-4 text-center text-slate-500">Nenhum cartão cadastrado.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <h2 class="text-base font-bold text-slate-900">Lançamentos recentes</h2>
                        <p class="mt-1 text-sm text-slate-600">Compras e valores utilizados no cartão.</p>
                    </div>
                </div>

                <div class="mt-4 overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-100 text-sm">
                        <thead class="bg-slate-900 text-left text-xs font-semibold uppercase tracking-wide text-white">
                            <tr>
                                <th class="px-3 py-2">Compra</th>
                                <th class="px-3 py-2">Cartão</th>
                                <th class="px-3 py-2">Valor</th>
                                <th class="px-3 py-2">Cobrança</th>
                                <th class="px-3 py-2">Moeda</th>
                                <th class="px-3 py-2">Data</th>
                                <th class="px-3 py-2">Fatura</th>
                                <th class="px-3 py-2">Ações</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 bg-white">
                            @forelse($purchases as $purchase)
                                <tr>
                                    <td class="px-3 py-2">
                                        <div class="font-medium text-slate-900">{{ $purchase->purchase_name }}</div>
                                        @if($purchase->observacao)
                                            <div class="text-xs text-slate-500">{{ $purchase->observacao }}</div>
                                        @endif
                                    </td>
                                    <td class="px-3 py-2 text-slate-700">{{ $purchase->card?->name }} •••• {{ $purchase->card?->last4_digits }}</td>
                                    <td class="px-3 py-2 text-slate-700">R$ {{ number_format((float) $purchase->purchase_value, 2, ',', '.') }}</td>
                                    <td class="px-3 py-2 text-slate-700">{{ $billingTypes[$purchase->billing_type] ?? '-' }}</td>
                                    <td class="px-3 py-2 text-slate-700">{{ $purchase->currency ?: '-' }}</td>
                                    <td class="px-3 py-2 text-slate-700">{{ $purchase->purchase_date?->format('d/m/Y') ?: '-' }}</td>
                                    <td class="px-3 py-2 text-slate-700">
                                        @if($purchase->invoice_storage_path)
                                            <a href="{{ route('administrativo.cartao-ti.purchases.invoice.download', $purchase) }}" class="inline-flex rounded-md border border-[#033151] bg-white px-3 py-1.5 text-xs font-semibold text-[#033151] hover:bg-slate-50">Baixar</a>
                                        @else
                                            <span class="text-xs text-slate-500">Sem fatura</span>
                                        @endif
                                    </td>
                                    <td class="px-3 py-2">
                                        <a href="{{ route('administrativo.cartao-ti.purchases.edit', $purchase) }}" class="inline-flex rounded-md border border-slate-300 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50">Editar</a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="px-3 py-4 text-center text-slate-500">Nenhum lançamento cadastrado.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="mt-4">
                    {{ $purchases->links() }}
                </div>
            </section>
        @endif
    </div>
</x-app-layout>
