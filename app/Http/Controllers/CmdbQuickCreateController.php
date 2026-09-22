<?php

namespace App\Http\Controllers;

use App\Models\CmdbAsset;
use App\Models\CmdbConnector;
use App\Models\CmdbDiscoveredAsset;
use App\Models\CmdbSyncLog;
use App\Models\CmdbService;
use App\Models\CmdbManufacturer;
use App\Models\CmdbModel;
use App\Models\CmdbRack;
use App\Models\Departamento;
use App\Models\Unidade;
use App\Services\CmdbAssetDuplicateService;
use App\Services\CmdbDiscoveredAssetService;
use App\Services\CmdbQuickCreateService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class CmdbQuickCreateController extends Controller
{
    private const TYPES = [
        'server' => 'Servidor físico',
        'virtual_machine' => 'Máquina virtual',
        'database' => 'Banco de dados',
        'switch' => 'Switch',
        'firewall' => 'Firewall',
        'sonicwall' => 'Sonicwall',
        'storage' => 'Storage',
        'ups' => 'Nobreak / UPS',
        'link' => 'Link de internet',
        'circuit' => 'Circuito',
        'rack' => 'Rack',
        'certificate' => 'Certificado SSL',
        'backup' => 'Backup',
        'other' => 'Outro',
    ];

    private const STATUSES = [
        'active' => 'Ativo',
        'inactive' => 'Inativo',
        'maintenance' => 'Em manutenção',
        'decommissioned' => 'Descomissionado',
        'planned' => 'Planejado',
    ];

    private const CRITICALITIES = [
        'low' => 'Baixa',
        'medium' => 'Média',
        'high' => 'Alta',
        'critical' => 'Crítica',
    ];

    private const ENVIRONMENTS = [
        'production' => 'Produção',
        'homologation' => 'Homologação',
        'development' => 'Desenvolvimento',
        'test' => 'Teste',
    ];
    private const HOSTNAME_DRIVEN_TYPES = [
        'server',
        'virtual_machine',
        'database',
        'switch',
        'firewall',
        'sonicwall',
        'storage',
        'ups',
    ];
    private const VIRTUAL_MACHINE_UNIT_ID = 1;
    private const VIRTUAL_MACHINE_LOCATION = 'Rua Lord Lovat, 420, Jardim Esplanada';

    public function __construct(
        private readonly CmdbQuickCreateService $quickCreateService,
        private readonly CmdbAssetDuplicateService $duplicateService,
        private readonly CmdbDiscoveredAssetService $discoveredAssetService,
    ) {
    }

    public function index(): View
    {
        return view('cmdb.quick-create.index', [
            'setupPending' => false,
        ]);
    }

    public function create(Request $request): View
    {
        return view('cmdb.quick-create.create', [
            'setupPending' => false,
            'title' => 'Cadastro Rápido',
            'prefill' => $this->prefillFromQuery($request),
            'assetTypes' => self::TYPES,
            'hostnameDrivenTypes' => self::HOSTNAME_DRIVEN_TYPES,
            'statusOptions' => self::STATUSES,
            'criticalityOptions' => self::CRITICALITIES,
            'environmentOptions' => self::ENVIRONMENTS,
            'manufacturers' => $this->manufacturers(),
            'models' => $this->models(),
            'racks' => $this->racks(),
            'unitOptions' => $this->unitOptions(),
            'connectors' => $this->connectors(),
            'services' => Schema::hasTable('cmdb_services') ? CmdbService::query()->where('active', true)->orderBy('name')->get(['id', 'name']) : collect(),
            'fromDiscovery' => filled($request->query('source_log_id')),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $asset = $this->quickCreateService->create($this->validated($request), $request->user());
        $this->markDiscoveryAsCreated($request, $asset);

        return redirect()->route('cmdb.assets.show', $asset)->with('success', 'Ativo criado com sucesso.');
    }

    public function duplicate(CmdbAsset $asset): View
    {
        return view('cmdb.quick-create.duplicate', [
            'setupPending' => false,
            'title' => 'Duplicar ativo',
            'sourceAsset' => $asset,
            'prefill' => $this->duplicateService->prepare($asset),
            'assetTypes' => self::TYPES,
            'hostnameDrivenTypes' => self::HOSTNAME_DRIVEN_TYPES,
            'statusOptions' => self::STATUSES,
            'criticalityOptions' => self::CRITICALITIES,
            'environmentOptions' => self::ENVIRONMENTS,
            'manufacturers' => $this->manufacturers(),
            'models' => $this->models(),
            'racks' => $this->racks(),
            'unitOptions' => $this->unitOptions(),
            'connectors' => $this->connectors(),
            'services' => Schema::hasTable('cmdb_services') ? CmdbService::query()->where('active', true)->orderBy('name')->get(['id', 'name']) : collect(),
        ]);
    }

    public function storeDuplicate(Request $request, CmdbAsset $asset): RedirectResponse
    {
        $newAsset = $this->duplicateService->duplicate($asset, $this->validated($request), $request->user(), (bool) $request->boolean('copy_relationships'));

        return redirect()->route('cmdb.assets.show', $newAsset)->with('success', 'Ativo duplicado com sucesso.');
    }

    private function markDiscoveryAsCreated(Request $request, CmdbAsset $asset): void
    {
        if (! Schema::hasTable('cmdb_discovered_assets')) {
            return;
        }

        $sourceLogId = $request->integer('source_log_id');
        $sourceExternalId = trim((string) $request->input('source_external_id', ''));
        $sourceConnectorId = $request->integer('source_connector_id');

        if ($sourceLogId <= 0 && $sourceExternalId === '' && $sourceConnectorId <= 0) {
            return;
        }

        $query = CmdbDiscoveredAsset::query();

        if ($sourceLogId > 0) {
            $query->where('sync_log_id', $sourceLogId);
        }

        if ($sourceConnectorId > 0) {
            $query->where('connector_id', $sourceConnectorId);
        }

        if ($sourceExternalId !== '') {
            $query->where(function ($builder) use ($sourceExternalId): void {
                $builder->where('source_external_id', $sourceExternalId)
                    ->orWhere('hostname', $sourceExternalId)
                    ->orWhere('object_id', $sourceExternalId);
            });
        }

        $discoveredAsset = $query->orderByDesc('discovered_at')->orderByDesc('id')->first();

        if (! $discoveredAsset) {
            return;
        }

        $this->discoveredAssetService->markCreated($discoveredAsset, $asset, $request->user(), 'Ativo criado pelo cadastro rápido a partir de descoberta SNMP.');
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'asset_type' => ['required', 'string', 'max:50'],
            'name' => ['nullable', 'string', 'max:255'],
            'hostname' => ['nullable', 'string', 'max:255'],
            'manufacturer_id' => ['nullable', 'integer', 'min:1'],
            'model_id' => ['nullable', 'integer', 'min:1'],
            'manufacturer' => ['nullable', 'string', 'max:255'],
            'model' => ['nullable', 'string', 'max:255'],
            'serial_number' => ['nullable', 'string', 'max:255'],
            'patrimony_number' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'in:' . implode(',', array_keys(self::STATUSES))],
            'criticality' => ['nullable', 'in:' . implode(',', array_keys(self::CRITICALITIES))],
            'environment' => ['nullable', 'in:' . implode(',', array_keys(self::ENVIRONMENTS))],
            'company_id' => ['nullable', 'integer', 'min:1'],
            'unit_id' => ['nullable', 'integer', 'min:1'],
            'organization_unit_id' => ['nullable', 'integer', 'exists:organization_units,id'],
            'organization_department_id' => ['nullable', 'integer', 'exists:organization_departments,id'],
            'location' => ['nullable', 'string', 'max:255'],
            'rack_id' => ['nullable', 'integer', 'min:1'],
            'main_ip' => ['nullable', 'string', 'max:45'],
            'subnet_mask' => ['nullable', 'string', 'max:45'],
            'gateway' => ['nullable', 'string', 'max:45'],
            'dns' => ['nullable', 'string', 'max:255'],
            'vlan' => ['nullable', 'string', 'max:255'],
            'mac_address' => ['nullable', 'string', 'max:255'],
            'operating_system' => ['nullable', 'string', 'max:255'],
            'responsible_user_id' => ['nullable', 'integer', 'min:1'],
            'description' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
            'source_type' => ['nullable', 'string', 'max:50'],
            'source_connector_id' => ['nullable', 'integer', 'min:1'],
            'source_external_id' => ['nullable', 'string', 'max:255'],
            'copy_relationships' => ['nullable', 'boolean'],
            'server_cpu' => ['nullable', 'string', 'max:255'],
            'server_service_id' => ['nullable', 'integer', 'exists:cmdb_services,id'],
            'server_service_description' => ['nullable', 'string'],
            'server_service_type' => ['nullable', 'string', 'max:255'],
            'server_memory_gb' => ['nullable', 'integer', 'min:0'],
            'server_storage_total_gb' => ['nullable', 'integer', 'min:0'],
            'server_raid' => ['nullable', 'string', 'max:255'],
            'server_hypervisor' => ['nullable', 'string', 'max:255'],
            'server_is_virtualized' => ['nullable', 'boolean'],
            'server_warranty_end_date' => ['nullable', 'date'],
            'vm_host_asset_id' => ['nullable', 'integer', 'min:1'],
            'vm_cpu_cores' => ['nullable', 'integer', 'min:0'],
            'vm_memory_gb' => ['nullable', 'integer', 'min:0'],
            'vm_storage_gb' => ['nullable', 'integer', 'min:0'],
            'vm_hypervisor' => ['nullable', 'string', 'max:255'],
            'vm_datastore' => ['nullable', 'string', 'max:255'],
            'db_server_asset_id' => ['nullable', 'integer', 'min:1'],
            'db_database_type' => ['nullable', 'string', 'max:255'],
            'db_version' => ['nullable', 'string', 'max:255'],
            'db_port' => ['nullable', 'integer', 'min:0'],
            'db_instance_name' => ['nullable', 'string', 'max:255'],
            'db_backup_enabled' => ['nullable', 'boolean'],
            'db_replication_enabled' => ['nullable', 'boolean'],
            'db_notes' => ['nullable', 'string'],
            'network_device_type' => ['nullable', 'string', 'max:255'],
            'network_firmware_version' => ['nullable', 'string', 'max:255'],
            'network_ports_count' => ['nullable', 'integer', 'min:0'],
            'network_poe_enabled' => ['nullable', 'boolean'],
            'network_stack_enabled' => ['nullable', 'boolean'],
            'network_management_ip' => ['nullable', 'string', 'max:45'],
            'firewall_firewall_type' => ['nullable', 'string', 'max:255'],
            'firewall_firmware_version' => ['nullable', 'string', 'max:255'],
            'firewall_license_key' => ['nullable', 'string', 'max:255'],
            'firewall_license_expiration_date' => ['nullable', 'date'],
            'firewall_ha_enabled' => ['nullable', 'boolean'],
            'firewall_vpn_enabled' => ['nullable', 'boolean'],
            'firewall_vpn_count' => ['nullable', 'integer', 'min:0'],
            'storage_storage_type' => ['nullable', 'string', 'max:255'],
            'storage_total_capacity_tb' => ['nullable', 'numeric', 'min:0'],
            'storage_used_capacity_tb' => ['nullable', 'numeric', 'min:0'],
            'storage_raid' => ['nullable', 'string', 'max:255'],
            'storage_iscsi_enabled' => ['nullable', 'boolean'],
            'storage_nfs_enabled' => ['nullable', 'boolean'],
            'storage_smb_enabled' => ['nullable', 'boolean'],
            'ups_power_va' => ['nullable', 'integer', 'min:0'],
            'ups_power_watts' => ['nullable', 'integer', 'min:0'],
            'ups_battery_quantity' => ['nullable', 'integer', 'min:0'],
            'ups_battery_type' => ['nullable', 'string', 'max:255'],
            'ups_installation_date' => ['nullable', 'date'],
            'ups_last_battery_replacement_date' => ['nullable', 'date'],
            'ups_next_battery_replacement_date' => ['nullable', 'date'],
            'ups_estimated_runtime_minutes' => ['nullable', 'integer', 'min:0'],
            'ups_warranty_start_date' => ['nullable', 'date'],
            'ups_warranty_end_date' => ['nullable', 'date'],
        ]);

        $data['name'] = trim((string) ($data['name'] ?? ''));
        $data['hostname'] = trim((string) ($data['hostname'] ?? ''));

        if (in_array($data['asset_type'], self::HOSTNAME_DRIVEN_TYPES, true)) {
            if ($data['hostname'] === '') {
                throw ValidationException::withMessages([
                    'hostname' => 'Informe o hostname do ativo.',
                ]);
            }

            $data['name'] = $data['hostname'];
        } else {
            if ($data['name'] === '' && $data['hostname'] === '') {
                throw ValidationException::withMessages([
                    'name' => 'Informe o nome do ativo.',
                    'hostname' => 'Informe ao menos o nome ou o hostname do ativo.',
                ]);
            }

            if ($data['name'] === '' && $data['hostname'] !== '') {
                $data['name'] = $data['hostname'];
            }
        }

        if ($data['hostname'] === '') {
            $data['hostname'] = null;
        }

        if ($data['name'] === '') {
            $data['name'] = null;
        }

        if (($data['asset_type'] ?? null) === 'virtual_machine') {
            $data['organization_unit_id'] = app(\App\Services\OrganizationLookupService::class)
                ->findUnitBySourceId(self::VIRTUAL_MACHINE_UNIT_ID)?->id
                ?? ($data['organization_unit_id'] ?? null);
            $data['location'] = self::VIRTUAL_MACHINE_LOCATION;
            $data['rack_id'] = null;
        }

        return $data;
    }

    private function prefillFromQuery(Request $request): array
    {
        $prefill = [
            'asset_type' => $request->query('asset_type', 'server'),
            'name' => $request->query('name'),
            'hostname' => $request->query('hostname'),
            'main_ip' => $request->query('ip'),
            'description' => $request->query('description'),
            'source_type' => $request->query('source_type', 'manual'),
            'source_connector_id' => $request->query('source_connector_id'),
            'source_external_id' => $request->query('source_external_id'),
        ];

        $sourceLogId = $request->integer('source_log_id');
        if ($sourceLogId && Schema::hasTable('cmdb_sync_logs')) {
            $log = CmdbSyncLog::query()->with('connector')->find($sourceLogId);
            $discovered = data_get($log?->details, 'discovery.items.0.discovered')
                ?? data_get($log?->details, 'items.0.discovered')
                ?? [];

            $prefill['name'] = $prefill['name'] ?: data_get($discovered, 'hostname');
            $prefill['hostname'] = $prefill['hostname'] ?: data_get($discovered, 'hostname');
            $prefill['main_ip'] = $prefill['main_ip'] ?: data_get($discovered, 'ip');
            $prefill['description'] = $prefill['description'] ?: data_get($discovered, 'description');
            $prefill['source_type'] = data_get($discovered, 'source_type', 'snmp');
            $prefill['source_connector_id'] = $log?->connector_id;
            $prefill['source_external_id'] = data_get($discovered, 'external_id') ?: data_get($discovered, 'object_id');
        }

        return $prefill;
    }

    private function manufacturers()
    {
        return Schema::hasTable('cmdb_manufacturers')
            ? CmdbManufacturer::query()->orderBy('name')->get(['id', 'name'])
            : collect();
    }

    private function models()
    {
        return Schema::hasTable('cmdb_models')
            ? CmdbModel::query()->orderBy('name')->get(['id', 'name', 'manufacturer_id'])
            : collect();
    }

    private function racks()
    {
        return Schema::hasTable('cmdb_racks')
            ? CmdbRack::query()->orderBy('name')->get(['id', 'name', 'unit_id', 'location'])
            : collect();
    }

    private function unitOptions(): array
    {
        return app(\App\Services\OrganizationLookupService::class)
            ->unitOptions()
            ->map(fn ($item) => [
                'id' => (string) ($item->organization_unit_id ?? ''),
                'label' => $item->name,
                'scope' => 'Unidade',
            ])
            ->filter(fn (array $item) => $item['id'] !== '')
            ->values()
            ->all();
    }

    private function connectors()
    {
        return Schema::hasTable('cmdb_connectors')
            ? CmdbConnector::query()->orderBy('name')->get(['id', 'name', 'connector_type'])
            : collect();
    }
}
