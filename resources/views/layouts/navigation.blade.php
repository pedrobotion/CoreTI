@php
    $user = Auth::user();
    // Área atual (p/ o breadcrumb do topo), derivada da rota.
    $secaoAtual = match (true) {
        request()->routeIs('bancada-servicos.*') => 'Bancada de Serviços',
        request()->routeIs('administrativo*') => 'Administrativo',
        request()->routeIs('service-desk.*') || request()->routeIs('jira-projects.*') => 'Service Desk',
        request()->routeIs('dashboards.*') => 'Dashboards',
        request()->routeIs('cmdb.*') || request()->routeIs('network-datacenter.*') => 'CMDB',
        request()->routeIs('coordenacao.*') => 'Coordenação',
        request()->routeIs('unidade-digital*') => 'Unidade Digital',
        request()->routeIs('indicadores*') => 'Indicadores',
        request()->routeIs('impressoras.*') => 'Impressoras',
        request()->routeIs('cartao-ti.*') || request()->routeIs('administrativo.cartao-ti.*') => 'Cartão TI',
        request()->routeIs('dashboard') => 'Início',
        default => null,
    };

    // Avisos/alertas em que o colaborador está cadastrado (para o sino do topo).
    // Defensivo: roda em toda página, não pode quebrar o layout.
    $avisosAtivos = collect();
    try {
        $recAviso = \App\Models\CoreTiNotificationRecipient::query()
            ->where('email', $user->email)
            ->where('active', true)
            ->with(['rules' => fn ($q) => $q->where('active', true)->orderBy('label')])
            ->first();
        $avisosAtivos = $recAviso ? $recAviso->rules : collect();
    } catch (\Throwable $e) {
        $avisosAtivos = collect();
    }
    $avisosCount = $avisosAtivos->count();
    $cargoUsuario = $user->role === 'admin' ? 'Administrador' : 'SED';
    $inicialUsuario = mb_strtoupper(mb_substr(trim((string) $user->name), 0, 1));
@endphp

<nav
    x-data="{
        profileOpen: false,
        bellOpen: false,
        mobileMenuOpen: false,
        theme: window.getTheme ? window.getTheme() : 'system',
        circuitsOpen: false,
        adminOpen: false,
        indicadoresOpen: false,
        unidadeDigitalOpen: false,
        monitoramentoOpen: false,
        setTheme(mode) {
            this.theme = mode;
            if (window.setTheme) {
                window.setTheme(mode);
            }
        }
    }"
