@php
    $isServiceDesk = request()->routeIs('service-desk.*') || request()->routeIs('jira-projects.*');
    $isBancadaServicos = request()->routeIs('bancada-servicos.*');
    $isAdministrativo = request()->routeIs('administrativo*');
    $isNetworkDatacenter = request()->routeIs('network-datacenter.*') || request()->routeIs('cmdb.*');
    $isCoordenacao = request()->routeIs('coordenacao.*');
    $canServiceDesk = $user->hasModuleAccess('servicedesk');
    $canUnidades = $user->hasModuleAccess('unidades');
    $canAplicativos = $user->hasModuleAccess('aplicativos');
    $canBancada = $user->hasModuleAccess('bancada');
    $canAdministrativo = $user->hasModuleAccess('administrativo');
    $canNetworkDatacenter = $user->hasModuleAccess('cmdb') || $user->hasModuleAccess('network_datacenter');
    $canCoordenacao = $user->hasModuleAccess('coordenacao');
    $canExpurgoAd = $user->hasModuleAccess('ad');
    // Telefonia e Equipamentos são módulos próprios: moram no menu do
    // Administrativo e do ServiceDesk, mas não dependem da permissão deles.
    $canTelefonia = $user->hasModuleAccess('telefonia');
    $canEquipamentos = $user->hasModuleAccess('equipamentos');
    $canImpressoras = $user->hasModuleAccess('impressoras');
    $isAdminRole = $user->role === 'admin';
    // Administrativo pede apenas o módulo para consultar; a escrita é que
    // exige 'manage'. Antes o menu exigia também role=admin, espelhando um
    // grupo de rotas que pedia as duas coisas — e quem recebia o módulo
    // continuava sem ver nada.
    $canPainelAdministrativo = $canAdministrativo;

    // Unidade Digital NÃO pertence ao Administrativo: fica restrita a
    // administrador, para não entrar junto com uma permissão que não é a dela.
    // (O módulo de Governança foi removido em 2026-10-21.)
    $canUnidadeDigital = $isAdminRole;
    $temAlgumModulo = $canServiceDesk || $canAplicativos || $isAdminRole
        || $canCoordenacao || $canUnidades || $canBancada
        || $canPainelAdministrativo || $canImpressoras;
@endphp

