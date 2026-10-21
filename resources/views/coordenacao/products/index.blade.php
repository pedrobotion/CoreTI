<x-app-layout>
    <div class="mx-auto max-w-7xl space-y-6">
        @include('coordenacao.partials.nav',['active'=>'products'])

        <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:px-6">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div class="flex items-center gap-3">
                    <span class="grid h-9 w-9 shrink-0 place-items-center rounded-lg bg-teal-50 text-teal-600">
                        <svg viewBox="0 0 24 24" class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 1v22M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
                    </span>
                    <div>
                        <h2 class="text-base font-bold text-slate-900">Produtos de TI</h2>
                        <p class="mt-0.5 text-sm text-slate-500">Cadastre contratos e defina como cada produto será apropriado no rateio.</p>
                    </div>
                </div>
                <a href="{{ route('coordenacao.products.create') }}" style="background-color: #033151; border-color: #033151;" class="inline-flex min-h-10 items-center justify-center rounded-lg px-4 text-sm font-semibold text-white hover:opacity-90">Novo produto</a>
            </div>
            <form class="mt-4 flex gap-2">
                <input name="q" value="{{ request('q') }}" placeholder="Buscar produto" class="w-full rounded-md border-slate-300 text-sm">
                <button style="background-color: #033151; border-color: #033151;" class="inline-flex items-center justify-center rounded-lg px-4 text-sm font-semibold text-white hover:opacity-90">Buscar</button>
            </form>
        </section>

        <section class="rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="flex items-center justify-between gap-3 border-b border-slate-100 p-5 sm:px-6">
                <h2 class="text-base font-bold text-slate-900">Produtos cadastrados</h2>
                <span class="inline-flex items-center rounded-full bg-teal-50 px-2.5 py-1 text-xs font-semibold text-teal-700 ring-1 ring-inset ring-teal-200">{{ number_format($products->total(), 0, ',', '.') }} registro(s)</span>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full min-w-[900px] text-sm">
                    <thead>
                        <tr class="border-b border-slate-100 text-left text-xs font-semibold uppercase tracking-wide text-slate-400">
                            <th class="px-5 py-3 sm:px-6">Produto</th><th class="px-5 py-3">Fornecedor</th><th class="px-5 py-3">Valor</th><th class="px-5 py-3">Cobrança</th><th class="px-5 py-3">Base</th><th class="px-5 py-3">Status</th><th class="px-5 py-3 text-right">Ações</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse($products as $product)
                            <tr class="transition hover:bg-slate-50">
                                <td class="px-5 py-3 font-medium text-slate-800 sm:px-6">{{ $product->name }}</td>
                                <td class="px-5 py-3 text-slate-600">{{ $product->supplier ?: '-' }}</td>
                                <td class="px-5 py-3 font-mono text-slate-700">R$ {{ number_format($product->contract_amount,2,',','.') }}</td>
                                <td class="px-5 py-3 text-slate-600">{{ ['monthly'=>'Mensal','annual'=>'Anual','one_time'=>'Pontual','custom'=>'Personalizada'][$product->billing_period] ?? $product->billing_period }}</td>
                                <td class="px-5 py-3 text-slate-600">{{ ['all_collaborators'=>'Colaboradores','office_users'=>'Office','email_users'=>'E-mail','manual_users'=>'Manual'][$product->allocation_basis] ?? $product->allocation_basis }}</td>
                                <td class="px-5 py-3">
                                    <span class="inline-flex items-center rounded-full px-2 py-1 text-xs font-semibold ring-1 ring-inset {{ $product->is_active ? 'bg-emerald-50 text-emerald-700 ring-emerald-200' : 'bg-slate-100 text-slate-600 ring-slate-200' }}">{{ $product->is_active ? 'Ativo' : 'Inativo' }}</span>
                                </td>
                                <td class="px-5 py-3">
                                    <div class="flex justify-end gap-2">
                                        <a href="{{ route('coordenacao.products.edit',$product) }}" class="inline-flex items-center justify-center rounded-md border border-slate-300 bg-white px-3 py-1.5 text-xs font-semibold text-[#033151] hover:bg-slate-50">Editar</a>
                                        <form method="POST" action="{{ route('coordenacao.products.toggle',$product) }}">@csrf @method('PATCH')<button class="inline-flex items-center justify-center rounded-md border border-slate-300 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50">{{ $product->is_active ? 'Pausar' : 'Ativar' }}</button></form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="px-6 py-10 text-center text-sm text-slate-400">Nenhum produto cadastrado.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="border-t border-slate-100 p-5 sm:px-6">{{ $products->links() }}</div>
        </section>
    </div>
</x-app-layout>
