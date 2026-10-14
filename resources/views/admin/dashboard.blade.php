<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Painel de Administração') }}
        </h2>
    </x-slot>

    <div class="py-10">
        <div class="mx-auto max-w-7xl space-y-4 sm:px-6 lg:px-8">

            @if(session('success'))
                <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('success') }}</div>
            @endif
            @if(session('error'))
                <div class="rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">{{ session('error') }}</div>
            @endif

            @unless($permissionsReady)
                <div class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                    <p class="font-semibold">Estrutura de acessos ainda não migrada.</p>
                    <p>Os acessos abaixo refletem o modelo antigo e não podem ser alterados por esta tela.
                       Rode <code class="rounded bg-amber-100 px-1">php artisan migrate</code> para habilitar a edição.</p>
                </div>
            @endunless

            <div class="flex items-baseline justify-between">
                <h3 class="text-lg font-semibold text-gray-900">Gerenciamento de Usuários</h3>
                <span class="text-sm text-gray-500">{{ $users->count() }} usuário(s)</span>
            </div>

            @foreach($users as $user)
                @php
                    $granted = $user->moduleAccessMap();
                    $acessoTotal = $user->isMasterAccount() || $user->role === 'admin';
                    $totalConcedidos = collect($granted)->filter()->count();
                    $userScopes = ($scopeOptions ?? []) !== [] ? $user->scopes->pluck('scope')->all() : [];
                @endphp

                <section class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm {{ $user->id === auth()->id() ? 'ring-1 ring-slate-300' : '' }}">

                    {{-- Cabeçalho: identificação + ações --}}
                    <div class="flex flex-wrap items-start justify-between gap-4 border-b border-gray-100 px-5 py-4">
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <p class="text-sm font-semibold text-gray-900">{{ $user->name }}</p>

                                @if($user->id === auth()->id())
                                    <span class="rounded-full px-2 py-0.5 text-xs font-medium text-white" style="background-color:#033151;">Você</span>
                                @endif

                                @if($user->is_active)
                                    <span class="rounded-full bg-green-100 px-2 py-0.5 text-xs font-semibold text-green-800">Aprovado</span>
                                @else
                                    <span class="rounded-full bg-yellow-100 px-2 py-0.5 text-xs font-semibold text-yellow-800">Pendente</span>
                                @endif

                                @if($user->role === 'admin')
                                    <span class="rounded-full bg-purple-100 px-2 py-0.5 text-xs font-semibold text-purple-800">Admin</span>
                                @else
                                    <span class="rounded-full bg-blue-100 px-2 py-0.5 text-xs font-semibold text-blue-800">Usuário</span>
                                @endif

                                @if($user->isMasterAccount())
                                    <span class="rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-semibold text-emerald-800">Master</span>
                                @endif
                            </div>
                            <p class="mt-0.5 truncate text-sm text-gray-500">{{ $user->email }}</p>
                        </div>

                        @if($user->id !== auth()->id() && ! $user->isMasterAccount())
                            <div class="flex flex-wrap items-center gap-2">
                                @can('approve', $user)
                                    @if(! $user->is_active)
                                        <form action="{{ route('admin.approve', $user->id) }}" method="POST">
                                            @csrf
                                            <button type="submit" class="btn-coreti-primary text-sm">Aprovar</button>
                                        </form>
                                    @endif
                                @endcan

                                @can('updateRole', $user)
                                    <form action="{{ route('admin.update-role', $user->id) }}" method="POST" data-confirm-message="Deseja alterar a permissão deste usuário?">
                                        @csrf @method('PATCH')
                                        <button type="submit" class="btn-coreti-secondary text-sm">
                                            {{ $user->role === 'admin' ? 'Remover Admin' : 'Tornar Admin' }}
                                        </button>
                                    </form>
                                @endcan

                                @can('resetPassword', $user)
                                    <form action="{{ route('admin.reset-password', $user->id) }}" method="POST" data-confirm-message="Tem certeza que deseja iniciar o reset de senha deste usuário?">
                                        @csrf
                                        <button type="submit" class="btn-coreti-secondary text-sm">Reset de senha</button>
                                    </form>
                                @endcan

                                @can('delete', $user)
                                    <form action="{{ route('admin.delete', $user->id) }}" method="POST" data-confirm-message="Tem certeza que deseja deletar este usuário?">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="btn-coreti-danger text-sm">Deletar</button>
                                    </form>
                                @endcan
                            </div>
                        @endif
                    </div>

                    {{-- Acessos --}}
                    @if($acessoTotal)
                        <div class="flex items-center gap-2 px-5 py-3 text-sm">
                            <span class="inline-block h-2 w-2 rounded-full {{ $user->isMasterAccount() ? 'bg-emerald-500' : 'bg-purple-500' }}"></span>
                            <span class="font-medium {{ $user->isMasterAccount() ? 'text-emerald-800' : 'text-purple-800' }}">
                                Acesso total a todos os módulos{{ $user->isMasterAccount() ? ' (conta master protegida)' : '' }}
                            </span>
                            @unless($user->isMasterAccount())
                                <span class="text-gray-400">— remova o perfil Admin para definir acessos por módulo.</span>
                            @endunless
                        </div>
                    @else
                        <details class="group">
                            <summary class="flex cursor-pointer list-none flex-wrap items-center justify-between gap-3 px-5 py-3 hover:bg-gray-50">
                                <div class="flex min-w-0 flex-wrap items-center gap-2">
                                    @if($totalConcedidos === 0)
                                        <span class="inline-flex items-center gap-1.5 rounded-full bg-amber-50 px-2.5 py-1 text-xs font-semibold text-amber-800 ring-1 ring-inset ring-amber-200">
                                            <svg viewBox="0 0 20 20" fill="currentColor" class="h-3.5 w-3.5"><path fill-rule="evenodd" d="M8.485 2.495c.673-1.167 2.357-1.167 3.03 0l6.28 10.875c.673 1.167-.17 2.63-1.516 2.63H3.72c-1.347 0-2.19-1.463-1.516-2.63L8.485 2.495ZM10 6a.75.75 0 0 1 .75.75v3.5a.75.75 0 0 1-1.5 0v-3.5A.75.75 0 0 1 10 6Zm0 8a1 1 0 1 0 0-2 1 1 0 0 0 0 2Z" clip-rule="evenodd"/></svg>
                                            Sem acesso a nenhum módulo
                                        </span>
                                        <span class="text-xs text-gray-500">Este usuário consegue entrar, mas não enxerga nada.</span>
                                    @else
                                        <span class="text-xs font-semibold uppercase tracking-wide text-gray-400">Acessos</span>
                                        @foreach($granted as $moduleKey => $abilities)
                                            @continue(! \App\Support\ModuleRegistry::exists($moduleKey))
                                            <span class="inline-flex items-center gap-1 rounded-full bg-slate-100 px-2.5 py-1 text-xs font-medium text-slate-700">
                                                {{ \App\Support\ModuleRegistry::label($moduleKey) }}
                                                @if(in_array('manage', $abilities, true))
                                                    <span class="text-slate-400">· admin</span>
                                                @endif
                                            </span>
                                        @endforeach
                                    @endif
                                </div>

                                <span class="shrink-0 text-xs font-semibold text-slate-600 group-open:hidden">Gerenciar acessos ▾</span>
                                <span class="hidden shrink-0 text-xs font-semibold text-slate-600 group-open:inline">Fechar ▴</span>
                            </summary>

                            <form
                                action="{{ route('admin.update-module-access', $user->id) }}"
                                method="POST"
                                class="border-t border-gray-100 bg-gray-50/60 px-5 py-4"
                                data-module-form
                            >
                                @csrf
                                @method('PATCH')

                                <div class="space-y-5">
                                    @if(! empty($scopeOptions))
                                        <div>
                                            <p class="mb-2 text-[11px] font-semibold uppercase tracking-wider text-gray-400">Operação — dados visíveis</p>
                                            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                                                <div class="rounded-lg border bg-white p-3 {{ $userScopes ? 'border-slate-300' : 'border-amber-200' }} sm:col-span-2">
                                                    <p class="text-sm font-semibold leading-snug text-gray-800">Escopo de dados (Sede × Cerrado)</p>
                                                    <p class="mt-0.5 text-[11px] leading-snug text-gray-400">Define quais operações o usuário enxerga nos dados (dashboards, telefonia, equipamentos…). Sem marcar nenhum, as telas abrem mas ficam <strong>sem dados</strong>.</p>
                                                    <div class="mt-2.5 flex flex-wrap gap-x-6 gap-y-1.5 border-t border-gray-100 pt-2.5">
                                                        @foreach($scopeOptions as $scopeKey => $scopeLabel)
                                                            <label class="flex cursor-pointer items-start gap-2 text-xs leading-snug text-gray-600">
                                                                <input
                                                                    type="checkbox"
                                                                    name="scopes[]"
                                                                    value="{{ $scopeKey }}"
                                                                    class="mt-0.5 shrink-0 rounded border-gray-300"
                                                                    @checked(in_array($scopeKey, $userScopes, true))
                                                                    @disabled(! $permissionsReady)
                                                                >
                                                                <span>{{ $scopeLabel }}</span>
                                                            </label>
                                                        @endforeach
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    @endif

                                    @foreach($moduleGroups as $groupName => $modules)
                                        <div>
                                            <p class="mb-2 text-[11px] font-semibold uppercase tracking-wider text-gray-400">{{ $groupName }}</p>

                                            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                                                @foreach($modules as $module)
                                                    @php
                                                        $moduleKey = $module['key'];
                                                        $moduleGranted = $granted[$moduleKey] ?? [];
                                                        $abilities = \App\Support\ModuleRegistry::abilities($moduleKey);
                                                    @endphp

                                                    <div class="rounded-lg border bg-white p-3 {{ $moduleGranted ? 'border-slate-300' : 'border-gray-200' }}">
                                                        <p class="text-sm font-semibold leading-snug text-gray-800">{{ $module['label'] }}</p>

                                                        @if(! empty($module['description']))
                                                            <p class="mt-0.5 text-[11px] leading-snug text-gray-400">{{ $module['description'] }}</p>
                                                        @endif

                                                        <div class="mt-2.5 space-y-1.5 border-t border-gray-100 pt-2.5">
                                                            @foreach($abilities as $ability)
                                                                <label class="flex cursor-pointer items-start gap-2 text-xs leading-snug text-gray-600">
                                                                    <input
                                                                        type="checkbox"
                                                                        name="modules[{{ $moduleKey }}][]"
                                                                        value="{{ $ability }}"
                                                                        class="mt-0.5 shrink-0 rounded border-gray-300"
                                                                        data-ability="{{ $ability }}"
                                                                        @checked(in_array($ability, $moduleGranted, true))
                                                                        @disabled(! $permissionsReady)
                                                                    >
                                                                    <span>{{ \App\Support\ModuleRegistry::abilityLabel($moduleKey, $ability) }}</span>
                                                                </label>
                                                            @endforeach
                                                        </div>
                                                    </div>
                                                @endforeach
                                            </div>
                                        </div>
                                    @endforeach
                                </div>

                                <div class="mt-5 flex flex-wrap items-center justify-between gap-3 border-t border-gray-200 pt-4">
                                    <p class="text-xs text-gray-500">
                                        “Administrar” concede automaticamente os níveis abaixo dele.
                                        Desmarcar tudo remove o acesso ao módulo.
                                    </p>
                                    <div class="flex items-center gap-3">
                                        <button type="button" class="text-xs text-gray-500 hover:underline" data-clear-all>Desmarcar tudo</button>
                                        <button type="submit" class="btn-coreti-primary text-sm" @disabled(! $permissionsReady)>Salvar acessos</button>
                                    </div>
                                </div>
                            </form>
                        </details>
                    @endif
                </section>
            @endforeach

            @if (session('password_reset'))
                @php $reset = session('password_reset'); @endphp
                <div x-data="{ open: true }" x-show="open" style="position:fixed; top:16px; left:50%; transform:translateX(-50%); z-index:50;">
                    <div class="w-[480px] max-w-[92vw] rounded-lg border border-gray-200 bg-white p-4 shadow-lg">
                        <div class="text-sm font-semibold text-gray-900">Reset de senha iniciado</div>
                        <div class="mt-2 text-sm text-gray-600">Envie este link para o usuário concluir a troca:</div>
                        <div class="mt-3 flex items-center gap-2">
                            <input type="text" readonly value="{{ $reset['link'] }}" class="w-full rounded-md border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-900" />
                            <button
                                type="button"
                                class="inline-flex items-center justify-center rounded-md border border-gray-200 px-3 py-2 text-sm text-gray-700 hover:bg-gray-100"
                                @click="navigator.clipboard.writeText({{ Js::from($reset['link']) }})"
                            >Copiar</button>
                        </div>
                        <p class="mt-2 text-xs text-gray-500">Usuário: {{ $reset['email'] }}</p>
                        <div class="mt-3 flex justify-end">
                            <button type="button" class="text-sm text-gray-500 hover:text-gray-700" @click="open = false">Fechar</button>
                        </div>
                    </div>
                </div>
            @endif
        </div>
    </div>

    @push('scripts')
    <script>
    (() => {
        // "Administrar" implica as demais permissões no servidor; refletir isso
        // na hora evita que a tela volte diferente do que o admin marcou.
        const ordem = ['view', 'request', 'execute', 'manage'];

        document.querySelectorAll('[data-module-form]').forEach((form) => {
            form.addEventListener('change', (evento) => {
                const alvo = evento.target;
                if (!(alvo instanceof HTMLInputElement) || alvo.type !== 'checkbox') return;

                const cartao = alvo.closest('.rounded-lg');
                if (!cartao) return;

                const caixas = [...cartao.querySelectorAll('input[type=checkbox][data-ability]')];
                const posicao = (el) => ordem.indexOf(el.dataset.ability);

                if (alvo.checked) {
                    caixas.filter((c) => posicao(c) < posicao(alvo)).forEach((c) => { c.checked = true; });
                } else {
                    caixas.filter((c) => posicao(c) > posicao(alvo)).forEach((c) => { c.checked = false; });
                }
            });

            form.querySelector('[data-clear-all]')?.addEventListener('click', () => {
                form.querySelectorAll('input[type=checkbox]').forEach((c) => { c.checked = false; });
            });
        });
    })();
    </script>
    @endpush
</x-app-layout>
