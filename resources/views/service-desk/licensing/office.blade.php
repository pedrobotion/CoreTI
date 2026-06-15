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
            @if($canManage)
                <div class="flex flex-wrap items-center gap-2">
                    <form method="POST" action="{{ route('administrativo.licensing.office.import-matriculas') }}" data-confirm-message="Importar matrículas por correspondência de e-mail no Office?">
                        @csrf
                        <button type="submit" class="toolbar-btn inline-flex min-h-10 items-center justify-center rounded-md border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50">
                            Importar matrículas
                        </button>
                    </form>
                    <button type="button" onclick="openOfficeCreateModal()" class="toolbar-btn-primary inline-flex min-h-10 items-center justify-center rounded-md border border-slate-900 bg-slate-900 px-4 text-sm font-semibold text-white shadow-sm hover:bg-slate-800">
                        Adicionar licença
                    </button>
                </div>
            @endif
        </div>

        <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
            <div class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm"><p class="text-xs uppercase text-slate-500">Total ativos</p><p class="mt-2 text-2xl font-bold">{{ $stats['total'] }}</p></div>
            <div class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm"><p class="text-xs uppercase text-slate-500">Office Apps</p><p class="mt-2 text-2xl font-bold">{{ $stats['office_apps'] }}</p></div>
            <div class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm"><p class="text-xs uppercase text-slate-500">Office Business</p><p class="mt-2 text-2xl font-bold">{{ $stats['office_business'] }}</p></div>
            <div class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm"><p class="text-xs uppercase text-slate-500">Power BI</p><p class="mt-2 text-2xl font-bold">{{ $stats['powerbi'] }}</p></div>
            <div class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm"><p class="text-xs uppercase text-slate-500">Visio</p><p class="mt-2 text-2xl font-bold">{{ $stats['visio'] }}</p></div>
        </section>

        <section class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
            <form method="GET" class="toolbar-search-form">
                <select name="status" class="toolbar-select status-select text-sm shadow-sm focus:border-slate-900 focus:ring-slate-900">
                    <option value="todos" @selected($status === 'todos')>Todos</option>
                    <option value="ativo" @selected($status === 'ativo')>Ativos</option>
                    <option value="inativo" @selected($status === 'inativo')>Inativos</option>
                    <option value="sem_unidade" @selected($status === 'sem_unidade')>Sem unidade</option>
                </select>
                <input name="q" value="{{ $search }}" placeholder="Buscar e-mail, matrícula, colaborador, centro ou área" class="toolbar-input search-input min-w-0 flex-1 rounded-md border-slate-300 text-sm shadow-sm focus:border-slate-900 focus:ring-slate-900" style="min-width: 300px;">
                <button class="toolbar-btn-primary inline-flex w-auto items-center justify-center rounded-md border border-slate-900 bg-slate-900 px-4 text-sm font-semibold text-white shadow-sm hover:bg-slate-800">Buscar</button>
                <a href="{{ route('service-desk.office') }}" class="toolbar-btn inline-flex w-auto items-center justify-center rounded-md border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50">Limpar</a>
            </form>
        </section>

        <section class="overflow-x-auto rounded-lg border border-slate-200 bg-white shadow-sm">
            <table class="w-full min-w-[1200px] text-sm">
                <thead class="bg-slate-900 text-left text-xs font-semibold uppercase text-white">
                    <tr>
                        <th class="px-4 py-3">Nome</th>
                        <th class="px-4 py-3">E-mail</th>
                        <th class="px-4 py-3">Unidade</th>
                        <th class="px-4 py-3">Unicoop</th>
                        <th class="px-4 py-3">Área</th>
                        <th class="px-4 py-3">Licenças</th>
                        <th class="px-4 py-3">Ações</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200">
                    @forelse ($licenses as $item)
                        <tr>
                            <td class="px-4 py-3">{{ $item->nome }}</td>
                            <td class="px-4 py-3">{{ $item->email }}</td>
                            <td class="px-4 py-3">{{ $item->departamento_unidade }}</td>
                            <td class="px-4 py-3">{{ $item->unicoop_office ?: '-' }}</td>
                            <td class="px-4 py-3">{{ $item->area_office ?: '-' }}</td>
                            <td class="px-4 py-3">
                                {{ $item->office_apps ? 'Apps ' : '' }}
                                {{ $item->office_business ? 'Business ' : '' }}
                                {{ $item->powerbi_pro ? 'PBI Pro ' : '' }}
                                {{ $item->powerbi_premium ? 'PBI Premium ' : '' }}
                                {{ $item->visio_plan ? 'Visio' : '' }}
                            </td>
                            <td class="px-4 py-3">
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
                                            'office_apps' => (bool) $item->office_apps,
                                            'office_business' => (bool) $item->office_business,
                                            'powerbi_pro' => (bool) $item->powerbi_pro,
                                            'powerbi_premium' => (bool) $item->powerbi_premium,
                                            'visio_plan' => (bool) $item->visio_plan,
                                            'update_url' => route('administrativo.licensing.office.update', $item),
                                        ];
                                    @endphp
                                    <div class="flex flex-wrap items-center gap-2">
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
                        <tr><td colspan="7" class="px-4 py-6 text-center text-slate-500">Nenhuma licença cadastrada.</td></tr>
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
                    <select
                        id="office-edit-departamento"
                        name="departamento_unidade"
                        required
                        class="toolbar-input rounded-md border-slate-300 text-sm shadow-sm"
                        onchange="applyOfficeUnitFromSelect(this, 'edit')"
                    >
                        <option value="">Departamento/Unidade</option>
                        @foreach($officeEditOptions as $unitOption)
                            <option
                                value="{{ $unitOption->name }}"
                                data-unicoop="{{ $unitOption->unicoop }}"
                                data-area="{{ $unitOption->area }}"
                            >
                                {{ $unitOption->name }}@if(($unitOption->source ?? null) === 'unidade') (Unidade) @elseif(($unitOption->source ?? null) === 'departamento') (Departamento) @endif
                            </option>
                        @endforeach
                    </select>
                    <input id="office-unicoop-edit" name="unicoop_office" readonly placeholder="Unicoop" class="toolbar-input rounded-md border-slate-300 bg-slate-50 text-sm">
                    <input id="office-area-edit" name="area_office" readonly placeholder="Área" class="toolbar-input rounded-md border-slate-300 bg-slate-50 text-sm">
                    <div></div>
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
                                this.unicoop = c.unicoop_office || '';
                                this.area = c.area_office || '';
                                this.status = 'Dados preenchidos.';
                            } catch (error) {
                                this.status = 'Não foi possível consultar a matrícula.';
                            }
                        },
                        applyOfficeCreateUnitFromSelect(event) {
                            const selected = event?.target?.options?.[event.target.selectedIndex];
                            if (!selected) return;
                            this.unicoop = selected.getAttribute('data-unicoop') || '';
                            this.area = selected.getAttribute('data-area') || '';
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
                            <option
                                value="{{ $unitOption->name }}"
                                data-unicoop="{{ $unitOption->unicoop }}"
                                data-area="{{ $unitOption->area }}"
                            >
                                {{ $unitOption->name }}@if(($unitOption->source ?? null) === 'unidade') (Unidade) @elseif(($unitOption->source ?? null) === 'departamento') (Departamento) @endif
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
            function applyOfficeUnitFromSelect(selectEl, officeId) {
                if (!selectEl) return;
                const selected = selectEl.options[selectEl.selectedIndex];
                if (!selected) return;

                const unicoop = selected.getAttribute('data-unicoop') || '';
                const area = selected.getAttribute('data-area') || '';
                const unicoopInput = document.getElementById(`office-unicoop-${officeId}`);
                const areaInput = document.getElementById(`office-area-${officeId}`);

                if (unicoopInput) {
                    unicoopInput.value = unicoop;
                }
                if (areaInput) {
                    areaInput.value = area;
                }
            }

            function openOfficeEditModal(item) {
                const modal = document.getElementById('office-edit-modal');
                const form = document.getElementById('office-edit-form');
                if (!modal || !form || !item) return;

                form.action = item.update_url || '#';
                document.getElementById('office-edit-matricula').value = item.matricula || '';
                document.getElementById('office-edit-nome').value = item.nome || '';
                document.getElementById('office-edit-email').value = item.email || '';
                document.getElementById('office-unicoop-edit').value = item.unicoop_office || '';
                document.getElementById('office-area-edit').value = item.area_office || '';

                const depSelect = document.getElementById('office-edit-departamento');
                if (depSelect) {
                    depSelect.value = item.departamento_unidade || '';
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
