<x-app-layout>
    <div class="mx-auto max-w-7xl space-y-6">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
            <div>
                <p class="text-sm font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">ServiceDesk</p>
                <h1 class="mt-1 text-2xl font-bold text-slate-950 dark:text-white">Gerenciamento de E-mails - {{ $scopeLabel }}</h1>
                <p class="mt-2 max-w-2xl text-sm text-slate-600 dark:text-slate-300">
                    Cadastro local de e-mails com vínculo principal por matrícula do colaborador.
                </p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <a href="{{ route('service-desk.emails.export') }}" class="toolbar-btn inline-flex min-h-10 items-center justify-center rounded-md border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50">
                    Exportar CSV
                </a>
                <button type="button" onclick="openEmailCreateModal()" class="toolbar-btn-primary inline-flex min-h-10 items-center justify-center rounded-md border border-slate-900 bg-slate-900 px-4 text-sm font-semibold text-white shadow-sm hover:bg-slate-800">
                    Adicionar e-mail
                </button>
            </div>
        </div>

        <section class="rounded-lg border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <div class="border-b border-slate-200 p-5 dark:border-slate-800">
                @if (($manualCostCenterEmailsCount ?? 0) > 0)
                    <div class="mb-5 rounded-lg border border-amber-200 bg-amber-50 p-4 dark:border-amber-900/40 dark:bg-amber-950/20">
                        <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                        <div>
                            <p class="text-sm font-semibold uppercase tracking-wide text-amber-800 dark:text-amber-200">E-mails sem centro de custo</p>
                            <h2 class="mt-1 text-lg font-bold text-slate-950 dark:text-white">{{ $manualCostCenterEmailsCount }} registros</h2>
                            <p class="mt-1 text-sm text-slate-600 dark:text-slate-300">
                                    Apenas os e-mails sem centro de custo definido aparecem aqui. Os que já foram preenchidos pela automação ficam fora desta fila.
                            </p>
                        </div>
                        <div class="rounded-md bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm ring-1 ring-amber-200 dark:bg-slate-900 dark:text-slate-200 dark:ring-amber-900/40">
                                Fonte: {{ $manualCostCenterSourceLabel ?? 'Google Workspace Admin' }}
                        </div>
                    </div>

                    <div class="mt-4 overflow-hidden rounded-md border border-amber-200 bg-white dark:border-amber-900/40 dark:bg-slate-950">
                        <div class="max-h-72 overflow-y-auto">
                            <table class="min-w-full text-sm">
                                <thead class="bg-amber-100 text-left text-xs font-semibold uppercase text-amber-900 dark:bg-amber-950/60 dark:text-amber-100">
                                    <tr>
                                        <th class="px-4 py-3">E-mail</th>
                                        <th class="px-4 py-3">Nome</th>
                                        <th class="px-4 py-3">Nome usuário</th>
                                        <th class="px-4 py-3">Centro atual</th>
                                        <th class="px-4 py-3">Status</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-amber-100 dark:divide-amber-900/40">
                                    @forelse($manualCostCenterEmails as $manualEmail)
                                        <tr class="odd:bg-white even:bg-amber-50/60 dark:odd:bg-slate-950 dark:even:bg-slate-900">
                                            <td class="px-4 py-3 text-slate-900 dark:text-slate-100">{{ $manualEmail->email }}</td>
                                            <td class="px-4 py-3 text-slate-700 dark:text-slate-300">{{ $manualEmail->nome ?? '-' }}</td>
                                            <td class="px-4 py-3 text-slate-700 dark:text-slate-300">{{ $manualEmail->nome_usuario ?? '-' }}</td>
                                            <td class="px-4 py-3 text-slate-700 dark:text-slate-300">{{ $manualEmail->centro_custo ?? '-' }}</td>
                                            <td class="px-4 py-3 text-slate-700 dark:text-slate-300">
                                                {{ $manualEmail->mapeamento_status ?? '-' }}
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="5" class="px-4 py-6 text-center text-slate-500 dark:text-slate-400">Nenhum e-mail pendente de definição manual.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                @endif

                <form method="GET" action="{{ route("service-desk.emails.{$scope}") }}" class="toolbar-search-form">
                    <select name="status" class="toolbar-select status-select text-sm shadow-sm focus:border-slate-900 focus:ring-slate-900">
                        <option value="todos" @selected($status === 'todos')>Todos</option>
                        <option value="ativos" @selected($status === 'ativos')>Ativos</option>
                        <option value="inativos" @selected($status === 'inativos')>Inativos</option>
                    </select>
                    <input name="q" value="{{ $search }}" placeholder="Buscar e-mail, matricula, colaborador, centro ou area" class="toolbar-input search-input min-w-0 flex-1 rounded-md border-slate-300 text-sm shadow-sm focus:border-slate-900 focus:ring-slate-900" style="min-width: 300px;">
                    <button type="submit" class="toolbar-btn-primary inline-flex w-auto items-center justify-center rounded-md border border-slate-900 bg-slate-900 px-4 text-sm font-semibold text-white shadow-sm hover:bg-slate-800">Buscar</button>
                    <a href="{{ route("service-desk.emails.{$scope}") }}" class="toolbar-btn inline-flex w-auto items-center justify-center rounded-md border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50">Limpar</a>
                </form>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full min-w-[1180px] text-sm">
                    <thead class="bg-slate-900 text-left text-xs font-semibold uppercase text-white">
                        <tr>
                            <th class="px-4 py-3">E-mail</th>
                            <th class="px-4 py-3">Matricula</th>
                            <th class="px-4 py-3">Colaborador</th>
                            <th class="px-4 py-3">Centro de Custo</th>
                            <th class="px-4 py-3">Unicoop</th>
                            <th class="px-4 py-3">Area</th>
                            <th class="px-4 py-3">Data Inclusao</th>
                            <th class="px-4 py-3">Data Desativacao</th>
                            <th class="px-4 py-3">Status</th>
                            <th class="px-4 py-3">Observacao</th>
                            <th class="sticky right-0 z-10 w-44 bg-slate-900 px-4 py-3">Acoes</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200 dark:divide-slate-800">
                        @forelse ($emails as $email)
                            <tr class="group odd:bg-slate-50 even:bg-white dark:odd:bg-slate-950 dark:even:bg-slate-900">
                                <td class="px-4 py-3 text-slate-900 dark:text-slate-100">{{ $email->email }}</td>
                                <td class="px-4 py-3 text-slate-700 dark:text-slate-300">{{ $email->matricula }}</td>
                                <td class="px-4 py-3 text-slate-700 dark:text-slate-300">{{ $email->colaborador_nome }}</td>
                                <td class="px-4 py-3 text-slate-700 dark:text-slate-300">{{ $email->centro_custo ?? '-' }}</td>
                                <td class="px-4 py-3 text-slate-700 dark:text-slate-300">{{ $email->unicoop_sede ?? '-' }}</td>
                                <td class="px-4 py-3 text-slate-700 dark:text-slate-300">{{ $email->area_sede ?? '-' }}</td>
                                <td class="px-4 py-3 text-slate-700 dark:text-slate-300">{{ optional($email->data_inclusao)->format('d/m/Y') ?? '-' }}</td>
                                <td class="px-4 py-3 text-slate-700 dark:text-slate-300">{{ optional($email->data_desativacao)->format('d/m/Y') ?? '-' }}</td>
                                <td class="px-4 py-3 text-slate-700 dark:text-slate-300">{{ $email->ativo ? 'Ativo' : 'Inativo' }}</td>
                                <td class="px-4 py-3 text-slate-700 dark:text-slate-300">{{ $email->observacao ?? '-' }}</td>
                                <td class="sticky right-0 bg-white px-4 py-3 shadow-[-8px_0_12px_-12px_rgba(15,23,42,0.65)] group-odd:bg-slate-50 group-even:bg-white dark:bg-slate-900 dark:group-odd:bg-slate-950 dark:group-even:bg-slate-900">
                                    <div class="flex min-w-36 items-center gap-2 whitespace-nowrap">
                                        <button type="button" onclick="openModal('email-edit-modal-{{ $email->id }}')" class="inline-flex h-8 w-8 items-center justify-center rounded-md border border-slate-300 text-blue-600 hover:bg-slate-50 hover:text-blue-800" title="Editar" aria-label="Editar">
                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" class="h-4 w-4" aria-hidden="true">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M4 20h4l10-10-4-4L4 16v4Z" />
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="m12 6 4 4" />
                                            </svg>
                                        </button>
                                        <form method="POST" action="{{ route('service-desk.emails.toggle', [$scope, $email]) }}">
                                            @csrf
                                            @method('PATCH')
                                            <button type="submit" class="inline-flex h-8 w-8 items-center justify-center rounded-md border border-slate-300 {{ $email->ativo ? 'text-emerald-700 hover:bg-slate-50 hover:text-emerald-900' : 'text-amber-700 hover:bg-slate-50 hover:text-amber-900' }}" title="{{ $email->ativo ? 'Desativar' : 'Ativar' }}" aria-label="{{ $email->ativo ? 'Desativar' : 'Ativar' }}">
                                                @if($email->ativo)
                                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" class="h-4 w-4" aria-hidden="true">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 3v9" />
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M7 5.8a8 8 0 1 0 10 0" />
                                                    </svg>
                                                @else
                                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" class="h-4 w-4" aria-hidden="true">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 3v9" />
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M7 5.8a8 8 0 1 0 10 0" />
                                                    </svg>
                                                @endif
                                            </button>
                                        </form>
                                        <form method="POST" action="{{ route('service-desk.emails.destroy', [$scope, $email]) }}" data-confirm-message="Deseja remover este e-mail?">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="inline-flex h-8 w-8 items-center justify-center rounded-md border border-slate-300 text-red-600 hover:bg-slate-50 hover:text-red-800" title="Excluir" aria-label="Excluir">
                                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" class="h-4 w-4" aria-hidden="true">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M4 7h16" />
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M10 11v6M14 11v6" />
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M6 7l1 13h10l1-13M9 7V5h6v2" />
                                                </svg>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="11" class="px-5 py-8 text-center text-slate-500 dark:text-slate-400">Nenhum e-mail cadastrado.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="flex flex-col gap-3 border-t border-slate-200 px-5 py-4 sm:flex-row sm:items-center sm:justify-between dark:border-slate-800">
                <form method="GET" action="{{ route("service-desk.emails.{$scope}") }}" class="flex items-center gap-2 text-sm text-slate-600 dark:text-slate-300">
                    <span>E-mails por pagina:</span>
                    <select name="per_page" onchange="this.form.submit()" class="toolbar-select text-sm shadow-sm">
                        <option value="10" @selected($perPage === 10)>10</option>
                        <option value="25" @selected($perPage === 25)>25</option>
                        <option value="50" @selected($perPage === 50)>50</option>
                    </select>
                </form>
                {{ $emails->links() }}
            </div>
        </section>
    </div>

    @foreach($emails as $email)
        <div id="email-edit-modal-{{ $email->id }}" class="fixed inset-0 z-50 hidden items-center justify-center bg-slate-900/60 px-4">
            <div class="w-full max-w-6xl rounded-lg border border-slate-200 bg-white p-5 shadow-xl">
                <div class="mb-4 flex items-center justify-between">
                    <h2 class="text-lg font-semibold text-slate-900">Editar e-mail</h2>
                    <button type="button" onclick="closeModal('email-edit-modal-{{ $email->id }}', true)" class="rounded-md border border-slate-200 px-2 py-1 text-slate-600 hover:bg-slate-100 hover:text-slate-900">Fechar</button>
                </div>

                <form
                    method="POST"
                    action="{{ route('service-desk.emails.update', [$scope, $email]) }}"
                    class="mt-3 grid gap-3 sm:grid-cols-2 lg:grid-cols-3"
                x-data="{
                    collaboratorUrl: @js(route('service-desk.emails.lookup.collaborator')),
                    costCenterUrl: @js(route('service-desk.emails.lookup.cost-center', $scope)),
                    cristalinaIiAreaOptions: @js($cristalinaIiAreaOptions),
                    idPessoa: @js($email->id_pessoa),
                    matricula: @js($email->matricula),
                    nome: @js($email->colaborador_nome),
                    emailValue: @js($email->email),
                        centroCusto: @js($email->centro_custo),
                        unicoop: @js($email->unicoop_sede),
                        area: @js($email->area_sede),
                    manualCostCenter: false,
                    collaboratorStatus: '',
                    costCenterStatus: '',
                    isCristalinaIi(value = null) {
                        return String(value ?? this.centroCusto ?? '').trim().toLowerCase() === 'cristalina ii';
                    },
                    applyCostCenterFromSelect(event) {
                        const selected = event?.target?.options?.[event.target.selectedIndex];
                        if (!selected) return;
                        this.centroCusto = selected.value || '';
                        this.unicoop = selected.getAttribute('data-unicoop') || '';
                        this.area = selected.getAttribute('data-area') || '';
                        this.manualCostCenter = true;
                        if (this.isCristalinaIi()) {
                            this.area = '';
                            this.costCenterStatus = 'Selecione uma area para Cristalina II.';
                            return;
                        }
                        this.lookupCostCenter();
                    },
                        async lookupCollaborator() {
                            const value = (this.matricula || '').trim();
                            if (!value) return;
                            this.collaboratorStatus = 'Buscando matricula...';
                            try {
                                const response = await fetch(`${this.collaboratorUrl}?matricula=${encodeURIComponent(value)}`, { headers: { Accept: 'application/json' } });
                                const payload = await response.json();
                                if (!response.ok || !payload.found) {
                                    this.collaboratorStatus = payload.message || 'Matricula nao encontrada.';
                                    return;
                                }
                                const collaborator = payload.collaborator;
                                this.idPessoa = collaborator.id_pessoa || '';
                                this.matricula = collaborator.matricula || value;
                                this.nome = collaborator.nome || '';
                                this.emailValue = collaborator.email || this.emailValue;
                                this.centroCusto = collaborator.centro_custo || this.centroCusto;
                                this.unicoop = collaborator.unicoop_sede || this.unicoop;
                                this.area = collaborator.area_sede || this.area;
                                this.manualCostCenter = false;
                                this.collaboratorStatus = 'Colaborador preenchido.';
                                if (this.centroCusto && (!this.unicoop || !this.area)) await this.lookupCostCenter();
                            } catch (error) {
                                this.collaboratorStatus = 'Nao foi possivel consultar a matricula.';
                            }
                        },
                        async lookupCostCenter() {
                            const value = (this.centroCusto || '').trim();
                            if (!value) return;
                            this.costCenterStatus = 'Buscando centro de custo...';
                            try {
                                const params = new URLSearchParams({ centro_custo: value });
                                if (this.isCristalinaIi() && this.area) {
                                    params.set('area_sede', this.area);
                                }
                                const response = await fetch(`${this.costCenterUrl}?${params.toString()}`, { headers: { Accept: 'application/json' } });
                                const payload = await response.json();
                                if (!response.ok || !payload.found) {
                                    this.costCenterStatus = payload.message || 'Centro de custo nao encontrado.';
                                    return;
                                }
                                const costCenter = payload.cost_center;
                                this.centroCusto = costCenter.centro_custo || value;
                                this.unicoop = costCenter.unicoop_sede || '';
                                this.area = costCenter.area_sede || '';
                                this.costCenterStatus = 'Unicoop e area preenchidos.';
                            } catch (error) {
                                this.costCenterStatus = 'Nao foi possivel consultar o centro de custo.';
                            }
                        },
                    }"
                >
                    @csrf
                    @method('PUT')
                    <input type="hidden" name="id_pessoa" x-model="idPessoa">
                    <input type="hidden" name="centro_custo_manual" :value="manualCostCenter ? 1 : 0">

                    <div>
                        <label class="text-sm font-medium text-slate-700">Matricula</label>
                        <input name="matricula" x-model="matricula" @change="lookupCollaborator" @blur="lookupCollaborator" class="mt-1 w-full rounded-md border-slate-300 text-sm shadow-sm focus:border-slate-900 focus:ring-slate-900">
                    </div>
                    <div>
                        <label class="text-sm font-medium text-slate-700">Colaborador</label>
                        <input name="colaborador_nome" x-model="nome" required class="mt-1 w-full rounded-md border-slate-300 text-sm shadow-sm focus:border-slate-900 focus:ring-slate-900">
                    </div>
                    <div>
                        <label class="text-sm font-medium text-slate-700">E-mail</label>
                        <input type="email" name="email" x-model="emailValue" required class="mt-1 w-full rounded-md border-slate-300 text-sm shadow-sm focus:border-slate-900 focus:ring-slate-900">
                    </div>
                    <div>
                        <label class="text-sm font-medium text-slate-700">{{ $scope === 'sede' ? 'Centro de custo' : 'Unidade' }}</label>
                        <select name="centro_custo" x-model="centroCusto" @change="applyCostCenterFromSelect($event)" class="mt-1 w-full rounded-md border-slate-300 text-sm shadow-sm focus:border-slate-900 focus:ring-slate-900">
                            <option value="">Selecione</option>
                            @foreach($costCenterRecords as $costCenter)
                                <option value="{{ $costCenter->name }}" data-unicoop="{{ $costCenter->unicoop }}" data-area="{{ $costCenter->area }}">
                                    {{ $costCenter->name }}
                                </option>
                            @endforeach
                        </select>
                        <p x-show="costCenterStatus" x-text="costCenterStatus" class="mt-1 text-xs text-slate-500"></p>
                    </div>
                    <div>
                        <label class="text-sm font-medium text-slate-700">Unicoop</label>
                        <input name="unicoop_sede" x-model="unicoop" readonly class="mt-1 w-full rounded-md border-slate-300 bg-slate-50 text-sm shadow-sm">
                    </div>
                    <div>
                        <label class="text-sm font-medium text-slate-700">Area</label>
                        <input name="area_sede" x-model="area" readonly class="mt-1 w-full rounded-md border-slate-300 bg-slate-50 text-sm shadow-sm">
                    </div>
                    <div>
                        <label class="text-sm font-medium text-slate-700">Data inclusao</label>
                        <input type="date" name="data_inclusao" value="{{ optional($email->data_inclusao)->format('Y-m-d') }}" class="mt-1 w-full rounded-md border-slate-300 text-sm shadow-sm">
                    </div>
                    <div>
                        <label class="text-sm font-medium text-slate-700">Data desativacao</label>
                        <input type="date" name="data_desativacao" value="{{ optional($email->data_desativacao)->format('Y-m-d') }}" class="mt-1 w-full rounded-md border-slate-300 text-sm shadow-sm">
                    </div>
                    <div class="sm:col-span-2 lg:col-span-1">
                        <label class="text-sm font-medium text-slate-700">Observacao</label>
                        <textarea name="observacao" rows="2" class="mt-1 w-full rounded-md border-slate-300 text-sm shadow-sm">{{ $email->observacao }}</textarea>
                    </div>
                    <div class="sm:col-span-2 lg:col-span-3 flex items-end justify-end gap-2">
                        <button type="button" onclick="closeModal('email-edit-modal-{{ $email->id }}', true)" class="toolbar-btn inline-flex min-h-10 items-center justify-center rounded-md border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50">Cancelar</button>
                        <button type="submit" class="inline-flex min-h-10 items-center justify-center rounded-md border border-slate-900 bg-slate-900 px-4 text-sm font-semibold text-white shadow-sm hover:bg-slate-800">
                            Salvar
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endforeach

    <div id="email-create-modal" class="fixed inset-0 z-50 hidden items-center justify-center bg-slate-900/60 px-4">
        <div class="form-modal-shell max-w-6xl rounded-lg border border-slate-200 bg-white p-5 shadow-xl">
            <div class="form-modal-header">
                <h2 class="form-modal-title">Adicionar e-mail</h2>
                <button type="button" onclick="closeEmailCreateModal(true)" class="form-modal-close">Fechar</button>
            </div>

            <form
                method="POST"
                action="{{ route('service-desk.emails.store', $scope) }}"
                class="mt-3 grid gap-3 sm:grid-cols-2 lg:grid-cols-3"
                x-data="{
                    collaboratorUrl: @js(route('service-desk.emails.lookup.collaborator')),
                    costCenterUrl: @js(route('service-desk.emails.lookup.cost-center', $scope)),
                    idPessoa: @js(old('id_pessoa', $filters['selected_id_pessoa'] ?? '')),
                    matricula: @js(old('matricula', $filters['selected_matricula'] ?? '')),
                    nome: @js(old('colaborador_nome', $filters['selected_nome'] ?? '')),
                    email: @js(old('email', '')),
                    centroCusto: @js(old('centro_custo', '')),
                    unicoop: @js(old('unicoop_sede', '')),
                    area: @js(old('area_sede', '')),
                    manualCostCenter: false,
                    collaboratorStatus: '',
                    costCenterStatus: '',
                    isCristalinaIi(value = null) {
                        return String(value ?? this.centroCusto ?? '').trim().toLowerCase() === 'cristalina ii';
                    },
                    applyCostCenterFromSelect(event) {
                        const selected = event?.target?.options?.[event.target.selectedIndex];
                        if (!selected) return;
                        this.centroCusto = selected.value || '';
                        this.unicoop = selected.getAttribute('data-unicoop') || '';
                        this.area = selected.getAttribute('data-area') || '';
                        this.manualCostCenter = !!this.centroCusto;
                        if (this.isCristalinaIi()) {
                            this.area = '';
                            this.costCenterStatus = 'Selecione uma area para Cristalina II.';
                            return;
                        }
                        this.lookupCostCenter();
                    },
                    async lookupCollaborator() {
                        const value = this.matricula.trim();
                        if (!value) return;
                        this.collaboratorStatus = 'Buscando matricula...';
                        try {
                            const response = await fetch(`${this.collaboratorUrl}?matricula=${encodeURIComponent(value)}`, { headers: { Accept: 'application/json' } });
                            const payload = await response.json();
                            if (!response.ok || !payload.found) {
                                this.collaboratorStatus = payload.message || 'Matricula nao encontrada.';
                                return;
                            }
                            const collaborator = payload.collaborator;
                            this.idPessoa = collaborator.id_pessoa || '';
                            this.matricula = collaborator.matricula || value;
                            this.nome = collaborator.nome || '';
                            this.email = collaborator.email || this.email;
                            this.centroCusto = collaborator.centro_custo || this.centroCusto;
                            this.unicoop = collaborator.unicoop_sede || this.unicoop;
                            this.area = collaborator.area_sede || this.area;
                            this.manualCostCenter = false;
                            this.collaboratorStatus = 'Colaborador preenchido.';
                            if (this.centroCusto && (!this.unicoop || !this.area)) {
                                if (this.isCristalinaIi() && !this.area) {
                                    this.costCenterStatus = 'Selecione uma area para Cristalina II.';
                                } else {
                                    await this.lookupCostCenter();
                                }
                            }
                        } catch (error) {
                            this.collaboratorStatus = 'Nao foi possivel consultar a matricula.';
                        }
                    },
                    async lookupCostCenter() {
                        const value = this.centroCusto.trim();
                        if (!value) return;
                        this.costCenterStatus = 'Buscando centro de custo...';
                        try {
                            const params = new URLSearchParams({ centro_custo: value });
                            if (this.isCristalinaIi() && this.area) {
                                params.set('area_sede', this.area);
                            }
                            const response = await fetch(`${this.costCenterUrl}?${params.toString()}`, { headers: { Accept: 'application/json' } });
                            const payload = await response.json();
                            if (!response.ok || !payload.found) {
                                this.costCenterStatus = payload.message || 'Centro de custo nao encontrado.';
                                return;
                            }
                            const costCenter = payload.cost_center;
                            this.centroCusto = costCenter.centro_custo || value;
                            this.unicoop = costCenter.unicoop_sede || '';
                            this.area = costCenter.area_sede || '';
                            this.manualCostCenter = true;
                            this.costCenterStatus = 'Unicoop e area preenchidos.';
                        } catch (error) {
                            this.costCenterStatus = 'Nao foi possivel consultar o centro de custo.';
                        }
                    },
                }"
            >
                @csrf
                <input type="hidden" name="id_pessoa" x-model="idPessoa">
                <input type="hidden" name="centro_custo_manual" :value="manualCostCenter ? 1 : 0">
                <input type="hidden" name="area_sede" x-model="area">

                <div>
                    <label class="text-sm font-medium text-slate-700">Matricula</label>
                    <input name="matricula" x-model="matricula" @change="lookupCollaborator" @blur="lookupCollaborator" @if($scope !== 'genericos') required @endif class="mt-1 w-full rounded-md border-slate-300 text-sm shadow-sm focus:border-slate-900 focus:ring-slate-900">
                    <p x-show="collaboratorStatus" x-text="collaboratorStatus" class="mt-1 text-xs text-slate-500"></p>
                    <x-input-error :messages="$errors->get('matricula')" class="mt-2" />
                </div>

                <div>
                    <label class="text-sm font-medium text-slate-700">Colaborador</label>
                    <input name="colaborador_nome" x-model="nome" required class="mt-1 w-full rounded-md border-slate-300 text-sm shadow-sm focus:border-slate-900 focus:ring-slate-900">
                    <x-input-error :messages="$errors->get('colaborador_nome')" class="mt-2" />
                </div>

                <div>
                    <label class="text-sm font-medium text-slate-700">E-mail</label>
                    <input type="email" name="email" x-model="email" required class="mt-1 w-full rounded-md border-slate-300 text-sm shadow-sm focus:border-slate-900 focus:ring-slate-900">
                    <x-input-error :messages="$errors->get('email')" class="mt-2" />
                </div>

                <div>
                    <label class="text-sm font-medium text-slate-700">{{ $scope === 'sede' ? 'Centro de custo' : 'Unidade' }}</label>
                    <select name="centro_custo" x-model="centroCusto" @change="applyCostCenterFromSelect($event)" class="mt-1 w-full rounded-md border-slate-300 text-sm shadow-sm focus:border-slate-900 focus:ring-slate-900">
                        <option value="">Selecione</option>
                        @foreach ($costCenterRecords as $costCenter)
                            <option value="{{ $costCenter->name }}" data-unicoop="{{ $costCenter->unicoop }}" data-area="{{ $costCenter->area }}">
                                {{ $costCenter->name }}
                            </option>
                        @endforeach
                    </select>
                    <p x-show="costCenterStatus" x-text="costCenterStatus" class="mt-1 text-xs text-slate-500"></p>
                </div>

                <div>
                    <label class="text-sm font-medium text-slate-700">Unicoop</label>
                    <input name="unicoop_sede" x-model="unicoop" readonly class="mt-1 w-full rounded-md border-slate-300 bg-slate-50 text-sm shadow-sm focus:border-slate-900 focus:ring-slate-900">
                </div>

                <div x-show="isCristalinaIi()" x-cloak>
                    <label class="text-sm font-medium text-slate-700">Area</label>
                    <select x-model="area" @change="lookupCostCenter" class="mt-1 w-full rounded-md border-slate-300 text-sm shadow-sm focus:border-slate-900 focus:ring-slate-900">
                        <option value="">Selecione</option>
                        @foreach ($cristalinaIiAreaOptions as $areaOption)
                            <option value="{{ $areaOption }}">{{ $areaOption }}</option>
                        @endforeach
                    </select>
                </div>
                <div x-show="!isCristalinaIi()" x-cloak>
                    <label class="text-sm font-medium text-slate-700">Area</label>
                    <input x-model="area" readonly class="mt-1 w-full rounded-md border-slate-300 bg-slate-50 text-sm shadow-sm focus:border-slate-900 focus:ring-slate-900">
                </div>

                <div>
                    <label class="text-sm font-medium text-slate-700">Data inclusao</label>
                    <input type="date" name="data_inclusao" value="{{ old('data_inclusao', now()->toDateString()) }}" class="mt-1 w-full rounded-md border-slate-300 text-sm shadow-sm focus:border-slate-900 focus:ring-slate-900">
                </div>

                <div class="sm:col-span-2">
                    <label class="text-sm font-medium text-slate-700">Observacao</label>
                    <textarea name="observacao" rows="2" class="mt-1 w-full rounded-md border-slate-300 text-sm shadow-sm focus:border-slate-900 focus:ring-slate-900">{{ old('observacao') }}</textarea>
                </div>

                <div class="flex items-end justify-end gap-2">
                    <button type="button" onclick="closeEmailCreateModal(true)" class="toolbar-btn inline-flex min-h-10 items-center justify-center rounded-md border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50">Cancelar</button>
                    <button type="submit" class="inline-flex min-h-10 items-center justify-center rounded-md border border-slate-900 bg-slate-900 px-4 text-sm font-semibold text-white shadow-sm hover:bg-slate-800">
                        Adicionar e-mail
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openEmailCreateModal() {
            const modal = document.getElementById('email-create-modal');
            if (!modal) return;
            modal.classList.remove('hidden');
            modal.classList.add('flex');
        }

        function closeEmailCreateModal(clear = false) {
            const modal = document.getElementById('email-create-modal');
            if (!modal) return;
            if (clear) {
                resetModalForm(modal);
            }
            modal.classList.remove('flex');
            modal.classList.add('hidden');
        }

        function openModal(id) {
            const modal = document.getElementById(id);
            if (!modal) return;
            modal.classList.remove('hidden');
            modal.classList.add('flex');
        }

        function closeModal(id, clear = false) {
            const modal = document.getElementById(id);
            if (!modal) return;
            if (clear) {
                resetModalForm(modal);
            }
            modal.classList.remove('flex');
            modal.classList.add('hidden');
        }

        function resetModalForm(modal) {
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

        @if($errors->has('matricula') || $errors->has('colaborador_nome') || $errors->has('email'))
            document.addEventListener('DOMContentLoaded', openEmailCreateModal);
        @endif
    </script>
</x-app-layout>
