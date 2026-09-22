@php
    $details = $details ?? [];
    $serverDetails = $details['server'] ?? [];
    $vmDetails = $details['virtual_machine'] ?? [];
    $databaseDetails = $details['database'] ?? [];
    $networkDetails = $details['network_device'] ?? [];
    $firewallDetails = $details['firewall'] ?? [];
    $storageDetails = $details['storage'] ?? [];
    $upsDetails = $details['ups'] ?? [];
    $currentType = $asset->asset_type;
    $relationshipLabels = [
        'hosts' => 'Hospeda',
        'depends_on' => 'Depende de',
        'connects_to' => 'Conecta-se a',
        'protects' => 'Protege',
        'backs_up_to' => 'Faz backup para',
        'replicates_to' => 'Replica para',
        'uses' => 'Utiliza',
        'located_in' => 'Está localizado em',
        'powered_by' => 'Alimentado por',
        'monitored_by' => 'Monitorado por',
    ];
    $historyLabels = \App\Services\CmdbAssetHistoryService::LABELS;
    $documentLabels = [
        'document' => 'Documento',
        'photo' => 'Foto',
        'manual' => 'Manual',
        'invoice' => 'Nota Fiscal',
        'contract' => 'Contrato',
        'certificate' => 'Certificado',
        'network_diagram' => 'Diagrama de Rede',
        'backup_evidence' => 'Evidência de Backup',
        'other' => 'Outro',
    ];
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
    $hasHistories = $hasHistories ?? true;
    $hasDocuments = $hasDocuments ?? true;

    $documentItems = $hasDocuments
        ? $asset->documents->where('document_type', '!=', 'photo')
        : collect();
    $photoItems = $hasDocuments
        ? $asset->photos
        : collect();
    $historyItems = $hasHistories
        ? $asset->histories
        : collect();
@endphp

