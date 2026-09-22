@php
    $servico = $servico ?? null;
    $isEdit = filled($servico?->id);
    $locationValue = old('local_busca', $servico?->local_nome ? (($servico->local_tipo === 'unidade' ? $servico->local_nome : $servico->local_nome) ?: '') : '');
    $razaoSocialValue = old('razao_social', $servico?->razao_social);
@endphp

<section
    x-data="{
        locations: @js($locationOptions ?? []),
        razaoSocial: @js($razaoSocialValue ?? ''),
        localBusca: @js($locationValue ?? ''),
        localTipo: @js(old('local_tipo', $servico?->local_tipo)),
        localNome: @js(old('local_nome', $servico?->local_nome)),
        organizationUnitId: @js(old('organization_unit_id', $servico?->organization_unit_id)),
        organizationDepartmentId: @js(old('organization_department_id', $servico?->organization_department_id)),
        enderecoInstalacao: @js(old('endereco_instalacao', $servico?->endereco_instalacao)),
        tempFichaPath: '',
        tempFichaName: '',
        extractionLoading: false,
        extractionPreview: null,
        extractionMessage: '',
        extractionWarnings: [],
        showManualFields: false,
        missingOnlyMode: false,
        normalize(value) {
            return (value || '').toString().trim().toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '');
        },
        resolveLocation(value) {
            const needle = (value || '').toString().trim().toLowerCase();
            if (!needle) {
                return;
            }
            const normalized = needle.normalize('NFD').replace(/[\u0300-\u036f]/g, '');
            const match = this.locations.find((location) => {
                const label = (location.label || '').toLowerCase();
                const name = (location.name || '').toLowerCase();
                const search = (location.search || '').toLowerCase();
                return [label, name, search].some((item) => item === needle || item.includes(needle) || needle.includes(item) || item === normalized || item.includes(normalized));
            });
            if (!match) {
                return;
            }
            this.localTipo = match.type || '';
            this.localNome = match.name || '';
            this.organizationUnitId = match.id_unidade || '';
            this.organizationDepartmentId = match.id_departamento || '';
            if (match.endereco) {
                this.enderecoInstalacao = match.endereco;
            }
            this.localBusca = match.label || match.name || value;
        },
        previewEntries() {
            return Object.entries(this.extractionPreview || {}).filter(([key]) => !['contato', 'outros_contatos'].includes(key));
        },
        fieldValue(refName) {
            switch (refName) {
                case 'razao_social':
                    return this.razaoSocial;
                case 'local_busca':
                    return this.localBusca;
                case 'local_tipo':
                    return this.localTipo;
                case 'local_nome':
                    return this.localNome;
                case 'organization_unit_id':
                    return this.organizationUnitId;
                case 'organization_department_id':
                    return this.organizationDepartmentId;
                case 'endereco_instalacao':
                    return this.enderecoInstalacao;
                default: {
                    const input = this.$refs[refName];
                    return input && typeof input.value !== 'undefined' ? input.value : '';
                }
            }
        },
        hasFieldValue(refName) {
            return (this.fieldValue(refName) || '').toString().trim() !== '';
        },
        shouldShowField(refName) {
            return !this.missingOnlyMode || !this.hasFieldValue(refName);
        },
        applyExtraction() {
            const data = this.extractionPreview || {};
            const fields = [
                'contrato',
                'razao_social',
                'velocidade_nominal',
                'rede',
                'mascara',
                'gateway',
                'broadcast',
                'faixa',
                'qtd',
                'estacao_a',
                'endereco',
                'numero',
                'bairro',
                'municipio',
                'uf',
            ];
            fields.forEach((field) => {
                const input = this.$refs[field];
                if (input && Object.prototype.hasOwnProperty.call(data, field)) {
                    const currentValue = (input.value || '').toString().trim();
                    if (currentValue) {
                        return;
                    }

                    const nextValue = data[field] ?? '';
                    input.value = nextValue;
                    input.dispatchEvent(new Event('input', { bubbles: true }));
                    input.dispatchEvent(new Event('change', { bubbles: true }));

                    if (field === 'razao_social') {
                        this.razaoSocial = nextValue;
                    }
                    if (field === 'local_busca' && data[field]) {
                        this.localBusca = data[field];
                    }
                }
            });

            this.extractionMessage = 'Dados extraídos automaticamente. Revise antes de salvar.';
            this.extractionPreview = null;
            this.showManualFields = true;
            this.missingOnlyMode = true;
        },
        async extractFicha() {
            const fileInput = this.$refs.extractFile;
            if (!fileInput || !fileInput.files || !fileInput.files.length) {
                this.extractionMessage = 'Selecione uma ficha PDF antes de extrair.';
                return;
            }

            this.extractionLoading = true;
            this.extractionMessage = '';
            this.extractionWarnings = [];

            try {
                const formData = new FormData();
                formData.append('ficha_pdf', fileInput.files[0]);
                formData.append('_token', @js(csrf_token()));

                const response = await fetch(@js(route('network-datacenter.servicos.extract-ficha')), {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: formData,
                });

                const payload = await response.json();
                if (!response.ok || payload.success === false) {
                    throw new Error(payload.message || 'Não foi possível extrair os dados automaticamente.');
                }

                this.tempFichaPath = payload.temp_path || '';
                this.tempFichaName = payload.temp_name || fileInput.files[0].name;
                const hiddenPath = this.$refs.tempFichaPath;
                const hiddenName = this.$refs.tempFichaName;
                const hiddenResult = this.$refs.iaResultadoJson;
                if (hiddenPath) hiddenPath.value = this.tempFichaPath;
                if (hiddenName) hiddenName.value = this.tempFichaName;
                if (hiddenResult) hiddenResult.value = JSON.stringify(payload.data || {});

                this.extractionPreview = payload.data || {};
                this.extractionWarnings = payload.warnings || [];
                this.extractionMessage = payload.message || 'Dados extraídos com sucesso. Revise antes de salvar.';
            } catch (error) {
                this.extractionMessage = error.message || 'Não foi possível extrair os dados automaticamente.';
            } finally {
                this.extractionLoading = false;
            }
        }
    }"
    x-init="if (localBusca) resolveLocation(localBusca)"
    class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm"
