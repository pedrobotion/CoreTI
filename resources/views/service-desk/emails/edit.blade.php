<x-app-layout>
    <div class="mx-auto max-w-3xl">
        <section class="rounded-lg border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <p class="text-sm font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">ServiceDesk</p>
            <h1 class="mt-1 text-2xl font-bold text-slate-950 dark:text-white">Editar e-mail - {{ $scopeLabel }}</h1>

            <form
                method="POST"
                action="{{ route('service-desk.emails.update', [$scope, $emailRecord]) }}"
                class="mt-6 grid gap-4 sm:grid-cols-2"
                x-data="{
                    collaboratorUrl: @js(route('service-desk.emails.lookup.collaborator')),
                    costCenterUrl: @js(route('service-desk.emails.lookup.cost-center', $scope)),
                    cristalinaIiAreaOptions: @js($cristalinaIiAreaOptions),
                    idPessoa: @js(old('id_pessoa', $emailRecord->id_pessoa)),
                    matricula: @js(old('matricula', $emailRecord->matricula)),
                    nome: @js(old('colaborador_nome', $emailRecord->colaborador_nome)),
                    email: @js(old('email', $emailRecord->email)),
                    centroCusto: @js(old('centro_custo', $emailRecord->centro_custo)),
                    unicoop: @js(old('unicoop_sede', $emailRecord->unicoop_sede)),
                    area: @js(old('area_sede', $emailRecord->area_sede)),
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
                @method('PUT')
                <input type="hidden" name="id_pessoa" x-model="idPessoa">
                <input type="hidden" name="centro_custo_manual" :value="manualCostCenter ? 1 : 0">
                <input type="hidden" name="area_sede" x-model="area">

                <div>
                    <label class="text-sm font-medium text-slate-700 dark:text-slate-200">Matricula</label>
                    <input name="matricula" x-model="matricula" @change="lookupCollaborator" @blur="lookupCollaborator" @if($scope !== 'genericos') required @endif class="mt-1 w-full rounded-md border-slate-300 text-sm shadow-sm focus:border-slate-900 focus:ring-slate-900 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-100">
                    <p x-show="collaboratorStatus" x-text="collaboratorStatus" class="mt-1 text-xs text-slate-500 dark:text-slate-400"></p>
                    <x-input-error :messages="$errors->get('matricula')" class="mt-2" />
                </div>

                <div>
                    <label class="text-sm font-medium text-slate-700 dark:text-slate-200">Colaborador</label>
                    <input name="colaborador_nome" x-model="nome" required class="mt-1 w-full rounded-md border-slate-300 text-sm shadow-sm focus:border-slate-900 focus:ring-slate-900 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-100">
                    <x-input-error :messages="$errors->get('colaborador_nome')" class="mt-2" />
                </div>

                <div class="sm:col-span-2">
                    <label class="text-sm font-medium text-slate-700 dark:text-slate-200">E-mail</label>
                    <input type="email" name="email" x-model="email" required class="mt-1 w-full rounded-md border-slate-300 text-sm shadow-sm focus:border-slate-900 focus:ring-slate-900 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-100">
                    <x-input-error :messages="$errors->get('email')" class="mt-2" />
                </div>

                <div>
                    <label class="text-sm font-medium text-slate-700 dark:text-slate-200">{{ $scope === 'sede' ? 'Centro de custo' : 'Unidade' }}</label>
                    <select name="centro_custo" x-model="centroCusto" @change="applyCostCenterFromSelect($event)" class="mt-1 w-full rounded-md border-slate-300 text-sm shadow-sm focus:border-slate-900 focus:ring-slate-900 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-100">
                        <option value="">Selecione</option>
                        @foreach ($costCenterRecords as $costCenter)
                            <option value="{{ $costCenter->name }}" data-unicoop="{{ $costCenter->unicoop }}" data-area="{{ $costCenter->area }}">
                                {{ $costCenter->name }}
                            </option>
                        @endforeach
                    </select>
                    <p x-show="costCenterStatus" x-text="costCenterStatus" class="mt-1 text-xs text-slate-500 dark:text-slate-400"></p>
                </div>

                <div>
                    <label class="text-sm font-medium text-slate-700 dark:text-slate-200">Unicoop</label>
                    <input name="unicoop_sede" x-model="unicoop" readonly class="mt-1 w-full rounded-md border-slate-300 bg-slate-50 text-sm shadow-sm focus:border-slate-900 focus:ring-slate-900 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100">
                </div>

                <div x-show="isCristalinaIi()" x-cloak>
                    <label class="text-sm font-medium text-slate-700 dark:text-slate-200">Area</label>
                    <select x-model="area" @change="lookupCostCenter" class="mt-1 w-full rounded-md border-slate-300 text-sm shadow-sm focus:border-slate-900 focus:ring-slate-900 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-100">
                        <option value="">Selecione</option>
                        @foreach ($cristalinaIiAreaOptions as $areaOption)
                            <option value="{{ $areaOption }}">{{ $areaOption }}</option>
                        @endforeach
                    </select>
                </div>
                <div x-show="!isCristalinaIi()" x-cloak>
                    <label class="text-sm font-medium text-slate-700 dark:text-slate-200">Area</label>
                    <input x-model="area" readonly class="mt-1 w-full rounded-md border-slate-300 bg-slate-50 text-sm shadow-sm focus:border-slate-900 focus:ring-slate-900 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100">
                </div>

                <div>
                    <label class="text-sm font-medium text-slate-700 dark:text-slate-200">Data inclusao</label>
                    <input type="date" name="data_inclusao" value="{{ old('data_inclusao', optional($emailRecord->data_inclusao)->format('Y-m-d')) }}" class="mt-1 w-full rounded-md border-slate-300 text-sm shadow-sm focus:border-slate-900 focus:ring-slate-900 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-100">
                </div>

                <div>
                    <label class="text-sm font-medium text-slate-700 dark:text-slate-200">Data desativacao</label>
                    <input type="date" name="data_desativacao" value="{{ old('data_desativacao', optional($emailRecord->data_desativacao)->format('Y-m-d')) }}" class="mt-1 w-full rounded-md border-slate-300 text-sm shadow-sm focus:border-slate-900 focus:ring-slate-900 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-100">
                </div>

                <div class="sm:col-span-2">
                    <label class="text-sm font-medium text-slate-700 dark:text-slate-200">Observacao</label>
                    <textarea name="observacao" rows="3" class="mt-1 w-full rounded-md border-slate-300 text-sm shadow-sm focus:border-slate-900 focus:ring-slate-900 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-100">{{ old('observacao', $emailRecord->observacao) }}</textarea>
                </div>

                <div class="flex gap-2 sm:col-span-2">
                    <button type="submit" class="inline-flex min-h-10 items-center justify-center rounded-md border border-slate-900 bg-slate-900 px-4 text-sm font-semibold text-white shadow-sm hover:bg-slate-800">
                        Salvar
                    </button>
                    <a href="{{ route("service-desk.emails.{$scope}") }}" class="inline-flex min-h-10 items-center justify-center rounded-md border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-100 dark:hover:bg-slate-800">
                        Voltar
                    </a>
                </div>
            </form>
        </section>
    </div>
</x-app-layout>
