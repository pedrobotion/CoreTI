<?php

namespace App\Services;

use App\Models\CmdbAsset;
use App\Models\CmdbAssetHistory;
use App\Models\CmdbExpiration;
use App\Models\CmdbFirewall;
use App\Models\CmdbIpAddress;
use App\Models\CmdbNetworkDevice;
use App\Models\CmdbRack;
use App\Models\CmdbStorage;
use App\Models\CmdbUps;
use App\Models\CmdbServer;
use App\Models\CmdbDatabase;
use App\Models\CmdbVirtualMachine;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CmdbQuickCreateService
{
    public function __construct(
        private readonly CmdbAssetDetailService $detailService,
        private readonly CmdbAssetHistoryService $historyService,
    ) {
    }

    public function create(array $data, ?User $user = null, string $historyMessage = 'Ativo criado via cadastro rápido'): CmdbAsset
    {
        // $historyMessage também precisa ser capturado: sem ele, o closure
        // estoura ao montar o registro de histórico.
        return DB::transaction(function () use ($data, $user, $historyMessage): CmdbAsset {
            $asset = CmdbAsset::create($this->assetPayload($data));
            $this->applyDependencies($asset, $data);
            $this->historyService->record($asset, $user, 'created', $historyMessage, null, [], $asset->toArray());

            return $asset;
        });
    }

    public function duplicate(CmdbAsset $source, array $overrides = [], ?User $user = null, bool $copyRelationships = false): CmdbAsset
    {
        $payload = array_merge($this->payloadFromAsset($source), $overrides);

        return DB::transaction(function () use ($source, $payload, $user, $copyRelationships): CmdbAsset {
            $asset = CmdbAsset::create($this->assetPayload($payload));
            $this->applyDependencies($asset, $payload);

            if ($copyRelationships && Schema::hasTable('cmdb_relationships')) {
                foreach ($source->outgoingRelationships()->get() as $relationship) {
                    $relationship->replicate([
                        'id',
                        'created_at',
                        'updated_at',
                        'deleted_at',
                    ])->fill([
                        'source_asset_id' => $asset->id,
                    ])->save();
                }
            }

            $this->historyService->record($asset, $user, 'created', 'Ativo duplicado a partir de ativo existente', null, [], $asset->toArray());

            return $asset;
        });
    }

    public function payloadFromAsset(CmdbAsset $asset): array
    {
        $asset->loadMissing([
            'serverDetails',
            'virtualMachineDetails',
            'databaseDetails',
            'networkDeviceDetails',
            'firewallDetails',
            'storageDetails',
            'upsDetails',
            'ipAddresses',
        ]);

        $payload = [
            'asset_type' => $asset->asset_type,
            'name' => $asset->name,
            'hostname' => $asset->hostname,
            'manufacturer_id' => $asset->manufacturer_id,
            'manufacturer' => $asset->manufacturer,
            'model_id' => $asset->model_id,
            'model' => $asset->model,
            'serial_number' => $asset->serial_number,
            'patrimony_number' => $asset->patrimony_number,
            'status' => $asset->status,
            'criticality' => $asset->criticality,
            'environment' => $asset->environment,
            'company_id' => $asset->company_id,
            'unit_id' => $asset->unit_id,
            'location' => $asset->location,
            'rack_id' => $asset->rack_id,
            'main_ip' => $asset->main_ip,
            'operating_system' => $asset->operating_system,
            'description' => $asset->description,
            'notes' => $asset->notes,
            'source_type' => $asset->source_type,
            'source_connector_id' => $asset->source_connector_id,
            'source_external_id' => $asset->source_external_id,
        ];

        if ($asset->serverDetails) {
            $payload = array_merge($payload, [
                'server_service_id' => $asset->serverDetails->service_id,
                'server_service_description' => $asset->serverDetails->service_description,
                'server_service_type' => $asset->serverDetails->service_type,
                'server_cpu' => $asset->serverDetails->cpu,
                'server_memory_gb' => $asset->serverDetails->memory_gb,
                'server_storage_total_gb' => $asset->serverDetails->storage_total_gb,
                'server_raid' => $asset->serverDetails->raid,
                'server_hypervisor' => $asset->serverDetails->hypervisor,
                'server_is_virtualized' => $asset->serverDetails->is_virtualized,
                'server_warranty_end_date' => $this->dateString($asset->serverDetails->warranty_end_date),
            ]);
        }

        if ($asset->virtualMachineDetails) {
            $payload = array_merge($payload, [
                'vm_host_asset_id' => $asset->virtualMachineDetails->host_asset_id,
                'vm_cpu_cores' => $asset->virtualMachineDetails->cpu_cores,
                'vm_memory_gb' => $asset->virtualMachineDetails->memory_gb,
                'vm_storage_gb' => $asset->virtualMachineDetails->storage_gb,
                'vm_hypervisor' => $asset->virtualMachineDetails->hypervisor,
                'vm_datastore' => $asset->virtualMachineDetails->datastore,
            ]);
        }

        if ($asset->databaseDetails) {
            $payload = array_merge($payload, [
                'db_server_asset_id' => $asset->databaseDetails->server_asset_id,
                'db_database_type' => $asset->databaseDetails->database_type,
                'db_version' => $asset->databaseDetails->version,
                'db_port' => $asset->databaseDetails->port,
                'db_instance_name' => $asset->databaseDetails->instance_name,
                'db_backup_enabled' => $asset->databaseDetails->backup_enabled,
                'db_replication_enabled' => $asset->databaseDetails->replication_enabled,
                'db_notes' => $asset->databaseDetails->notes,
            ]);
        }

        if ($asset->networkDeviceDetails) {
            $payload = array_merge($payload, [
                'network_device_type' => $asset->networkDeviceDetails->device_type,
                'network_firmware_version' => $asset->networkDeviceDetails->firmware_version,
                'network_ports_count' => $asset->networkDeviceDetails->ports_count,
                'network_poe_enabled' => $asset->networkDeviceDetails->poe_enabled,
                'network_stack_enabled' => $asset->networkDeviceDetails->stack_enabled,
                'network_management_ip' => $asset->networkDeviceDetails->management_ip,
            ]);
        }

        if ($asset->firewallDetails) {
            $payload = array_merge($payload, [
                'firewall_firewall_type' => $asset->firewallDetails->firewall_type,
                'firewall_firmware_version' => $asset->firewallDetails->firmware_version,
                'firewall_license_key' => $asset->firewallDetails->license_key,
                'firewall_license_expiration_date' => $this->dateString($asset->firewallDetails->license_expiration_date),
                'firewall_ha_enabled' => $asset->firewallDetails->ha_enabled,
                'firewall_vpn_enabled' => $asset->firewallDetails->vpn_enabled,
                'firewall_vpn_count' => $asset->firewallDetails->vpn_count,
            ]);
        }

        if ($asset->storageDetails) {
            $payload = array_merge($payload, [
                'storage_storage_type' => $asset->storageDetails->storage_type,
                'storage_total_capacity_tb' => $asset->storageDetails->total_capacity_tb,
                'storage_used_capacity_tb' => $asset->storageDetails->used_capacity_tb,
                'storage_raid' => $asset->storageDetails->raid,
                'storage_iscsi_enabled' => $asset->storageDetails->iscsi_enabled,
                'storage_nfs_enabled' => $asset->storageDetails->nfs_enabled,
                'storage_smb_enabled' => $asset->storageDetails->smb_enabled,
            ]);
        }

        if ($asset->upsDetails) {
            $payload = array_merge($payload, [
                'ups_power_va' => $asset->upsDetails->power_va,
                'ups_power_watts' => $asset->upsDetails->power_watts,
                'ups_battery_quantity' => $asset->upsDetails->battery_quantity,
                'ups_battery_type' => $asset->upsDetails->battery_type,
                'ups_installation_date' => $this->dateString($asset->upsDetails->installation_date),
                'ups_last_battery_replacement_date' => $this->dateString($asset->upsDetails->last_battery_replacement_date),
                'ups_next_battery_replacement_date' => $this->dateString($asset->upsDetails->next_battery_replacement_date),
                'ups_estimated_runtime_minutes' => $asset->upsDetails->estimated_runtime_minutes,
                'ups_warranty_end_date' => $this->dateString($asset->upsDetails->warranty_end_date),
            ]);
        }

        if ($asset->ipAddresses->first()) {
            $ip = $asset->ipAddresses->first();
            $payload = array_merge($payload, [
                'ip_principal' => $ip->ip_address,
                'mascara' => $ip->subnet_mask,
                'gateway' => $ip->gateway,
                'dns' => $ip->dns,
                'vlan' => $ip->vlan,
                'mac' => $ip->mac_address,
            ]);
        }

        return $payload;
    }

    private function assetPayload(array $data): array
    {
        return [
            'asset_type' => $data['asset_type'] ?? 'other',
            'name' => $data['name'] ?? null,
            'hostname' => $data['hostname'] ?? null,
            'description' => $data['description'] ?? null,
            'manufacturer_id' => $data['manufacturer_id'] ?? null,
            'manufacturer' => $data['manufacturer'] ?? null,
            'model_id' => $data['model_id'] ?? null,
            'model' => $data['model'] ?? null,
            'serial_number' => $data['serial_number'] ?? null,
            'patrimony_number' => $data['patrimony_number'] ?? null,
            'status' => $data['status'] ?? 'active',
            'criticality' => $data['criticality'] ?? 'medium',
            'environment' => $data['environment'] ?? 'production',
            'company_id' => $data['company_id'] ?? null,
            'unit_id' => $data['unit_id'] ?? null,
            'organization_unit_id' => $data['organization_unit_id'] ?? null,
            'organization_department_id' => $data['organization_department_id'] ?? null,
            'location' => $data['location'] ?? null,
            'rack_id' => $data['rack_id'] ?? null,
            'main_ip' => $data['main_ip'] ?? null,
            'operating_system' => $data['operating_system'] ?? null,
            'responsible_user_id' => $data['responsible_user_id'] ?? null,
            'notes' => $data['notes'] ?? null,
            'source_type' => $data['source_type'] ?? 'manual',
            'source_connector_id' => $data['source_connector_id'] ?? null,
            'source_external_id' => $data['source_external_id'] ?? null,
        ];
    }

    public function applyDependencies(CmdbAsset $asset, array $data): void
    {
        $this->detailService->sync($asset, $data);

        if (! blank($data['main_ip'] ?? null) && Schema::hasTable('cmdb_ip_addresses')) {
            CmdbIpAddress::query()
                ->where('asset_id', $asset->id)
                ->where('is_primary', true)
                ->update(['is_primary' => false]);

            CmdbIpAddress::query()->updateOrCreate(
                [
                    'asset_id' => $asset->id,
                    'ip_address' => $data['main_ip'],
                    'is_primary' => true,
                ],
                [
                    'subnet_mask' => $data['subnet_mask'] ?? null,
                    'gateway' => $data['gateway'] ?? null,
                    'dns' => $data['dns'] ?? null,
                    'vlan' => $data['vlan'] ?? null,
                    'mac_address' => $data['mac_address'] ?? null,
                    'notes' => $data['notes'] ?? null,
                ]
            );
        }

        if (($data['asset_type'] ?? null) === 'rack' && Schema::hasTable('cmdb_racks')) {
            CmdbRack::query()->updateOrCreate(
                ['name' => $asset->name],
                [
                    'unit_id' => $data['unit_id'] ?? null,
                    'organization_unit_id' => $data['organization_unit_id'] ?? null,
                    'organization_department_id' => $data['organization_department_id'] ?? null,
                    'location' => $data['location'] ?? null,
                    'room' => $data['rack_room'] ?? null,
                    'floor' => $data['rack_floor'] ?? null,
                    'capacity_u' => $data['rack_capacity_u'] ?? null,
                    'notes' => $data['notes'] ?? null,
                ]
            );
        }
    }

    private function dateString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof Carbon) {
            return $value->toDateString();
        }

        return (string) $value;
    }
}
