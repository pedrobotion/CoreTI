@php
    $asset = $asset ?? null;
    $details = $details ?? [];
    $isEdit = (bool) $asset;
    $currentType = old('asset_type', $asset->asset_type ?? $selectedType ?? '');
    $serverDetails = $details['server'] ?? [];
    $vmDetails = $details['virtual_machine'] ?? [];
    $databaseDetails = $details['database'] ?? [];
    $networkDetails = $details['network_device'] ?? [];
    $firewallDetails = $details['firewall'] ?? [];
    $storageDetails = $details['storage'] ?? [];
    $upsDetails = $details['ups'] ?? [];
    $syncDetails = $details['sync'] ?? [];
    $currentStatus = old('status', $asset->status ?? 'active');
    $currentCriticality = old('criticality', $asset->criticality ?? 'medium');
    $currentEnvironment = old('environment', $asset->environment ?? 'production');
    $hostnameDrivenTypes = array_values($hostnameDrivenTypes ?? []);
    $syncTypeLabels = [
        'manual' => 'Manual',
        'vmware' => 'VMware',
        'proxmox' => 'Proxmox',
        'hyperv' => 'Hyper-V',
        'snmp' => 'SNMP',
        'ssh' => 'SSH',
        'winrm' => 'WinRM',
        'api' => 'API',
        'other' => 'Outro',
    ];
    $syncStatusLabels = [
        'never_synced' => 'Nunca sincronizado',
        'synced' => 'Sincronizado',
        'pending' => 'Pendente',
        'error' => 'Erro',
        'ignored' => 'Ignorado',
    ];
    $technicalMode = (bool) ($technicalMode ?? false);
    $canTechnicalMode = (bool) ($canTechnicalMode ?? false);
@endphp