>
    <header class="top-header bg-white">
        <div class="header-bar flex h-full items-center gap-4 px-4 sm:px-6 lg:px-8">
            <nav aria-label="Trilha" class="header-crumb flex min-w-0 items-center gap-2 text-sm">
                <a href="{{ route('dashboard') }}" class="font-semibold text-slate-500 transition hover:text-slate-700">CoreTI</a>
                @if ($secaoAtual)
                    <svg class="h-3.5 w-3.5 shrink-0 text-slate-300" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 6l6 6-6 6" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    <span class="truncate font-bold text-[#033151]">{{ $secaoAtual }}</span>
                @endif
            </nav>

            <div class="nav-actions-fixed ml-auto flex shrink-0 items-center gap-2">
                <button
                    type="button"
                    @click="mobileMenuOpen = true"
                    class="mobile-menu-trigger rounded-md border border-slate-300 bg-white px-3 py-2 text-xs font-semibold text-slate-700 shadow-sm"
                >
                    Menu
                </button>

                <div class="theme-switcher flex">
                    <button type="button" class="theme-toggle" :class="{ 'theme-toggle-active': theme === 'light' }" @click="setTheme('light')" aria-label="Ativar tema claro">
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 3v2.2m0 13.6V21m9-9h-2.2M5.2 12H3m14.164 6.164-1.556-1.556M8.392 7.392 6.836 5.836m10.328 0-1.556 1.556M8.392 16.608l-1.556 1.556M15.5 12A3.5 3.5 0 1 1 12 8.5a3.5 3.5 0 0 1 3.5 3.5Z" />
                        </svg>
                    </button>
                    <button type="button" class="theme-toggle" :class="{ 'theme-toggle-active': theme === 'dark' }" @click="setTheme('dark')" aria-label="Ativar tema escuro">
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M21 12.8A8.98 8.98 0 0 1 11.2 3a9 9 0 1 0 9.8 9.8Z" />
                        </svg>
                    </button>
                    <button type="button" class="theme-toggle" :class="{ 'theme-toggle-active': theme === 'system' }" @click="setTheme('system')" aria-label="Usar tema do sistema">
                        Auto
                    </button>
                </div>

                <div class="relative">
                    <button
                        type="button"
                        @click="bellOpen = !bellOpen"
                        class="relative grid h-10 w-10 place-items-center rounded-lg border border-slate-200 bg-white text-slate-600 transition hover:bg-slate-50"
                        aria-label="Avisos e alertas"
                    >
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M18 8a6 6 0 0 0-12 0c0 7-3 9-3 9h18s-3-2-3-9M13.73 21a2 2 0 0 1-3.46 0" stroke-linecap="round" stroke-linejoin="round"/></svg>
                        @if ($avisosCount > 0)
                            <span class="absolute -right-0.5 -top-0.5 grid h-4 min-w-[16px] place-items-center rounded-full bg-rose-500 px-1 text-[10px] font-bold text-white">{{ $avisosCount > 9 ? '9+' : $avisosCount }}</span>
                        @endif
                    </button>

                    <div
                        x-cloak
                        x-show="bellOpen"
                        x-transition
                        @click.outside="bellOpen = false"
                        class="absolute right-0 z-50 mt-2 w-80 max-w-[90vw] rounded-xl border border-slate-200 bg-white py-2 shadow-lg"
                    >
                        <div class="flex items-center justify-between border-b border-slate-100 px-4 py-2">
                            <p class="text-sm font-semibold text-slate-900">Avisos e alertas</p>
                            <span class="inline-flex items-center rounded-full bg-slate-100 px-2 py-0.5 text-xs font-semibold text-slate-600">{{ $avisosCount }}</span>
                        </div>
                        <div class="max-h-80 overflow-y-auto">
                            @forelse ($avisosAtivos as $aviso)
                                <div class="flex items-start gap-2.5 px-4 py-2.5 transition hover:bg-slate-50">
                                    <span class="mt-0.5 grid h-7 w-7 shrink-0 place-items-center rounded-lg bg-amber-50 text-amber-600">
                                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M18 8a6 6 0 0 0-12 0c0 7-3 9-3 9h18s-3-2-3-9" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                    </span>
                                    <div class="min-w-0">
                                        <p class="text-sm font-semibold text-slate-800">{{ $aviso->label }}</p>
                                        @if ($aviso->description)
                                            <p class="mt-0.5 text-xs text-slate-500">{{ $aviso->description }}</p>
                                        @endif
                                    </div>
                                </div>
                            @empty
                                <p class="px-4 py-6 text-center text-sm text-slate-400">Você não está cadastrado em nenhum aviso.</p>
                            @endforelse
                        </div>
                        @if ($user->role === 'admin')
                            <div class="border-t border-slate-100 px-4 pt-2">
                                <a href="{{ route('service-desk.notification-settings') }}" class="block py-1 text-xs font-semibold text-[#033151] hover:underline">Gerenciar destinatários de avisos →</a>
                            </div>
                        @endif
                    </div>
                </div>

                <div class="relative">
                    <button
                        type="button"
                        @click="profileOpen = !profileOpen"
                        class="flex items-center gap-2.5 rounded-xl border border-slate-200 bg-white py-1.5 pl-1.5 pr-2.5 transition hover:bg-slate-50"
                        aria-label="Abrir menu do perfil"
                    >
                        <span class="grid h-9 w-9 shrink-0 place-items-center rounded-lg bg-[#033151] text-sm font-bold text-white">{{ $inicialUsuario }}</span>
                        <span class="hidden min-w-0 text-left sm:block">
                            <span class="block max-w-[160px] truncate text-sm font-semibold leading-tight text-slate-900">{{ $user->name }}</span>
                            <span class="block truncate text-xs leading-tight text-slate-500">{{ $cargoUsuario }}</span>
                        </span>
                        <svg class="hidden h-4 w-4 shrink-0 text-slate-400 sm:block" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m6 9 6 6 6-6" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    </button>

                    <div
                        x-cloak
                        x-show="profileOpen"
                        x-transition
                        @click.outside="profileOpen = false"
                        class="profile-dropdown absolute mt-2 w-56 rounded-lg border border-slate-200 bg-white py-1 shadow-lg"
                    >
                        <div class="border-b border-slate-100 px-4 py-2">
                            <p class="truncate text-sm font-medium text-slate-900">{{ $user->name }}</p>
                            <p class="truncate text-xs text-slate-500">{{ $user->email }}</p>
                        </div>

                        <a
                            href="{{ route('profile.edit') }}"
                            class="block px-4 py-2 text-sm text-slate-700 hover:bg-slate-100"
                        >
                            Perfil
                        </a>

                        @if ($user->role === 'admin')
                            <a
                                href="{{ route('admin.dashboard') }}"
                                class="block px-4 py-2 text-sm text-slate-700 hover:bg-slate-100"
                            >
                                Administração
                            </a>
                        @endif

                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button
                                type="submit"
                                class="block w-full px-4 py-2 text-left text-sm text-slate-700 hover:bg-slate-100"
                            >
                                Sair
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <aside class="desktop-sidebar {{ request()->routeIs('cmdb.*') ? 'cmdb-desktop-sidebar' : '' }} fixed bottom-0 left-0 top-0 z-40 w-64 shadow-sm">
        @include('layouts.partials.sidebar-menu', ['containerClass' => 'coreti-sidebar-shell'])
    </aside>

    <div
        x-cloak
        x-show="mobileMenuOpen"
        x-transition.opacity
        @click="mobileMenuOpen = false"
        class="mobile-overlay fixed inset-0 z-40 bg-slate-900/30"
    ></div>

    <aside
        class="mobile-sidebar mobile-sidebar-position corp-sidebar fixed bottom-0 left-0 z-50 w-[88vw] max-w-xs border-r border-slate-200 shadow-xl"
        :style="mobileMenuOpen ? 'transform: translateX(0%); transition: transform .2s ease;' : 'transform: translateX(-100%); transition: transform .2s ease;'"
    >
        @include('layouts.partials.sidebar-menu', ['containerClass' => 'coreti-sidebar-shell'])
    </aside>
</nav>

@push('scripts')
<script>
(function () {
    // Transforma cada título do menu (.coreti-sidebar-heading) num grupo
    // retrátil: clicar no título abre/fecha os itens daquele bloco. O estado
    // fica no localStorage e a seção da página atual começa sempre aberta.
    function buildGroups(nav) {
        if (!nav || nav.dataset.sbGroups) return;
        nav.dataset.sbGroups = '1';
        Array.prototype.slice.call(nav.querySelectorAll('.coreti-sidebar-heading')).forEach(function (h) {
            var title = (h.textContent || '').trim();
            var items = [];
            var el = h.nextElementSibling;
            while (el && !el.classList.contains('coreti-sidebar-heading')) {
                var next = el.nextElementSibling;
                items.push(el);
                el = next;
            }
            if (!items.length) return;
            var wrap = document.createElement('div');
            wrap.className = 'coreti-sidebar-group-items';
            h.parentNode.insertBefore(wrap, h.nextSibling);
            items.forEach(function (i) { wrap.appendChild(i); });
            h.classList.add('coreti-sidebar-heading-toggle');
            h.setAttribute('role', 'button');
            h.setAttribute('tabindex', '0');
            var chev = document.createElement('span');
            chev.className = 'coreti-sidebar-group-chevron';
            chev.textContent = '⌄';
            h.appendChild(chev);
            var key = 'coreti.sbgrp.' + title;
            var hasActive = !!wrap.querySelector('.coreti-sidebar-link-active');
            var stored = null;
            try { stored = localStorage.getItem(key); } catch (e) {}
            var open = stored === null ? true : stored === '1';
            if (hasActive) open = true;
            function apply(o) {
                wrap.style.display = o ? '' : 'none';
                h.classList.toggle('is-collapsed', !o);
                h.setAttribute('aria-expanded', o ? 'true' : 'false');
            }
            apply(open);
            function toggle() {
                open = !open;
                apply(open);
                try { localStorage.setItem(key, open ? '1' : '0'); } catch (e) {}
            }
            h.addEventListener('click', toggle);
            h.addEventListener('keydown', function (e) {
                if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); toggle(); }
            });
        });
    }
    function init() {
        document.querySelectorAll('.coreti-sidebar-nav').forEach(buildGroups);
    }
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
</script>
@endpush
