@php
    $isServiceDesk = request()->routeIs('service-desk.*') || request()->routeIs('jira-projects.*');
    $isBancadaServicos = request()->routeIs('bancada-servicos.*');
    $isAdministrativo = request()->routeIs('administrativo*');
    $canServiceDesk = $user->hasModuleAccess('servicedesk');
    $canUnidades = $user->hasModuleAccess('unidades');
    $canAplicativos = $user->hasModuleAccess('aplicativos');
    $canBancada = $user->hasModuleAccess('bancada');
    $canAdministrativo = $user->hasModuleAccess('administrativo');
@endphp

<div class="{{ $containerClass ?? 'coreti-sidebar-shell' }}">
    <div class="coreti-sidebar-brand">
        <a href="{{ route('dashboard') }}" class="inline-flex items-center">
            <img
                src="{{ asset('images/branding/cocari-logo-white.png') }}"
                alt="Cocari"
                class="coreti-sidebar-logo"
            >
        </a>
    </div>

    <a href="{{ route('profile.edit') }}" class="coreti-sidebar-user">
        <span class="coreti-sidebar-avatar">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M15.8 8.8a3.8 3.8 0 1 1-7.6 0 3.8 3.8 0 0 1 7.6 0ZM4.8 20a7.2 7.2 0 0 1 14.4 0" />
            </svg>
        </span>
        <span class="min-w-0">
            <span class="coreti-sidebar-user-name">{{ $user->name }}</span>
            <span class="coreti-sidebar-user-role">{{ $user->role === 'admin' ? 'Administrador' : 'SED' }}</span>
        </span>
    </a>

    <div class="coreti-sidebar-divider"></div>

    <nav class="coreti-sidebar-nav" aria-label="{{ $isServiceDesk ? 'Menu ServiceDesk' : ($isBancadaServicos ? 'Menu Bancada de Serviços' : 'Menu principal') }}">
        @if ($isServiceDesk)
            <a href="{{ route('home') }}" class="coreti-sidebar-link">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M15 18l-6-6 6-6" />
                </svg>
                <span>Voltar ao CoreTI</span>
            </a>

            <p class="coreti-sidebar-heading">ServiceDesk</p>

            <a href="{{ route('service-desk.dashboard') }}" class="coreti-sidebar-link {{ request()->routeIs('service-desk.dashboard') ? 'coreti-sidebar-link-active' : '' }}">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M4 13.5A8 8 0 1 1 12 21H4v-7.5Z" />
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 8v5l3 2" />
                </svg>
                <span>Dashboard</span>
            </a>

            <a href="{{ route('service-desk.tickets') }}" class="coreti-sidebar-link {{ request()->routeIs('service-desk.tickets') ? 'coreti-sidebar-link-active' : '' }}">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M5 6h14v12H5zM8 10h8M8 14h5" />
                </svg>
                <span>Chamados</span>
                <span class="coreti-sidebar-chevron">‹</span>
            </a>

            <a href="{{ route('service-desk.my-queue') }}" class="coreti-sidebar-link {{ request()->routeIs('service-desk.my-queue') ? 'coreti-sidebar-link-active' : '' }}">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M8 7h12M8 12h12M8 17h12M4 7h.01M4 12h.01M4 17h.01" />
                </svg>
                <span>Minha fila</span>
                <span class="coreti-sidebar-chevron">‹</span>
            </a>

            <p class="coreti-sidebar-heading">E-mails</p>

            <a href="{{ route('service-desk.emails.sede') }}" class="coreti-sidebar-link {{ request()->routeIs('service-desk.emails.sede') ? 'coreti-sidebar-link-active' : '' }}">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M4 6h16v12H4zM4 7l8 6 8-6" />
                </svg>
                <span>Sede</span>
                <span class="coreti-sidebar-chevron">‹</span>
            </a>

            <a href="{{ route('service-desk.emails.unidades') }}" class="coreti-sidebar-link {{ request()->routeIs('service-desk.emails.unidades') ? 'coreti-sidebar-link-active' : '' }}">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M4 6h16v12H4zM4 7l8 6 8-6" />
                </svg>
                <span>Unidades</span>
                <span class="coreti-sidebar-chevron">‹</span>
            </a>

            <a href="{{ route('service-desk.emails.cerrado') }}" class="coreti-sidebar-link {{ request()->routeIs('service-desk.emails.cerrado') ? 'coreti-sidebar-link-active' : '' }}">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M4 6h16v12H4zM4 7l8 6 8-6" />
                </svg>
                <span>Cerrado</span>
                <span class="coreti-sidebar-chevron">‹</span>
            </a>

            <a href="{{ route('service-desk.emails.genericos') }}" class="coreti-sidebar-link {{ request()->routeIs('service-desk.emails.genericos') ? 'coreti-sidebar-link-active' : '' }}">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M4 6h16v12H4zM4 7l8 6 8-6" />
                </svg>
                <span>Genéricos</span>
                <span class="coreti-sidebar-chevron">‹</span>
            </a>

            <p class="coreti-sidebar-heading">Licenciamento</p>

            <a href="{{ route('service-desk.office') }}" class="coreti-sidebar-link {{ request()->routeIs('service-desk.office') ? 'coreti-sidebar-link-active' : '' }}">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
                    <rect width="10" height="14" x="7" y="5" rx="1.8" stroke-width="1.8" />
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M11 18h2" />
                </svg>
                <span>Office</span>
                <span class="coreti-sidebar-chevron">‹</span>
            </a>

            @if ($user->role === 'admin')
                <p class="coreti-sidebar-heading">Jira</p>

                <a href="{{ route('jira-projects.index') }}" class="coreti-sidebar-link {{ request()->routeIs('jira-projects.*') ? 'coreti-sidebar-link-active' : '' }}">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M4 6h16v12H4zM8 10h8M8 14h6" />
                    </svg>
                    <span>Painel Jira</span>
                    <span class="coreti-sidebar-chevron">‹</span>
                </a>

            @endif
        @elseif ($isBancadaServicos)
            <a href="{{ route('home') }}" class="coreti-sidebar-link">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M15 18l-6-6 6-6" />
                </svg>
                <span>Voltar ao CoreTI</span>
            </a>

            <p class="coreti-sidebar-heading">Bancada de Serviços</p>

            <a href="{{ route('bancada-servicos.dashboard') }}" class="coreti-sidebar-link {{ request()->routeIs('bancada-servicos.dashboard') ? 'coreti-sidebar-link-active' : '' }}">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M4 13.5A8 8 0 1 1 12 21H4v-7.5Z" />
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 8v5l3 2" />
                </svg>
                <span>Dashboard</span>
            </a>

            <a href="{{ route('bancada-servicos.tickets') }}" class="coreti-sidebar-link {{ request()->routeIs('bancada-servicos.tickets') ? 'coreti-sidebar-link-active' : '' }}">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M5 6h14v12H5zM8 10h8M8 14h5" />
                </svg>
                <span>Chamados</span>
                <span class="coreti-sidebar-chevron">‹</span>
            </a>

            <a href="{{ route('bancada-servicos.assets') }}" class="coreti-sidebar-link {{ request()->routeIs('bancada-servicos.assets') || request()->routeIs('bancada-servicos.assets.edit') || request()->routeIs('bancada-servicos.assets.history') ? 'coreti-sidebar-link-active' : '' }}">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M5 4h14v16H5zM8 8h8M8 12h8M8 16h5" />
                </svg>
                <span>Equipamentos</span>
                <span class="coreti-sidebar-chevron">‹</span>
            </a>

            <a href="{{ route('bancada-servicos.assets.delivered') }}" class="coreti-sidebar-link {{ request()->routeIs('bancada-servicos.assets.delivered') ? 'coreti-sidebar-link-active' : '' }}">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M20 6 9 17l-5-5" />
                </svg>
                <span>Entregues</span>
                <span class="coreti-sidebar-chevron">‹</span>
            </a>

            <a href="{{ route('bancada-servicos.assets.discarded') }}" class="coreti-sidebar-link {{ request()->routeIs('bancada-servicos.assets.discarded') ? 'coreti-sidebar-link-active' : '' }}">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M6 7h12M9 7V5h6v2M8 7l1 12h6l1-12" />
                </svg>
                <span>Descartados</span>
                <span class="coreti-sidebar-chevron">‹</span>
            </a>

            <a href="{{ route('bancada-servicos.assets.backup') }}" class="coreti-sidebar-link {{ request()->routeIs('bancada-servicos.assets.backup') ? 'coreti-sidebar-link-active' : '' }}">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M4 12h16M12 4v16" />
                    <circle cx="12" cy="12" r="9" stroke-width="1.8" />
                </svg>
                <span>Backup</span>
                <span class="coreti-sidebar-chevron">‹</span>
            </a>

            <a href="{{ route('bancada-servicos.awaiting-delivery') }}" class="coreti-sidebar-link {{ request()->routeIs('bancada-servicos.awaiting-delivery') ? 'coreti-sidebar-link-active' : '' }}">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M3 12h12M9 6l6 6-6 6M16 7h5v10h-5" />
                </svg>
                <span>Aguardando entrega</span>
                <span class="coreti-sidebar-chevron">‹</span>
            </a>

            <a href="{{ route('bancada-servicos.routes.config') }}" class="coreti-sidebar-link {{ request()->routeIs('bancada-servicos.routes.config') ? 'coreti-sidebar-link-active' : '' }}">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M4 7h16M4 12h10M4 17h7" />
                </svg>
                <span>Rotas de malote</span>
                <span class="coreti-sidebar-chevron">‹</span>
            </a>

            <a href="{{ route('bancada-servicos.sla') }}" class="coreti-sidebar-link {{ request()->routeIs('bancada-servicos.sla') ? 'coreti-sidebar-link-active' : '' }}">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 6v6l4 2M22 12a10 10 0 1 1-20 0 10 10 0 0 1 20 0Z" />
                </svg>
                <span>SLA</span>
                <span class="coreti-sidebar-chevron">‹</span>
            </a>

            <a href="{{ route('bancada-servicos.reports') }}" class="coreti-sidebar-link {{ request()->routeIs('bancada-servicos.reports') ? 'coreti-sidebar-link-active' : '' }}">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M6 20V4M10 16v4M14 11v9M18 7v13" />
                </svg>
                <span>Relatórios</span>
                <span class="coreti-sidebar-chevron">‹</span>
            </a>
        @elseif ($isAdministrativo)
            <a href="{{ route('home') }}" class="coreti-sidebar-link">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M15 18l-6-6 6-6" />
                </svg>
                <span>Voltar ao CoreTI</span>
            </a>

            <p class="coreti-sidebar-heading">Administrativo</p>

            <a href="{{ route('administrativo') }}" class="coreti-sidebar-link {{ request()->routeIs('administrativo') ? 'coreti-sidebar-link-active' : '' }}">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M4 20h16M6 20V8l6-4 6 4v12M9 20v-6h6v6" />
                </svg>
                <span>Painel</span>
                <span class="coreti-sidebar-chevron">‹</span>
            </a>

            <a href="{{ route('administrativo.entrada-fiscal') }}" class="coreti-sidebar-link {{ request()->routeIs('administrativo.entrada-fiscal') ? 'coreti-sidebar-link-active' : '' }}">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M6 3v18M10 7h8M10 12h8M10 17h5" /></svg>
                <span>Entrada Fiscal</span>
                <span class="coreti-sidebar-chevron">‹</span>
            </a>

            <a href="{{ route('administrativo.terceiros') }}" class="coreti-sidebar-link {{ request()->routeIs('administrativo.terceiros') ? 'coreti-sidebar-link-active' : '' }}">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M5 6h14v12H5zM8 10h8M8 14h5" /></svg>
                <span>Terceiros</span>
                <span class="coreti-sidebar-chevron">‹</span>
            </a>

            <a href="{{ route('administrativo.pecas') }}" class="coreti-sidebar-link {{ request()->routeIs('administrativo.pecas') ? 'coreti-sidebar-link-active' : '' }}">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M4 12h16M12 4v16" /><circle cx="12" cy="12" r="9" stroke-width="1.8" /></svg>
                <span>Peças</span>
                <span class="coreti-sidebar-chevron">‹</span>
            </a>

            <a href="{{ route('administrativo.estoque-interno') }}" class="coreti-sidebar-link {{ request()->routeIs('administrativo.estoque-interno') ? 'coreti-sidebar-link-active' : '' }}">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M3 7h18M6 7h12v13H6z" /></svg>
                <span>Estoque Interno</span>
                <span class="coreti-sidebar-chevron">‹</span>
            </a>

            <a href="{{ route('administrativo.nota-saida') }}" class="coreti-sidebar-link {{ request()->routeIs('administrativo.nota-saida') ? 'coreti-sidebar-link-active' : '' }}">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M4 20h16M6 20V8l6-4 6 4v12" /></svg>
                <span>Nota de Saída</span>
                <span class="coreti-sidebar-chevron">‹</span>
            </a>

            <a href="{{ route('administrativo.empresas-terceirizadas') }}" class="coreti-sidebar-link {{ request()->routeIs('administrativo.empresas-terceirizadas') ? 'coreti-sidebar-link-active' : '' }}">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M4 19h16M6 19V5h12v14" /></svg>
                <span>Empresas Terceirizadas</span>
                <span class="coreti-sidebar-chevron">‹</span>
            </a>

            <a href="{{ route('administrativo.historico') }}" class="coreti-sidebar-link {{ request()->routeIs('administrativo.historico') ? 'coreti-sidebar-link-active' : '' }}">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 8v5l3 2" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M3 12a9 9 0 1 0 3-6.7M3 4v3h3" /></svg>
                <span>Histórico</span>
                <span class="coreti-sidebar-chevron">‹</span>
            </a>

            <a href="{{ route('administrativo.relatorios') }}" class="coreti-sidebar-link {{ request()->routeIs('administrativo.relatorios') ? 'coreti-sidebar-link-active' : '' }}">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M6 3v18M10 7h8M10 12h8M10 17h5" />
                </svg>
                <span>Relatórios</span>
                <span class="coreti-sidebar-chevron">‹</span>
            </a>

            <a href="{{ route('administrativo.configuracoes') }}" class="coreti-sidebar-link {{ request()->routeIs('administrativo.configuracoes') ? 'coreti-sidebar-link-active' : '' }}">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 15.5a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7Z" />
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M19 12a7 7 0 0 0-.1-1.2l2-1.5-2-3.4-2.4 1a7 7 0 0 0-2-1.2L14.2 3h-4.4l-.3 2.7a7 7 0 0 0-2 1.2l-2.4-1-2 3.4 2 1.5A7 7 0 0 0 5 12c0 .4 0 .8.1 1.2l-2 1.5 2 3.4 2.4-1a7 7 0 0 0 2 1.2l.3 2.7h4.4l.3-2.7a7 7 0 0 0 2-1.2l2.4 1 2-3.4-2-1.5c.1-.4.1-.8.1-1.2Z" />
                </svg>
                <span>Configurações</span>
                <span class="coreti-sidebar-chevron">‹</span>
            </a>

            <p class="coreti-sidebar-heading">Licenciamento</p>

            <a href="{{ route('administrativo.licensing.email') }}" class="coreti-sidebar-link {{ request()->routeIs('administrativo.licensing.email') ? 'coreti-sidebar-link-active' : '' }}">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M4 6h16v12H4zM4 7l8 6 8-6" />
                </svg>
                <span>E-mail</span>
                <span class="coreti-sidebar-chevron">‹</span>
            </a>

            <a href="{{ route('administrativo.licensing.jira') }}" class="coreti-sidebar-link {{ request()->routeIs('administrativo.licensing.jira') ? 'coreti-sidebar-link-active' : '' }}">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M5 6h14v12H5zM8 10h8M8 14h5" />
                </svg>
                <span>Jira</span>
                <span class="coreti-sidebar-chevron">‹</span>
            </a>

            <a href="{{ route('administrativo.licensing.office') }}" class="coreti-sidebar-link {{ request()->routeIs('administrativo.licensing.office') ? 'coreti-sidebar-link-active' : '' }}">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
                    <rect width="10" height="14" x="7" y="5" rx="1.8" stroke-width="1.8" />
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M11 18h2" />
                </svg>
                <span>Rateio Office</span>
                <span class="coreti-sidebar-chevron">‹</span>
            </a>
        @else
            <a href="{{ route('dashboard') }}" class="coreti-sidebar-link {{ request()->routeIs('dashboard') || request()->routeIs('home') ? 'coreti-sidebar-link-active' : '' }}">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M4 13.5A8 8 0 1 1 12 21H4v-7.5Z" />
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 8v5l3 2" />
                </svg>
                <span>Dashboard</span>
            </a>

            <p class="coreti-sidebar-heading">Módulos</p>

            @if ($canServiceDesk)
            <a href="{{ route('service-desk.dashboard') }}" class="coreti-sidebar-link">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M5 6h14v12H5zM8 10h8M8 14h5" />
                </svg>
                <span>ServiceDesk</span>
                <span class="coreti-sidebar-chevron">‹</span>
            </a>
            @endif

            @if ($canAplicativos)
            <a href="{{ route('applications.index') }}" class="coreti-sidebar-link {{ request()->routeIs('applications.*') ? 'coreti-sidebar-link-active' : '' }}">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M5 5h6v6H5zM13 5h6v6h-6zM5 13h6v6H5zM13 13h6v6h-6z" />
                </svg>
                <span>Aplicativos</span>
                <span class="coreti-sidebar-chevron">‹</span>
            </a>
            @endif

            @if ($canUnidades)
            <a href="{{ route('circuits.units') }}" class="coreti-sidebar-link {{ request()->routeIs('circuits.units') || request()->routeIs('circuits.units.create') || request()->routeIs('circuits.units.edit') ? 'coreti-sidebar-link-active' : '' }}">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 21s6-5.4 6-11a6 6 0 1 0-12 0c0 5.6 6 11 6 11Z" />
                    <circle cx="12" cy="10" r="2" stroke-width="1.8" />
                </svg>
                <span>Unidades - Circuitos</span>
                <span class="coreti-sidebar-chevron">‹</span>
            </a>

            <a href="{{ route('circuits.units.dashboard') }}" class="coreti-sidebar-link {{ request()->routeIs('circuits.units.dashboard') ? 'coreti-sidebar-link-active' : '' }}">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M4 19V5M4 19h16M8 16v-5M12 16V8M16 16v-3" />
                </svg>
                <span>Unidades - Dashboard</span>
                <span class="coreti-sidebar-chevron">‹</span>
            </a>
            @endif

            @if ($user->role === 'admin' && $canAdministrativo)
            <a href="{{ route('monitoramento') }}" class="coreti-sidebar-link {{ request()->routeIs('monitoramento*') ? 'coreti-sidebar-link-active' : '' }}">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
                    <rect width="16" height="11" x="4" y="5" rx="1.8" stroke-width="1.8" />
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M9 20h6M12 16v4" />
                </svg>
                <span>Monitoramento</span>
                <span class="coreti-sidebar-chevron">‹</span>
            </a>

            <a href="{{ route('indicadores') }}" class="coreti-sidebar-link {{ request()->routeIs('indicadores*') ? 'coreti-sidebar-link-active' : '' }}">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M4 19V5M4 19h16M8 16v-5M12 16V8M16 16v-3" />
                </svg>
                <span>Indicadores</span>
                <span class="coreti-sidebar-chevron">‹</span>
            </a>

            <a href="{{ route('unidade-digital') }}" class="coreti-sidebar-link {{ request()->routeIs('unidade-digital*') ? 'coreti-sidebar-link-active' : '' }}">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
                    <rect width="10" height="14" x="7" y="5" rx="1.8" stroke-width="1.8" />
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M11 18h2" />
                </svg>
                <span>Unidade Digital</span>
                <span class="coreti-sidebar-chevron">‹</span>
            </a>

            @if ($canBancada)
            <a href="{{ route('bancada-servicos.dashboard') }}" class="coreti-sidebar-link {{ request()->routeIs('bancada-servicos.*') ? 'coreti-sidebar-link-active' : '' }}">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M5 17h14M7 17V8h10v9M9 20h6" />
                </svg>
                <span>Bancada</span>
                <span class="coreti-sidebar-chevron">‹</span>
            </a>
            @endif

            <a href="{{ route('governanca') }}" class="coreti-sidebar-link {{ request()->routeIs('governanca*') ? 'coreti-sidebar-link-active' : '' }}">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 3 4 7l8 4 8-4-8-4ZM4 12l8 4 8-4M4 17l8 4 8-4" />
                </svg>
                <span>Governança</span>
                <span class="coreti-sidebar-chevron">‹</span>
            </a>

            <p class="coreti-sidebar-heading">Administração</p>

            <a href="{{ route('administrativo') }}" class="coreti-sidebar-link {{ request()->routeIs('administrativo*') ? 'coreti-sidebar-link-active' : '' }}">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M4 20h16M6 20V8l6-4 6 4v12M9 20v-6h6v6" />
                </svg>
                <span>Administrativo</span>
                <span class="coreti-sidebar-chevron">‹</span>
            </a>


            <p class="coreti-sidebar-heading">Gestão de Dados</p>

            <a href="{{ route('administrativo.relatorios') }}" class="coreti-sidebar-link {{ request()->routeIs('administrativo.relatorios') ? 'coreti-sidebar-link-active' : '' }}">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M6 3v18M10 7h8M10 12h8M10 17h5" />
                </svg>
                <span>Relatórios</span>
                <span class="coreti-sidebar-chevron">‹</span>
            </a>
            @endif
        @endif
    </nav>

    <form method="POST" action="{{ route('logout') }}" class="coreti-sidebar-logout-form">
        @csrf
        <button type="submit" class="coreti-sidebar-logout">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M15 17l5-5-5-5M20 12H8M11 20H5a1 1 0 0 1-1-1V5a1 1 0 0 1 1-1h6" />
            </svg>
            <span>Sair</span>
        </button>
    </form>
</div>