<x-app-layout>
    <div
        x-data="{
            type: @js($currentType),
            manufacturerId: @js((string) old('manufacturer_id', $asset->manufacturer_id ?? '')),
            modelId: @js((string) old('model_id', $asset->model_id ?? '')),
            hostnameDrivenTypes: @js($hostnameDrivenTypes),
            manufacturers: @js(($manufacturers ?? collect())->map(fn ($item) => ['id' => $item->id, 'name' => $item->name, 'models' => $item->models->map(fn ($model) => ['id' => $model->id, 'name' => $model->name, 'manufacturer_id' => $model->manufacturer_id])->values()->all()])->values()->all()),
            models: @js(($models ?? collect())->map(fn ($item) => ['id' => $item->id, 'name' => $item->name, 'manufacturer_id' => $item->manufacturer_id, 'asset_type' => $item->asset_type])->values()->all()),
            isHostnameDrivenType() {
                return this.hostnameDrivenTypes.includes(this.type);
            },
            availableModels() {
                return this.models.filter((model) => ! this.manufacturerId || ! model.manufacturer_id || String(model.manufacturer_id) === String(this.manufacturerId));
            }
        }"
        x-cloak
    >
        <style>[x-cloak]{display:none !important;}</style>

        <div class="mx-auto max-w-7xl space-y-6">
            @include('cmdb.partials._nav', ['title' => $isEdit ? 'Editar ativo' : 'Novo ativo', 'description' => 'Cadastro centralizado de ativos do CMDB.'])
            @if($isEdit)
                <div class="flex flex-wrap items-center justify-between gap-3 rounded-lg border border-slate-200 bg-white px-4 py-3 shadow-sm">
                    <div><p class="text-sm font-semibold text-slate-900">Modo de edição</p><p class="text-xs text-slate-500">O modo simples mostra somente os campos operacionais.</p></div>
                    <div class="flex gap-2"><a href="{{ route('cmdb.assets.edit', $asset) }}" class="cmdb-action {{ ! $technicalMode ? 'cmdb-action-primary' : '' }}">Modo simples</a>@if($canTechnicalMode)<a href="{{ route('cmdb.assets.edit', [$asset, 'mode' => 'technical']) }}" class="cmdb-action {{ $technicalMode ? 'cmdb-action-primary' : '' }}">Modo técnico</a>@endif</div>
                </div>
            @endif

            @if(session('success'))
                <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
                    {{ session('success') }}
                </div>
            @endif

            @if(session('warning'))
                <div class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                    {{ session('warning') }}
                </div>
            @endif

            @if ($errors->any())
                <div class="rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">
                    <p class="font-semibold">Corrija os campos destacados antes de salvar.</p>
                </div>
            @endif

            <section class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                <form method="POST" action="{{ $isEdit ? route('cmdb.assets.update', $asset) : route('cmdb.assets.store') }}" class="space-y-6">
                    @csrf
                    @if($isEdit)
                        @method('PUT')
                    @endif

                    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                        <div>
                            <label class="text-sm font-medium text-slate-700">Tipo do ativo</label>
                            <select name="asset_type" x-model="type" class="mt-1 w-full rounded-md border-slate-300 text-sm">
                                <option value="">Selecione</option>
                                @foreach($types as $key => $label)
                                    <option value="{{ $key }}" @selected(old('asset_type', $asset->asset_type ?? $selectedType ?? '') === $key)>{{ $label }}</option>
                                @endforeach
                            </select>
                            <p class="mt-1 text-xs text-slate-500">Alterar o tipo preserva os dados específicos anteriores.</p>
                        </div>

                        <div x-show="!isHostnameDrivenType()">
                            <label class="text-sm font-medium text-slate-700">Nome</label>
                            <input name="name" value="{{ old('name', $asset->name ?? '') }}" class="mt-1 w-full rounded-md border-slate-300 text-sm">
                        </div>

                        <div>
                            <label class="text-sm font-medium text-slate-700" x-text="isHostnameDrivenType() ? 'Hostname' : 'Hostname (opcional)'"></label>
                            <input name="hostname" value="{{ old('hostname', $asset->hostname ?? $asset->name ?? '') }}" class="mt-1 w-full rounded-md border-slate-300 text-sm">
                            <p class="mt-1 text-xs text-slate-500" x-show="isHostnameDrivenType()">Para esse tipo de ativo, o nome interno acompanha o hostname automaticamente.</p>
                        </div>

                        <div>
                            <label class="text-sm font-medium text-slate-700">Fabricante cadastrado</label>
                            <select
                                name="manufacturer_id"
                                x-model="manufacturerId"
                                @change="modelId = ''"
                                class="mt-1 w-full rounded-md border-slate-300 text-sm"
                            >
                                <option value="">Selecione</option>
                                @foreach($manufacturers ?? collect() as $manufacturer)
                                    <option value="{{ $manufacturer->id }}" @selected((string) old('manufacturer_id', $asset->manufacturer_id ?? '') === (string) $manufacturer->id)>{{ $manufacturer->name }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <label class="text-sm font-medium text-slate-700">Modelo cadastrado</label>
                            <select
                                name="model_id"
                                x-model="modelId"
                                class="mt-1 w-full rounded-md border-slate-300 text-sm"
                            >
                                <option value="">Selecione</option>
                                <template x-for="model in availableModels()" :key="model.id">
                                    <option :value="model.id" x-text="model.name"></option>
                                </template>
                            </select>
                        </div>

                        <div>
                            <label class="text-sm font-medium text-slate-700">Fabricante</label>
                            <input name="manufacturer" value="{{ old('manufacturer', $asset->manufacturer ?? '') }}" class="mt-1 w-full rounded-md border-slate-300 text-sm" x-ref="manufacturerText">
                        </div>

                        <div>
                            <label class="text-sm font-medium text-slate-700">Modelo</label>
                            <input name="model" value="{{ old('model', $asset->model ?? '') }}" class="mt-1 w-full rounded-md border-slate-300 text-sm" x-ref="modelText">
                        </div>

                        <div>
                            <label class="text-sm font-medium text-slate-700">Número de série</label>
                            <input name="serial_number" value="{{ old('serial_number', $asset->serial_number ?? '') }}" class="mt-1 w-full rounded-md border-slate-300 text-sm">
                        </div>

                        <div>
                            <label class="text-sm font-medium text-slate-700">Patrimônio</label>
                            <input name="patrimony_number" value="{{ old('patrimony_number', $asset->patrimony_number ?? '') }}" class="mt-1 w-full rounded-md border-slate-300 text-sm">
                        </div>

                        <div>
                            <label class="text-sm font-medium text-slate-700">Status</label>
                            <select name="status" class="mt-1 w-full rounded-md border-slate-300 text-sm">
                                @foreach($statusLabels as $status => $label)
                                    <option value="{{ $status }}" @selected(old('status', $currentStatus) === $status)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <label class="text-sm font-medium text-slate-700">Criticidade</label>
                            <select name="criticality" class="mt-1 w-full rounded-md border-slate-300 text-sm">
                                @foreach($criticalityLabels as $criticality => $label)
                                    <option value="{{ $criticality }}" @selected(old('criticality', $currentCriticality) === $criticality)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <label class="text-sm font-medium text-slate-700">Ambiente</label>
                            <select name="environment" class="mt-1 w-full rounded-md border-slate-300 text-sm">
                                @foreach($environmentLabels as $environment => $label)
                                    <option value="{{ $environment }}" @selected(old('environment', $currentEnvironment) === $environment)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <label class="text-sm font-medium text-slate-700">Empresa</label>
                            <input name="company_id" type="number" min="1" value="{{ old('company_id', $asset->company_id ?? '') }}" class="mt-1 w-full rounded-md border-slate-300 text-sm" placeholder="ID da empresa">
                        </div>

                        <div>
                            <label class="text-sm font-medium text-slate-700">Unidade</label>
                            <select name="organization_unit_id" class="mt-1 w-full rounded-md border-slate-300 text-sm">
                                <option value="">Selecione</option>
                                @foreach($unitOptions as $unit)
                                    <option value="{{ $unit['id'] }}" @selected((string) old('organization_unit_id', $asset->organization_unit_id ?? '') === (string) $unit['id'])>{{ $unit['label'] }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <label class="text-sm font-medium text-slate-700">Localização</label>
                            <input name="location" value="{{ old('location', $asset->location ?? '') }}" class="mt-1 w-full rounded-md border-slate-300 text-sm">
                        </div>

                        <div x-show="type !== 'virtual_machine'">
                            <label class="text-sm font-medium text-slate-700">Rack</label>
                            <select name="rack_id" :disabled="type === 'virtual_machine'" class="mt-1 w-full rounded-md border-slate-300 text-sm">
                                <option value="">Sem rack</option>
                                @foreach($racks as $rack)
                                    <option value="{{ $rack->id }}" @selected((string) old('rack_id', $asset->rack_id ?? '') === (string) $rack->id)>{{ $rack->name }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <label class="text-sm font-medium text-slate-700">IP principal</label>
                            <input name="main_ip" value="{{ old('main_ip', $asset->main_ip ?? '') }}" class="mt-1 w-full rounded-md border-slate-300 text-sm" placeholder="Ex.: 10.0.0.10">
                        </div>

                        <div>
                            <label class="text-sm font-medium text-slate-700">Sistema operacional</label>
                            <input name="operating_system" value="{{ old('operating_system', $asset->operating_system ?? '') }}" class="mt-1 w-full rounded-md border-slate-300 text-sm">
                        </div>

                        <div>
                            <label class="text-sm font-medium text-slate-700">Responsável</label>
                            <select name="responsible_user_id" class="mt-1 w-full rounded-md border-slate-300 text-sm">
                                <option value="">Sem responsável</option>
                                @foreach($users as $user)
                                    <option value="{{ $user->id }}" @selected((string) old('responsible_user_id', $asset->responsible_user_id ?? '') === (string) $user->id)>{{ $user->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div>
                        <label class="text-sm font-medium text-slate-700">Descrição</label>
                        <textarea name="description" rows="3" class="mt-1 w-full rounded-md border-slate-300 text-sm">{{ old('description', $asset->description ?? '') }}</textarea>
                    </div>

                    <div>
                        <label class="text-sm font-medium text-slate-700">Observações</label>
                        <textarea name="notes" rows="4" class="mt-1 w-full rounded-md border-slate-300 text-sm">{{ old('notes', $asset->notes ?? '') }}</textarea>
                    </div>

                    @if($technicalMode || ! $isEdit)
                    <section class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
                        <div class="flex items-center justify-between gap-3">
                            <div>
                                <h3 class="text-base font-semibold text-slate-900">Sincronização</h3>
                                <p class="text-sm text-slate-500">Origem, conector e referência externa do ativo.</p>
                            </div>
                            <span class="inline-flex rounded-full bg-blue-100 px-2.5 py-0.5 text-xs font-semibold text-blue-800">{{ $syncStatusLabels[$syncDetails['sync_status'] ?? 'never_synced'] ?? 'Nunca sincronizado' }}</span>
                        </div>

                        <div class="mt-4 grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                            <div>
                                <label class="text-sm font-medium text-slate-700">Origem</label>
                                <select name="source_type" class="mt-1 w-full rounded-md border-slate-300 text-sm">
                                    @foreach($syncTypeLabels as $key => $label)
                                        <option value="{{ $key }}" @selected(old('source_type', $asset->source_type ?? 'manual') === $key)>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div>
                                <label class="text-sm font-medium text-slate-700">Conector</label>
                                <select name="source_connector_id" class="mt-1 w-full rounded-md border-slate-300 text-sm">
                                    <option value="">Sem conector</option>
                                    @foreach($connectors ?? collect() as $connector)
                                        <option value="{{ $connector->id }}" @selected((string) old('source_connector_id', $asset->source_connector_id ?? '') === (string) $connector->id)>
                                            {{ $connector->name }} ({{ $connector->connector_type }})
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <div>
                                <label class="text-sm font-medium text-slate-700">ID externo</label>
                                <input name="source_external_id" value="{{ old('source_external_id', $asset->source_external_id ?? '') }}" class="mt-1 w-full rounded-md border-slate-300 text-sm">
                            </div>

                            <div>
                                <label class="text-sm font-medium text-slate-700">Última sincronização</label>
                                <input value="{{ $syncDetails['last_synced_at'] ?? '-' }}" readonly class="mt-1 w-full rounded-md border-slate-300 bg-slate-50 text-sm text-slate-500">
                            </div>

                            <div>
                                <label class="text-sm font-medium text-slate-700">Status</label>
                                <input value="{{ $syncStatusLabels[$syncDetails['sync_status'] ?? 'never_synced'] ?? 'Nunca sincronizado' }}" readonly class="mt-1 w-full rounded-md border-slate-300 bg-slate-50 text-sm text-slate-500">
                            </div>

                            <div>
                                <label class="text-sm font-medium text-slate-700">Erro de sincronização</label>
                                <textarea readonly rows="2" class="mt-1 w-full rounded-md border-slate-300 bg-slate-50 text-sm text-slate-500">{{ $syncDetails['sync_error'] ?? '-' }}</textarea>
                            </div>
                        </div>
                    </section>
                    @endif

                    <div class="rounded-lg border border-slate-200 bg-slate-50 p-4">
                        <div class="flex items-center justify-between gap-3">
                            <div>
                                <h3 class="text-base font-semibold text-slate-900">Campos específicos do tipo selecionado</h3>
                                <p class="text-sm text-slate-500">Preencha apenas a seção do tipo de ativo em uso.</p>
                            </div>
                        </div>
                    </div>

                    <section x-show="type === 'server' || type === 'virtual_machine'" class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
                        <h3 class="text-base font-semibold text-slate-900">Dados operacionais do servidor / VM</h3>
                        <p class="mt-1 text-sm text-slate-500">Os campos abaixo foram alinhados ao layout da planilha de servidores. Para ativos de infraestrutura, o nome interno acompanha o hostname.</p>
                        <div class="mt-4 grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                            <div>
                                <label class="text-sm font-medium text-slate-700">State</label>
                                <input name="server_power_state" value="{{ old('server_power_state', $serverDetails['power_state'] ?? '') }}" class="mt-1 w-full rounded-md border-slate-300 text-sm" placeholder="Powered On">
                            </div>
                            <div>
                                <label class="text-sm font-medium text-slate-700">Status operacional</label>
                                <input name="server_operational_status" value="{{ old('server_operational_status', $serverDetails['operational_status'] ?? '') }}" class="mt-1 w-full rounded-md border-slate-300 text-sm" placeholder="Normal, Alert, Warning">
                            </div>
                            <div>
                                <label class="text-sm font-medium text-slate-700">Espaço usado (GB)</label>
                                <input name="server_used_space_gb" type="number" step="0.01" min="0" value="{{ old('server_used_space_gb', $serverDetails['used_space_gb'] ?? '') }}" class="mt-1 w-full rounded-md border-slate-300 text-sm">
                            </div>
                            <div class="md:col-span-2 xl:col-span-3">
                                <label class="text-sm font-medium text-slate-700">Serviço principal</label>
                                <select name="server_service_id" class="mt-1 w-full rounded-md border-slate-300 text-sm">
                                    <option value="">Selecione um serviço do catálogo</option>
                                    @foreach(($services ?? collect()) as $service)
                                        <option value="{{ $service->id }}" @selected((string) old('server_service_id', $serverDetails['service_id'] ?? '') === (string) $service->id)>{{ $service->name }}</option>
                                    @endforeach
                                </select>
                                <p class="mt-1 text-xs text-slate-500">O texto complementar abaixo permanece disponível para detalhes específicos.</p>
                            </div>
                            <div class="md:col-span-2 xl:col-span-3">
                                <label class="text-sm font-medium text-slate-700">Descrição complementar do serviço</label>
                                <textarea name="server_service_description" rows="2" class="mt-1 w-full rounded-md border-slate-300 text-sm">{{ old('server_service_description', $serverDetails['service_description'] ?? '') }}</textarea>
                            </div>
                            <div>
                                <label class="text-sm font-medium text-slate-700">Empresa</label>
                                <input name="server_company_name" value="{{ old('server_company_name', $serverDetails['company_name'] ?? '') }}" class="mt-1 w-full rounded-md border-slate-300 text-sm">
                            </div>
                            <div>
                                <label class="text-sm font-medium text-slate-700">Tipo de serviço</label>
                                <input name="server_service_type" value="{{ old('server_service_type', $serverDetails['service_type'] ?? '') }}" class="mt-1 w-full rounded-md border-slate-300 text-sm">
                            </div>
                            <div>
                                <label class="text-sm font-medium text-slate-700">Publicação web</label>
                                <select name="server_web_publication" class="mt-1 w-full rounded-md border-slate-300 text-sm">
                                    <option value="">Selecione</option>
                                    <option value="1" @selected((string) old('server_web_publication', array_key_exists('web_publication', $serverDetails) ? (int) $serverDetails['web_publication'] : '') === '1')>Sim</option>
                                    <option value="0" @selected((string) old('server_web_publication', array_key_exists('web_publication', $serverDetails) ? (int) $serverDetails['web_publication'] : '') === '0')>Não</option>
                                </select>
                            </div>
                            <div>
                                <label class="text-sm font-medium text-slate-700">Política de backup</label>
                                <input name="server_backup_policy" value="{{ old('server_backup_policy', $serverDetails['backup_policy'] ?? '') }}" class="mt-1 w-full rounded-md border-slate-300 text-sm">
                            </div>
                            <div>
                                <label class="text-sm font-medium text-slate-700">Retenção</label>
                                <input name="server_backup_retention" value="{{ old('server_backup_retention', $serverDetails['backup_retention'] ?? '') }}" class="mt-1 w-full rounded-md border-slate-300 text-sm">
                            </div>
                        </div>

                        <div class="mt-6 rounded-lg border border-slate-200 bg-slate-50 p-4">
                            <h4 class="text-sm font-semibold text-slate-900">Campos técnicos complementares</h4>
                        </div>
                        <div class="mt-4 grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                            <div>
                                <label class="text-sm font-medium text-slate-700">Processador / CPU</label>
                                <input name="server_cpu" value="{{ old('server_cpu', $serverDetails['cpu'] ?? '') }}" class="mt-1 w-full rounded-md border-slate-300 text-sm">
                            </div>
                            <div>
                                <label class="text-sm font-medium text-slate-700">Memória RAM (GB)</label>
                                <input name="server_memory_gb" type="number" min="0" value="{{ old('server_memory_gb', $serverDetails['memory_gb'] ?? '') }}" class="mt-1 w-full rounded-md border-slate-300 text-sm">
                            </div>
                            <div>
                                <label class="text-sm font-medium text-slate-700">Armazenamento total (GB)</label>
                                <input name="server_storage_total_gb" type="number" min="0" value="{{ old('server_storage_total_gb', $serverDetails['storage_total_gb'] ?? '') }}" class="mt-1 w-full rounded-md border-slate-300 text-sm">
                            </div>
                            <div>
                                <label class="text-sm font-medium text-slate-700">RAID</label>
                                <input name="server_raid" value="{{ old('server_raid', $serverDetails['raid'] ?? '') }}" class="mt-1 w-full rounded-md border-slate-300 text-sm">
                            </div>
                            <div>
                                <label class="text-sm font-medium text-slate-700">Hypervisor</label>
                                <input name="server_hypervisor" value="{{ old('server_hypervisor', $serverDetails['hypervisor'] ?? '') }}" class="mt-1 w-full rounded-md border-slate-300 text-sm">
                            </div>
                            <label class="flex items-center gap-2 text-sm font-medium text-slate-700 md:pt-8">
                                <input type="checkbox" name="server_is_virtualized" value="1" @checked(old('server_is_virtualized', $serverDetails['is_virtualized'] ?? false)) class="rounded border-slate-300 text-[#033151]">
                                É virtualizado?
                            </label>
                            <div>
                                <label class="text-sm font-medium text-slate-700">Início da garantia</label>
                                <input type="date" name="server_warranty_start_date" value="{{ old('server_warranty_start_date', $serverDetails['warranty_start_date'] ?? '') }}" class="mt-1 w-full rounded-md border-slate-300 text-sm">
                            </div>
                            <div>
                                <label class="text-sm font-medium text-slate-700">Fim da garantia</label>
                                <input type="date" name="server_warranty_end_date" value="{{ old('server_warranty_end_date', $serverDetails['warranty_end_date'] ?? '') }}" class="mt-1 w-full rounded-md border-slate-300 text-sm">
                            </div>
                        </div>
                    </section>

                    <section x-show="type === 'virtual_machine'" class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
                        <h3 class="text-base font-semibold text-slate-900">Máquina virtual</h3>
                        <div class="mt-4 grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                            <div class="md:col-span-2 xl:col-span-3">
                                <label class="text-sm font-medium text-slate-700">Host físico / servidor hospedeiro</label>
                                <select name="vm_host_asset_id" class="mt-1 w-full rounded-md border-slate-300 text-sm">
                                    <option value="">Selecione</option>
                                    @foreach($serverAssets as $host)
                                        <option value="{{ $host->id }}" @selected((string) old('vm_host_asset_id', $vmDetails['host_asset_id'] ?? '') === (string) $host->id)>{{ $host->hostname ?: $host->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="text-sm font-medium text-slate-700">Núcleos de CPU</label>
                                <input name="vm_cpu_cores" type="number" min="0" value="{{ old('vm_cpu_cores', $vmDetails['cpu_cores'] ?? '') }}" class="mt-1 w-full rounded-md border-slate-300 text-sm">
                            </div>
                            <div>
                                <label class="text-sm font-medium text-slate-700">Memória RAM (GB)</label>
                                <input name="vm_memory_gb" type="number" min="0" value="{{ old('vm_memory_gb', $vmDetails['memory_gb'] ?? '') }}" class="mt-1 w-full rounded-md border-slate-300 text-sm">
                            </div>
                            <div>
                                <label class="text-sm font-medium text-slate-700">Armazenamento (GB)</label>
                                <input name="vm_storage_gb" type="number" min="0" value="{{ old('vm_storage_gb', $vmDetails['storage_gb'] ?? '') }}" class="mt-1 w-full rounded-md border-slate-300 text-sm">
                            </div>
                            <div>
                                <label class="text-sm font-medium text-slate-700">Hypervisor</label>
                                <input name="vm_hypervisor" value="{{ old('vm_hypervisor', $vmDetails['hypervisor'] ?? '') }}" class="mt-1 w-full rounded-md border-slate-300 text-sm">
                            </div>
                            <div>
                                <label class="text-sm font-medium text-slate-700">Datastore</label>
                                <input name="vm_datastore" value="{{ old('vm_datastore', $vmDetails['datastore'] ?? '') }}" class="mt-1 w-full rounded-md border-slate-300 text-sm">
                            </div>
                        </div>
                    </section>

                    <section x-show="type === 'database'" class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
                        <h3 class="text-base font-semibold text-slate-900">Banco de dados</h3>
                        <div class="mt-4 grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                            <div class="md:col-span-2 xl:col-span-3">
                                <label class="text-sm font-medium text-slate-700">Servidor onde está hospedado</label>
                                <select name="db_server_asset_id" class="mt-1 w-full rounded-md border-slate-300 text-sm">
                                    <option value="">Selecione</option>
                                    @foreach($databaseHostAssets as $host)
                                        <option value="{{ $host->id }}" @selected((string) old('db_server_asset_id', $databaseDetails['server_asset_id'] ?? '') === (string) $host->id)>{{ $host->hostname ?: $host->name }} ({{ $types[$host->asset_type] ?? $host->asset_type }})</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="text-sm font-medium text-slate-700">Tipo de banco</label>
                                <select name="db_database_type" class="mt-1 w-full rounded-md border-slate-300 text-sm">
                                    <option value="">Selecione</option>
                                    @foreach($databaseTypes as $typeOption)
                                        <option value="{{ $typeOption }}" @selected(old('db_database_type', $databaseDetails['database_type'] ?? '') === $typeOption)>{{ $typeOption }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="text-sm font-medium text-slate-700">Versão</label>
                                <input name="db_version" value="{{ old('db_version', $databaseDetails['version'] ?? '') }}" class="mt-1 w-full rounded-md border-slate-300 text-sm">
                            </div>
                            <div>
                                <label class="text-sm font-medium text-slate-700">Porta</label>
                                <input name="db_port" type="number" min="1" max="65535" value="{{ old('db_port', $databaseDetails['port'] ?? '') }}" class="mt-1 w-full rounded-md border-slate-300 text-sm">
                            </div>
                            <div>
                                <label class="text-sm font-medium text-slate-700">Nome da instância</label>
                                <input name="db_instance_name" value="{{ old('db_instance_name', $databaseDetails['instance_name'] ?? '') }}" class="mt-1 w-full rounded-md border-slate-300 text-sm">
                            </div>
                            <label class="flex items-center gap-2 text-sm font-medium text-slate-700 md:pt-8">
                                <input type="checkbox" name="db_backup_enabled" value="1" @checked(old('db_backup_enabled', $databaseDetails['backup_enabled'] ?? false)) class="rounded border-slate-300 text-[#033151]">
                                Backup habilitado?
                            </label>
                            <label class="flex items-center gap-2 text-sm font-medium text-slate-700 md:pt-8">
                                <input type="checkbox" name="db_replication_enabled" value="1" @checked(old('db_replication_enabled', $databaseDetails['replication_enabled'] ?? false)) class="rounded border-slate-300 text-[#033151]">
                                Replicação habilitada?
                            </label>
                            <div class="md:col-span-2 xl:col-span-3">
                                <label class="text-sm font-medium text-slate-700">Observações</label>
                                <textarea name="db_notes" rows="3" class="mt-1 w-full rounded-md border-slate-300 text-sm">{{ old('db_notes', $databaseDetails['notes'] ?? '') }}</textarea>
                            </div>
                        </div>
                    </section>

                    <section x-show="type === 'switch'" class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
                        <h3 class="text-base font-semibold text-slate-900">Switch / Equipamento de rede</h3>
                        <div class="mt-4 grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                            <div>
                                <label class="text-sm font-medium text-slate-700">Tipo de equipamento</label>
                                <select name="network_device_type" class="mt-1 w-full rounded-md border-slate-300 text-sm">
                                    <option value="">Selecione</option>
                                    @foreach($networkDeviceTypes as $deviceType)
                                        <option value="{{ $deviceType }}" @selected(old('network_device_type', $networkDetails['device_type'] ?? '') === $deviceType)>{{ $deviceType }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="text-sm font-medium text-slate-700">Versão do firmware</label>
                                <input name="network_firmware_version" value="{{ old('network_firmware_version', $networkDetails['firmware_version'] ?? '') }}" class="mt-1 w-full rounded-md border-slate-300 text-sm">
                            </div>
                            <div>
                                <label class="text-sm font-medium text-slate-700">Quantidade de portas</label>
                                <input name="network_ports_count" type="number" min="0" value="{{ old('network_ports_count', $networkDetails['ports_count'] ?? '') }}" class="mt-1 w-full rounded-md border-slate-300 text-sm">
                            </div>
                            <label class="flex items-center gap-2 text-sm font-medium text-slate-700 md:pt-8">
                                <input type="checkbox" name="network_poe_enabled" value="1" @checked(old('network_poe_enabled', $networkDetails['poe_enabled'] ?? false)) class="rounded border-slate-300 text-[#033151]">
                                PoE habilitado?
                            </label>
                            <label class="flex items-center gap-2 text-sm font-medium text-slate-700 md:pt-8">
                                <input type="checkbox" name="network_stack_enabled" value="1" @checked(old('network_stack_enabled', $networkDetails['stack_enabled'] ?? false)) class="rounded border-slate-300 text-[#033151]">
                                Stack habilitado?
                            </label>
                            <div>
                                <label class="text-sm font-medium text-slate-700">IP de gerenciamento</label>
                                <input name="network_management_ip" value="{{ old('network_management_ip', $networkDetails['management_ip'] ?? '') }}" class="mt-1 w-full rounded-md border-slate-300 text-sm">
                            </div>
                        </div>
                    </section>

                    <section x-show="type === 'firewall' || type === 'sonicwall'" class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
                        <h3 class="text-base font-semibold text-slate-900">Firewall / Sonicwall</h3>
                        <div class="mt-4 grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                            <div>
                                <label class="text-sm font-medium text-slate-700">Tipo de firewall</label>
                                <select name="firewall_firewall_type" class="mt-1 w-full rounded-md border-slate-300 text-sm">
                                    <option value="">Selecione</option>
                                    @foreach($firewallTypes as $firewallType)
                                        <option value="{{ $firewallType }}" @selected(old('firewall_firewall_type', $firewallDetails['firewall_type'] ?? '') === $firewallType)>{{ $firewallType }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="text-sm font-medium text-slate-700">Versão do firmware</label>
                                <input name="firewall_firmware_version" value="{{ old('firewall_firmware_version', $firewallDetails['firmware_version'] ?? '') }}" class="mt-1 w-full rounded-md border-slate-300 text-sm">
                            </div>
                            <div>
                                <label class="text-sm font-medium text-slate-700">Chave / licença</label>
                                <input name="firewall_license_key" value="{{ old('firewall_license_key', $firewallDetails['license_key'] ?? '') }}" class="mt-1 w-full rounded-md border-slate-300 text-sm">
                            </div>
                            <div>
                                <label class="text-sm font-medium text-slate-700">Expiração da licença</label>
                                <input type="date" name="firewall_license_expiration_date" value="{{ old('firewall_license_expiration_date', $firewallDetails['license_expiration_date'] ?? '') }}" class="mt-1 w-full rounded-md border-slate-300 text-sm">
                            </div>
                            <label class="flex items-center gap-2 text-sm font-medium text-slate-700 md:pt-8">
                                <input type="checkbox" name="firewall_ha_enabled" value="1" @checked(old('firewall_ha_enabled', $firewallDetails['ha_enabled'] ?? false)) class="rounded border-slate-300 text-[#033151]">
                                Alta disponibilidade?
                            </label>
                            <label class="flex items-center gap-2 text-sm font-medium text-slate-700 md:pt-8">
                                <input type="checkbox" name="firewall_vpn_enabled" value="1" @checked(old('firewall_vpn_enabled', $firewallDetails['vpn_enabled'] ?? false)) class="rounded border-slate-300 text-[#033151]">
                                VPN habilitada?
                            </label>
                            <div>
                                <label class="text-sm font-medium text-slate-700">Quantidade de VPNs</label>
                                <input name="firewall_vpn_count" type="number" min="0" value="{{ old('firewall_vpn_count', $firewallDetails['vpn_count'] ?? '') }}" class="mt-1 w-full rounded-md border-slate-300 text-sm">
                            </div>
                        </div>
                    </section>

                    <section x-show="type === 'storage'" class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
                        <h3 class="text-base font-semibold text-slate-900">Storage</h3>
                        <div class="mt-4 grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                            <div>
                                <label class="text-sm font-medium text-slate-700">Tipo de storage</label>
                                <select name="storage_storage_type" class="mt-1 w-full rounded-md border-slate-300 text-sm">
                                    <option value="">Selecione</option>
                                    @foreach($storageTypes as $storageType)
                                        <option value="{{ $storageType }}" @selected(old('storage_storage_type', $storageDetails['storage_type'] ?? '') === $storageType)>{{ $storageType }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="text-sm font-medium text-slate-700">Capacidade total (TB)</label>
                                <input name="storage_total_capacity_tb" type="number" step="0.01" min="0" value="{{ old('storage_total_capacity_tb', $storageDetails['total_capacity_tb'] ?? '') }}" class="mt-1 w-full rounded-md border-slate-300 text-sm">
                            </div>
                            <div>
                                <label class="text-sm font-medium text-slate-700">Capacidade usada (TB)</label>
                                <input name="storage_used_capacity_tb" type="number" step="0.01" min="0" value="{{ old('storage_used_capacity_tb', $storageDetails['used_capacity_tb'] ?? '') }}" class="mt-1 w-full rounded-md border-slate-300 text-sm">
                            </div>
                            <div>
                                <label class="text-sm font-medium text-slate-700">RAID</label>
                                <input name="storage_raid" value="{{ old('storage_raid', $storageDetails['raid'] ?? '') }}" class="mt-1 w-full rounded-md border-slate-300 text-sm">
                            </div>
                            <label class="flex items-center gap-2 text-sm font-medium text-slate-700 md:pt-8">
                                <input type="checkbox" name="storage_iscsi_enabled" value="1" @checked(old('storage_iscsi_enabled', $storageDetails['iscsi_enabled'] ?? false)) class="rounded border-slate-300 text-[#033151]">
                                iSCSI habilitado?
                            </label>
                            <label class="flex items-center gap-2 text-sm font-medium text-slate-700 md:pt-8">
                                <input type="checkbox" name="storage_nfs_enabled" value="1" @checked(old('storage_nfs_enabled', $storageDetails['nfs_enabled'] ?? false)) class="rounded border-slate-300 text-[#033151]">
                                NFS habilitado?
                            </label>
                            <label class="flex items-center gap-2 text-sm font-medium text-slate-700 md:pt-8">
                                <input type="checkbox" name="storage_smb_enabled" value="1" @checked(old('storage_smb_enabled', $storageDetails['smb_enabled'] ?? false)) class="rounded border-slate-300 text-[#033151]">
                                SMB habilitado?
                            </label>
                        </div>
                    </section>

                    <section x-show="type === 'ups'" class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
                        <h3 class="text-base font-semibold text-slate-900">Nobreak / UPS</h3>
                        <div class="mt-4 grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                            <div>
                                <label class="text-sm font-medium text-slate-700">Potência em VA</label>
                                <input name="ups_power_va" type="number" min="0" value="{{ old('ups_power_va', $upsDetails['power_va'] ?? '') }}" class="mt-1 w-full rounded-md border-slate-300 text-sm">
                            </div>
                            <div>
                                <label class="text-sm font-medium text-slate-700">Potência em Watts</label>
                                <input name="ups_power_watts" type="number" min="0" value="{{ old('ups_power_watts', $upsDetails['power_watts'] ?? '') }}" class="mt-1 w-full rounded-md border-slate-300 text-sm">
                            </div>
                            <div>
                                <label class="text-sm font-medium text-slate-700">Quantidade de baterias</label>
                                <input name="ups_battery_quantity" type="number" min="0" value="{{ old('ups_battery_quantity', $upsDetails['battery_quantity'] ?? '') }}" class="mt-1 w-full rounded-md border-slate-300 text-sm">
                            </div>
                            <div>
                                <label class="text-sm font-medium text-slate-700">Tipo de bateria</label>
                                <input name="ups_battery_type" value="{{ old('ups_battery_type', $upsDetails['battery_type'] ?? '') }}" class="mt-1 w-full rounded-md border-slate-300 text-sm">
                            </div>
                            <div>
                                <label class="text-sm font-medium text-slate-700">Data de instalação</label>
                                <input type="date" name="ups_installation_date" value="{{ old('ups_installation_date', $upsDetails['installation_date'] ?? '') }}" class="mt-1 w-full rounded-md border-slate-300 text-sm">
                            </div>
                            <div>
                                <label class="text-sm font-medium text-slate-700">Última troca das baterias</label>
                                <input type="date" name="ups_last_battery_replacement_date" value="{{ old('ups_last_battery_replacement_date', $upsDetails['last_battery_replacement_date'] ?? '') }}" class="mt-1 w-full rounded-md border-slate-300 text-sm">
                            </div>
                            <div>
                                <label class="text-sm font-medium text-slate-700">Próxima troca das baterias</label>
                                <input type="date" name="ups_next_battery_replacement_date" value="{{ old('ups_next_battery_replacement_date', $upsDetails['next_battery_replacement_date'] ?? '') }}" class="mt-1 w-full rounded-md border-slate-300 text-sm">
                            </div>
                            <div>
                                <label class="text-sm font-medium text-slate-700">Autonomia estimada (min)</label>
                                <input name="ups_estimated_runtime_minutes" type="number" min="0" value="{{ old('ups_estimated_runtime_minutes', $upsDetails['estimated_runtime_minutes'] ?? '') }}" class="mt-1 w-full rounded-md border-slate-300 text-sm">
                            </div>
                            <div>
                                <label class="text-sm font-medium text-slate-700">Início da garantia</label>
                                <input type="date" name="ups_warranty_start_date" value="{{ old('ups_warranty_start_date', $upsDetails['warranty_start_date'] ?? '') }}" class="mt-1 w-full rounded-md border-slate-300 text-sm">
                            </div>
                            <div>
                                <label class="text-sm font-medium text-slate-700">Fim da garantia</label>
                                <input type="date" name="ups_warranty_end_date" value="{{ old('ups_warranty_end_date', $upsDetails['warranty_end_date'] ?? '') }}" class="mt-1 w-full rounded-md border-slate-300 text-sm">
                            </div>
                            <div class="md:col-span-2 xl:col-span-3">
                                <label class="text-sm font-medium text-slate-700">Rack do nobreak</label>
                                <select name="ups_rack_id" class="mt-1 w-full rounded-md border-slate-300 text-sm">
                                    <option value="">Sem rack específico</option>
                                    @foreach($racks as $rack)
                                        <option value="{{ $rack->id }}" @selected((string) old('ups_rack_id', $upsDetails['rack_id'] ?? $asset->rack_id ?? '') === (string) $rack->id)>{{ $rack->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    </section>

                    <div class="flex justify-end gap-2">
                        <a href="{{ $isEdit ? route('cmdb.assets.show', $asset) : route('cmdb.assets.index') }}" class="inline-flex min-h-10 items-center justify-center rounded-md border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-700 hover:bg-slate-50">Cancelar</a>
                        <button type="submit" class="inline-flex min-h-10 items-center justify-center rounded-md bg-[#033151] px-4 text-sm font-semibold text-white hover:opacity-90">
                            {{ $isEdit ? 'Salvar alterações' : 'Salvar ativo' }}
                        </button>
                    </div>
                </form>
            </section>
        </div>
    </div>
</x-app-layout>
