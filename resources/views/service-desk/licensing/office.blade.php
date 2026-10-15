<x-app-layout>
    <div class="mx-auto max-w-7xl space-y-6">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
            <div>
                <p class="text-sm font-semibold uppercase tracking-wide text-slate-500">Licenciamento</p>
                <h1 class="mt-1 text-2xl font-bold text-slate-950">Office</h1>
                <p class="mt-2 max-w-2xl text-sm text-slate-600">
                    Cadastro local de licenças com preenchimento automático por matrícula do colaborador.
                </p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <a href="{{ route('administrativo.licensing.office.export', request()->query()) }}" class="toolbar-btn inline-flex min-h-10 items-center justify-center rounded-md border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50">
                    Exportar CSV por licença
                </a>
                @if($canManage)
                    <form method="POST" action="{{ route('administrativo.licensing.office.sync-microsoft') }}" data-confirm-message="Consultar agora todos os usuários licenciados no Microsoft 365?" x-data="{ loading: false }" @submit="loading = true">
                        @csrf
                        <button type="submit" class="toolbar-btn inline-flex min-h-10 items-center justify-center gap-2 rounded-md border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50" :disabled="loading">
                            <svg x-show="loading" x-cloak class="h-4 w-4 animate-spin text-slate-500" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                <circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="3" stroke-linecap="round" opacity="0.25"></circle>
                                <path d="M21 12a9 9 0 0 0-9-9" stroke="currentColor" stroke-width="3" stroke-linecap="round"></path>
                            </svg>
                            <span x-show="!loading">Sincronizar Microsoft 365</span>
                            <span x-show="loading" x-cloak>Sincronizando...</span>
                        </button>
                    </form>
                    <form method="POST" action="{{ route('administrativo.licensing.office.import-matriculas') }}" x-data="{ loading: false }" @submit="loading = true">
                        @csrf
                        <button type="submit" class="toolbar-btn inline-flex min-h-10 items-center justify-center gap-2 rounded-md border border-blue-200 bg-blue-50 px-4 text-sm font-semibold text-blue-800 hover:bg-blue-100" :disabled="loading">
                            <span x-show="!loading">Atualizar vínculos internos</span><span x-show="loading" x-cloak>Atualizando...</span>
                        </button>
                    </form>
                    <a href="{{ route('service-desk.office.change-requests') }}" class="toolbar-btn inline-flex min-h-10 items-center justify-center rounded-md border border-amber-300 bg-amber-50 px-4 text-sm font-semibold text-amber-800 hover:bg-amber-100">Alterações pendentes</a>
                    <button type="button" onclick="openOfficeCreateModal()" class="toolbar-btn-primary inline-flex min-h-10 items-center justify-center rounded-md border border-slate-900 bg-slate-900 px-4 text-sm font-semibold text-white shadow-sm hover:bg-slate-800">
                        Adicionar licença
                    </button>
                @endif
            </div>
        </div>

        <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
            <div class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm"><p class="text-xs uppercase text-slate-500">Total ativos</p><p class="mt-2 text-2xl font-bold">{{ $stats['total'] }}</p></div>
            <div class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm"><p class="text-xs uppercase text-slate-500">Office Apps</p><p class="mt-2 text-2xl font-bold">{{ $stats['office_apps'] }}</p></div>
            <div class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm"><p class="text-xs uppercase text-slate-500">Office Business</p><p class="mt-2 text-2xl font-bold">{{ $stats['office_business'] }}</p></div>
            <div class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm"><p class="text-xs uppercase text-slate-500">Power BI</p><p class="mt-2 text-2xl font-bold">{{ $stats['powerbi'] }}</p></div>
            <div class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm"><p class="text-xs uppercase text-slate-500">Visio</p><p class="mt-2 text-2xl font-bold">{{ $stats['visio'] }}</p></div>
        </section>

        <section class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
            <div class="grid gap-2 sm:grid-cols-2 xl:grid-cols-4">
                @foreach(['sede' => 'Sede', 'unidades' => 'Unidades', 'cerrado' => 'Cerrado', 'genericos' => 'Genéricos'] as $divisionKey => $divisionLabel)
                    <a href="{{ route('service-desk.office', array_merge(request()->query(), ['division' => $divisionKey, 'page' => 1])) }}"
                       class="min-h-[74px] rounded-md border px-4 py-3 transition {{ $division === $divisionKey ? 'border-slate-900 bg-slate-900 text-white' : 'border-slate-200 bg-slate-50 text-slate-700 hover:border-slate-400' }}">
                        <div class="text-xs font-semibold uppercase tracking-wide opacity-75">{{ $divisionLabel }}</div>
                        <div class="mt-1 text-xl font-bold">{{ $divisionStats[$divisionKey] ?? 0 }}</div>
                    </a>
                @endforeach
            </div>
            <form method="GET" class="toolbar-search-form mt-4">
                <select name="division" class="toolbar-select w-[150px] max-w-full text-sm shadow-sm focus:border-slate-900 focus:ring-slate-900">
                    <option value="todos" @selected($division === 'todos')>Todas as divisões</option>
                    <option value="sede" @selected($division === 'sede')>Sede</option>
                    <option value="unidades" @selected($division === 'unidades')>Unidades</option>
                    <option value="cerrado" @selected($division === 'cerrado')>Cerrado</option>
                    <option value="genericos" @selected($division === 'genericos')>Genéricos</option>
                </select>
                <select name="status" class="toolbar-select status-select w-[96px] max-w-full text-sm shadow-sm focus:border-slate-900 focus:ring-slate-900">
                    <option value="todos" @selected($status === 'todos')>Todos</option>
                    <option value="ativo" @selected($status === 'ativo')>Ativos</option>
                    <option value="inativo" @selected($status === 'inativo')>Inativos</option>
                    <option value="sem_unidade" @selected($status === 'sem_unidade')>Sem unidade</option>
                    <option value="matched" @selected($status === 'matched')>Vinculados</option>
                    <option value="manual_assignment" @selected($status === 'manual_assignment')>Ajustados manualmente</option>
                    <option value="not_found" @selected($status === 'not_found')>Não encontrados</option>
                    <option value="ambiguous" @selected($status === 'ambiguous')>Ambíguos</option>
                    <option value="invalid_centro_custo" @selected($status === 'invalid_centro_custo')>CC inválido</option>
                </select>
                <input name="q" value="{{ $search }}" placeholder="Buscar e-mail, matrícula, colaborador, centro ou área" class="toolbar-input search-input min-w-[240px] flex-1 rounded-md border-slate-300 text-sm shadow-sm focus:border-slate-900 focus:ring-slate-900 sm:min-w-[300px]">
                <button class="toolbar-btn-primary inline-flex w-auto items-center justify-center rounded-md border border-slate-900 bg-slate-900 px-4 text-sm font-semibold text-white shadow-sm hover:bg-slate-800">Buscar</button>
                <a href="{{ route('service-desk.office') }}" class="toolbar-btn inline-flex w-auto items-center justify-center rounded-md border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50">Limpar</a>
            </form>
            @if($division === 'cerrado')
                <p class="mt-3 text-xs text-slate-500">A atividade é baseada no último login bem-sucedido retornado pelo Microsoft Graph. Ela indica acesso recente ao Microsoft 365, não o uso individual de um aplicativo.</p>
            @endif
        </section>

        @if($pendingRateioBatch && $pendingRateioRows->isNotEmpty())
            <section class="overflow-hidden rounded-lg border border-amber-200 bg-white shadow-sm">
                <div class="flex flex-col gap-2 border-b border-amber-200 bg-amber-50 p-5 lg:flex-row lg:items-center lg:justify-between">
                    <div>
                        <h2 class="text-base font-semibold text-amber-950">Pendências do rateio Office</h2>
                        <p class="mt-1 text-sm text-amber-800">{{ $pendingRateioRows->count() }} atribuições não entraram no cálculo da competência {{ $pendingRateioBatch->competence }}. Cada linha representa um colaborador e uma licença.</p>
                    </div>
                    <a href="{{ route('administrativo.licensing.office', ['competence' => $pendingRateioBatch->competence, 'pending' => 1]) }}" class="inline-flex min-h-9 items-center justify-center rounded-md border border-amber-300 bg-white px-3 text-sm font-semibold text-amber-900 hover:bg-amber-100">Abrir rateio</a>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[1250px] text-sm">
                        <thead class="bg-slate-50 text-left text-xs font-semibold uppercase text-slate-500">
                            <tr>
                                <th class="px-4 py-3">Colaborador</th>
                                <th class="px-4 py-3">E-mail</th>
                                <th class="px-4 py-3">Licença</th>
                                <th class="px-4 py-3">Unidade</th>
                                <th class="px-4 py-3">Centro de custo</th>
                                <th class="px-4 py-3">Pendência</th>
                                <th class="px-4 py-3">Detalhe</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200">
                            @foreach($pendingRateioRows as $pendingRow)
                                <tr class="hover:bg-amber-50/50">
                                    <td class="px-4 py-3 font-medium text-slate-900">{{ $pendingRow->user_name }}</td>
                                    <td class="px-4 py-3">{{ $pendingRow->user_email }}</td>
                                    <td class="px-4 py-3">{{ $pendingRow->sku_name }}</td>
                                    <td class="px-4 py-3">{{ $pendingRow->unit_name_snapshot ?: 'N/D' }}</td>
                                    <td class="px-4 py-3">{{ $pendingRow->cost_center_code_snapshot ?: 'N/D' }}@if($pendingRow->cost_center_name_snapshot)<div class="text-xs text-slate-500">{{ $pendingRow->cost_center_name_snapshot }}</div>@endif</td>
                                    <td class="px-4 py-3 font-semibold text-amber-800">{{ $pendingRateioLabels[$pendingRow->classification_status] ?? $pendingRow->classification_status }}</td>
                                    <td class="max-w-md px-4 py-3 text-xs text-slate-600">{{ $pendingRow->classification_message ?: '-' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </section>
        @endif

        <section class="overflow-x-auto rounded-lg border border-slate-200 bg-white shadow-sm">
            <table class="w-full min-w-[1200px] text-sm">
                <thead class="bg-slate-900 text-left text-xs font-semibold uppercase text-white">
                    <tr>
                        <th class="px-4 py-3">Nome</th>
                        <th class="px-4 py-3">E-mail</th>
                        <th class="px-4 py-3">Matrícula</th>
                        <th class="px-4 py-3">Centro Custo</th>
                        <th class="px-4 py-3">Nome Centro Custo</th>
                        <th class="px-4 py-3">Cargo</th>
                        <th class="px-4 py-3">Atividade M365</th>
                        <th class="px-4 py-3">Match</th>
                        <th class="px-4 py-3">Licenças</th>
                        <th class="px-4 py-3 text-right whitespace-nowrap">Ações</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200">
                    @forelse ($licenses as $item)
                        <tr>
                            <td class="px-4 py-3">{{ $item->nome }}</td>
                            <td class="px-4 py-3">{{ $item->email }}</td>
                            <td class="px-4 py-3">{{ $item->matricula ?: '-' }}</td>
                            <td class="px-4 py-3">{{ $item->centro_custo ?: '-' }}</td>
                            <td class="px-4 py-3">{{ $item->catalog_cost_center_name ?: $item->centro_custo_nome ?: '-' }}</td>
                            <td class="px-4 py-3">{{ $item->cargo_nome ?: '-' }}</td>
                            <td class="px-4 py-3">
                                @php
                                    $lastSignIn = $item->microsoft_last_sign_in_at ? \Carbon\Carbon::parse($item->microsoft_last_sign_in_at) : null;
                                    $recentSignIn = $lastSignIn && $lastSignIn->greaterThanOrEqualTo(now()->subDays(10));
                                @endphp
                                @if($lastSignIn)
                                    <span class="inline-flex rounded-full px-2 py-1 text-xs font-semibold {{ $recentSignIn ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-100 text-slate-700' }}">
                                        {{ $recentSignIn ? 'Sim' : 'Não' }}
                                    </span>
                                    <div class="mt-1 text-xs text-slate-500">{{ $lastSignIn->format('d/m/Y H:i') }}</div>
                                @else
                                    <span class="text-xs text-slate-500">Não disponível</span>
                                @endif
                            </td>
                            <td class="px-4 py-3">
                                <div>{{ $item->colaborador_match_status ?: '-' }}</div>
                                @if($item->colaborador_match_score)
                                    <div class="text-xs text-slate-500">{{ number_format((float) $item->colaborador_match_score, 2, ',', '.') }}%</div>
                                @endif
                            </td>
                            <td class="px-4 py-3">
                                @php
                                    $skuLabels = collect($item->license_skus ?? [])->map(function ($sku) {
                                        if (is_string($sku)) return $sku;
                                        return $sku['sku_part_number'] ?? $sku['sku_id'] ?? null;
                                    })->filter()->values();
                                @endphp
                                {{ $skuLabels->isNotEmpty() ? $skuLabels->implode(', ') : 'Licença não catalogada' }}
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap">
                                @if($canManage)
                                    @php
                                        $officeEditPayload = [
                                            'id' => $item->id,
                                            'nome' => $item->nome,
                                            'email' => $item->email,
                                            'matricula' => $item->matricula,
                                            'departamento_unidade' => $item->departamento_unidade,
                                            'unicoop_office' => $item->unicoop_office,
                                            'area_office' => $item->area_office,
                                            'organization_unit_id' => $item->organization_unit_id,
                                            'organization_department_id' => $item->organization_department_id,
                                            'organization_cost_center_id' => $item->organization_cost_center_id,
                                            'office_key' => mb_strtolower(trim((string) $item->departamento_unidade)) . '|' . trim((string) $item->unicoop_office) . '|' . trim((string) $item->area_office),
                                            'office_apps' => (bool) $item->office_apps,
                                            'office_business' => (bool) $item->office_business,
                                            'powerbi_pro' => (bool) $item->powerbi_pro,
                                            'powerbi_premium' => (bool) $item->powerbi_premium,
                                            'visio_plan' => (bool) $item->visio_plan,
                                            'update_url' => route('administrativo.licensing.office.update', $item),
                                        ];
                                    @endphp
                                    <div class="flex items-center justify-end gap-2 whitespace-nowrap">
                                        @php
                                            $officeOrganizationPayload = [
                                                'departamento_unidade' => $item->departamento_unidade,
                                                'unicoop_office' => $item->unicoop_office,
                                                'area_office' => $item->area_office,
                                                'organization_unit_id' => $item->organization_unit_id,
                                                'organization_department_id' => $item->organization_department_id,
                                                'organization_cost_center_id' => $item->organization_cost_center_id,
                                                'update_url' => route('administrativo.licensing.office.organization.update', $item),
                                            ];
                                        @endphp
                                        <button
                                            type="button"
                                            class="inline-flex h-8 items-center justify-center rounded-md border border-amber-300 px-2 text-xs font-semibold text-amber-800 hover:bg-amber-50"
                                            onclick='openOfficeOrganizationModal(@json($officeOrganizationPayload))'
                                            title="Ajustar organização"
                                        >Ajustar</button>
                                        <button
                                            type="button"
                                            class="inline-flex h-8 w-8 items-center justify-center rounded-md border border-slate-300 text-blue-600 hover:bg-slate-50 hover:text-blue-800"
                                            onclick='openOfficeEditModal(@json($officeEditPayload))'
                                            title="Editar"
                                            aria-label="Editar"
                                        >
                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" class="h-4 w-4" aria-hidden="true">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M4 20h4l10-10-4-4L4 16v4Z" />
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="m12 6 4 4" />
                                            </svg>
                                        </button>
                                        <form method="POST" action="{{ route('administrativo.licensing.office.toggle', $item) }}" data-confirm-message="Alterar status desta licença?">
                                            @csrf
                                            @method('PATCH')
                                            <button class="inline-flex h-8 w-8 items-center justify-center rounded-md border border-slate-300 {{ $item->ativo ? 'text-emerald-600 hover:bg-slate-50 hover:text-emerald-800' : 'text-orange-600 hover:bg-slate-50 hover:text-orange-800' }}" title="{{ $item->ativo ? 'Desativar' : 'Ativar' }}" aria-label="{{ $item->ativo ? 'Desativar' : 'Ativar' }}">
                                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" class="h-4 w-4" aria-hidden="true">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 3v9" />
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M7 5.8a8 8 0 1 0 10 0" />
                                                </svg>
                                            </button>
                                        </form>
                                    </div>
                                @else
                                    -
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="10" class="px-4 py-6 text-center text-slate-500">Nenhuma licença cadastrada.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </section>

        <div>{{ $licenses->links() }}</div>

    </div>

    @if($canManage)
        <div id="office-edit-modal" class="fixed inset-0 z-50 hidden items-center justify-center bg-slate-900/60 px-4">
            <div class="form-modal-shell max-w-6xl rounded-lg border border-slate-200 bg-white p-5 shadow-xl">
                <div class="form-modal-header">
                    <h2 class="form-modal-title">Editar licença</h2>
                    <button type="button" onclick="closeOfficeEditModal(true)" class="form-modal-close">Fechar</button>
                </div>
                <form id="office-edit-form" method="POST" action="#" class="mt-3 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                    @csrf
                    @method('PUT')
                    <input id="office-edit-matricula" name="matricula" placeholder="Matrícula" class="toolbar-input rounded-md border-slate-300 text-sm">
                    <input id="office-edit-nome" name="nome" required placeholder="Nome" class="toolbar-input rounded-md border-slate-300 text-sm">
                    <input id="office-edit-email" name="email" type="email" required placeholder="E-mail" class="toolbar-input rounded-md border-slate-300 text-sm">
                    <input id="office-organization-unit-edit" type="hidden" name="organization_unit_id">
                    <input id="office-organization-department-edit" type="hidden" name="organization_department_id">
                    <input id="office-organization-cost-center-edit" type="hidden" name="organization_cost_center_id">
                    <select
                        id="office-edit-departamento"
                        name="departamento_unidade"
                        required
                        class="toolbar-input rounded-md border-slate-300 text-sm shadow-sm"
                        onchange="applyOfficeUnitFromSelect(this, 'edit')"
                    >
                        <option value="">Departamento/Unidade</option>
                        @foreach($officeEditOptions as $unitOption)
                            @php
                                $unitLabel = $unitOption->display_name ?? $unitOption->name;
                                if (str_contains(mb_strtolower($unitOption->name), 'cristalina ii')) {
                                    $unitLabel .= ' - ' . $unitOption->unicoop . '.' . $unitOption->area;
                                }
                                if (($unitOption->source ?? null) === 'unidade') {
                                    $unitLabel .= ' (Unidade)';
                                } elseif (($unitOption->source ?? null) === 'departamento') {
                                    $unitLabel .= ' (Departamento)';
                                }
                            @endphp
                            <option
                                value="{{ $unitOption->name }}"
                                data-office-key="{{ $unitOption->office_key }}"
                                data-unicoop="{{ $unitOption->unicoop }}"
                                data-area="{{ $unitOption->area }}"
                                data-organization-unit-id="{{ $unitOption->organization_unit_id }}"
                                data-organization-department-id="{{ $unitOption->organization_department_id }}"
                                data-organization-cost-center-id="{{ $unitOption->organization_cost_center_id }}"
                            >
                                {{ $unitLabel }}
                            </option>
                        @endforeach
                    </select>
                    <input id="office-unicoop-edit" name="unicoop_office" readonly placeholder="Unicoop" class="toolbar-input rounded-md border-slate-300 bg-slate-50 text-sm">
                    <input id="office-area-edit" name="area_office" readonly placeholder="Área" class="toolbar-input rounded-md border-slate-300 bg-slate-50 text-sm">
                    <label class="inline-flex items-center gap-2 text-sm"><input id="office-edit-apps" type="checkbox" name="office_apps" value="1"> Office Apps</label>
                    <label class="inline-flex items-center gap-2 text-sm"><input id="office-edit-business" type="checkbox" name="office_business" value="1"> Office Business</label>
                    <label class="inline-flex items-center gap-2 text-sm"><input id="office-edit-powerbi" type="checkbox" name="powerbi_pro" value="1"> Power BI Pro</label>
                    <label class="inline-flex items-center gap-2 text-sm"><input id="office-edit-powerbi-premium" type="checkbox" name="powerbi_premium" value="1"> Power BI Premium</label>
                    <label class="inline-flex items-center gap-2 text-sm"><input id="office-edit-visio" type="checkbox" name="visio_plan" value="1"> Visio Plan</label>
                    <div class="sm:col-span-2 lg:col-span-3 flex items-end justify-end gap-2">
                        <button type="button" onclick="closeOfficeEditModal(true)" class="toolbar-btn inline-flex items-center justify-center rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700">Cancelar</button>
                        <button class="toolbar-btn-primary inline-flex items-center justify-center rounded-md border border-slate-900 bg-slate-900 px-4 py-2 text-sm font-semibold text-white">Salvar alterações</button>
                    </div>
                </form>
            </div>
        </div>

        <div id="office-organization-modal" class="fixed inset-0 z-50 hidden items-center justify-center bg-slate-900/60 px-4">
            <div class="form-modal-shell max-w-2xl rounded-lg border border-slate-200 bg-white p-5 shadow-xl">
                <div class="form-modal-header">
                    <div>
                        <h2 class="form-modal-title">Ajuste manual de organização</h2>
                        <p class="mt-1 text-xs text-slate-500">Este ajuste será preservado nas próximas sincronizações.</p>
                    </div>
                    <button type="button" onclick="closeOfficeOrganizationModal(true)" class="form-modal-close">Fechar</button>
                </div>
                <form id="office-organization-form" method="POST" action="#" class="mt-4 grid gap-3 sm:grid-cols-2">
                    @csrf
                    @method('PATCH')
                    <input id="office-organization-unit-organization" type="hidden" name="organization_unit_id">
                    <input id="office-organization-department-organization" type="hidden" name="organization_department_id">
                    <input id="office-organization-cost-center-organization" type="hidden" name="organization_cost_center_id">
                    <select id="office-organization-departamento" name="departamento_unidade" required class="toolbar-input rounded-md border-slate-300 text-sm" onchange="applyOfficeUnitFromSelect(this, 'organization')">
                        <option value="">Departamento/Unidade</option>
                        @foreach($officeEditOptions as $unitOption)
                            <option value="{{ $unitOption->name }}" data-office-key="{{ $unitOption->office_key }}" data-unicoop="{{ $unitOption->unicoop }}" data-area="{{ $unitOption->area }}" data-organization-unit-id="{{ $unitOption->organization_unit_id }}" data-organization-department-id="{{ $unitOption->organization_department_id }}" data-organization-cost-center-id="{{ $unitOption->organization_cost_center_id }}">{{ $unitOption->display_name ?? $unitOption->name }}</option>
                        @endforeach
                    </select>
                    <input id="office-unicoop-organization" name="unicoop_office" readonly placeholder="Unicoop" class="toolbar-input rounded-md border-slate-300 bg-slate-50 text-sm">
                    <input id="office-area-organization" name="area_office" readonly placeholder="Área" class="toolbar-input rounded-md border-slate-300 bg-slate-50 text-sm">
                    <div class="sm:col-span-2 flex justify-end gap-2">
                        <button type="button" onclick="closeOfficeOrganizationModal(true)" class="toolbar-btn inline-flex items-center justify-center rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700">Cancelar</button>
                        <button class="toolbar-btn-primary inline-flex items-center justify-center rounded-md border border-slate-900 bg-slate-900 px-4 py-2 text-sm font-semibold text-white">Salvar ajuste</button>
                    </div>
                </form>
            </div>
        </div>

        <div id="office-create-modal" class="fixed inset-0 z-50 hidden items-center justify-center bg-slate-900/60 px-4">
            <div class="form-modal-shell max-w-6xl rounded-lg border border-slate-200 bg-white p-5 shadow-xl">
                <div class="form-modal-header">
                    <h2 class="form-modal-title">Adicionar licença</h2>
                    <button type="button" onclick="closeOfficeCreateModal(true)" class="form-modal-close">Fechar</button>
                </div>
                <form
                    method="POST"
                    action="{{ route('administrativo.licensing.office.store') }}"
                    class="mt-3 grid gap-3 sm:grid-cols-2 lg:grid-cols-3"
                    x-data="{
                        lookupUrl: @js(route('administrativo.licensing.office.lookup.matricula')),
                        matricula: '',
                        nome: '',
                        email: '',
                        departamento: '',
                        unicoop: '',
                        area: '',
                        organizationUnitId: '',
                        organizationDepartmentId: '',
                        organizationCostCenterId: '',
                        status: '',
                        async lookupByMatricula() {
                            const value = (this.matricula || '').trim();
                            if (!value) return;
                            this.status = 'Buscando matrícula...';
                            try {
                                const response = await fetch(`${this.lookupUrl}?matricula=${encodeURIComponent(value)}`, { headers: { Accept: 'application/json' } });
                                const payload = await response.json();
                                if (!response.ok || !payload.found) {
                                    this.status = payload.message || 'Matrícula não encontrada.';
                                    return;
                                }
                                const c = payload.collaborator;
                                this.nome = c.nome || '';
                                this.email = c.email || '';
                                this.departamento = c.departamento_unidade || '';
                                const cristalina = isCristalinaDepartmentLabel(this.departamento);
                                this.unicoop = cristalina ? '16' : (c.unicoop_office || '');
                                this.area = cristalina ? '154' : (c.area_office || '');
                                const depSelect = document.getElementById('office-departamento-create');
                                if (depSelect) {
                                    selectOfficeOptionByMeta(depSelect, {
                                        departamento_unidade: this.departamento,
                                        unicoop_office: this.unicoop,
                                        area_office: this.area,
                                    });
                                    const selected = depSelect.options?.[depSelect.selectedIndex];
                                    this.organizationUnitId = selected?.getAttribute('data-organization-unit-id') || '';
                                    this.organizationDepartmentId = selected?.getAttribute('data-organization-department-id') || '';
                                    this.organizationCostCenterId = selected?.getAttribute('data-organization-cost-center-id') || '';
                                }
                                this.status = 'Dados preenchidos.';
                            } catch (error) {
                                this.status = 'Não foi possível consultar a matrícula.';
                            }
                        },
                        applyOfficeCreateUnitFromSelect(event) {
                            const selected = event?.target?.options?.[event.target.selectedIndex];
                            if (!selected) return;
                            const cristalina = isCristalinaDepartmentLabel(selected.textContent || '');
                            this.unicoop = cristalina ? '16' : (selected.getAttribute('data-unicoop') || '');
                            this.area = cristalina ? '154' : (selected.getAttribute('data-area') || '');
                            this.organizationUnitId = selected.getAttribute('data-organization-unit-id') || '';
                            this.organizationDepartmentId = selected.getAttribute('data-organization-department-id') || '';
                            this.organizationCostCenterId = selected.getAttribute('data-organization-cost-center-id') || '';
                        },
                    }"
                    >
                    @csrf
                    <div>
                        <input name="matricula" x-model="matricula" @change="lookupByMatricula" @blur="lookupByMatricula" required placeholder="Matrícula" class="toolbar-input rounded-md border-slate-300 text-sm">
                        <p x-show="status" x-text="status" class="mt-1 text-xs text-slate-500"></p>
                    </div>
                    <input name="nome" x-model="nome" required readonly placeholder="Nome" class="toolbar-input rounded-md border-slate-300 bg-slate-50 text-sm shadow-sm">
                    <input name="email" x-model="email" required readonly type="email" placeholder="E-mail" class="toolbar-input rounded-md border-slate-300 bg-slate-50 text-sm shadow-sm">
                    <input type="hidden" name="organization_unit_id" x-model="organizationUnitId">
                    <input type="hidden" name="organization_department_id" x-model="organizationDepartmentId">
                    <input type="hidden" name="organization_cost_center_id" x-model="organizationCostCenterId">
                    <select
                        id="office-departamento-create"
                        name="departamento_unidade"
                        x-model="departamento"
                        required
                        @change="applyOfficeCreateUnitFromSelect($event)"
                        class="toolbar-input rounded-md border-slate-300 text-sm shadow-sm"
                    >
                        <option value="">Departamento/Unidade</option>
                        @foreach($officeUnitOptions as $unitOption)
                            @php
                                $unitLabel = $unitOption->name;
                                if (str_contains(mb_strtolower($unitOption->name), 'cristalina ii')) {
                                    $unitLabel .= ' - ' . $unitOption->unicoop . '.' . $unitOption->area;
                                }
                                if (($unitOption->source ?? null) === 'unidade') {
                                    $unitLabel .= ' (Unidade)';
                                } elseif (($unitOption->source ?? null) === 'departamento') {
                                    $unitLabel .= ' (Departamento)';
                                }
                            @endphp
                            <option
                                value="{{ $unitOption->name }}"
                                data-office-key="{{ $unitOption->office_key }}"
                                data-unicoop="{{ $unitOption->unicoop }}"
                                data-area="{{ $unitOption->area }}"
                                data-organization-unit-id="{{ $unitOption->organization_unit_id }}"
                                data-organization-department-id="{{ $unitOption->organization_department_id }}"
                                data-organization-cost-center-id="{{ $unitOption->organization_cost_center_id }}"
                            >
                                {{ $unitLabel }}
                            </option>
                        @endforeach
                    </select>
                    <input name="unicoop_office" x-model="unicoop" readonly placeholder="Unicoop" class="toolbar-input rounded-md border-slate-300 bg-slate-50 text-sm shadow-sm">
                    <input name="area_office" x-model="area" readonly placeholder="Área" class="toolbar-input rounded-md border-slate-300 bg-slate-50 text-sm shadow-sm">
                    <div></div>
                    <label class="inline-flex items-center gap-2 text-sm"><input type="checkbox" name="office_apps" value="1"> Office Apps</label>
                    <label class="inline-flex items-center gap-2 text-sm"><input type="checkbox" name="office_business" value="1"> Office Business</label>
                    <label class="inline-flex items-center gap-2 text-sm"><input type="checkbox" name="powerbi_pro" value="1"> Power BI Pro</label>
                    <label class="inline-flex items-center gap-2 text-sm"><input type="checkbox" name="powerbi_premium" value="1"> Power BI Premium</label>
                    <label class="inline-flex items-center gap-2 text-sm"><input type="checkbox" name="visio_plan" value="1"> Visio Plan</label>
                    <div class="sm:col-span-2 lg:col-span-3 flex items-end justify-end gap-2">
                        <button type="button" onclick="closeOfficeCreateModal(true)" class="toolbar-btn inline-flex items-center justify-center rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700">Cancelar</button>
                        <button class="toolbar-btn-primary inline-flex items-center justify-center rounded-md border border-slate-900 bg-slate-900 px-4 py-2 text-sm font-semibold text-white">Salvar</button>
                    </div>
                </form>
            </div>
        </div>

        <script>
            function normalizeOfficeDepartmentLabel(value) {
                return (value || '')
                    .toString()
                    .trim()
                    .toLowerCase()
                    .normalize('NFD')
                    .replace(/[\u0300-\u036f]/g, '')
                    .replace(/[^a-z0-9]+/g, ' ')
                    .trim();
            }

            function isCristalinaDepartmentLabel(value) {
                return normalizeOfficeDepartmentLabel(value) === 'cristalina';
            }

            function selectOfficeOptionByMeta(selectEl, item) {
                if (!selectEl || !item) return;

                const organizationUnitId = String(item.organization_unit_id || '');
                const organizationDepartmentId = String(item.organization_department_id || '');
                const organizationCostCenterId = String(item.organization_cost_center_id || '');
                const key = `${String(item.departamento_unidade || '').trim().toLowerCase()}|${String(item.unicoop_office || '').trim()}|${String(item.area_office || '').trim()}`;
                const options = Array.from(selectEl.options || []);
                const exactIds = options.find((option) =>
                    (option.getAttribute('data-organization-unit-id') || '') === organizationUnitId
                    && (option.getAttribute('data-organization-department-id') || '') === organizationDepartmentId
                    && (option.getAttribute('data-organization-cost-center-id') || '') === organizationCostCenterId
                );

                if (exactIds && (organizationUnitId || organizationDepartmentId || organizationCostCenterId)) {
                    selectEl.selectedIndex = options.indexOf(exactIds);
                    return;
                }
                const exact = options.find((option) => (option.getAttribute('data-office-key') || '').toLowerCase() === key);

                if (exact) {
                    selectEl.selectedIndex = options.indexOf(exact);
                    return;
                }

                const fallback = options.find((option) => (option.value || '') === (item.departamento_unidade || ''));
                if (fallback) {
                    selectEl.selectedIndex = options.indexOf(fallback);
                }
            }

            function applyOfficeUnitFromSelect(selectEl, officeId) {
                if (!selectEl) return;
                const selected = selectEl.options[selectEl.selectedIndex];
                if (!selected) return;

                const unicoop = selected.getAttribute('data-unicoop') || '';
                const area = selected.getAttribute('data-area') || '';
                const organizationUnitId = selected.getAttribute('data-organization-unit-id') || '';
                const organizationDepartmentId = selected.getAttribute('data-organization-department-id') || '';
                const organizationCostCenterId = selected.getAttribute('data-organization-cost-center-id') || '';
                const forcedUnicoop = isCristalinaDepartmentLabel(selected.textContent || '') ? '16' : unicoop;
                const forcedArea = isCristalinaDepartmentLabel(selected.textContent || '') ? '154' : area;
                const unicoopInput = document.getElementById(`office-unicoop-${officeId}`);
                const areaInput = document.getElementById(`office-area-${officeId}`);
                const unitInput = document.getElementById(`office-organization-unit-${officeId}`);
                const departmentInput = document.getElementById(`office-organization-department-${officeId}`);
                const costCenterInput = document.getElementById(`office-organization-cost-center-${officeId}`);

                if (unicoopInput) {
                    unicoopInput.value = forcedUnicoop;
                }
                if (areaInput) {
                    areaInput.value = forcedArea;
                }
                if (unitInput) {
                    unitInput.value = organizationUnitId;
                }
                if (departmentInput) {
                    departmentInput.value = organizationDepartmentId;
                }
                if (costCenterInput) {
                    costCenterInput.value = organizationCostCenterId;
                }
            }

            function applyOfficeCostCenterFromSelect(selectEl, officeId) {
                if (!selectEl) return;
                const selected = selectEl.options[selectEl.selectedIndex];
                if (!selected) return;

                const unicoopInput = document.getElementById(`office-unicoop-${officeId}`);
                const areaInput = document.getElementById(`office-area-${officeId}`);
                if (unicoopInput) unicoopInput.value = selected.getAttribute('data-unicoop') || '';
                if (areaInput) areaInput.value = selected.getAttribute('data-area') || '';
                const costCenterInput = document.getElementById(`office-organization-cost-center-${officeId}`);
                if (costCenterInput) costCenterInput.value = selected.value || '';
            }

            function openOfficeEditModal(item) {
                const modal = document.getElementById('office-edit-modal');
                const form = document.getElementById('office-edit-form');
                if (!modal || !form || !item) return;

                form.action = item.update_url || '#';
                document.getElementById('office-edit-matricula').value = item.matricula || '';
                document.getElementById('office-edit-nome').value = item.nome || '';
                document.getElementById('office-edit-email').value = item.email || '';
                const cristalina = isCristalinaDepartmentLabel(item.departamento_unidade || '');
                document.getElementById('office-unicoop-edit').value = cristalina ? '16' : (item.unicoop_office || '');
                document.getElementById('office-area-edit').value = cristalina ? '154' : (item.area_office || '');
                document.getElementById('office-organization-unit-edit').value = item.organization_unit_id || '';
                document.getElementById('office-organization-department-edit').value = item.organization_department_id || '';

                const depSelect = document.getElementById('office-edit-departamento');
                if (depSelect) {
                    selectOfficeOptionByMeta(depSelect, item);
                    applyOfficeUnitFromSelect(depSelect, 'edit');
                }

                document.getElementById('office-edit-apps').checked = !!item.office_apps;
                document.getElementById('office-edit-business').checked = !!item.office_business;
                document.getElementById('office-edit-powerbi').checked = !!item.powerbi_pro;
                document.getElementById('office-edit-powerbi-premium').checked = !!item.powerbi_premium;
                document.getElementById('office-edit-visio').checked = !!item.visio_plan;

                modal.classList.remove('hidden');
                modal.classList.add('flex');
            }

            function openOfficeOrganizationModal(item) {
                const modal = document.getElementById('office-organization-modal');
                const form = document.getElementById('office-organization-form');
                const select = document.getElementById('office-organization-departamento');
                if (!modal || !form || !select) return;
                form.action = item.update_url || '#';
                selectOfficeOptionByMeta(select, item);
                document.getElementById('office-unicoop-organization').value = item.unicoop_office || '';
                document.getElementById('office-area-organization').value = item.area_office || '';
                document.getElementById('office-organization-unit-organization').value = item.organization_unit_id || '';
                document.getElementById('office-organization-department-organization').value = item.organization_department_id || '';
                document.getElementById('office-organization-cost-center-organization').value = item.organization_cost_center_id || '';
                applyOfficeUnitFromSelect(select, 'organization');
                modal.classList.remove('hidden');
                modal.classList.add('flex');
            }

            function closeOfficeOrganizationModal(clear = false) {
                const modal = document.getElementById('office-organization-modal');
                if (!modal) return;
                if (clear) document.getElementById('office-organization-form')?.reset();
                modal.classList.remove('flex');
                modal.classList.add('hidden');
            }

            function closeOfficeEditModal(clear = false) {
                const modal = document.getElementById('office-edit-modal');
                if (!modal) return;
                if (clear) {
                    const form = document.getElementById('office-edit-form');
                    if (form) form.reset();
                }
                modal.classList.remove('flex');
                modal.classList.add('hidden');
            }

            function openOfficeCreateModal() {
                const modal = document.getElementById('office-create-modal');
                if (!modal) return;
                modal.classList.remove('hidden');
                modal.classList.add('flex');
            }

            function closeOfficeCreateModal(clear = false) {
                const modal = document.getElementById('office-create-modal');
                if (!modal) return;
                if (clear) {
                    resetOfficeCreateForm(modal);
                }
                modal.classList.remove('flex');
                modal.classList.add('hidden');
            }

            function resetOfficeCreateForm(modal) {
                const form = modal.querySelector('form');
                if (!form) return;
                form.reset();
                form.querySelectorAll('input, textarea, select').forEach((field) => {
                    if (field.type === 'checkbox' || field.type === 'radio') {
                        field.dispatchEvent(new Event('change', { bubbles: true }));
                        return;
                    }
                    field.dispatchEvent(new Event('input', { bubbles: true }));
                    field.dispatchEvent(new Event('change', { bubbles: true }));
                });
                form.querySelectorAll('p[x-show]').forEach((el) => {
                    el.textContent = '';
                });
            }
        </script>
    @endif
</x-app-layout>
