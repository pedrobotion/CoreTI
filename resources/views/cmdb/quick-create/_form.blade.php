@php
    $prefill = $prefill ?? [];
    $action = $action ?? route('cmdb.quick-create.store');
    $submitLabel = $submitLabel ?? 'Salvar ativo';
    $typeValue = old('asset_type', $prefill['asset_type'] ?? 'server');
    $assetTypes = $assetTypes ?? [];
    $statusOptions = $statusOptions ?? [];
    $criticalityOptions = $criticalityOptions ?? [];
    $environmentOptions = $environmentOptions ?? [];
    $manufacturers = $manufacturers ?? collect();
    $models = $models ?? collect();
    $racks = $racks ?? collect();
    $unitOptions = $unitOptions ?? [];
    $connectors = $connectors ?? collect();
    $services = $services ?? collect();
    $hostnameDrivenTypes = array_values($hostnameDrivenTypes ?? []);
@endphp

<form method="POST" action="{{ $action }}" class="space-y-6">
    @csrf

    @if(!empty($sourceAsset))
        <input type="hidden" name="source_asset_id" value="{{ $sourceAsset->id }}">
    @endif

    <div
        x-data="{
            type: @js($typeValue),
            manufacturers: @js($manufacturers->map(fn ($item) => ['id' => $item->id, 'name' => $item->name])->values()->all()),
            models: @js($models->map(fn ($item) => ['id' => $item->id, 'name' => $item->name, 'manufacturer_id' => $item->manufacturer_id])->values()->all()),
            manufacturerId: @js((string) old('manufacturer_id', $prefill['manufacturer_id'] ?? '')),
            modelId: @js((string) old('model_id', $prefill['model_id'] ?? '')),
            hostnameDrivenTypes: @js($hostnameDrivenTypes),
            isHostnameDrivenType() {
                return this.hostnameDrivenTypes.includes(this.type);
            },
            availableModels() {
                return this.models.filter((model) => ! this.manufacturerId || ! model.manufacturer_id || String(model.manufacturer_id) === String(this.manufacturerId));
            }
        }"
        x-cloak
        class="space-y-6"
    >
        <style>[x-cloak]{display:none !important;}</style>

        <section class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
            <div class="flex items-center justify-between gap-3">
                <div>
                    <h2 class="text-base font-semibold text-slate-900">Cadastro rápido de ativo</h2>
                    <p class="text-sm text-slate-500">Campos mínimos e blocos condicionais por tipo.</p>
                </div>
                @if(($prefill['source_type'] ?? null) === 'snmp')
                    <span class="inline-flex rounded-full bg-blue-100 px-2.5 py-0.5 text-xs font-semibold text-blue-800">Pré-preenchido por descoberta</span>
                @endif
            </div>

            <div class="mt-4 grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                <div>
                    <label class="text-sm font-medium text-slate-700">Tipo do ativo</label>
                    <select name="asset_type" x-model="type" class="mt-1 w-full rounded-md border-slate-300 text-sm" required>
                        <option value="">Selecione</option>
                        @foreach($assetTypes as $key => $label)
                            <option value="{{ $key }}" @selected(old('asset_type', $typeValue) === $key)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div x-show="!isHostnameDrivenType()">
                    <label class="text-sm font-medium text-slate-700">Nome</label>
                    <input name="name" value="{{ old('name', $prefill['name'] ?? '') }}" class="mt-1 w-full rounded-md border-slate-300 text-sm">
                </div>
                <div>
                    <label class="text-sm font-medium text-slate-700" x-text="isHostnameDrivenType() ? 'Hostname' : 'Hostname (opcional)'"></label>
                    <input name="hostname" value="{{ old('hostname', $prefill['hostname'] ?? $prefill['name'] ?? '') }}" class="mt-1 w-full rounded-md border-slate-300 text-sm">
                    <p class="mt-1 text-xs text-slate-500" x-show="isHostnameDrivenType()">Para esse tipo de ativo, o nome interno acompanha o hostname automaticamente.</p>
                </div>
                <div>
                    <label class="text-sm font-medium text-slate-700">Fabricante</label>
                    <select name="manufacturer_id" x-model="manufacturerId" @change="modelId = ''" class="mt-1 w-full rounded-md border-slate-300 text-sm">
                        <option value="">Selecione</option>
                        @foreach($manufacturers as $manufacturer)
                            <option value="{{ $manufacturer->id }}" @selected((string) old('manufacturer_id', $prefill['manufacturer_id'] ?? '') === (string) $manufacturer->id)>{{ $manufacturer->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="text-sm font-medium text-slate-700">Modelo</label>
                    <select name="model_id" x-model="modelId" class="mt-1 w-full rounded-md border-slate-300 text-sm">
                        <option value="">Selecione</option>
                        <template x-for="model in availableModels()" :key="model.id">
                            <option :value="model.id" :selected="String(model.id) === String(modelId)" x-text="model.name"></option>
                        </template>
                    </select>
                </div>
                <div>
                    <label class="text-sm font-medium text-slate-700">Número de série</label>
                    <input name="serial_number" value="{{ old('serial_number', $prefill['serial_number'] ?? '') }}" class="mt-1 w-full rounded-md border-slate-300 text-sm">
                </div>
                <div>
                    <label class="text-sm font-medium text-slate-700">Patrimônio</label>
                    <input name="patrimony_number" value="{{ old('patrimony_number', $prefill['patrimony_number'] ?? '') }}" class="mt-1 w-full rounded-md border-slate-300 text-sm">
                </div>
                <div>
                    <label class="text-sm font-medium text-slate-700">Status</label>
                    <select name="status" class="mt-1 w-full rounded-md border-slate-300 text-sm">
                        @foreach($statusOptions as $key => $label)
                            <option value="{{ $key }}" @selected(old('status', $prefill['status'] ?? 'active') === $key)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="text-sm font-medium text-slate-700">Criticidade</label>
                    <select name="criticality" class="mt-1 w-full rounded-md border-slate-300 text-sm">
                        @foreach($criticalityOptions as $key => $label)
                            <option value="{{ $key }}" @selected(old('criticality', $prefill['criticality'] ?? 'medium') === $key)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="text-sm font-medium text-slate-700">Ambiente</label>
                    <select name="environment" class="mt-1 w-full rounded-md border-slate-300 text-sm">
                        @foreach($environmentOptions as $key => $label)
                            <option value="{{ $key }}" @selected(old('environment', $prefill['environment'] ?? 'production') === $key)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="text-sm font-medium text-slate-700">Empresa</label>
                    <input name="company_id" type="number" min="1" value="{{ old('company_id', $prefill['company_id'] ?? '') }}" class="mt-1 w-full rounded-md border-slate-300 text-sm">
                </div>
                <div>
                    <label class="text-sm font-medium text-slate-700">Unidade / Departamento</label>
                    <select name="organization_unit_id" class="mt-1 w-full rounded-md border-slate-300 text-sm">
                        <option value="">Selecione</option>
                        @foreach($unitOptions as $unit)
                            <option value="{{ $unit['id'] }}" @selected((string) old('organization_unit_id', $prefill['organization_unit_id'] ?? '') === (string) $unit['id'])>{{ $unit['label'] }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="text-sm font-medium text-slate-700">Localização</label>
                    <input name="location" value="{{ old('location', $prefill['location'] ?? '') }}" class="mt-1 w-full rounded-md border-slate-300 text-sm">
                </div>
                <div x-show="type !== 'virtual_machine'">
                    <label class="text-sm font-medium text-slate-700">Rack</label>
                    <select name="rack_id" :disabled="type === 'virtual_machine'" class="mt-1 w-full rounded-md border-slate-300 text-sm">
                        <option value="">Sem rack</option>
                        @foreach($racks as $rack)
                            <option value="{{ $rack->id }}" @selected((string) old('rack_id', $prefill['rack_id'] ?? '') === (string) $rack->id)>{{ $rack->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="text-sm font-medium text-slate-700">IP principal</label>
                    <input name="main_ip" value="{{ old('main_ip', $prefill['main_ip'] ?? '') }}" class="mt-1 w-full rounded-md border-slate-300 text-sm">
                </div>
                <div>
                    <label class="text-sm font-medium text-slate-700">Sistema operacional</label>
                    <input name="operating_system" value="{{ old('operating_system', $prefill['operating_system'] ?? '') }}" class="mt-1 w-full rounded-md border-slate-300 text-sm">
                </div>
                <div>
                    <label class="text-sm font-medium text-slate-700">Conector SNMP</label>
                    <select name="source_connector_id" class="mt-1 w-full rounded-md border-slate-300 text-sm">
                        <option value="">Sem conector</option>
                        @foreach($connectors as $connector)
                            <option value="{{ $connector->id }}" @selected((string) old('source_connector_id', $prefill['source_connector_id'] ?? '') === (string) $connector->id)>{{ $connector->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="text-sm font-medium text-slate-700">ID externo</label>
                    <input name="source_external_id" value="{{ old('source_external_id', $prefill['source_external_id'] ?? '') }}" class="mt-1 w-full rounded-md border-slate-300 text-sm">
                </div>
                <div class="md:col-span-2 xl:col-span-3">
                    <label class="text-sm font-medium text-slate-700">Descrição</label>
                    <textarea name="description" rows="3" class="mt-1 w-full rounded-md border-slate-300 text-sm">{{ old('description', $prefill['description'] ?? '') }}</textarea>
                </div>
                <div class="md:col-span-2 xl:col-span-3">
                    <label class="text-sm font-medium text-slate-700">Observações</label>
                    <textarea name="notes" rows="4" class="mt-1 w-full rounded-md border-slate-300 text-sm">{{ old('notes', $prefill['notes'] ?? '') }}</textarea>
                </div>
            </div>
        </section>

        <section class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm" x-show="type === 'server'">
            <h3 class="text-base font-semibold text-slate-900">Servidor</h3>
            <div class="mt-4 grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                <input name="server_cpu" value="{{ old('server_cpu', $prefill['server_cpu'] ?? '') }}" class="w-full rounded-md border-slate-300 text-sm" placeholder="CPU">
                <select name="server_service_id" class="w-full rounded-md border-slate-300 text-sm"><option value="">Serviço (opcional)</option>@foreach($services as $service)<option value="{{ $service->id }}" @selected((string) old('server_service_id', $prefill['server_service_id'] ?? '') === (string) $service->id)>{{ $service->name }}</option>@endforeach</select>
                <input name="server_service_type" value="{{ old('server_service_type', $prefill['server_service_type'] ?? '') }}" class="w-full rounded-md border-slate-300 text-sm" placeholder="Tipo de serviço (legado)">
                <textarea name="server_service_description" rows="1" class="w-full rounded-md border-slate-300 text-sm" placeholder="Descrição complementar"></textarea>
                <input name="server_memory_gb" type="number" min="0" value="{{ old('server_memory_gb', $prefill['server_memory_gb'] ?? '') }}" class="w-full rounded-md border-slate-300 text-sm" placeholder="Memória RAM GB">
                <input name="server_storage_total_gb" type="number" min="0" value="{{ old('server_storage_total_gb', $prefill['server_storage_total_gb'] ?? '') }}" class="w-full rounded-md border-slate-300 text-sm" placeholder="Armazenamento GB">
                <input name="server_raid" value="{{ old('server_raid', $prefill['server_raid'] ?? '') }}" class="w-full rounded-md border-slate-300 text-sm" placeholder="RAID">
                <input name="server_hypervisor" value="{{ old('server_hypervisor', $prefill['server_hypervisor'] ?? '') }}" class="w-full rounded-md border-slate-300 text-sm" placeholder="Hypervisor">
                <label class="inline-flex items-center gap-2 text-sm text-slate-700"><input type="checkbox" name="server_is_virtualized" value="1" @checked(old('server_is_virtualized', $prefill['server_is_virtualized'] ?? false))> Virtualizado</label>
                <input name="server_warranty_end_date" type="date" value="{{ old('server_warranty_end_date', $prefill['server_warranty_end_date'] ?? '') }}" class="w-full rounded-md border-slate-300 text-sm">
            </div>
        </section>

        <section class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm" x-show="type === 'switch'">
            <h3 class="text-base font-semibold text-slate-900">Equipamento de rede</h3>
            <div class="mt-4 grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                <input name="network_device_type" value="{{ old('network_device_type', $prefill['network_device_type'] ?? '') }}" class="w-full rounded-md border-slate-300 text-sm" placeholder="Tipo">
                <input name="network_firmware_version" value="{{ old('network_firmware_version', $prefill['network_firmware_version'] ?? '') }}" class="w-full rounded-md border-slate-300 text-sm" placeholder="Firmware">
                <input name="network_ports_count" type="number" min="0" value="{{ old('network_ports_count', $prefill['network_ports_count'] ?? '') }}" class="w-full rounded-md border-slate-300 text-sm" placeholder="Portas">
                <input name="network_management_ip" value="{{ old('network_management_ip', $prefill['network_management_ip'] ?? '') }}" class="w-full rounded-md border-slate-300 text-sm" placeholder="IP de gerenciamento">
                <label class="inline-flex items-center gap-2 text-sm text-slate-700"><input type="checkbox" name="network_poe_enabled" value="1" @checked(old('network_poe_enabled', $prefill['network_poe_enabled'] ?? false))> PoE</label>
                <label class="inline-flex items-center gap-2 text-sm text-slate-700"><input type="checkbox" name="network_stack_enabled" value="1" @checked(old('network_stack_enabled', $prefill['network_stack_enabled'] ?? false))> Stack</label>
            </div>
        </section>

        <section class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm" x-show="type === 'firewall' || type === 'sonicwall'">
            <h3 class="text-base font-semibold text-slate-900">Firewall / SonicWall</h3>
            <div class="mt-4 grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                <input name="firewall_firewall_type" value="{{ old('firewall_firewall_type', $prefill['firewall_firewall_type'] ?? '') }}" class="w-full rounded-md border-slate-300 text-sm" placeholder="Tipo">
                <input name="firewall_firmware_version" value="{{ old('firewall_firmware_version', $prefill['firewall_firmware_version'] ?? '') }}" class="w-full rounded-md border-slate-300 text-sm" placeholder="Firmware">
                <input name="firewall_license_key" value="{{ old('firewall_license_key', $prefill['firewall_license_key'] ?? '') }}" class="w-full rounded-md border-slate-300 text-sm" placeholder="Licença">
                <input name="firewall_license_expiration_date" type="date" value="{{ old('firewall_license_expiration_date', $prefill['firewall_license_expiration_date'] ?? '') }}" class="w-full rounded-md border-slate-300 text-sm">
                <input name="firewall_vpn_count" type="number" min="0" value="{{ old('firewall_vpn_count', $prefill['firewall_vpn_count'] ?? '') }}" class="w-full rounded-md border-slate-300 text-sm" placeholder="Qtd. VPNs">
                <label class="inline-flex items-center gap-2 text-sm text-slate-700"><input type="checkbox" name="firewall_ha_enabled" value="1" @checked(old('firewall_ha_enabled', $prefill['firewall_ha_enabled'] ?? false))> HA habilitado</label>
                <label class="inline-flex items-center gap-2 text-sm text-slate-700"><input type="checkbox" name="firewall_vpn_enabled" value="1" @checked(old('firewall_vpn_enabled', $prefill['firewall_vpn_enabled'] ?? false))> VPN habilitada</label>
            </div>
        </section>

        <section class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm" x-show="type === 'ups'">
            <h3 class="text-base font-semibold text-slate-900">Nobreak / UPS</h3>
            <div class="mt-4 grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                <input name="ups_power_va" type="number" min="0" value="{{ old('ups_power_va', $prefill['ups_power_va'] ?? '') }}" class="w-full rounded-md border-slate-300 text-sm" placeholder="Potência VA">
                <input name="ups_power_watts" type="number" min="0" value="{{ old('ups_power_watts', $prefill['ups_power_watts'] ?? '') }}" class="w-full rounded-md border-slate-300 text-sm" placeholder="Potência Watts">
                <input name="ups_battery_quantity" type="number" min="0" value="{{ old('ups_battery_quantity', $prefill['ups_battery_quantity'] ?? '') }}" class="w-full rounded-md border-slate-300 text-sm" placeholder="Qtd. baterias">
                <input name="ups_battery_type" value="{{ old('ups_battery_type', $prefill['ups_battery_type'] ?? '') }}" class="w-full rounded-md border-slate-300 text-sm" placeholder="Tipo de bateria">
                <input name="ups_installation_date" type="date" value="{{ old('ups_installation_date', $prefill['ups_installation_date'] ?? '') }}" class="w-full rounded-md border-slate-300 text-sm">
                <input name="ups_last_battery_replacement_date" type="date" value="{{ old('ups_last_battery_replacement_date', $prefill['ups_last_battery_replacement_date'] ?? '') }}" class="w-full rounded-md border-slate-300 text-sm">
                <input name="ups_next_battery_replacement_date" type="date" value="{{ old('ups_next_battery_replacement_date', $prefill['ups_next_battery_replacement_date'] ?? '') }}" class="w-full rounded-md border-slate-300 text-sm">
                <input name="ups_estimated_runtime_minutes" type="number" min="0" value="{{ old('ups_estimated_runtime_minutes', $prefill['ups_estimated_runtime_minutes'] ?? '') }}" class="w-full rounded-md border-slate-300 text-sm" placeholder="Autonomia (min)">
                <input name="ups_warranty_start_date" type="date" value="{{ old('ups_warranty_start_date', $prefill['ups_warranty_start_date'] ?? '') }}" class="w-full rounded-md border-slate-300 text-sm">
                <input name="ups_warranty_end_date" type="date" value="{{ old('ups_warranty_end_date', $prefill['ups_warranty_end_date'] ?? '') }}" class="w-full rounded-md border-slate-300 text-sm">
            </div>
        </section>

        <section class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm" x-show="type === 'storage'">
            <h3 class="text-base font-semibold text-slate-900">Storage</h3>
            <div class="mt-4 grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                <input name="storage_storage_type" value="{{ old('storage_storage_type', $prefill['storage_storage_type'] ?? '') }}" class="w-full rounded-md border-slate-300 text-sm" placeholder="Tipo">
                <input name="storage_total_capacity_tb" type="number" step="0.01" min="0" value="{{ old('storage_total_capacity_tb', $prefill['storage_total_capacity_tb'] ?? '') }}" class="w-full rounded-md border-slate-300 text-sm" placeholder="Capacidade total TB">
                <input name="storage_used_capacity_tb" type="number" step="0.01" min="0" value="{{ old('storage_used_capacity_tb', $prefill['storage_used_capacity_tb'] ?? '') }}" class="w-full rounded-md border-slate-300 text-sm" placeholder="Usado TB">
                <input name="storage_raid" value="{{ old('storage_raid', $prefill['storage_raid'] ?? '') }}" class="w-full rounded-md border-slate-300 text-sm" placeholder="RAID">
                <label class="inline-flex items-center gap-2 text-sm text-slate-700"><input type="checkbox" name="storage_iscsi_enabled" value="1" @checked(old('storage_iscsi_enabled', $prefill['storage_iscsi_enabled'] ?? false))> iSCSI</label>
                <label class="inline-flex items-center gap-2 text-sm text-slate-700"><input type="checkbox" name="storage_nfs_enabled" value="1" @checked(old('storage_nfs_enabled', $prefill['storage_nfs_enabled'] ?? false))> NFS</label>
                <label class="inline-flex items-center gap-2 text-sm text-slate-700"><input type="checkbox" name="storage_smb_enabled" value="1" @checked(old('storage_smb_enabled', $prefill['storage_smb_enabled'] ?? false))> SMB</label>
            </div>
        </section>

        <section class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm" x-show="type === 'virtual_machine'">
            <h3 class="text-base font-semibold text-slate-900">Máquina virtual</h3>
            <div class="mt-4 grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                <input name="vm_host_asset_id" type="number" min="1" value="{{ old('vm_host_asset_id', $prefill['vm_host_asset_id'] ?? '') }}" class="w-full rounded-md border-slate-300 text-sm" placeholder="Host físico">
                <input name="vm_cpu_cores" type="number" min="0" value="{{ old('vm_cpu_cores', $prefill['vm_cpu_cores'] ?? '') }}" class="w-full rounded-md border-slate-300 text-sm" placeholder="CPU cores">
                <input name="vm_memory_gb" type="number" min="0" value="{{ old('vm_memory_gb', $prefill['vm_memory_gb'] ?? '') }}" class="w-full rounded-md border-slate-300 text-sm" placeholder="Memória GB">
                <input name="vm_storage_gb" type="number" min="0" value="{{ old('vm_storage_gb', $prefill['vm_storage_gb'] ?? '') }}" class="w-full rounded-md border-slate-300 text-sm" placeholder="Storage GB">
                <input name="vm_hypervisor" value="{{ old('vm_hypervisor', $prefill['vm_hypervisor'] ?? '') }}" class="w-full rounded-md border-slate-300 text-sm" placeholder="Hypervisor">
                <input name="vm_datastore" value="{{ old('vm_datastore', $prefill['vm_datastore'] ?? '') }}" class="w-full rounded-md border-slate-300 text-sm" placeholder="Datastore">
            </div>
        </section>

        <section class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm" x-show="type === 'database'">
            <h3 class="text-base font-semibold text-slate-900">Banco de dados</h3>
            <div class="mt-4 grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                <input name="db_server_asset_id" type="number" min="1" value="{{ old('db_server_asset_id', $prefill['db_server_asset_id'] ?? '') }}" class="w-full rounded-md border-slate-300 text-sm" placeholder="Servidor">
                <input name="db_database_type" value="{{ old('db_database_type', $prefill['db_database_type'] ?? '') }}" class="w-full rounded-md border-slate-300 text-sm" placeholder="Tipo">
                <input name="db_version" value="{{ old('db_version', $prefill['db_version'] ?? '') }}" class="w-full rounded-md border-slate-300 text-sm" placeholder="Versão">
                <input name="db_port" type="number" min="0" value="{{ old('db_port', $prefill['db_port'] ?? '') }}" class="w-full rounded-md border-slate-300 text-sm" placeholder="Porta">
                <input name="db_instance_name" value="{{ old('db_instance_name', $prefill['db_instance_name'] ?? '') }}" class="w-full rounded-md border-slate-300 text-sm" placeholder="Instância">
                <label class="inline-flex items-center gap-2 text-sm text-slate-700"><input type="checkbox" name="db_backup_enabled" value="1" @checked(old('db_backup_enabled', $prefill['db_backup_enabled'] ?? false))> Backup</label>
                <label class="inline-flex items-center gap-2 text-sm text-slate-700"><input type="checkbox" name="db_replication_enabled" value="1" @checked(old('db_replication_enabled', $prefill['db_replication_enabled'] ?? false))> Replicação</label>
            </div>
        </section>

        @if(!empty($sourceAsset))
            <section class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                <label class="inline-flex items-center gap-2 text-sm text-slate-700">
                    <input type="checkbox" name="copy_relationships" value="1" class="rounded border-slate-300 text-[#033151]">
                    Copiar relacionamentos
                </label>
            </section>
        @endif

        <div class="flex justify-end gap-2">
            <a href="{{ route('cmdb.quick-create.index') }}" class="inline-flex min-h-10 items-center justify-center rounded-md border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-700 hover:bg-slate-50">Cancelar</a>
            <button type="submit" class="inline-flex min-h-10 items-center justify-center rounded-md bg-[#033151] px-4 text-sm font-semibold text-white hover:opacity-90">{{ $submitLabel }}</button>
        </div>
    </div>
</form>
