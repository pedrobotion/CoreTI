<x-app-layout>
    <div class="mx-auto max-w-7xl space-y-6">
        @include('coordenacao.partials.nav', ['active' => 'dashboard'])

        @php
            $stats = $report['stats'];
            $topPeople = $report['people']->sortByDesc('monthly_cost')->take(10);
            $topUnits = $report['units']->take(10);
            $topRules = $report['ruleSummary']->sortByDesc('monthly_total')->take(10);
            $topGroups = $report['groupFrequency']->take(10);
            $warnings = $report['warnings'] ?? collect();
        @endphp

        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <a href="{{ route('coordenacao.products.index') }}" class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm transition hover:border-slate-400">
                <p class="text-sm font-medium text-slate-500">Produtos de TI ativos</p>
                <div class="mt-2 text-3xl font-bold text-slate-950">{{ number_format($productsCount ?? 0, 0, ',', '.') }}</div>
                <p class="mt-1 text-xs text-slate-500">Contratos e serviços configuráveis</p>
            </a>
            <a href="{{ route('coordenacao.rateio.index') }}" class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm transition hover:border-slate-400">
                <p class="text-sm font-medium text-slate-500">Último rateio</p>
                <div class="mt-2 text-2xl font-bold text-slate-950">{{ $latestClosing?->reference_month?->format('m/Y') ?? 'Não calculado' }}</div>
                <p class="mt-1 text-xs text-slate-500">{{ $latestClosing ? 'R$ ' . number_format($latestClosing->total_amount, 2, ',', '.') . ' · ' . $latestClosing->status : 'Crie uma competência mensal' }}</p>
            </a>
            <a href="{{ route('coordenacao.pending.index') }}" class="rounded-lg border border-amber-200 bg-amber-50 p-5 shadow-sm transition hover:border-amber-400">
                <p class="text-sm font-medium text-amber-800">Pendências</p>
                <div class="mt-2 text-3xl font-bold text-amber-950">{{ number_format($pendingCount ?? 0, 0, ',', '.') }}</div>
                <p class="mt-1 text-xs text-amber-800">Itens que precisam de revisão</p>
            </a>
            <a href="{{ route('coordenacao.closings.index') }}" class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm transition hover:border-slate-400">
                <p class="text-sm font-medium text-slate-500">Fechamentos</p>
                <div class="mt-2 text-3xl font-bold text-slate-950">{{ number_format($closingsCount ?? 0, 0, ',', '.') }}</div>
                <p class="mt-1 text-xs text-slate-500">Competências registradas</p>
            </a>
        </div>

        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-6">
            <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <p class="text-sm font-medium text-slate-500 dark:text-slate-400">Colaboradores</p>
                <div class="mt-3 text-3xl font-bold text-slate-950 dark:text-white">{{ number_format($stats['people_total'], 0, ',', '.') }}</div>
                <p class="mt-2 text-xs text-slate-500 dark:text-slate-400">considerados na base do AD</p>
            </div>

            <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <p class="text-sm font-medium text-slate-500 dark:text-slate-400">Com Office</p>
                <div class="mt-3 text-3xl font-bold text-slate-950 dark:text-white">{{ number_format($stats['people_with_office'], 0, ',', '.') }}</div>
                <p class="mt-2 text-xs text-slate-500 dark:text-slate-400">licenças ativas ou inativas</p>
            </div>

            <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <p class="text-sm font-medium text-slate-500 dark:text-slate-400">Com custo em grupos</p>
                <div class="mt-3 text-3xl font-bold text-slate-950 dark:text-white">{{ number_format($stats['people_with_group_costs'], 0, ',', '.') }}</div>
                <p class="mt-2 text-xs text-slate-500 dark:text-slate-400">Citrix, TS, Progress e similares</p>
            </div>

            <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <p class="text-sm font-medium text-slate-500 dark:text-slate-400">Regras ativas</p>
                <div class="mt-3 text-3xl font-bold text-slate-950 dark:text-white">{{ number_format($stats['rules_active'], 0, ',', '.') }}</div>
                <p class="mt-2 text-xs text-slate-500 dark:text-slate-400">regras de custo aplicáveis</p>
            </div>

            <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <p class="text-sm font-medium text-slate-500 dark:text-slate-400">Custo mensal</p>
                <div class="mt-3 text-3xl font-bold text-slate-950 dark:text-white">{{ 'R$ ' . number_format($stats['monthly_cost_total'], 2, ',', '.') }}</div>
                <p class="mt-2 text-xs text-slate-500 dark:text-slate-400">estimativa recorrente</p>
            </div>

            <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <p class="text-sm font-medium text-slate-500 dark:text-slate-400">Custo pontual</p>
                <div class="mt-3 text-3xl font-bold text-slate-950 dark:text-white">{{ 'R$ ' . number_format($stats['one_time_cost_total'], 2, ',', '.') }}</div>
                <p class="mt-2 text-xs text-slate-500 dark:text-slate-400">itens não recorrentes</p>
            </div>
        </div>

        @if (($stats['rules_active'] ?? 0) === 0)
            <div class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900 dark:border-amber-900/40 dark:bg-amber-950/40 dark:text-amber-100">
                Nenhuma regra de custo está ativa. Ajuste os valores em <a href="{{ route('coordenacao.rules') }}" class="font-semibold underline">Regras de custo</a> para gerar a estimativa financeira.
            </div>
        @endif

        <div class="grid gap-6 xl:grid-cols-[1.2fr_0.8fr]">
            <section class="rounded-lg border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <div class="border-b border-slate-200 px-5 py-4 dark:border-slate-800">
                    <h2 class="text-base font-semibold text-slate-950 dark:text-white">Colaboradores com maior custo mensal</h2>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[960px] text-sm">
                        <thead class="bg-slate-50 text-left text-xs font-semibold uppercase text-slate-500 dark:bg-slate-950/40 dark:text-slate-400">
                            <tr>
                                <th class="px-5 py-3">Nome</th>
                                <th class="px-5 py-3">Unidade</th>
                                <th class="px-5 py-3">Office</th>
                                <th class="px-5 py-3">Grupos</th>
                                <th class="px-5 py-3">Custo mensal</th>
                                <th class="px-5 py-3">Custo pontual</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                            @forelse ($topPeople as $person)
                                <tr>
                                    <td class="px-5 py-4">
                                        <div class="font-medium text-slate-900 dark:text-slate-100">{{ $person['name'] ?: 'Sem nome' }}</div>
                                        <div class="text-xs text-slate-500 dark:text-slate-400">{{ $person['email'] ?: 'Sem e-mail' }}</div>
                                    </td>
                                    <td class="px-5 py-4 text-slate-600 dark:text-slate-300">{{ $person['resolved_unit'] ?: 'Sem unidade' }}</td>
                                    <td class="px-5 py-4 text-slate-600 dark:text-slate-300">
                                        {{ trim(implode(' | ', array_filter([
                                            !empty($person['office_flags']['office_apps']) ? 'Apps' : null,
                                            !empty($person['office_flags']['office_business']) ? 'Business' : null,
                                            !empty($person['office_flags']['powerbi_pro']) ? 'PBI Pro' : null,
                                            !empty($person['office_flags']['powerbi_premium']) ? 'PBI Premium' : null,
                                            !empty($person['office_flags']['visio_plan']) ? 'Visio' : null,
                                        ]))) ?: '-' }}
                                    </td>
                                    <td class="px-5 py-4 text-slate-600 dark:text-slate-300">{{ ! empty($person['matched_rule_names']) ? implode(' | ', $person['matched_rule_names']) : '-' }}</td>
                                    <td class="px-5 py-4 font-semibold text-slate-900 dark:text-slate-100">{{ 'R$ ' . number_format((float) ($person['monthly_cost'] ?? 0), 2, ',', '.') }}</td>
                                    <td class="px-5 py-4 font-semibold text-slate-900 dark:text-slate-100">{{ 'R$ ' . number_format((float) ($person['one_time_cost'] ?? 0), 2, ',', '.') }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="px-5 py-8 text-center text-slate-500 dark:text-slate-400">Nenhum colaborador encontrado.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="grid gap-6">
                <div class="rounded-lg border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    <div class="border-b border-slate-200 px-5 py-4 dark:border-slate-800">
                        <h2 class="text-base font-semibold text-slate-950 dark:text-white">Unidades com maior custo</h2>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full min-w-[520px] text-sm">
                            <thead class="bg-slate-50 text-left text-xs font-semibold uppercase text-slate-500 dark:bg-slate-950/40 dark:text-slate-400">
                                <tr>
                                    <th class="px-5 py-3">Unidade</th>
                                    <th class="px-5 py-3">Colab.</th>
                                    <th class="px-5 py-3">Custo</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                                @forelse ($topUnits as $unit)
                                    <tr>
                                        <td class="px-5 py-4 font-medium text-slate-900 dark:text-slate-100">{{ $unit['unit'] }}</td>
                                        <td class="px-5 py-4 text-slate-600 dark:text-slate-300">{{ number_format($unit['users'], 0, ',', '.') }}</td>
                                        <td class="px-5 py-4 font-semibold text-slate-900 dark:text-slate-100">{{ 'R$ ' . number_format((float) $unit['monthly_cost'], 2, ',', '.') }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="3" class="px-5 py-8 text-center text-slate-500 dark:text-slate-400">Sem dados.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="rounded-lg border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    <div class="border-b border-slate-200 px-5 py-4 dark:border-slate-800">
                        <h2 class="text-base font-semibold text-slate-950 dark:text-white">Regras com maior impacto</h2>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full min-w-[520px] text-sm">
                            <thead class="bg-slate-50 text-left text-xs font-semibold uppercase text-slate-500 dark:bg-slate-950/40 dark:text-slate-400">
                                <tr>
                                    <th class="px-5 py-3">Regra</th>
                                    <th class="px-5 py-3">Usuários</th>
                                    <th class="px-5 py-3">Valor</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                                @forelse ($topRules as $rule)
                                    <tr>
                                        <td class="px-5 py-4">
                                            <div class="font-medium text-slate-900 dark:text-slate-100">{{ $rule['name'] }}</div>
                                            <div class="text-xs text-slate-500 dark:text-slate-400">{{ $rule['source_type'] }}</div>
                                        </td>
                                        <td class="px-5 py-4 text-slate-600 dark:text-slate-300">{{ number_format($rule['matched_users'], 0, ',', '.') }}</td>
                                        <td class="px-5 py-4 font-semibold text-slate-900 dark:text-slate-100">{{ 'R$ ' . number_format((float) $rule['monthly_total'], 2, ',', '.') }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="3" class="px-5 py-8 text-center text-slate-500 dark:text-slate-400">Sem regras ativas.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="rounded-lg border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    <div class="border-b border-slate-200 px-5 py-4 dark:border-slate-800">
                        <h2 class="text-base font-semibold text-slate-950 dark:text-white">Grupos AD mais frequentes</h2>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full min-w-[520px] text-sm">
                            <thead class="bg-slate-50 text-left text-xs font-semibold uppercase text-slate-500 dark:bg-slate-950/40 dark:text-slate-400">
                                <tr>
                                    <th class="px-5 py-3">Grupo</th>
                                    <th class="px-5 py-3">Usuários</th>
                                    <th class="px-5 py-3">Candidato</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                                @forelse ($topGroups as $group)
                                    @php
                                        $isCandidate = $report['candidates']->contains(fn ($candidate) => $candidate->normalized === $group->normalized);
                                    @endphp
                                    <tr>
                                        <td class="px-5 py-4 font-medium text-slate-900 dark:text-slate-100">{{ $group->group }}</td>
                                        <td class="px-5 py-4 text-slate-600 dark:text-slate-300">{{ number_format($group->users, 0, ',', '.') }}</td>
                                        <td class="px-5 py-4">
                                            <span class="inline-flex rounded-full px-2 py-1 text-xs font-semibold {{ $isCandidate ? 'bg-amber-100 text-amber-800 dark:bg-amber-400/15 dark:text-amber-200' : 'bg-emerald-100 text-emerald-800 dark:bg-emerald-400/15 dark:text-emerald-200' }}">
                                                {{ $isCandidate ? 'Sim' : 'Não' }}
                                            </span>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="3" class="px-5 py-8 text-center text-slate-500 dark:text-slate-400">Sem grupos detectados.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </section>
        </div>
    </div>
</x-app-layout>