>
    <form
        method="POST"
        action="{{ $isEdit ? route('network-datacenter.servicos.update', $servico) : route('network-datacenter.servicos.store') }}"
        enctype="multipart/form-data"
        class="space-y-6"
    >
        @csrf
        @if($isEdit)
            @method('PUT')
        @endif

        <div class="rounded-lg border border-slate-200 bg-slate-50 p-4">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h3 class="text-sm font-semibold text-slate-900">Importar dados da ficha de ativação</h3>
                    <p class="mt-1 text-xs text-slate-500">Anexe um PDF textual para extrair os campos antes de salvar. O cadastro continua manual se nada for identificado.</p>
                </div>
                <button type="button" x-on:click="extractFicha()" class="inline-flex min-h-10 items-center justify-center rounded-md bg-[#033151] px-4 text-sm font-semibold text-white hover:opacity-90" :disabled="extractionLoading">
                    <span x-show="!extractionLoading">Extrair dados da ficha</span>
                    <span x-show="extractionLoading">Extraindo...</span>
                </button>
            </div>
            <div class="mt-4 grid gap-4 md:grid-cols-2">
                <div>
                    <label class="text-sm font-medium text-slate-700">Selecionar PDF para extração</label>
                    <input x-ref="extractFile" type="file" accept="application/pdf" class="mt-1 block w-full rounded-md border border-slate-300 bg-white text-sm">
                </div>
                <div>
                    <label class="text-sm font-medium text-slate-700">Arquivo temporário</label>
                    <input x-ref="tempFichaPath" type="hidden" name="temp_ficha_path" value="{{ old('temp_ficha_path') }}">
                    <input x-ref="tempFichaName" type="hidden" name="temp_ficha_name" value="{{ old('temp_ficha_name') }}">
                    <input x-ref="iaResultadoJson" type="hidden" name="ia_resultado_json" value="{{ old('ia_resultado_json') }}">
                    <div class="mt-1 rounded-md border border-dashed border-slate-300 bg-white px-3 py-2 text-sm text-slate-600">
                        @{{ tempFichaName || 'Nenhum arquivo temporário carregado.' }}
                    </div>
                </div>
            </div>

            <div x-show="extractionMessage" class="mt-4 rounded-md border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700" x-text="extractionMessage"></div>

            <div x-show="extractionWarnings.length" class="mt-3 rounded-md border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-800">
                <div class="font-semibold">Avisos</div>
                <ul class="mt-1 list-disc pl-5">
                    <template x-for="warning in extractionWarnings" :key="warning">
                        <li x-text="warning"></li>
                    </template>
                </ul>
            </div>

            <div x-show="extractionPreview" class="mt-4 rounded-lg border border-slate-200 bg-white p-4">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <h4 class="text-sm font-semibold text-slate-900">Prévia dos dados extraídos</h4>
                    <div class="flex gap-2">
                        <button type="button" x-on:click="applyExtraction()" class="inline-flex min-h-10 items-center justify-center rounded-md bg-[#033151] px-4 text-sm font-semibold text-white hover:opacity-90">Aplicar no formulário</button>
                        <button type="button" x-on:click="extractionPreview = null" class="inline-flex min-h-10 items-center justify-center rounded-md border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-700 hover:bg-slate-50">Cancelar</button>
                    </div>
                </div>
                <div class="mt-3 grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                    <template x-for="[key, value] of previewEntries()" :key="key">
                        <div class="rounded-md border border-slate-200 bg-slate-50 p-3">
                            <div class="text-xs font-semibold uppercase text-slate-500" x-text="key.replaceAll('_', ' ')"></div>
                            <div class="mt-1 text-sm text-slate-900" x-text="value || '-'"></div>
                        </div>
                    </template>
                </div>
            </div>
        </div>

        <div class="flex flex-wrap items-center justify-between gap-3 rounded-lg border border-dashed border-slate-200 bg-white p-4">
            <div>
                <h3 class="text-sm font-semibold text-slate-900">Cadastro manual</h3>
                <p class="mt-1 text-xs text-slate-500">Use esta opção somente se preferir preencher os campos sem importar a ficha.</p>
            </div>
            <button type="button" x-on:click="showManualFields = !showManualFields; if (showManualFields) { missingOnlyMode = false; }" class="inline-flex min-h-10 items-center justify-center rounded-md border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                <span x-show="!showManualFields">Adicionar manualmente</span>
                <span x-show="showManualFields">Ocultar cadastro manual</span>
            </button>
        </div>

        <div x-show="showManualFields" x-cloak class="space-y-6">
            <div class="grid gap-4 md:grid-cols-2">
                <div x-show="shouldShowField('contrato')" x-cloak>
                    <label class="text-sm font-medium text-slate-700">Contrato</label>
                    <input x-ref="contrato" name="contrato" value="{{ old('contrato', $servico?->contrato) }}" class="mt-1 w-full rounded-md border-slate-300 text-sm">
                </div>
                <div x-show="shouldShowField('razao_social')" x-cloak>
                    <label class="text-sm font-medium text-slate-700">Razão social</label>
                    <input x-ref="razao_social" name="razao_social" x-model="razaoSocial" value="{{ old('razao_social', $servico?->razao_social) }}" class="mt-1 w-full rounded-md border-slate-300 text-sm">
                </div>
                <div x-show="shouldShowField('velocidade_nominal')" x-cloak>
                    <label class="text-sm font-medium text-slate-700">Velocidade nominal</label>
                    <select x-ref="velocidade_nominal" name="velocidade_nominal" class="mt-1 w-full rounded-md border-slate-300 text-sm">
                        <option value="">Selecione</option>
                        @foreach($velocidades as $velocidade)
                            <option value="{{ $velocidade }}" @selected(old('velocidade_nominal', $servico?->velocidade_nominal) === $velocidade)>{{ $velocidade }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="grid gap-4 md:grid-cols-3">
                <div x-show="shouldShowField('rede')" x-cloak>
                    <label class="text-sm font-medium text-slate-700">Rede</label>
                    <input x-ref="rede" name="rede" value="{{ old('rede', $servico?->rede) }}" class="mt-1 w-full rounded-md border-slate-300 text-sm" placeholder="192.168.0.0">
                </div>
                <div x-show="shouldShowField('mascara')" x-cloak>
                    <label class="text-sm font-medium text-slate-700">Máscara</label>
                    <input x-ref="mascara" name="mascara" value="{{ old('mascara', $servico?->mascara) }}" class="mt-1 w-full rounded-md border-slate-300 text-sm" placeholder="255.255.255.0">
                </div>
                <div x-show="shouldShowField('gateway')" x-cloak>
                    <label class="text-sm font-medium text-slate-700">Gateway</label>
                    <input x-ref="gateway" name="gateway" value="{{ old('gateway', $servico?->gateway) }}" class="mt-1 w-full rounded-md border-slate-300 text-sm" placeholder="192.168.0.1">
                </div>
                <div x-show="shouldShowField('broadcast')" x-cloak>
                    <label class="text-sm font-medium text-slate-700">Broadcast</label>
                    <input x-ref="broadcast" name="broadcast" value="{{ old('broadcast', $servico?->broadcast) }}" class="mt-1 w-full rounded-md border-slate-300 text-sm" placeholder="192.168.0.255">
                </div>
                <div x-show="shouldShowField('faixa')" x-cloak>
                    <label class="text-sm font-medium text-slate-700">Faixa</label>
                    <input x-ref="faixa" name="faixa" value="{{ old('faixa', $servico?->faixa) }}" class="mt-1 w-full rounded-md border-slate-300 text-sm" placeholder="192.168.0.1 - 192.168.0.254">
                </div>
                <div x-show="shouldShowField('qtd')" x-cloak>
                    <label class="text-sm font-medium text-slate-700">QTD</label>
                    <input x-ref="qtd" name="qtd" value="{{ old('qtd', $servico?->qtd) }}" class="mt-1 w-full rounded-md border-slate-300 text-sm">
                </div>
            </div>

            <div class="rounded-lg border border-slate-200 bg-slate-50 p-4">
                <h3 class="text-sm font-semibold text-slate-900">Local/Unidade</h3>
                <p class="mt-1 text-xs text-slate-500">Selecione um local conhecido para preencher automaticamente o endereço quando houver cadastro base.</p>
                <div class="mt-3 grid gap-4 md:grid-cols-2">
                    <div class="md:col-span-2">
                        <label class="text-sm font-medium text-slate-700">Local/Unidade</label>
                        <input
                            x-ref="local_busca"
                            x-model="localBusca"
                            x-on:change="resolveLocation($event.target.value)"
                            list="network-datacenter-locations"
                            name="local_busca"
                            placeholder="Digite ou selecione um local"
                            class="mt-1 w-full rounded-md border-slate-300 text-sm"
                        >
                        <datalist id="network-datacenter-locations">
                            @foreach($locationOptions as $location)
                                <option value="{{ $location['label'] }}"></option>
                            @endforeach
                        </datalist>
                    </div>
                    <div>
                        <label class="text-sm font-medium text-slate-700">Endereço de instalação</label>
                        <textarea x-ref="endereco_instalacao" name="endereco_instalacao" x-model="enderecoInstalacao" rows="3" class="mt-1 w-full rounded-md border-slate-300 text-sm">{{ old('endereco_instalacao', $servico?->endereco_instalacao) }}</textarea>
                    </div>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label class="text-sm font-medium text-slate-700">Estação A</label>
                            <input x-ref="estacao_a" name="estacao_a" value="{{ old('estacao_a', $servico?->estacao_a) }}" class="mt-1 w-full rounded-md border-slate-300 text-sm">
                        </div>
                        <div>
                            <label class="text-sm font-medium text-slate-700">UF</label>
                            <input x-ref="uf" name="uf" value="{{ old('uf', $servico?->uf) }}" class="mt-1 w-full rounded-md border-slate-300 text-sm" maxlength="10">
                        </div>
                        <div>
                            <label class="text-sm font-medium text-slate-700">Endereço</label>
                            <input x-ref="endereco" name="endereco" value="{{ old('endereco', $servico?->endereco) }}" class="mt-1 w-full rounded-md border-slate-300 text-sm">
                        </div>
                        <div>
                            <label class="text-sm font-medium text-slate-700">Número</label>
                            <input x-ref="numero" name="numero" value="{{ old('numero', $servico?->numero) }}" class="mt-1 w-full rounded-md border-slate-300 text-sm">
                        </div>
                        <div>
                            <label class="text-sm font-medium text-slate-700">Bairro</label>
                            <input x-ref="bairro" name="bairro" value="{{ old('bairro', $servico?->bairro) }}" class="mt-1 w-full rounded-md border-slate-300 text-sm">
                        </div>
                        <div>
                            <label class="text-sm font-medium text-slate-700">Município</label>
                            <input x-ref="municipio" name="municipio" value="{{ old('municipio', $servico?->municipio) }}" class="mt-1 w-full rounded-md border-slate-300 text-sm">
                        </div>
                    </div>
                    <input type="hidden" name="local_tipo" x-model="localTipo" value="{{ old('local_tipo', $servico?->local_tipo) }}">
                    <input type="hidden" name="local_nome" x-model="localNome" value="{{ old('local_nome', $servico?->local_nome) }}">
                    <input type="hidden" name="organization_unit_id" x-model="organizationUnitId" value="{{ old('organization_unit_id', $servico?->organization_unit_id) }}">
                    <input type="hidden" name="organization_department_id" x-model="organizationDepartmentId" value="{{ old('organization_department_id', $servico?->organization_department_id) }}">
                </div>
            </div>

            <div class="grid gap-4 md:grid-cols-2">
                <div x-show="shouldShowField('status')" x-cloak>
                    <label class="text-sm font-medium text-slate-700">Status</label>
                    <select name="status" class="mt-1 w-full rounded-md border-slate-300 text-sm">
                        <option value="ativo" @selected(old('status', $servico?->status ?? 'ativo') === 'ativo')>Ativo</option>
                        <option value="inativo" @selected(old('status', $servico?->status) === 'inativo')>Inativo</option>
                    </select>
                </div>
                <div class="md:col-span-2">
                    <label class="text-sm font-medium text-slate-700">Observação</label>
                    <textarea name="observacao" rows="3" class="mt-1 w-full rounded-md border-slate-300 text-sm">{{ old('observacao', $servico?->observacao) }}</textarea>
                </div>
            </div>

            <div class="grid gap-4 md:grid-cols-2">
                <div>
                    <label class="text-sm font-medium text-slate-700">Ficha de ativação PDF</label>
                    <input type="file" name="ficha_pdf" accept="application/pdf" class="mt-1 block w-full rounded-md border border-slate-300 bg-white text-sm">
                    <p class="mt-1 text-xs text-slate-500">Se você usou a importação acima, este campo pode ficar em branco. O arquivo temporário será reaproveitado no salvamento.</p>
                </div>
                <div class="flex items-end justify-end gap-2">
                    <a href="{{ $isEdit ? route('network-datacenter.servicos.show', $servico) : route('network-datacenter.servicos.index') }}" class="inline-flex min-h-10 items-center justify-center rounded-md border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-700 hover:bg-slate-50">Cancelar</a>
                    <button type="submit" class="inline-flex min-h-10 items-center justify-center rounded-md bg-[#033151] px-4 text-sm font-semibold text-white hover:opacity-90">
                        {{ $isEdit ? 'Salvar alterações' : 'Cadastrar serviço' }}
                    </button>
                </div>
            </div>
        </div>
    </form>
</section>