<x-app-layout>
    <div class="mx-auto max-w-7xl space-y-6">
        @include('cmdb.partials._nav', ['title' => ($asset->hostname ?: $asset->name), 'description' => 'Detalhes do ativo'])

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

        <div class="flex flex-wrap gap-2">
            <a href="{{ route('cmdb.assets.edit', $asset) }}" class="inline-flex min-h-10 items-center justify-center rounded-md bg-[#033151] px-4 text-sm font-semibold text-white hover:opacity-90">Editar</a>
            <a href="{{ route('cmdb.assets.duplicate', $asset) }}" class="inline-flex min-h-10 items-center justify-center rounded-md border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-700 hover:bg-slate-50">Duplicar ativo</a>
            <a href="{{ route('cmdb.assets.index', ['type' => $asset->asset_type]) }}" class="inline-flex min-h-10 items-center justify-center rounded-md border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-700 hover:bg-slate-50">Voltar</a>
            <a href="{{ route('cmdb.assets.dependencies', $asset) }}" class="inline-flex min-h-10 items-center justify-center rounded-md border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-700 hover:bg-slate-50">Dependências</a>
            <a href="{{ route('cmdb.assets.diagram', $asset) }}" class="inline-flex min-h-10 items-center justify-center rounded-md border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-700 hover:bg-slate-50">Ver diagrama</a>
            <a href="{{ route('cmdb.expirations.index', ['q' => ($asset->hostname ?: $asset->name)]) }}" class="inline-flex min-h-10 items-center justify-center rounded-md border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-700 hover:bg-slate-50">Vencimentos</a>
        </div>

        <div class="flex flex-wrap gap-2">
            <span class="inline-flex rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-semibold text-slate-700">{{ $types[$asset->asset_type] ?? $asset->asset_type }}</span>
            <span class="inline-flex rounded-full bg-blue-100 px-2.5 py-0.5 text-xs font-semibold text-blue-800">{{ $statusLabels[$asset->status] ?? $asset->status }}</span>
            <span class="inline-flex rounded-full bg-amber-100 px-2.5 py-0.5 text-xs font-semibold text-amber-800">{{ $criticalityLabels[$asset->criticality] ?? $asset->criticality }}</span>
            <span class="inline-flex rounded-full bg-emerald-100 px-2.5 py-0.5 text-xs font-semibold text-emerald-800">{{ $environmentLabels[$asset->environment] ?? $asset->environment }}</span>
        </div>

        <section class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
            <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Tipo do ativo</p>
                <p class="mt-2 text-base font-semibold text-slate-950">{{ $types[$asset->asset_type] ?? $asset->asset_type }}</p>
            </div>
            <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Status</p>
                <p class="mt-2 text-base font-semibold text-slate-950">{{ $statusLabels[$asset->status] ?? $asset->status }}</p>
            </div>
            <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Criticidade</p>
                <p class="mt-2 text-base font-semibold text-slate-950">{{ $criticalityLabels[$asset->criticality] ?? $asset->criticality }}</p>
            </div>
            <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Hostname</p>
                <p class="mt-2 text-base font-semibold text-slate-950">{{ $asset->hostname ?: '-' }}</p>
            </div>
            <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">IP principal</p>
                <p class="mt-2 text-base font-semibold text-slate-950">{{ $asset->main_ip ?: '-' }}</p>
            </div>
            <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Responsável</p>
                <p class="mt-2 text-base font-semibold text-slate-950">{{ $asset->responsibleUser?->name ?: '-' }}</p>
            </div>
            <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Fabricante</p>
                <p class="mt-2 text-base font-semibold text-slate-950">{{ ($hasManufacturers ?? false) ? ($asset->manufacturerCatalog?->name ?: ($asset->manufacturer ?: '-')) : ($asset->manufacturer ?: '-') }}</p>
                @if(($hasManufacturers ?? false) && $asset->manufacturerCatalog && $asset->manufacturer && $asset->manufacturerCatalog->name !== $asset->manufacturer)
                    <p class="mt-1 text-xs text-slate-500">Texto legado: {{ $asset->manufacturer }}</p>
                @endif
            </div>
            <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Modelo</p>
                <p class="mt-2 text-base font-semibold text-slate-950">{{ ($hasModels ?? false) ? ($asset->modelCatalog?->name ?: ($asset->model ?: '-')) : ($asset->model ?: '-') }}</p>
                @if(($hasModels ?? false) && $asset->modelCatalog && $asset->model && $asset->modelCatalog->name !== $asset->model)
                    <p class="mt-1 text-xs text-slate-500">Texto legado: {{ $asset->model }}</p>
                @endif
            </div>
            <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Sistema operacional</p>
                <p class="mt-2 text-base font-semibold text-slate-950">{{ $asset->operating_system ?: '-' }}</p>
            </div>
            <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Empresa</p>
                <p class="mt-2 text-base font-semibold text-slate-950">{{ $asset->company_id ?: '-' }}</p>
            </div>
            <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Unidade</p>
                <p class="mt-2 text-base font-semibold text-slate-950">{{ $asset->organizationUnit?->name ?? ($asset->unit_id ?: '-') }}</p>
            </div>
            <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Localização</p>
                <p class="mt-2 text-base font-semibold text-slate-950">{{ $asset->location ?: '-' }}</p>
            </div>
            <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Rack</p>
                <p class="mt-2 text-base font-semibold text-slate-950">
                    @if($asset->rack)
                        <a href="{{ route('cmdb.racks.show', $asset->rack) }}" class="text-[#033151] hover:underline">{{ $asset->rack->name }}</a>
                    @else
                        -
                    @endif
                </p>
            </div>
        </section>

        <section class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <div>
                    <h2 class="text-base font-semibold text-slate-900">Sincronização</h2>
                    <p class="mt-1 text-sm text-slate-500">Metadados de descoberta e sincronização do CMDB.</p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <span class="inline-flex rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-semibold text-slate-700">{{ $syncTypeLabels[$details['sync']['source_type'] ?? 'manual'] ?? ($details['sync']['source_type'] ?? 'manual') }}</span>
                    <span class="inline-flex rounded-full bg-blue-100 px-2.5 py-0.5 text-xs font-semibold text-blue-800">{{ $syncStatusLabels[$details['sync']['sync_status'] ?? 'never_synced'] ?? ($details['sync']['sync_status'] ?? 'Nunca sincronizado') }}</span>
                </div>
            </div>

            <div class="mt-4 grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Origem</p>
                    <p class="mt-2 text-sm font-semibold text-slate-950">{{ $syncTypeLabels[$details['sync']['source_type'] ?? 'manual'] ?? ($details['sync']['source_type'] ?? 'Manual') }}</p>
                </div>
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Conector</p>
                    <p class="mt-2 text-sm font-semibold text-slate-950">{{ $details['sync']['source_connector_name'] ?? '-' }}</p>
                </div>
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">ID externo</p>
                    <p class="mt-2 text-sm font-semibold text-slate-950 break-all">{{ $details['sync']['source_external_id'] ?? '-' }}</p>
                </div>
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Última sincronização</p>
                    <p class="mt-2 text-sm font-semibold text-slate-950">{{ $details['sync']['last_synced_at'] ?? '-' }}</p>
                </div>
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Status</p>
                    <p class="mt-2 text-sm font-semibold text-slate-950">{{ $syncStatusLabels[$details['sync']['sync_status'] ?? 'never_synced'] ?? ($details['sync']['sync_status'] ?? 'Nunca sincronizado') }}</p>
                </div>
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Hash</p>
                    <p class="mt-2 text-sm font-semibold text-slate-950 break-all">{{ $details['sync']['sync_hash'] ?? '-' }}</p>
                </div>
                @if(!empty($details['sync']['sync_error']))
                    <div class="md:col-span-2 xl:col-span-3">
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Erro</p>
                        <p class="mt-2 text-sm text-rose-700 whitespace-pre-line">{{ $details['sync']['sync_error'] }}</p>
                    </div>
                @endif
            </div>
        </section>

        <section class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="text-base font-semibold text-slate-900">Ações rápidas</h2>
            <div class="mt-4 flex flex-wrap gap-2">
                <a href="{{ route('cmdb.ip-addresses.create', ['asset_id' => $asset->id]) }}" class="inline-flex min-h-10 items-center justify-center rounded-md bg-[#033151] px-4 text-sm font-semibold text-white hover:opacity-90">Adicionar IP</a>
                <a href="{{ route('cmdb.relationships.index') }}" class="inline-flex min-h-10 items-center justify-center rounded-md border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-700 hover:bg-slate-50">Adicionar relacionamento</a>
                <a href="{{ route('cmdb.expirations.index') }}" class="inline-flex min-h-10 items-center justify-center rounded-md border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-700 hover:bg-slate-50">Adicionar vencimento</a>
            </div>
        </section>

        <section class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="text-base font-semibold text-slate-900">Detalhes específicos</h2>

            @if($currentType === 'server')
                <div class="mt-4 grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                    <div><p class="text-xs font-semibold uppercase text-slate-500">State</p><p class="mt-1 text-sm text-slate-950">{{ $serverDetails['power_state'] ?? '-' }}</p></div>
                    <div><p class="text-xs font-semibold uppercase text-slate-500">Status operacional</p><p class="mt-1 text-sm text-slate-950">{{ $serverDetails['operational_status'] ?? '-' }}</p></div>
                    <div><p class="text-xs font-semibold uppercase text-slate-500">Espaço usado (GB)</p><p class="mt-1 text-sm text-slate-950">{{ $serverDetails['used_space_gb'] ?? '-' }}</p></div>
                    <div><p class="text-xs font-semibold uppercase text-slate-500">Empresa</p><p class="mt-1 text-sm text-slate-950">{{ $serverDetails['company_name'] ?? '-' }}</p></div>
                    <div><p class="text-xs font-semibold uppercase text-slate-500">Tipo de serviço</p><p class="mt-1 text-sm text-slate-950">{{ $serverDetails['service_type'] ?? '-' }}</p></div>
                    <div><p class="text-xs font-semibold uppercase text-slate-500">Publicação web</p><p class="mt-1 text-sm text-slate-950">{{ array_key_exists('web_publication', $serverDetails) ? (!is_null($serverDetails['web_publication']) ? ($serverDetails['web_publication'] ? 'Sim' : 'Não') : '-') : '-' }}</p></div>
                    <div><p class="text-xs font-semibold uppercase text-slate-500">Política de backup</p><p class="mt-1 text-sm text-slate-950">{{ $serverDetails['backup_policy'] ?? '-' }}</p></div>
                    <div><p class="text-xs font-semibold uppercase text-slate-500">Retenção</p><p class="mt-1 text-sm text-slate-950">{{ $serverDetails['backup_retention'] ?? '-' }}</p></div>
                    <div class="md:col-span-2 xl:col-span-3"><p class="text-xs font-semibold uppercase text-slate-500">Serviços</p><p class="mt-1 text-sm text-slate-950 whitespace-pre-line">{{ $serverDetails['service_description'] ?? '-' }}</p></div>
                    <div><p class="text-xs font-semibold uppercase text-slate-500">CPU</p><p class="mt-1 text-sm text-slate-950">{{ $serverDetails['cpu'] ?? '-' }}</p></div>
                    <div><p class="text-xs font-semibold uppercase text-slate-500">Memória RAM (GB)</p><p class="mt-1 text-sm text-slate-950">{{ $serverDetails['memory_gb'] ?? '-' }}</p></div>
                    <div><p class="text-xs font-semibold uppercase text-slate-500">Armazenamento total (GB)</p><p class="mt-1 text-sm text-slate-950">{{ $serverDetails['storage_total_gb'] ?? '-' }}</p></div>
                    <div><p class="text-xs font-semibold uppercase text-slate-500">RAID</p><p class="mt-1 text-sm text-slate-950">{{ $serverDetails['raid'] ?? '-' }}</p></div>
                    <div><p class="text-xs font-semibold uppercase text-slate-500">Hypervisor</p><p class="mt-1 text-sm text-slate-950">{{ $serverDetails['hypervisor'] ?? '-' }}</p></div>
                    <div><p class="text-xs font-semibold uppercase text-slate-500">É virtualizado?</p><p class="mt-1 text-sm text-slate-950">{{ !empty($serverDetails['is_virtualized']) ? 'Sim' : 'Não' }}</p></div>
                    <div><p class="text-xs font-semibold uppercase text-slate-500">Início da garantia</p><p class="mt-1 text-sm text-slate-950">{{ $serverDetails['warranty_start_date'] ? \Illuminate\Support\Carbon::parse($serverDetails['warranty_start_date'])->format('d/m/Y') : '-' }}</p></div>
                    <div><p class="text-xs font-semibold uppercase text-slate-500">Fim da garantia</p><p class="mt-1 text-sm text-slate-950">{{ $serverDetails['warranty_end_date'] ? \Illuminate\Support\Carbon::parse($serverDetails['warranty_end_date'])->format('d/m/Y') : '-' }}</p></div>
                </div>
            @elseif($currentType === 'virtual_machine')
                <div class="mt-4 grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                    <div><p class="text-xs font-semibold uppercase text-slate-500">State</p><p class="mt-1 text-sm text-slate-950">{{ $serverDetails['power_state'] ?? '-' }}</p></div>
                    <div><p class="text-xs font-semibold uppercase text-slate-500">Status operacional</p><p class="mt-1 text-sm text-slate-950">{{ $serverDetails['operational_status'] ?? '-' }}</p></div>
                    <div><p class="text-xs font-semibold uppercase text-slate-500">Espaço usado (GB)</p><p class="mt-1 text-sm text-slate-950">{{ $serverDetails['used_space_gb'] ?? '-' }}</p></div>
                    <div><p class="text-xs font-semibold uppercase text-slate-500">Empresa</p><p class="mt-1 text-sm text-slate-950">{{ $serverDetails['company_name'] ?? '-' }}</p></div>
                    <div><p class="text-xs font-semibold uppercase text-slate-500">Tipo de serviço</p><p class="mt-1 text-sm text-slate-950">{{ $serverDetails['service_type'] ?? '-' }}</p></div>
                    <div><p class="text-xs font-semibold uppercase text-slate-500">Publicação web</p><p class="mt-1 text-sm text-slate-950">{{ array_key_exists('web_publication', $serverDetails) ? (!is_null($serverDetails['web_publication']) ? ($serverDetails['web_publication'] ? 'Sim' : 'Não') : '-') : '-' }}</p></div>
                    <div><p class="text-xs font-semibold uppercase text-slate-500">Política de backup</p><p class="mt-1 text-sm text-slate-950">{{ $serverDetails['backup_policy'] ?? '-' }}</p></div>
                    <div><p class="text-xs font-semibold uppercase text-slate-500">Retenção</p><p class="mt-1 text-sm text-slate-950">{{ $serverDetails['backup_retention'] ?? '-' }}</p></div>
                    <div class="md:col-span-2 xl:col-span-3"><p class="text-xs font-semibold uppercase text-slate-500">Serviços</p><p class="mt-1 text-sm text-slate-950 whitespace-pre-line">{{ $serverDetails['service_description'] ?? '-' }}</p></div>
                    <div class="md:col-span-2 xl:col-span-3"><p class="text-xs font-semibold uppercase text-slate-500">Host físico / servidor hospedeiro</p><p class="mt-1 text-sm text-slate-950">{{ $asset->virtualMachineDetails?->hostAsset?->name ?: '-' }}</p></div>
                    <div><p class="text-xs font-semibold uppercase text-slate-500">Núcleos de CPU</p><p class="mt-1 text-sm text-slate-950">{{ $vmDetails['cpu_cores'] ?? '-' }}</p></div>
                    <div><p class="text-xs font-semibold uppercase text-slate-500">Memória RAM (GB)</p><p class="mt-1 text-sm text-slate-950">{{ $vmDetails['memory_gb'] ?? '-' }}</p></div>
                    <div><p class="text-xs font-semibold uppercase text-slate-500">Armazenamento (GB)</p><p class="mt-1 text-sm text-slate-950">{{ $vmDetails['storage_gb'] ?? '-' }}</p></div>
                    <div><p class="text-xs font-semibold uppercase text-slate-500">Hypervisor</p><p class="mt-1 text-sm text-slate-950">{{ $vmDetails['hypervisor'] ?? '-' }}</p></div>
                    <div><p class="text-xs font-semibold uppercase text-slate-500">Datastore</p><p class="mt-1 text-sm text-slate-950">{{ $vmDetails['datastore'] ?? '-' }}</p></div>
                </div>
            @elseif($currentType === 'database')
                <div class="mt-4 grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                    <div class="md:col-span-2 xl:col-span-3"><p class="text-xs font-semibold uppercase text-slate-500">Servidor hospedado</p><p class="mt-1 text-sm text-slate-950">{{ $asset->databaseDetails?->serverAsset?->name ?: '-' }}</p></div>
                    <div><p class="text-xs font-semibold uppercase text-slate-500">Tipo</p><p class="mt-1 text-sm text-slate-950">{{ $databaseDetails['database_type'] ?? '-' }}</p></div>
                    <div><p class="text-xs font-semibold uppercase text-slate-500">Versão</p><p class="mt-1 text-sm text-slate-950">{{ $databaseDetails['version'] ?? '-' }}</p></div>
                    <div><p class="text-xs font-semibold uppercase text-slate-500">Porta</p><p class="mt-1 text-sm text-slate-950">{{ $databaseDetails['port'] ?? '-' }}</p></div>
                    <div><p class="text-xs font-semibold uppercase text-slate-500">Instância</p><p class="mt-1 text-sm text-slate-950">{{ $databaseDetails['instance_name'] ?? '-' }}</p></div>
                    <div><p class="text-xs font-semibold uppercase text-slate-500">Backup</p><p class="mt-1 text-sm text-slate-950">{{ !empty($databaseDetails['backup_enabled']) ? 'Sim' : 'Não' }}</p></div>
                    <div><p class="text-xs font-semibold uppercase text-slate-500">Replicação</p><p class="mt-1 text-sm text-slate-950">{{ !empty($databaseDetails['replication_enabled']) ? 'Sim' : 'Não' }}</p></div>
                    <div class="md:col-span-2 xl:col-span-3"><p class="text-xs font-semibold uppercase text-slate-500">Observações</p><p class="mt-1 text-sm text-slate-950 whitespace-pre-line">{{ $databaseDetails['notes'] ?? '-' }}</p></div>
                </div>
            @elseif(in_array($currentType, ['firewall', 'sonicwall'], true))
                <div class="mt-4 grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                    <div><p class="text-xs font-semibold uppercase text-slate-500">Tipo</p><p class="mt-1 text-sm text-slate-950">{{ $firewallDetails['firewall_type'] ?? '-' }}</p></div>
                    <div><p class="text-xs font-semibold uppercase text-slate-500">Firmware</p><p class="mt-1 text-sm text-slate-950">{{ $firewallDetails['firmware_version'] ?? '-' }}</p></div>
                    <div><p class="text-xs font-semibold uppercase text-slate-500">Chave / licença</p><p class="mt-1 text-sm text-slate-950">{{ $firewallDetails['license_key'] ?? '-' }}</p></div>
                    <div><p class="text-xs font-semibold uppercase text-slate-500">Expiração da licença</p><p class="mt-1 text-sm text-slate-950">{{ $firewallDetails['license_expiration_date'] ? \Illuminate\Support\Carbon::parse($firewallDetails['license_expiration_date'])->format('d/m/Y') : '-' }}</p></div>
                    <div><p class="text-xs font-semibold uppercase text-slate-500">Alta disponibilidade</p><p class="mt-1 text-sm text-slate-950">{{ !empty($firewallDetails['ha_enabled']) ? 'Sim' : 'Não' }}</p></div>
                    <div><p class="text-xs font-semibold uppercase text-slate-500">VPN</p><p class="mt-1 text-sm text-slate-950">{{ !empty($firewallDetails['vpn_enabled']) ? 'Sim' : 'Não' }}</p></div>
                    <div><p class="text-xs font-semibold uppercase text-slate-500">Quantidade de VPNs</p><p class="mt-1 text-sm text-slate-950">{{ $firewallDetails['vpn_count'] ?? '-' }}</p></div>
                </div>
            @elseif($currentType === 'switch')
                <div class="mt-4 grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                    <div><p class="text-xs font-semibold uppercase text-slate-500">Tipo de equipamento</p><p class="mt-1 text-sm text-slate-950">{{ $networkDetails['device_type'] ?? '-' }}</p></div>
                    <div><p class="text-xs font-semibold uppercase text-slate-500">Firmware</p><p class="mt-1 text-sm text-slate-950">{{ $networkDetails['firmware_version'] ?? '-' }}</p></div>
                    <div><p class="text-xs font-semibold uppercase text-slate-500">Quantidade de portas</p><p class="mt-1 text-sm text-slate-950">{{ $networkDetails['ports_count'] ?? '-' }}</p></div>
                    <div><p class="text-xs font-semibold uppercase text-slate-500">PoE</p><p class="mt-1 text-sm text-slate-950">{{ !empty($networkDetails['poe_enabled']) ? 'Sim' : 'Não' }}</p></div>
                    <div><p class="text-xs font-semibold uppercase text-slate-500">Stack</p><p class="mt-1 text-sm text-slate-950">{{ !empty($networkDetails['stack_enabled']) ? 'Sim' : 'Não' }}</p></div>
                    <div><p class="text-xs font-semibold uppercase text-slate-500">IP de gerenciamento</p><p class="mt-1 text-sm text-slate-950">{{ $networkDetails['management_ip'] ?? '-' }}</p></div>
                </div>
            @elseif($currentType === 'storage')
                <div class="mt-4 grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                    <div><p class="text-xs font-semibold uppercase text-slate-500">Tipo de storage</p><p class="mt-1 text-sm text-slate-950">{{ $storageDetails['storage_type'] ?? '-' }}</p></div>
                    <div><p class="text-xs font-semibold uppercase text-slate-500">Capacidade total (TB)</p><p class="mt-1 text-sm text-slate-950">{{ $storageDetails['total_capacity_tb'] ?? '-' }}</p></div>
                    <div><p class="text-xs font-semibold uppercase text-slate-500">Capacidade usada (TB)</p><p class="mt-1 text-sm text-slate-950">{{ $storageDetails['used_capacity_tb'] ?? '-' }}</p></div>
                    <div><p class="text-xs font-semibold uppercase text-slate-500">RAID</p><p class="mt-1 text-sm text-slate-950">{{ $storageDetails['raid'] ?? '-' }}</p></div>
                    <div><p class="text-xs font-semibold uppercase text-slate-500">iSCSI</p><p class="mt-1 text-sm text-slate-950">{{ !empty($storageDetails['iscsi_enabled']) ? 'Sim' : 'Não' }}</p></div>
                    <div><p class="text-xs font-semibold uppercase text-slate-500">NFS</p><p class="mt-1 text-sm text-slate-950">{{ !empty($storageDetails['nfs_enabled']) ? 'Sim' : 'Não' }}</p></div>
                    <div><p class="text-xs font-semibold uppercase text-slate-500">SMB</p><p class="mt-1 text-sm text-slate-950">{{ !empty($storageDetails['smb_enabled']) ? 'Sim' : 'Não' }}</p></div>
                </div>
            @elseif($currentType === 'ups')
                <div class="mt-4 grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                    <div><p class="text-xs font-semibold uppercase text-slate-500">Potência em VA</p><p class="mt-1 text-sm text-slate-950">{{ $upsDetails['power_va'] ?? '-' }}</p></div>
                    <div><p class="text-xs font-semibold uppercase text-slate-500">Potência em Watts</p><p class="mt-1 text-sm text-slate-950">{{ $upsDetails['power_watts'] ?? '-' }}</p></div>
                    <div><p class="text-xs font-semibold uppercase text-slate-500">Quantidade de baterias</p><p class="mt-1 text-sm text-slate-950">{{ $upsDetails['battery_quantity'] ?? '-' }}</p></div>
                    <div><p class="text-xs font-semibold uppercase text-slate-500">Tipo de bateria</p><p class="mt-1 text-sm text-slate-950">{{ $upsDetails['battery_type'] ?? '-' }}</p></div>
                    <div><p class="text-xs font-semibold uppercase text-slate-500">Data de instalação</p><p class="mt-1 text-sm text-slate-950">{{ $upsDetails['installation_date'] ? \Illuminate\Support\Carbon::parse($upsDetails['installation_date'])->format('d/m/Y') : '-' }}</p></div>
                    <div><p class="text-xs font-semibold uppercase text-slate-500">Última troca das baterias</p><p class="mt-1 text-sm text-slate-950">{{ $upsDetails['last_battery_replacement_date'] ? \Illuminate\Support\Carbon::parse($upsDetails['last_battery_replacement_date'])->format('d/m/Y') : '-' }}</p></div>
                    <div><p class="text-xs font-semibold uppercase text-slate-500">Próxima troca das baterias</p><p class="mt-1 text-sm text-slate-950">{{ $upsDetails['next_battery_replacement_date'] ? \Illuminate\Support\Carbon::parse($upsDetails['next_battery_replacement_date'])->format('d/m/Y') : '-' }}</p></div>
                    <div><p class="text-xs font-semibold uppercase text-slate-500">Autonomia estimada (min)</p><p class="mt-1 text-sm text-slate-950">{{ $upsDetails['estimated_runtime_minutes'] ?? '-' }}</p></div>
                    <div><p class="text-xs font-semibold uppercase text-slate-500">Início da garantia</p><p class="mt-1 text-sm text-slate-950">{{ $upsDetails['warranty_start_date'] ? \Illuminate\Support\Carbon::parse($upsDetails['warranty_start_date'])->format('d/m/Y') : '-' }}</p></div>
                    <div><p class="text-xs font-semibold uppercase text-slate-500">Fim da garantia</p><p class="mt-1 text-sm text-slate-950">{{ $upsDetails['warranty_end_date'] ? \Illuminate\Support\Carbon::parse($upsDetails['warranty_end_date'])->format('d/m/Y') : '-' }}</p></div>
                </div>
            @else
                <p class="mt-4 text-sm text-slate-500">Sem informações específicas para este tipo de ativo.</p>
            @endif
        </section>

        @if(!empty($hasBackupTable) && $asset->asset_type === 'virtual_machine')
            @php($backup = $asset->backupRecords->sortByDesc('last_synced_at')->first())
            <section class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <div>
                        <h2 class="text-base font-semibold text-slate-900">Proteção e backup</h2>
                        <p class="mt-1 text-sm text-slate-500">Estado calculado a partir da sincronização oficial do Veeam.</p>
                    </div>
                    @if($backup)
                        <span class="inline-flex rounded-full px-3 py-1 text-xs font-semibold {{ match($backup->protection_status) { 'protected' => 'bg-emerald-100 text-emerald-800', 'overdue' => 'bg-amber-100 text-amber-800', 'failed', 'unprotected' => 'bg-rose-100 text-rose-800', default => 'bg-slate-100 text-slate-700' } }}">{{ ['protected' => 'Protegido', 'overdue' => 'Atrasado', 'failed' => 'Falhou', 'unprotected' => 'Não protegido', 'unknown' => 'Desconhecido'][$backup->protection_status] ?? 'Desconhecido' }}</span>
                    @endif
                </div>
                @if($backup)
                    <div class="mt-4 grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                        <div><p class="text-xs font-semibold uppercase text-slate-500">Job</p><p class="mt-1 text-sm text-slate-900">{{ $backup->job_name ?: '-' }}</p></div>
                        <div><p class="text-xs font-semibold uppercase text-slate-500">Último sucesso</p><p class="mt-1 text-sm text-slate-900">{{ optional($backup->last_successful_backup_at)->format('d/m/Y H:i') ?: '-' }}</p></div>
                        <div><p class="text-xs font-semibold uppercase text-slate-500">Último restore point</p><p class="mt-1 text-sm text-slate-900">{{ optional($backup->latest_restore_point_at)->format('d/m/Y H:i') ?: '-' }}</p></div>
                        <div><p class="text-xs font-semibold uppercase text-slate-500">Repositório</p><p class="mt-1 text-sm text-slate-900">{{ $backup->repository_name ?: '-' }}</p></div>
                    </div>
                    @if($backup->last_error)
                        <p class="mt-4 rounded-md border border-rose-200 bg-rose-50 px-3 py-2 text-sm text-rose-800">{{ $backup->last_error }}</p>
                    @endif
                    <p class="mt-3 text-xs text-slate-500">Última sincronização: {{ optional($backup->last_synced_at)->format('d/m/Y H:i') ?: '-' }} • Match: {{ $backup->match_method ?: '-' }}</p>
                @else
                    <p class="mt-4 text-sm text-slate-500">Ainda não há dados de proteção sincronizados do Veeam.</p>
                @endif
            </section>
        @endif

        <div class="grid gap-6 xl:grid-cols-2">
            <section class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <h2 class="text-base font-semibold text-slate-900">Endereços IP</h2>
                    <a href="{{ route('cmdb.ip-addresses.index', ['asset_id' => $asset->id]) }}" class="text-sm font-semibold text-[#033151] hover:underline">Ver todos</a>
                </div>
                <form method="POST" action="{{ route('cmdb.ip-addresses.store') }}" class="mt-4 grid gap-3 md:grid-cols-2">
                    @csrf
                    <input type="hidden" name="asset_id" value="{{ $asset->id }}">
                    <div class="md:col-span-2">
                        <label class="text-sm font-medium text-slate-700">Endereço IP</label>
                        <input name="ip_address" value="{{ old('ip_address') }}" class="mt-1 w-full rounded-md border-slate-300 text-sm" placeholder="Ex.: 10.0.0.10">
                    </div>
                    <div>
                        <label class="text-sm font-medium text-slate-700">Máscara</label>
                        <input name="subnet_mask" value="{{ old('subnet_mask') }}" class="mt-1 w-full rounded-md border-slate-300 text-sm">
                    </div>
                    <div>
                        <label class="text-sm font-medium text-slate-700">Gateway</label>
                        <input name="gateway" value="{{ old('gateway') }}" class="mt-1 w-full rounded-md border-slate-300 text-sm">
                    </div>
                    <div>
                        <label class="text-sm font-medium text-slate-700">DNS</label>
                        <input name="dns" value="{{ old('dns') }}" class="mt-1 w-full rounded-md border-slate-300 text-sm">
                    </div>
                    <div>
                        <label class="text-sm font-medium text-slate-700">VLAN</label>
                        <input name="vlan" value="{{ old('vlan') }}" class="mt-1 w-full rounded-md border-slate-300 text-sm">
                    </div>
                    <div class="md:col-span-2">
                        <label class="text-sm font-medium text-slate-700">MAC Address</label>
                        <input name="mac_address" value="{{ old('mac_address') }}" class="mt-1 w-full rounded-md border-slate-300 text-sm">
                    </div>
                    <div class="md:col-span-2 flex items-center justify-between gap-2">
                        <label class="flex items-center gap-2 text-sm font-medium text-slate-700">
                            <input type="checkbox" name="is_primary" value="1" class="rounded border-slate-300 text-[#033151]">
                            IP Principal
                        </label>
                        <button type="submit" class="inline-flex min-h-10 items-center justify-center rounded-md bg-[#033151] px-4 text-sm font-semibold text-white hover:opacity-90">Salvar IP</button>
                    </div>
                </form>
                <div class="mt-4 space-y-3">
                    @forelse($asset->ipAddresses as $ip)
                        <div class="rounded-md border border-slate-200 p-3 text-sm">
                            <div>
                                <div class="flex flex-wrap items-center gap-2">
                                    <div class="font-semibold text-slate-900">{{ $ip->ip_address }}</div>
                                    @if($ip->is_primary)
                                        <span class="inline-flex rounded-full bg-emerald-100 px-2.5 py-0.5 text-xs font-semibold text-emerald-800">Principal</span>
                                    @endif
                                </div>
                                <div class="mt-1 text-slate-500">{{ $ip->subnet_mask ? 'Máscara: ' . $ip->subnet_mask : 'Sem máscara' }}{{ $ip->gateway ? ' • Gateway: ' . $ip->gateway : '' }}{{ $ip->vlan ? ' • VLAN: ' . $ip->vlan : '' }}</div>
                            </div>
                            <div class="flex gap-2">
                                <a href="{{ route('cmdb.ip-addresses.edit', $ip) }}" class="inline-flex rounded-md border border-slate-300 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50">Editar</a>
                            </div>
                        </div>
                    @empty
                        <p class="mt-2 text-sm text-slate-500">Sem IPs cadastrados.</p>
                    @endforelse
                </div>
            </section>

            <section class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                <h2 class="text-base font-semibold text-slate-900">Vencimentos</h2>
                <div class="mt-4 overflow-x-auto">
                    @forelse($asset->expirationItems as $expiration)
                        <div class="border-b border-slate-100 py-2 text-sm last:border-0">
                            <div class="font-semibold text-slate-900">{{ $expiration->title }}</div>
                            <div class="text-slate-500">
                                {{ $expiration->expiration_type }}
                                • {{ $expiration->expiration_date ? $expiration->expiration_date->format('d/m/Y') : '-' }}
                                • {{ $expiration->status }}
                                @if($expiration->jira_issue_key)
                                    • Jira: {{ $expiration->jira_issue_key }}
                                @endif
                            </div>
                        </div>
                    @empty
                        <p class="mt-2 text-sm text-slate-500">Sem vencimentos cadastrados.</p>
                    @endforelse
                </div>
            </section>
        </div>

        <div class="grid gap-6 xl:grid-cols-2">
            <section class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                <h2 class="text-base font-semibold text-slate-900">Este ativo se relaciona com</h2>
                <form method="POST" action="{{ route('cmdb.relationships.store') }}" class="mt-4 grid gap-3 md:grid-cols-2">
                    @csrf
                    <input type="hidden" name="source_asset_id" value="{{ $asset->id }}">
                    <div>
                        <label class="text-sm font-medium text-slate-700">Ativo de destino</label>
                        <select name="target_asset_id" required class="mt-1 w-full rounded-md border-slate-300 text-sm">
                            <option value="">Selecione</option>
                            @foreach($assetsForRelations as $candidate)
                                @if($candidate->id !== $asset->id)
                                    <option value="{{ $candidate->id }}" @selected(old('target_asset_id') == $candidate->id)>{{ $candidate->name }}</option>
                                @endif
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="text-sm font-medium text-slate-700">Tipo de relacionamento</label>
                        <select name="relationship_type" required class="mt-1 w-full rounded-md border-slate-300 text-sm">
                            @foreach($relationshipLabels as $type => $label)
                                <option value="{{ $type }}" @selected(old('relationship_type') === $type)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="md:col-span-2">
                        <label class="text-sm font-medium text-slate-700">Descrição</label>
                        <input name="description" value="{{ old('description') }}" class="mt-1 w-full rounded-md border-slate-300 text-sm">
                    </div>
                    <div class="md:col-span-2 flex justify-end">
                        <button type="submit" class="inline-flex min-h-10 items-center justify-center rounded-md bg-[#033151] px-4 text-sm font-semibold text-white hover:opacity-90">Salvar relacionamento</button>
                    </div>
                </form>

                <div class="mt-4 space-y-3">
                    @forelse($asset->outgoingRelationships as $relationship)
                        <div class="rounded-md border border-slate-200 p-3 text-sm">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="inline-flex rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-semibold text-slate-700">{{ $relationshipLabels[$relationship->relationship_type] ?? $relationship->relationship_type }}</span>
                                @if($relationship->targetAsset)
                                    <a href="{{ route('cmdb.assets.show', $relationship->targetAsset) }}" class="font-semibold text-[#033151] hover:underline">{{ $relationship->targetAsset->name }}</a>
                                @else
                                    <span class="text-slate-500">Sem ativo relacionado</span>
                                @endif
                            </div>
                            @if($relationship->description)
                                <div class="mt-1 text-xs text-slate-500">{{ $relationship->description }}</div>
                            @endif
                        </div>
                    @empty
                        <p class="mt-2 text-sm text-slate-500">Sem relacionamentos de saída.</p>
                    @endforelse
                </div>
            </section>

            <section class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                <h2 class="text-base font-semibold text-slate-900">Outros ativos relacionados a este</h2>
                <div class="mt-4 space-y-3">
                    @forelse($asset->incomingRelationships as $relationship)
                        <div class="rounded-md border border-slate-200 p-3 text-sm">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="inline-flex rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-semibold text-slate-700">{{ $relationshipLabels[$relationship->relationship_type] ?? $relationship->relationship_type }}</span>
                                @if($relationship->sourceAsset)
                                    <a href="{{ route('cmdb.assets.show', $relationship->sourceAsset) }}" class="font-semibold text-[#033151] hover:underline">{{ $relationship->sourceAsset->name }}</a>
                                @else
                                    <span class="text-slate-500">Sem ativo relacionado</span>
                                @endif
                            </div>
                            @if($relationship->description)
                                <div class="mt-1 text-xs text-slate-500">{{ $relationship->description }}</div>
                            @endif
                        </div>
                    @empty
                        <p class="mt-2 text-sm text-slate-500">Sem relacionamentos de entrada.</p>
                    @endforelse
                </div>
            </section>
        </div>

        <div class="grid gap-6 xl:grid-cols-2">
            <section class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <div>
                        <h2 class="text-base font-semibold text-slate-900">Documentos</h2>
                        <p class="mt-1 text-sm text-slate-500">Arquivos de apoio do ativo.</p>
                    </div>
                </div>
                <form method="POST" action="{{ route('cmdb.assets.documents.store', $asset) }}" enctype="multipart/form-data" class="mt-4 grid gap-3 md:grid-cols-2">
                    @csrf
                    <input type="hidden" name="document_type" value="document">
                    <div class="md:col-span-2">
                        <label class="text-sm font-medium text-slate-700">Título</label>
                        <input name="title" class="mt-1 w-full rounded-md border-slate-300 text-sm" placeholder="Ex.: Contrato, certificado, relatório">
                    </div>
                    <div class="md:col-span-2">
                        <label class="text-sm font-medium text-slate-700">Arquivo</label>
                        <input type="file" name="file" class="mt-1 w-full rounded-md border-slate-300 text-sm">
                    </div>
                    <div class="md:col-span-2">
                        <label class="text-sm font-medium text-slate-700">Descrição</label>
                        <textarea name="description" rows="2" class="mt-1 w-full rounded-md border-slate-300 text-sm"></textarea>
                    </div>
                    <div class="md:col-span-2 flex justify-end">
                        <button type="submit" class="inline-flex min-h-10 items-center justify-center rounded-md bg-[#033151] px-4 text-sm font-semibold text-white hover:opacity-90">Enviar documento</button>
                    </div>
                </form>
                <div class="mt-4 space-y-3">
                    @forelse($documentItems as $document)
                        <div class="rounded-md border border-slate-200 p-3 text-sm">
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <div>
                                    <div class="font-semibold text-slate-900">{{ $document->title }}</div>
                                    <div class="text-xs text-slate-500">{{ $documentLabels[$document->document_type] ?? $document->document_type }} • {{ $document->original_name }}</div>
                                </div>
                                <div class="flex flex-wrap gap-2">
                                    <a href="{{ route('cmdb.assets.documents.download', [$asset, $document]) }}" class="inline-flex rounded-md border border-[#033151] bg-white px-3 py-1.5 text-xs font-semibold text-[#033151] hover:bg-slate-50">Baixar</a>
                                    <form method="POST" action="{{ route('cmdb.assets.documents.destroy', [$asset, $document]) }}" onsubmit="return confirm('Remover este documento?')">
                                        @csrf
                                        @method('DELETE')
                                        <button class="inline-flex rounded-md border border-rose-200 bg-white px-3 py-1.5 text-xs font-semibold text-rose-700 hover:bg-rose-50">Excluir</button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    @empty
                        <p class="mt-2 text-sm text-slate-500">Nenhum documento enviado.</p>
                    @endforelse
                </div>
            </section>

            <section class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <div>
                        <h2 class="text-base font-semibold text-slate-900">Fotos</h2>
                        <p class="mt-1 text-sm text-slate-500">Miniaturas e registros visuais do ativo.</p>
                    </div>
                </div>
                <form method="POST" action="{{ route('cmdb.assets.documents.store', $asset) }}" enctype="multipart/form-data" class="mt-4 grid gap-3 md:grid-cols-2">
                    @csrf
                    <input type="hidden" name="document_type" value="photo">
                    <div class="md:col-span-2">
                        <label class="text-sm font-medium text-slate-700">Título</label>
                        <input name="title" class="mt-1 w-full rounded-md border-slate-300 text-sm" placeholder="Ex.: Foto do rack, foto frontal">
                    </div>
                    <div class="md:col-span-2">
                        <label class="text-sm font-medium text-slate-700">Arquivo</label>
                        <input type="file" name="file" accept="image/*" class="mt-1 w-full rounded-md border-slate-300 text-sm">
                    </div>
                    <div class="md:col-span-2">
                        <label class="text-sm font-medium text-slate-700">Descrição</label>
                        <textarea name="description" rows="2" class="mt-1 w-full rounded-md border-slate-300 text-sm"></textarea>
                    </div>
                    <div class="md:col-span-2 flex justify-end">
                        <button type="submit" class="inline-flex min-h-10 items-center justify-center rounded-md bg-[#033151] px-4 text-sm font-semibold text-white hover:opacity-90">Enviar foto</button>
                    </div>
                </form>
                <div class="mt-4 grid gap-3 sm:grid-cols-2">
                    @forelse($photoItems as $photo)
                        <div class="rounded-md border border-slate-200 p-3 text-sm">
                            <div class="space-y-3">
                                <a href="{{ route('cmdb.assets.documents.download', [$asset, $photo]) }}" target="_blank" class="block overflow-hidden rounded-md border border-slate-200 bg-slate-50">
                                    <img src="{{ route('cmdb.assets.documents.download', [$asset, $photo]) . '?inline=1' }}" alt="{{ $photo->title }}" class="h-36 w-full object-cover">
                                </a>
                                <div class="font-semibold text-slate-900">{{ $photo->title }}</div>
                                <div class="flex flex-wrap gap-2">
                                    <a href="{{ route('cmdb.assets.documents.download', [$asset, $photo]) }}" class="inline-flex rounded-md border border-[#033151] bg-white px-3 py-1.5 text-xs font-semibold text-[#033151] hover:bg-slate-50">Baixar</a>
                                    <form method="POST" action="{{ route('cmdb.assets.documents.destroy', [$asset, $photo]) }}" onsubmit="return confirm('Remover esta foto?')">
                                        @csrf
                                        @method('DELETE')
                                        <button class="inline-flex rounded-md border border-rose-200 bg-white px-3 py-1.5 text-xs font-semibold text-rose-700 hover:bg-rose-50">Excluir</button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    @empty
                        <p class="sm:col-span-2 text-sm text-slate-500">Nenhuma foto enviada.</p>
                    @endforelse
                </div>
            </section>
        </div>

        <section class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <div>
                    <h2 class="text-base font-semibold text-slate-900">Histórico do Ativo</h2>
                    <p class="mt-1 text-sm text-slate-500">Linha do tempo com eventos operacionais do CMDB.</p>
                </div>
            </div>
            <form method="POST" action="{{ route('cmdb.assets.histories.store', $asset) }}" class="mt-4 grid gap-3 md:grid-cols-2">
                @csrf
                <div class="md:col-span-2">
                    <label class="text-sm font-medium text-slate-700">Nota manual</label>
                    <textarea name="note" rows="3" class="mt-1 w-full rounded-md border-slate-300 text-sm" placeholder="Adicione uma observação operacional ao histórico do ativo"></textarea>
                </div>
                <div class="md:col-span-2 flex justify-end">
                    <button type="submit" class="inline-flex min-h-10 items-center justify-center rounded-md bg-[#033151] px-4 text-sm font-semibold text-white hover:opacity-90">Adicionar nota ao histórico</button>
                </div>
            </form>
            <div class="mt-4 space-y-3">
                @forelse($historyItems as $history)
                    <div class="rounded-md border border-slate-200 p-3 text-sm">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ optional($history->created_at)->format('d/m/Y H:i') }}</span>
                            <span class="inline-flex rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-semibold text-slate-700">{{ $historyLabels[$history->event_type] ?? $history->event_type }}</span>
                            <span class="font-semibold text-slate-900">{{ $history->title }}</span>
                        </div>
                        @if($history->user)
                            <div class="mt-1 text-xs text-slate-500">Usuário: {{ $history->user->name }}</div>
                        @endif
                        @if($history->description)
                            <div class="mt-1 text-slate-600">{{ $history->description }}</div>
                        @endif
                    </div>
                @empty
                    <p class="text-sm text-slate-500">Nenhum evento registrado ainda.</p>
                @endforelse
            </div>
        </section>

        <section class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="text-base font-semibold text-slate-900">Descrição</h2>
            <p class="mt-2 whitespace-pre-line text-sm text-slate-700">{{ $asset->description ?: 'Sem descrição.' }}</p>
        </section>

        <section class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="text-base font-semibold text-slate-900">Observações</h2>
            <p class="mt-2 whitespace-pre-line text-sm text-slate-700">{{ $asset->notes ?: 'Sem observações.' }}</p>
        </section>
    </div>
</x-app-layout>