<div class="{{ $containerClass ?? 'coreti-sidebar-shell' }} {{ $isNetworkDatacenter ? 'cmdb-sidebar-container' : '' }}">
    {{-- Rede animada (plexus) no fundo da sidebar — mesma linguagem da tela de
         login. Decorativa; fica atrás de todo o menu. Animada por JS na
         navigation.blade.php (roda só nas sidebars visíveis). --}}
    <canvas class="coreti-sidebar-net" aria-hidden="true"></canvas>

    <div class="coreti-sidebar-brand">
        <a href="{{ route('dashboard') }}" class="inline-flex items-center">
            <img
                src="{{ asset('images/branding/cocari-logo-white.png') }}"
                alt="Cocari"
                class="coreti-sidebar-logo"
            >
        </a>
    </div>

    <div class="coreti-sidebar-divider"></div>

    <nav class="coreti-sidebar-nav" aria-label="{{ $isServiceDesk ? 'Menu ServiceDesk' : ($isBancadaServicos ? 'Menu Bancada de Serviços' : ($isAdministrativo ? 'Menu Administrativo' : ($isNetworkDatacenter ? 'Menu CMDB' : ($isCoordenacao ? 'Menu Coordenação' : 'Menu principal')))) }}">
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

            @if ($canEquipamentos)
                <p class="coreti-sidebar-heading">Equipamentos</p>

                <a href="{{ route('service-desk.equipamentos.index') }}" class="coreti-sidebar-link {{ request()->routeIs('service-desk.equipamentos.*') ? 'coreti-sidebar-link-active' : '' }}">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M3 6h18v10H3zM8 20h8M12 16v4" />
                    </svg>
                    <span>Inventário</span>
                    <span class="coreti-sidebar-chevron">‹</span>
                </a>

                <a href="{{ route('service-desk.equipamentos.assignments.index') }}" class="coreti-sidebar-link {{ request()->routeIs('service-desk.equipamentos.assignments.*') ? 'coreti-sidebar-link-active' : '' }}">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M9 7a3 3 0 1 0 6 0 3 3 0 0 0-6 0ZM5 20a7 7 0 0 1 14 0" />
                    </svg>
                    <span>Entregas</span>
                    <span class="coreti-sidebar-chevron">‹</span>
                </a>
            @endif

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

            <p class="coreti-sidebar-heading">AD</p>

            <a href="{{ route('service-desk.ad') }}" class="coreti-sidebar-link {{ request()->routeIs('service-desk.ad') ? 'coreti-sidebar-link-active' : '' }}">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
                    <circle cx="12" cy="8" r="3" stroke-width="1.8" />
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M5 20a7 7 0 0 1 14 0M4 4h16v16H4z" />
                </svg>
                <span>Active Directory</span>
            </a>

            {{-- Sede, Unidades PR, Unidades GO e Outras unidades saíram daqui:
                 são filtros da própria tela do Active Directory, alcançáveis
                 pelo parâmetro ?category=. Continuam funcionando por URL. --}}

            <a href="{{ route('service-desk.ad.cadastro-incompleto.index') }}" class="coreti-sidebar-link coreti-sidebar-link-sub {{ request()->routeIs('service-desk.ad.cadastro-incompleto.*') ? 'coreti-sidebar-link-active' : '' }}">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M9 5H7a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2h-2" />
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M9 5a2 2 0 0 1 2-2h2a2 2 0 0 1 2 2M9 12h6M9 16h3" />
                </svg>
                <span>Cadastro incompleto</span>
            </a>

            <p class="coreti-sidebar-heading">Office 365</p>

            @foreach([
                'sede' => 'Sede',
                'unidades' => 'Unidades',
                'cerrado' => 'Cerrado',
                'genericos' => 'Genéricos',
            ] as $officeDivision => $officeDivisionLabel)
                <a href="{{ route('service-desk.office', ['division' => $officeDivision]) }}" class="coreti-sidebar-link {{ request()->routeIs('service-desk.office') && request()->query('division') === $officeDivision ? 'coreti-sidebar-link-active' : '' }}">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M4 6h16v12H4zM4 7l8 6 8-6" />
                    </svg>
                    <span>{{ $officeDivisionLabel }}</span>
                    <span class="coreti-sidebar-chevron">‹</span>
                </a>
            @endforeach

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

            <a href="{{ route('bancada-servicos.estoque') }}" class="coreti-sidebar-link {{ request()->routeIs('bancada-servicos.estoque*') ? 'coreti-sidebar-link-active' : '' }}">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
                </svg>
                <span>Estoque de peças</span>
                <span class="coreti-sidebar-chevron">‹</span>
            </a>

            <a href="{{ route('bancada-servicos.terceiros') }}" class="coreti-sidebar-link {{ request()->routeIs('bancada-servicos.terceiros') ? 'coreti-sidebar-link-active' : '' }}">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M17 20h5v-2a4 4 0 0 0-3-3.87M9 20H4v-2a4 4 0 0 1 3-3.87m5-1.13a4 4 0 1 0 0-8 4 4 0 0 0 0 8Z" />
                </svg>
                <span>Terceiros</span>
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

            <a href="{{ route('administrativo.cartao-ti.index') }}" class="coreti-sidebar-link {{ request()->routeIs('administrativo.cartao-ti.*') ? 'coreti-sidebar-link-active' : '' }}">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
                    <rect x="3" y="5" width="18" height="14" rx="2" stroke-width="1.8" />
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M3 10h18" />
                </svg>
                <span>Cartão TI</span>
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

            @if ($canTelefonia)
                <p class="coreti-sidebar-heading">Telefonia</p>

                <a href="{{ route('administrativo.telefonia.dashboard') }}" class="coreti-sidebar-link {{ request()->routeIs('administrativo.telefonia.dashboard') ? 'coreti-sidebar-link-active' : '' }}">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M4 20V10M10 20V4M16 20v-7M22 20H2" />
                    </svg>
                    <span>Painel</span>
                    <span class="coreti-sidebar-chevron">‹</span>
                </a>

                <a href="{{ route('administrativo.telefonia.lines.index') }}" class="coreti-sidebar-link {{ request()->routeIs('administrativo.telefonia.lines.*') ? 'coreti-sidebar-link-active' : '' }}">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M6 3h4l2 5-3 2a12 12 0 0 0 5 5l2-3 5 2v4a2 2 0 0 1-2 2A16 16 0 0 1 4 5a2 2 0 0 1 2-2Z" />
                    </svg>
                    <span>Linhas corporativas</span>
                    <span class="coreti-sidebar-chevron">‹</span>
                </a>

                <a href="{{ route('administrativo.telefonia.devices.index') }}" class="coreti-sidebar-link {{ request()->routeIs('administrativo.telefonia.devices.*') ? 'coreti-sidebar-link-active' : '' }}">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M7 2h10v20H7zM11 18h2" />
                    </svg>
                    <span>Aparelhos</span>
                    <span class="coreti-sidebar-chevron">‹</span>
                </a>

                <a href="{{ route('administrativo.telefonia.faturas.index') }}" class="coreti-sidebar-link {{ request()->routeIs('administrativo.telefonia.faturas.*') ? 'coreti-sidebar-link-active' : '' }}">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M6 2h12v20l-3-2-3 2-3-2-3 2zM9 7h6M9 11h6M9 15h4" />
                    </svg>
                    <span>Faturas</span>
                    <span class="coreti-sidebar-chevron">‹</span>
                </a>

                <a href="{{ route('administrativo.telefonia.relatorios.gastos') }}" class="coreti-sidebar-link {{ request()->routeIs('administrativo.telefonia.relatorios.*') ? 'coreti-sidebar-link-active' : '' }}">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M4 19h16M7 16V9M12 16V5M17 16v-4" />
                    </svg>
                    <span>Relatórios</span>
                    <span class="coreti-sidebar-chevron">‹</span>
                </a>

                <a href="{{ route('administrativo.telefonia.assignments.index') }}" class="coreti-sidebar-link {{ request()->routeIs('administrativo.telefonia.assignments.*') ? 'coreti-sidebar-link-active' : '' }}">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M10 13a5 5 0 0 0 7 0l2-2a5 5 0 0 0-7-7l-1 1" />
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M14 11a5 5 0 0 0-7 0l-2 2a5 5 0 0 0 7 7l1-1" />
                    </svg>
                    <span>Vínculos</span>
                    <span class="coreti-sidebar-chevron">‹</span>
                </a>
            @endif

            <p class="coreti-sidebar-heading">Office 365</p>

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

            <a href="{{ route('administrativo.licensing.office') }}" class="coreti-sidebar-link {{ request()->routeIs('administrativo.licensing.office*') || request()->routeIs('administrativo.licensing.office-prices.*') ? 'coreti-sidebar-link-active' : '' }}">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
                    <rect width="10" height="14" x="7" y="5" rx="1.8" stroke-width="1.8" />
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M11 18h2" />
                </svg>
                <span>Rateio Office</span>
                <span class="coreti-sidebar-chevron">‹</span>
            </a>
        @else
            @if (! $isNetworkDatacenter && ! $isCoordenacao)
            <a href="{{ route('dashboard') }}" class="coreti-sidebar-link {{ request()->routeIs('dashboard') || request()->routeIs('home') ? 'coreti-sidebar-link-active' : '' }}">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M4 13.5A8 8 0 1 1 12 21H4v-7.5Z" />
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 8v5l3 2" />
                </svg>
                <span>Dashboard</span>
            </a>

            @if ($temAlgumModulo)
            <p class="coreti-sidebar-heading">Módulos</p>
            @endif

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

            @if ($user->role === 'admin')
            <a href="{{ route('cost-centers.index') }}" class="coreti-sidebar-link {{ request()->routeIs('cost-centers.*') ? 'coreti-sidebar-link-active' : '' }}">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M4 6h16M4 12h16M4 18h16" />
                    <circle cx="8" cy="6" r="1" fill="currentColor" stroke="none" /><circle cx="8" cy="12" r="1" fill="currentColor" stroke="none" /><circle cx="8" cy="18" r="1" fill="currentColor" stroke="none" />
                </svg>
                <span>Centros de custo</span>
                <span class="coreti-sidebar-chevron">‹</span>
            </a>
            @endif

            @if ($canCoordenacao)
            <a href="{{ route('coordenacao.dashboard') }}" class="coreti-sidebar-link {{ request()->routeIs('coordenacao.*') ? 'coreti-sidebar-link-active' : '' }}">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M4 13.5A8 8 0 1 1 12 21H4v-7.5Z" />
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 8v5l3 2" />
                </svg>
                <span style="white-space: normal; overflow: visible; text-overflow: clip; line-height: 1.15;">Coordenação</span>
                <span class="coreti-sidebar-chevron">‹</span>
            </a>
            @endif

            @if ($canImpressoras)
            <a href="{{ route('impressoras.index') }}" class="coreti-sidebar-link {{ request()->routeIs('impressoras.*') ? 'coreti-sidebar-link-active' : '' }}">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M7 8V4h10v4" />
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M5 8h14a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2M7 17H5a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2" />
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M7 14h10v6H7z" />
                </svg>
                <span style="white-space: normal; overflow: visible; text-overflow: clip; line-height: 1.15;">Impressoras</span>
                <span class="coreti-sidebar-chevron">‹</span>
            </a>
            @endif

            {{-- Cadastro incompleto e Centro de custo foram para ServiceDesk > AD.
                 Aqui fica só a Exclusão de contas, que é destrutiva e continua com
                 módulo próprio para poder ser concedida a menos gente. --}}
            @if ($canExpurgoAd)
            @php($lotesPendentes = \App\Models\AdPurgeBatch::where('status', 'aguardando_aprovacao')->count())
            <a href="{{ route('ad.exclusao.index') }}" class="coreti-sidebar-link {{ request()->routeIs('ad.exclusao.*') ? 'coreti-sidebar-link-active' : '' }}">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M4 7h16M9 7V5h6v2M6 7l1 13h10l1-13" />
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M10 11v6M14 11v6" />
                </svg>
                <span style="white-space: normal; overflow: visible; text-overflow: clip; line-height: 1.15;">Exclusão de contas</span>
                @if ($lotesPendentes > 0)
                    <span class="ml-auto inline-flex h-5 min-w-[1.25rem] items-center justify-center rounded-full bg-amber-500 px-1.5 text-[11px] font-bold text-white">{{ $lotesPendentes }}</span>
                @else
                    <span class="coreti-sidebar-chevron">&lsaquo;</span>
                @endif
            </a>
            @endif


            @endif

            @if ($canCoordenacao && $isCoordenacao)
                <a href="{{ route('home') }}" class="coreti-sidebar-link">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M15 18l-6-6 6-6" />
                    </svg>
                    <span>Voltar ao CoreTI</span>
                </a>

                <p class="coreti-sidebar-heading">Coordenação</p>

                <a aria-current="{{ request()->routeIs('coordenacao.dashboard', 'coordenacao.index') ? 'page' : 'false' }}" href="{{ route('coordenacao.dashboard') }}" class="coreti-sidebar-link {{ request()->routeIs('coordenacao.dashboard', 'coordenacao.index') ? 'coreti-sidebar-link-active' : '' }}">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M4 13.5A8 8 0 1 1 12 21H4v-7.5Z" />
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 8v5l3 2" />
                    </svg>
                    <span>Visão geral</span>
                </a>

                <a aria-current="{{ request()->routeIs('coordenacao.products.*') ? 'page' : 'false' }}" href="{{ route('coordenacao.products.index') }}" class="coreti-sidebar-link {{ request()->routeIs('coordenacao.products.*') ? 'coreti-sidebar-link-active' : '' }}">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M4 6h16v12H4zM8 10h8M8 14h5" />
                    </svg>
                    <span>Produtos de TI</span>
                </a>

                <a aria-current="{{ request()->routeIs('coordenacao.rateio.*') ? 'page' : 'false' }}" href="{{ route('coordenacao.rateio.index') }}" class="coreti-sidebar-link {{ request()->routeIs('coordenacao.rateio.*') ? 'coreti-sidebar-link-active' : '' }}">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M4 19h16M7 16V9m5 7V5m5 11v-4" />
                    </svg>
                    <span>Rateio mensal</span>
                </a>

                <a aria-current="{{ request()->routeIs('coordenacao.collaborators*') ? 'page' : 'false' }}" href="{{ route('coordenacao.collaborators') }}" class="coreti-sidebar-link {{ request()->routeIs('coordenacao.collaborators*') ? 'coreti-sidebar-link-active' : '' }}">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M16 11a4 4 0 1 0-8 0 4 4 0 0 0 8 0Z" />
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M4 21a8 8 0 0 1 16 0" />
                    </svg>
                    <span>Colaboradores</span>
                </a>

                <a aria-current="{{ request()->routeIs('coordenacao.units*') ? 'page' : 'false' }}" href="{{ route('coordenacao.units') }}" class="coreti-sidebar-link {{ request()->routeIs('coordenacao.units*') ? 'coreti-sidebar-link-active' : '' }}">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M4 20h16M7 20V7l5-3 5 3v13" />
                    </svg>
                    <span>Unidades</span>
                </a>

                <a aria-current="{{ request()->routeIs('coordenacao.pending.*') ? 'page' : 'false' }}" href="{{ route('coordenacao.pending.index') }}" class="coreti-sidebar-link {{ request()->routeIs('coordenacao.pending.*') ? 'coreti-sidebar-link-active' : '' }}">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M4 6h16v12H4zM8 10h8M8 14h5" />
                    </svg>
                    <span>Pendências</span>
                </a>

                <a aria-current="{{ request()->routeIs('coordenacao.closings.*') ? 'page' : 'false' }}" href="{{ route('coordenacao.closings.index') }}" class="coreti-sidebar-link {{ request()->routeIs('coordenacao.closings.*') ? 'coreti-sidebar-link-active' : '' }}">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M5 5h14v14H5zM8 9h8M8 13h5" />
                    </svg>
                    <span>Fechamentos</span>
                </a>

                <a aria-current="{{ request()->routeIs('coordenacao.rules*') ? 'page' : 'false' }}" href="{{ route('coordenacao.rules') }}" class="coreti-sidebar-link {{ request()->routeIs('coordenacao.rules*') ? 'coreti-sidebar-link-active' : '' }}">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M4 6h16v12H4zM8 10h8M8 14h5" />
                    </svg>
                    <span>Histórico</span>
                </a>

                <a aria-current="false" href="{{ route('coordenacao.export', request()->query()) }}" class="coreti-sidebar-link">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 3v12m0 0 4-4m-4 4-4-4M5 21h14" />
                    </svg>
                    <span>Exportar</span>
                </a>
            @endif

            @if ($canNetworkDatacenter && ! $isCoordenacao)
                @if ($isNetworkDatacenter)
                    <a href="{{ route('home') }}" class="coreti-sidebar-link">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M15 18l-6-6 6-6" /></svg>
                        <span>Voltar ao CoreTI</span>
                    </a>
                    <p class="coreti-sidebar-heading">CMDB</p>
                    <a aria-current="{{ request()->routeIs('cmdb.dashboard', 'cmdb.index') ? 'page' : 'false' }}" href="{{ route('cmdb.dashboard') }}" class="coreti-sidebar-link cmdb-sidebar-link {{ request()->routeIs('cmdb.dashboard', 'cmdb.index') ? 'coreti-sidebar-link-active' : '' }}"><span class="cmdb-sidebar-label">Visão geral</span></a>
                    <a aria-current="{{ request()->routeIs('cmdb.assets.*') ? 'page' : 'false' }}" href="{{ route('cmdb.assets.index') }}" class="coreti-sidebar-link cmdb-sidebar-link {{ request()->routeIs('cmdb.assets.*') ? 'coreti-sidebar-link-active' : '' }}"><span class="cmdb-sidebar-label">Ativos</span><span class="coreti-sidebar-chevron cmdb-sidebar-arrow">‹</span></a>
                    <a href="{{ route('cmdb.assets.index', ['asset_type' => 'virtual_machine']) }}" class="coreti-sidebar-link cmdb-sidebar-link {{ request()->query('asset_type') === 'virtual_machine' ? 'coreti-sidebar-link-active' : '' }}"><span class="cmdb-sidebar-label">Máquinas virtuais</span></a>
                    <a href="{{ route('cmdb.assets.index', ['view' => 'hosts']) }}" class="coreti-sidebar-link cmdb-sidebar-link {{ request()->query('view') === 'hosts' ? 'coreti-sidebar-link-active' : '' }}"><span class="cmdb-sidebar-label">Hosts ESXi</span></a>
                    <a aria-current="{{ request()->routeIs('cmdb.discovered-assets.*', 'cmdb.discovery.*') ? 'page' : 'false' }}" href="{{ route('cmdb.discovered-assets.index') }}" class="coreti-sidebar-link cmdb-sidebar-link {{ request()->routeIs('cmdb.discovered-assets.*', 'cmdb.discovery.*') ? 'coreti-sidebar-link-active' : '' }}"><span class="cmdb-sidebar-label">Descobertas</span><span class="coreti-sidebar-chevron cmdb-sidebar-arrow">‹</span></a>
                    <a href="{{ route('cmdb.vlans.index') }}" class="coreti-sidebar-link cmdb-sidebar-link {{ request()->routeIs('cmdb.vlans.*') ? 'coreti-sidebar-link-active' : '' }}"><span class="cmdb-sidebar-label">VLANs</span></a>
                    <a aria-current="{{ request()->routeIs('cmdb.pending.*') ? 'page' : 'false' }}" href="{{ route('cmdb.pending.index') }}" class="coreti-sidebar-link cmdb-sidebar-link {{ request()->routeIs('cmdb.pending.*') ? 'coreti-sidebar-link-active' : '' }}"><span class="cmdb-sidebar-label">Pendências</span><span class="coreti-sidebar-chevron cmdb-sidebar-arrow">‹</span></a>
                    <a aria-current="{{ request()->routeIs('cmdb.reports') ? 'page' : 'false' }}" href="{{ route('cmdb.reports') }}" class="coreti-sidebar-link cmdb-sidebar-link {{ request()->routeIs('cmdb.reports') ? 'coreti-sidebar-link-active' : '' }}"><span class="cmdb-sidebar-label">Relatórios</span><span class="coreti-sidebar-chevron cmdb-sidebar-arrow">‹</span></a>
                    <a aria-current="{{ request()->routeIs('cmdb.database-backups.*') ? 'page' : 'false' }}" href="{{ route('cmdb.database-backups.index') }}" class="coreti-sidebar-link cmdb-sidebar-link {{ request()->routeIs('cmdb.database-backups.*') ? 'coreti-sidebar-link-active' : '' }}"><span class="cmdb-sidebar-label cmdb-sidebar-label-wrap">Backups de banco</span><span class="coreti-sidebar-chevron cmdb-sidebar-arrow">‹</span></a>
                    @if(\App\Models\CmdbBackupTarget::where('ativo', true)->exists())
                    {{-- Auditoria autônoma: só aparece quando há alvo ativo cadastrado. --}}
                    <a aria-current="{{ request()->routeIs('cmdb.backup-targets.*') ? 'page' : 'false' }}" href="{{ route('cmdb.backup-targets.index') }}" class="coreti-sidebar-link cmdb-sidebar-link {{ request()->routeIs('cmdb.backup-targets.*') ? 'coreti-sidebar-link-active' : '' }}"><span class="cmdb-sidebar-label cmdb-sidebar-label-wrap">Auditoria por SSH</span><span class="coreti-sidebar-chevron cmdb-sidebar-arrow">‹</span></a>
                    @endif
                    <p class="coreti-sidebar-heading">CONFIGURAÇÕES</p>
                    <a href="{{ route('cmdb.services.index') }}" class="coreti-sidebar-link cmdb-sidebar-link {{ request()->routeIs('cmdb.services.*') ? 'coreti-sidebar-link-active' : '' }}"><span class="cmdb-sidebar-label">Serviços</span><span class="coreti-sidebar-chevron cmdb-sidebar-arrow">‹</span></a>
                    <a href="{{ route('cmdb.service-rules.index') }}" class="coreti-sidebar-link cmdb-sidebar-link {{ request()->routeIs('cmdb.service-rules.*') ? 'coreti-sidebar-link-active' : '' }}"><span class="cmdb-sidebar-label cmdb-sidebar-label-wrap">Regras de sugestão</span><span class="coreti-sidebar-chevron cmdb-sidebar-arrow">‹</span></a>
                    <a href="{{ route('cmdb.connectors.index') }}" class="coreti-sidebar-link cmdb-sidebar-link {{ request()->routeIs('cmdb.connectors.*') ? 'coreti-sidebar-link-active' : '' }}"><span class="cmdb-sidebar-label cmdb-sidebar-label-wrap">Conectores</span><span class="coreti-sidebar-chevron cmdb-sidebar-arrow">‹</span></a>
                    <a href="{{ route('cmdb.manufacturers.index') }}" class="coreti-sidebar-link cmdb-sidebar-link {{ request()->routeIs('cmdb.manufacturers.*') ? 'coreti-sidebar-link-active' : '' }}"><span class="cmdb-sidebar-label cmdb-sidebar-label-wrap">Fabricantes</span><span class="coreti-sidebar-chevron cmdb-sidebar-arrow">‹</span></a>
                    <a href="{{ route('cmdb.models.index') }}" class="coreti-sidebar-link cmdb-sidebar-link {{ request()->routeIs('cmdb.models.*') ? 'coreti-sidebar-link-active' : '' }}"><span class="cmdb-sidebar-label cmdb-sidebar-label-wrap">Modelos</span><span class="coreti-sidebar-chevron cmdb-sidebar-arrow">‹</span></a>
                    <a href="{{ route('cmdb.racks.index') }}" class="coreti-sidebar-link cmdb-sidebar-link {{ request()->routeIs('cmdb.racks.*') ? 'coreti-sidebar-link-active' : '' }}"><span class="cmdb-sidebar-label cmdb-sidebar-label-wrap">Racks</span><span class="coreti-sidebar-chevron cmdb-sidebar-arrow">‹</span></a>
                    <a href="{{ route('cmdb.expirations.index') }}" class="coreti-sidebar-link cmdb-sidebar-link {{ request()->routeIs('cmdb.expirations.*') ? 'coreti-sidebar-link-active' : '' }}"><span class="cmdb-sidebar-label cmdb-sidebar-label-wrap">Vencimentos</span><span class="coreti-sidebar-chevron cmdb-sidebar-arrow">‹</span></a>
                    <p class="coreti-sidebar-heading">ADMINISTRAÇÃO TÉCNICA</p>
                    <a href="{{ route('cmdb.quick-create.index') }}" class="coreti-sidebar-link cmdb-sidebar-link"><span class="cmdb-sidebar-label cmdb-sidebar-label-wrap">Cadastro rápido</span></a>
                    <a href="{{ route('cmdb.import.index') }}" class="coreti-sidebar-link cmdb-sidebar-link"><span class="cmdb-sidebar-label cmdb-sidebar-label-wrap">Importação</span></a>
                    <a href="{{ route('cmdb.sync-jobs.index') }}" class="coreti-sidebar-link cmdb-sidebar-link"><span class="cmdb-sidebar-label cmdb-sidebar-label-wrap">Jobs e logs</span></a>
                    @if(false)
                    <a href="{{ route('home') }}" class="coreti-sidebar-link">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M15 18l-6-6 6-6" />
                        </svg>
                        <span>Voltar ao CoreTI</span>
                    </a>

                    <p class="coreti-sidebar-heading">CMDB</p>

                    <a href="{{ route('cmdb.dashboard') }}" class="coreti-sidebar-link {{ request()->routeIs('cmdb.dashboard') ? 'coreti-sidebar-link-active' : '' }}">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M4 13.5A8 8 0 1 1 12 21H4v-7.5Z" />
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 8v5l3 2" />
                        </svg>
                        <span>Dashboard</span>
                    </a>

                    <a href="{{ route('cmdb.quick-create.index') }}" class="coreti-sidebar-link {{ request()->routeIs('cmdb.quick-create.*') ? 'coreti-sidebar-link-active' : '' }}">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M4 6h16v12H4zM8 10h8M8 14h5" />
                        </svg>
                        <span>Cadastro Rápido</span>
                        <span class="coreti-sidebar-chevron">‹</span>
                    </a>

                    <a href="{{ route('cmdb.import.index') }}" class="coreti-sidebar-link {{ request()->routeIs('cmdb.import.*') ? 'coreti-sidebar-link-active' : '' }}">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 4v10m0 0 4-4m-4 4-4-4M5 20h14" />
                        </svg>
                        <span>Importação</span>
                        <span class="coreti-sidebar-chevron">‹</span>
                    </a>

                    <a href="{{ route('cmdb.import.batches.index') }}" class="coreti-sidebar-link {{ request()->routeIs('cmdb.import.batches.*') ? 'coreti-sidebar-link-active' : '' }}">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M4 6h16v12H4zM7 10h10M7 14h6" />
                        </svg>
                        <span>Lotes de Importação</span>
                        <span class="coreti-sidebar-chevron">‹</span>
                    </a>

                    <a href="{{ route('cmdb.discovery.index') }}" class="coreti-sidebar-link {{ request()->routeIs('cmdb.discovery.*') ? 'coreti-sidebar-link-active' : '' }}">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M11 18a7 7 0 1 0 0-14 7 7 0 0 0 0 14Z" />
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="m20 20-3.5-3.5" />
                        </svg>
                        <span>Descoberta</span>
                        <span class="coreti-sidebar-chevron">‹</span>
                    </a>

                    <a href="{{ route('cmdb.discovered-assets.index') }}" class="coreti-sidebar-link {{ request()->routeIs('cmdb.discovered-assets.*') ? 'coreti-sidebar-link-active' : '' }}">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M4 6h16v12H4zM8 10h8M8 14h5" />
                        </svg>
                        <span>Descobertas</span>
                        <span class="coreti-sidebar-chevron">‹</span>
                    </a>

                    <a href="{{ route('cmdb.manufacturers.index') }}" class="coreti-sidebar-link {{ request()->routeIs('cmdb.manufacturers.*') ? 'coreti-sidebar-link-active' : '' }}">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M4 6h16v12H4zM8 10h8M8 14h5" />
                        </svg>
                        <span>Fabricantes</span>
                        <span class="coreti-sidebar-chevron">‹</span>
                    </a>

                    <a href="{{ route('cmdb.models.index') }}" class="coreti-sidebar-link {{ request()->routeIs('cmdb.models.*') ? 'coreti-sidebar-link-active' : '' }}">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M4 4h7v7H4zM13 4h7v7h-7zM4 13h7v7H4zM13 13h7v7h-7z" />
                        </svg>
                        <span>Modelos</span>
                        <span class="coreti-sidebar-chevron">‹</span>
                    </a>

                    <a href="{{ route('cmdb.connectors.index') }}" class="coreti-sidebar-link {{ request()->routeIs('cmdb.connectors.*') ? 'coreti-sidebar-link-active' : '' }}">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M5 6h14v12H5zM8 10h8M8 14h5" />
                        </svg>
                        <span>Conectores</span>
                        <span class="coreti-sidebar-chevron">‹</span>
                    </a>

                    <a href="{{ route('cmdb.sync-jobs.index') }}" class="coreti-sidebar-link {{ request()->routeIs('cmdb.sync-jobs.*') ? 'coreti-sidebar-link-active' : '' }}">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M5 6h14v12H5zM8 10h8M8 14h5" />
                        </svg>
                        <span>Jobs de Sincronização</span>
                        <span class="coreti-sidebar-chevron">‹</span>
                    </a>

                    <a href="{{ route('cmdb.sync-logs.index') }}" class="coreti-sidebar-link {{ request()->routeIs('cmdb.sync-logs.*') ? 'coreti-sidebar-link-active' : '' }}">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M6 4h12v16H6zM9 8h6M9 12h6M9 16h4" />
                        </svg>
                        <span>Logs de Sincronização</span>
                        <span class="coreti-sidebar-chevron">‹</span>
                    </a>

                    <p class="coreti-sidebar-heading">CMDB</p>

                    <a href="{{ route('cmdb.assets.index') }}" class="coreti-sidebar-link {{ request()->routeIs('cmdb.assets.*') ? 'coreti-sidebar-link-active' : '' }}">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M4 6h16v12H4zM7 10h10M7 14h6" />
                        </svg>
                        <span>Ativos</span>
                        <span class="coreti-sidebar-chevron">‹</span>
                    </a>

                    <a href="{{ route('cmdb.assets.index', ['type' => 'server']) }}" class="coreti-sidebar-link {{ request()->query('type') === 'server' ? 'coreti-sidebar-link-active' : '' }}">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M4 5h16v14H4zM8 9h8M8 13h5" />
                        </svg>
                        <span>Servidores</span>
                        <span class="coreti-sidebar-chevron">‹</span>
                    </a>

                    <a href="{{ route('cmdb.assets.index', ['type' => 'virtual_machine']) }}" class="coreti-sidebar-link {{ request()->query('type') === 'virtual_machine' ? 'coreti-sidebar-link-active' : '' }}">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M4 5h16v14H4zM8 9h8M8 13h5" />
                        </svg>
                        <span>Máquinas Virtuais</span>
                        <span class="coreti-sidebar-chevron">‹</span>
                    </a>

                    <a href="{{ route('cmdb.assets.index', ['type' => 'database']) }}" class="coreti-sidebar-link {{ request()->query('type') === 'database' ? 'coreti-sidebar-link-active' : '' }}">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M4 5h16v14H4zM8 9h8M8 13h5" />
                        </svg>
                        <span>Bancos de Dados</span>
                        <span class="coreti-sidebar-chevron">‹</span>
                    </a>

                    <a href="{{ route('cmdb.assets.index', ['type' => 'switch']) }}" class="coreti-sidebar-link {{ request()->query('type') === 'switch' ? 'coreti-sidebar-link-active' : '' }}">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M4 5h16v14H4zM8 9h8M8 13h5" />
                        </svg>
                        <span>Equipamentos de Rede</span>
                        <span class="coreti-sidebar-chevron">‹</span>
                    </a>

                    <a href="{{ route('cmdb.assets.index', ['type' => 'firewall']) }}" class="coreti-sidebar-link {{ request()->query('type') === 'firewall' ? 'coreti-sidebar-link-active' : '' }}">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M4 5h16v14H4zM8 9h8M8 13h5" />
                        </svg>
                        <span>Firewalls</span>
                        <span class="coreti-sidebar-chevron">‹</span>
                    </a>

                    <a href="{{ route('cmdb.assets.index', ['type' => 'ups']) }}" class="coreti-sidebar-link {{ request()->query('type') === 'ups' ? 'coreti-sidebar-link-active' : '' }}">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M4 5h16v14H4zM8 9h8M8 13h5" />
                        </svg>
                        <span>Nobreaks / UPS</span>
                        <span class="coreti-sidebar-chevron">‹</span>
                    </a>

                    <a href="{{ route('cmdb.assets.index', ['type' => 'storage']) }}" class="coreti-sidebar-link {{ request()->query('type') === 'storage' ? 'coreti-sidebar-link-active' : '' }}">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M4 5h16v14H4zM8 9h8M8 13h5" />
                        </svg>
                        <span>Storages</span>
                        <span class="coreti-sidebar-chevron">‹</span>
                    </a>

                    <a href="{{ route('cmdb.links.index') }}" class="coreti-sidebar-link {{ request()->routeIs('cmdb.links.*') ? 'coreti-sidebar-link-active' : '' }}">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M4 5h16v14H4zM8 9h8M8 13h5" />
                        </svg>
                        <span>Links e Circuitos</span>
                        <span class="coreti-sidebar-chevron">‹</span>
                    </a>

                    <a href="{{ route('cmdb.assets.index', ['type' => 'rack']) }}" class="coreti-sidebar-link {{ request()->query('type') === 'rack' ? 'coreti-sidebar-link-active' : '' }}">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M4 5h16v14H4zM8 9h8M8 13h5" />
                        </svg>
                        <span>Racks</span>
                        <span class="coreti-sidebar-chevron">‹</span>
                    </a>

                    <a href="{{ route('cmdb.assets.index', ['type' => 'certificate']) }}" class="coreti-sidebar-link {{ request()->query('type') === 'certificate' ? 'coreti-sidebar-link-active' : '' }}">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M4 5h16v14H4zM8 9h8M8 13h5" />
                        </svg>
                        <span>Certificados</span>
                        <span class="coreti-sidebar-chevron">‹</span>
                    </a>

                    <a href="{{ route('cmdb.assets.index', ['type' => 'backup']) }}" class="coreti-sidebar-link {{ request()->query('type') === 'backup' ? 'coreti-sidebar-link-active' : '' }}">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M4 5h16v14H4zM8 9h8M8 13h5" />
                        </svg>
                        <span>Backups</span>
                        <span class="coreti-sidebar-chevron">‹</span>
                    </a>


                    <a href="{{ route('cmdb.expirations.index') }}" class="coreti-sidebar-link {{ request()->routeIs('cmdb.expirations.*') ? 'coreti-sidebar-link-active' : '' }}">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M4 5h16v14H4zM8 9h8M8 13h5" />
                        </svg>
                        <span>Vencimentos / Renovações</span>
                        <span class="coreti-sidebar-chevron">‹</span>
                    </a>

                    <a href="{{ route('cmdb.relationships.index') }}" class="coreti-sidebar-link {{ request()->routeIs('cmdb.relationships.*') ? 'coreti-sidebar-link-active' : '' }}">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M4 5h16v14H4zM8 9h8M8 13h5" />
                        </svg>
                        <span>Relacionamentos</span>
                        <span class="coreti-sidebar-chevron">‹</span>
                    </a>

                    <a href="{{ route('cmdb.reports') }}" class="coreti-sidebar-link {{ request()->routeIs('cmdb.reports') ? 'coreti-sidebar-link-active' : '' }}">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M4 5h16v14H4zM8 9h8M8 13h5" />
                        </svg>
                        <span>Relatórios</span>
                        <span class="coreti-sidebar-chevron">‹</span>
                    </a>
                    @endif
                @else
                    <a href="{{ route('cmdb.dashboard') }}" class="coreti-sidebar-link {{ request()->routeIs('cmdb.*') ? 'coreti-sidebar-link-active' : '' }}" style="min-height: 48px;">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M4 6h16v12H4zM7 10h10M7 14h6" />
                        </svg>
                        <span style="white-space: normal; overflow: visible; text-overflow: clip; line-height: 1.15;">CMDB</span>
                        <span class="coreti-sidebar-chevron">‹</span>
                    </a>
                @endif
            @endif

            @if (! $isNetworkDatacenter && ! $isCoordenacao)
            @if ($canUnidades)
            <a href="{{ route('circuits.units') }}" class="coreti-sidebar-link {{ request()->routeIs('circuits.units') || request()->routeIs('circuits.units.create') || request()->routeIs('circuits.units.edit') ? 'coreti-sidebar-link-active' : '' }}">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 21s6-5.4 6-11a6 6 0 1 0-12 0c0 5.6 6 11 6 11Z" />
                    <circle cx="12" cy="10" r="2" stroke-width="1.8" />
                </svg>
                <span>Unidades - Circuitos</span>
                <span class="coreti-sidebar-chevron">‹</span>
            </a>
            @endif

            @if ($canUnidadeDigital)
            <a href="{{ route('unidade-digital') }}" class="coreti-sidebar-link {{ request()->routeIs('unidade-digital*') ? 'coreti-sidebar-link-active' : '' }}">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
                    <rect width="10" height="14" x="7" y="5" rx="1.8" stroke-width="1.8" />
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M11 18h2" />
                </svg>
                <span>Unidade Digital</span>
                <span class="coreti-sidebar-chevron">‹</span>
            </a>
            @endif

            @if ($canBancada)
            <a href="{{ route('bancada-servicos.dashboard') }}" class="coreti-sidebar-link {{ request()->routeIs('bancada-servicos.*') ? 'coreti-sidebar-link-active' : '' }}">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M5 17h14M7 17V8h10v9M9 20h6" />
                </svg>
                <span>Bancada</span>
                <span class="coreti-sidebar-chevron">‹</span>
            </a>
            @endif

            @if ($canPainelAdministrativo)
            <p class="coreti-sidebar-heading">Administração</p>

            <a href="{{ route('administrativo') }}" class="coreti-sidebar-link {{ request()->routeIs('administrativo*') ? 'coreti-sidebar-link-active' : '' }}">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M4 20h16M6 20V8l6-4 6 4v12M9 20v-6h6v6" />
                </svg>
                <span>Administrativo</span>
                <span class="coreti-sidebar-chevron">‹</span>
            </a>
            @endif

            @if ($isAdminRole)
            <p class="coreti-sidebar-heading">Admin</p>

            <a href="{{ route('admin.dashboard') }}" class="coreti-sidebar-link {{ request()->routeIs('admin.dashboard') ? 'coreti-sidebar-link-active' : '' }}">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8ZM23 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75" />
                </svg>
                <span>Usuários</span>
            </a>

            <a href="{{ route('admin.notifications') }}" class="coreti-sidebar-link {{ request()->routeIs('admin.notifications*') ? 'coreti-sidebar-link-active' : '' }}">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M18 8a6 6 0 0 0-12 0c0 7-3 9-3 9h18s-3-2-3-9M13.73 21a2 2 0 0 1-3.46 0" />
                </svg>
                <span>Notificações</span>
            </a>
            @endif

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
