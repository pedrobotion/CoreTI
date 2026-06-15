<x-app-layout>
    <div class="mx-auto max-w-7xl space-y-6">
        @if (! $hasAnyDashboardAccess)
            <section class="rounded-lg border border-slate-200 bg-white p-8 shadow-sm">
                <p class="text-sm font-semibold uppercase tracking-wide text-slate-500">Visão geral</p>
                <h1 class="mt-2 text-2xl font-bold text-slate-950">Nenhum dashboard encontrado para o seu usuário</h1>
            </section>
        @else
        <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <p class="text-sm font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Visão geral</p>
                <h1 class="mt-1 text-2xl font-bold text-slate-950 dark:text-white">Dashboard CoreTI</h1>
                <p class="mt-2 max-w-2xl text-sm text-slate-600 dark:text-slate-300">
                    Acompanhamento rápido de acessos, circuitos, unidades e movimentações recentes.
                </p>
            </div>

            <div class="flex flex-wrap gap-2">
                <a href="{{ route('applications.index') }}" class="inline-flex min-h-10 items-center justify-center rounded-md border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100 dark:hover:bg-slate-800">
                    Ver aplicativos
                </a>
                <a href="{{ route('circuits.units') }}" class="inline-flex min-h-10 items-center justify-center rounded-md border border-slate-900 bg-slate-900 px-4 text-sm font-semibold text-white shadow-sm hover:bg-slate-800">
                    Ver circuitos
                </a>
                @if ($isAdmin)
                    <a href="{{ route('admin.dashboard') }}" class="inline-flex min-h-10 items-center justify-center rounded-md border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100 dark:hover:bg-slate-800">
                        Gerenciar usuários
                    </a>
                @endif
            </div>
        </div>

        <div class="dashboard-stat-grid grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
            <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <p class="text-sm font-medium text-slate-500 dark:text-slate-400">Circuitos cadastrados</p>
                <div class="mt-3 flex items-end justify-between gap-3">
                    <span class="text-3xl font-bold text-slate-950 dark:text-white">{{ number_format($stats['circuits_total'], 0, ',', '.') }}</span>
                    <span class="rounded-md bg-sky-100 px-2 py-1 text-xs font-semibold text-sky-800 dark:bg-sky-400/15 dark:text-sky-200">{{ number_format($stats['operators_total'], 0, ',', '.') }} operadoras</span>
                </div>
            </div>

            <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <p class="text-sm font-medium text-slate-500 dark:text-slate-400">Unidades monitoradas</p>
                <div class="mt-3 flex items-end justify-between gap-3">
                    <span class="text-3xl font-bold text-slate-950 dark:text-white">{{ number_format($stats['units_total'], 0, ',', '.') }}</span>
                    <span class="rounded-md bg-emerald-100 px-2 py-1 text-xs font-semibold text-emerald-800 dark:bg-emerald-400/15 dark:text-emerald-200">monitoradas</span>
                </div>
            </div>

            <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <p class="text-sm font-medium text-slate-500 dark:text-slate-400">Usuários ativos</p>
                <div class="mt-3 flex items-end justify-between gap-3">
                    <span class="text-3xl font-bold text-slate-950 dark:text-white">{{ number_format($stats['users_active'], 0, ',', '.') }}</span>
                    <span class="rounded-md bg-slate-100 px-2 py-1 text-xs font-semibold text-slate-700 dark:bg-slate-800 dark:text-slate-200">{{ number_format($stats['users_total'], 0, ',', '.') }} total</span>
                </div>
            </div>

            <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <p class="text-sm font-medium text-slate-500 dark:text-slate-400">Aplicativos</p>
                <div class="mt-3 flex items-end justify-between gap-3">
                    <span class="text-3xl font-bold text-slate-950 dark:text-white">{{ number_format($stats['applications_total'], 0, ',', '.') }}</span>
                    <span class="rounded-md bg-violet-100 px-2 py-1 text-xs font-semibold text-violet-800 dark:bg-violet-400/15 dark:text-violet-200">downloads</span>
                </div>
            </div>

            <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <p class="text-sm font-medium text-slate-500 dark:text-slate-400">Pendências de acesso</p>
                <div class="mt-3 flex items-end justify-between gap-3">
                    <span class="text-3xl font-bold text-slate-950 dark:text-white">{{ number_format($stats['users_pending'], 0, ',', '.') }}</span>
                    <span class="rounded-md bg-amber-100 px-2 py-1 text-xs font-semibold text-amber-800 dark:bg-amber-400/15 dark:text-amber-200">{{ number_format($stats['admins'], 0, ',', '.') }} admins</span>
                </div>
            </div>
        </div>

        <div class="dashboard-main-grid grid gap-6 xl:grid-cols-[1.15fr_0.85fr]">
            <section class="rounded-lg border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <div class="border-b border-slate-200 px-5 py-4 dark:border-slate-800">
                    <h2 class="text-base font-semibold text-slate-950 dark:text-white">Circuitos recentes</h2>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[720px] text-sm">
                        <thead class="bg-slate-50 text-left text-xs font-semibold uppercase text-slate-500 dark:bg-slate-950/40 dark:text-slate-400">
                            <tr>
                                <th class="px-5 py-3">Unidade</th>
                                <th class="px-5 py-3">Operadora</th>
                                <th class="px-5 py-3">Serviço</th>
                                <th class="px-5 py-3">Contato</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                            @forelse ($recentCircuits as $circuit)
                                <tr>
                                    <td class="px-5 py-4 font-medium text-slate-900 dark:text-slate-100">{{ $circuit->unidade->unidade ?? $circuit->unidades_circuitos }}</td>
                                    <td class="px-5 py-4 text-slate-600 dark:text-slate-300">{{ $circuit->operadora }}</td>
                                    <td class="px-5 py-4 text-slate-600 dark:text-slate-300">{{ $circuit->servico }}</td>
                                    <td class="px-5 py-4 text-slate-600 dark:text-slate-300">
                                        @if ($circuit->contato_whatsapp && $circuit->whatsappUrl())
                                            <a href="{{ $circuit->whatsappUrl() }}" target="_blank" rel="noopener noreferrer" class="underline hover:no-underline">
                                                {{ $circuit->contato }}
                                            </a>
                                        @else
                                            {{ $circuit->contato }}
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="px-5 py-8 text-center text-slate-500 dark:text-slate-400">Nenhum circuito cadastrado ainda.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>

            <div class="grid gap-6">
                <section class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    <h2 class="text-base font-semibold text-slate-950 dark:text-white">Operadoras com mais circuitos</h2>
                    <div class="mt-4 space-y-3">
                        @forelse ($operatorBreakdown as $operator)
                            @php
                                $percent = $stats['circuits_total'] > 0 ? max(4, round(($operator->total / $stats['circuits_total']) * 100)) : 0;
                            @endphp
                            <div>
                                <div class="mb-1 flex items-center justify-between gap-3 text-sm">
                                    <span class="truncate font-medium text-slate-700 dark:text-slate-200">{{ $operator->operadora }}</span>
                                    <span class="text-slate-500 dark:text-slate-400">{{ number_format($operator->total, 0, ',', '.') }}</span>
                                </div>
                                <div class="h-2 rounded-full bg-slate-100 dark:bg-slate-800">
                                    <div class="h-2 rounded-full bg-sky-500" style="width: {{ $percent }}%"></div>
                                </div>
                            </div>
                        @empty
                            <p class="text-sm text-slate-500 dark:text-slate-400">Sem dados de operadoras.</p>
                        @endforelse
                    </div>
                </section>
            </div>
        </div>

        @if ($isAdmin)
            <section class="rounded-lg border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <div class="flex flex-col gap-2 border-b border-slate-200 px-5 py-4 sm:flex-row sm:items-center sm:justify-between dark:border-slate-800">
                    <h2 class="text-base font-semibold text-slate-950 dark:text-white">Usuários aguardando aprovação</h2>
                    <a href="{{ route('admin.dashboard') }}" class="text-sm font-semibold text-slate-700 hover:text-slate-950 dark:text-slate-300 dark:hover:text-white">Abrir administração</a>
                </div>
                <div class="divide-y divide-slate-100 dark:divide-slate-800">
                    @forelse ($pendingUsers as $pendingUser)
                        <div class="flex flex-col gap-1 px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
                            <div>
                                <p class="font-medium text-slate-900 dark:text-slate-100">{{ $pendingUser->name }}</p>
                                <p class="text-sm text-slate-500 dark:text-slate-400">{{ $pendingUser->email }}</p>
                            </div>
                            <span class="text-sm text-slate-500 dark:text-slate-400">{{ optional($pendingUser->created_at)->format('d/m/Y H:i') }}</span>
                        </div>
                    @empty
                        <p class="px-5 py-6 text-sm text-slate-500 dark:text-slate-400">Nenhum usuário pendente no momento.</p>
                    @endforelse
                </div>
            </section>
        @endif
        @endif
    </div>
</x-app-layout>
