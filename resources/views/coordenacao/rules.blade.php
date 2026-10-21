<x-app-layout>
    <div class="mx-auto max-w-7xl space-y-6">
        @include('coordenacao.partials.nav', ['active' => 'rules'])

        <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <h2 class="text-base font-semibold text-slate-950 dark:text-white">{{ $editingRule ? 'Editar regra de custo' : 'Nova regra de custo' }}</h2>
                    <p class="mt-1 text-sm text-slate-600 dark:text-slate-300">
                        Defina o valor dos produtos e grupos usados no cálculo do custo mensal e pontual.
                    </p>
                </div>

                @if ($editingRule)
                    <a href="{{ route('coordenacao.rules') }}" class="inline-flex min-h-10 items-center justify-center rounded-md border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100 dark:hover:bg-slate-800">
                        Cancelar edição
                    </a>
                @endif
            </div>

            <form method="POST" action="{{ $editingRule ? route('coordenacao.rules.update', $editingRule) : route('coordenacao.rules.store') }}" class="mt-5 grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                @csrf
                @if ($editingRule)
                    @method('PATCH')
                @endif

                <div class="xl:col-span-2">
                    <label class="text-sm font-medium text-slate-700 dark:text-slate-200">Nome</label>
                    <input type="text" name="name" value="{{ old('name', $editingRule->name ?? '') }}" class="mt-1 w-full rounded-md border-slate-300 text-sm shadow-sm focus:border-slate-900 focus:ring-slate-900 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-100" required>
                </div>

                <div>
                    <label class="text-sm font-medium text-slate-700 dark:text-slate-200">Tipo</label>
                    <select name="source_type" class="mt-1 w-full rounded-md border-slate-300 text-sm shadow-sm focus:border-slate-900 focus:ring-slate-900 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-100" required>
                        @foreach ($sourceTypes as $value => $label)
                            <option value="{{ $value }}" @selected(old('source_type', $editingRule->source_type ?? 'office_flag') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="text-sm font-medium text-slate-700 dark:text-slate-200">Padrão</label>
                    <input type="text" name="match_value" value="{{ old('match_value', $editingRule->match_value ?? '') }}" placeholder="office_apps, Citrix, TS APP..." class="mt-1 w-full rounded-md border-slate-300 text-sm shadow-sm focus:border-slate-900 focus:ring-slate-900 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-100">
                </div>

                <div>
                    <label class="text-sm font-medium text-slate-700 dark:text-slate-200">Frequência</label>
                    <select name="billing_cycle" class="mt-1 w-full rounded-md border-slate-300 text-sm shadow-sm focus:border-slate-900 focus:ring-slate-900 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-100" required>
                        @foreach ($billingCycles as $value => $label)
                            <option value="{{ $value }}" @selected(old('billing_cycle', $editingRule->billing_cycle ?? 'monthly') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="text-sm font-medium text-slate-700 dark:text-slate-200">Valor</label>
                    <input type="number" step="0.01" min="0" name="amount" value="{{ old('amount', $editingRule->amount ?? 0) }}" class="mt-1 w-full rounded-md border-slate-300 text-sm shadow-sm focus:border-slate-900 focus:ring-slate-900 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-100" required>
                </div>

                <div>
                    <label class="text-sm font-medium text-slate-700 dark:text-slate-200">Ordem</label>
                    <input type="number" min="0" name="sort_order" value="{{ old('sort_order', $editingRule->sort_order ?? 0) }}" class="mt-1 w-full rounded-md border-slate-300 text-sm shadow-sm focus:border-slate-900 focus:ring-slate-900 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-100">
                </div>

                <div class="flex items-center gap-2">
                    <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $editingRule->is_active ?? true))>
                    <label class="text-sm font-medium text-slate-700 dark:text-slate-200">Ativa</label>
                </div>

                <div class="md:col-span-2 xl:col-span-4">
                    <label class="text-sm font-medium text-slate-700 dark:text-slate-200">Observações</label>
                    <textarea name="notes" rows="3" class="mt-1 w-full rounded-md border-slate-300 text-sm shadow-sm focus:border-slate-900 focus:ring-slate-900 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-100">{{ old('notes', $editingRule->notes ?? '') }}</textarea>
                </div>

                <div class="md:col-span-2 xl:col-span-4 flex justify-end">
                    <button type="submit" class="inline-flex min-h-10 items-center justify-center rounded-lg border border-[#033151] bg-[#033151] px-4 text-sm font-semibold text-white hover:opacity-90">
                        {{ $editingRule ? 'Atualizar regra' : 'Salvar regra' }}
                    </button>
                </div>
            </form>
        </section>

        <section class="rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-100 px-5 py-4 sm:px-6">
                <h2 class="text-base font-semibold text-slate-950 dark:text-white">Regras cadastradas</h2>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full min-w-[1200px] text-sm">
                    <thead class="border-b border-slate-100 text-left text-xs font-semibold uppercase tracking-wide text-slate-400">
                        <tr>
                            <th class="px-5 py-3">Nome</th>
                            <th class="px-5 py-3">Tipo</th>
                            <th class="px-5 py-3">Padrão</th>
                            <th class="px-5 py-3">Frequência</th>
                            <th class="px-5 py-3">Valor</th>
                            <th class="px-5 py-3">Ativa</th>
                            <th class="px-5 py-3">Ações</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        @forelse ($rules as $rule)
                            <tr>
                                <td class="px-5 py-4 font-medium text-slate-900 dark:text-slate-100">{{ $rule->name }}</td>
                                <td class="px-5 py-4 text-slate-600 dark:text-slate-300">{{ $sourceTypes[$rule->source_type] ?? $rule->source_type }}</td>
                                <td class="px-5 py-4 text-slate-600 dark:text-slate-300">{{ $rule->match_value ?: '-' }}</td>
                                <td class="px-5 py-4 text-slate-600 dark:text-slate-300">{{ $billingCycles[$rule->billing_cycle] ?? $rule->billing_cycle }}</td>
                                <td class="px-5 py-4 font-semibold text-slate-900 dark:text-slate-100">{{ 'R$ ' . number_format((float) $rule->amount, 2, ',', '.') }}</td>
                                <td class="px-5 py-4">
                                    <span class="inline-flex rounded-full px-2 py-1 text-xs font-semibold {{ $rule->is_active ? 'bg-emerald-50 text-emerald-700 ring-1 ring-inset ring-emerald-200 dark:bg-emerald-400/15 dark:text-emerald-200' : 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-200' }}">
                                        {{ $rule->is_active ? 'Sim' : 'Não' }}
                                    </span>
                                </td>
                                <td class="px-5 py-4">
                                    <div class="flex flex-wrap gap-2">
                                        <a href="{{ route('coordenacao.rules', ['edit' => $rule->id]) }}" class="inline-flex min-h-9 items-center justify-center rounded-md border border-slate-300 bg-white px-3 text-xs font-semibold text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-800">
                                            Editar
                                        </a>
                                        <form method="POST" action="{{ route('coordenacao.rules.toggle', $rule) }}">
                                            @csrf
                                            @method('PATCH')
                                            <button type="submit" class="inline-flex min-h-9 items-center justify-center rounded-md border border-slate-300 bg-white px-3 text-xs font-semibold text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-800">
                                                {{ $rule->is_active ? 'Desativar' : 'Ativar' }}
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-5 py-8 text-center text-slate-500 dark:text-slate-400">Nenhuma regra cadastrada.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</x-app-layout>
