<?php

namespace App\Services;

use App\Models\CmdbAsset;
use App\Models\CmdbDatabase;
use App\Models\CmdbExpiration;
use App\Models\CmdbFirewall;
use App\Models\CmdbNetworkDevice;
use App\Models\CmdbServer;
use App\Models\CmdbStorage;
use App\Models\CmdbUps;
use App\Models\CmdbVirtualMachine;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Carbon;

class CmdbAssetDetailService
{
    public function formDetails(?CmdbAsset $asset): array
    {
        return [
            'server' => $this->serverDetails($asset),
            'virtual_machine' => $this->virtualMachineDetails($asset),
            'database' => $this->databaseDetails($asset),
            'network_device' => $this->networkDeviceDetails($asset),
            'firewall' => $this->firewallDetails($asset),
            'storage' => $this->storageDetails($asset),
            'ups' => $this->upsDetails($asset),
            'sync' => $this->syncDetails($asset),
        ];
    }

    public function sync(CmdbAsset $asset, array $data): void
    {
        match ($asset->asset_type) {
            'server' => $this->syncServer($asset, $data),
            'virtual_machine' => $this->syncVirtualMachineWithOperationalData($asset, $data),
            'database' => $this->syncDatabase($asset, $data),
            'switch' => $this->syncNetworkDevice($asset, $data),
            'firewall', 'sonicwall' => $this->syncFirewall($asset, $data),
            'storage' => $this->syncStorage($asset, $data),
            'ups' => $this->syncUps($asset, $data),
            default => null,
        };
    }

    private function serverDetails(?CmdbAsset $asset): array
    {
        $details = $asset?->serverDetails;

        return [
            'service_id' => $details?->service_id,
            'power_state' => $details?->power_state,
            'operational_status' => $details?->operational_status,
            'cpu' => $details?->cpu,
            'memory_gb' => $details?->memory_gb,
            'storage_total_gb' => $details?->storage_total_gb,
            'used_space_gb' => $details?->used_space_gb,
            'service_description' => $details?->service_description,
            'company_name' => $details?->company_name,
            'service_type' => $details?->service_type,
            'backup_policy' => $details?->backup_policy,
            'backup_retention' => $details?->backup_retention,
            'web_publication' => $details?->web_publication,
            'raid' => $details?->raid,
            'hypervisor' => $details?->hypervisor,
            'is_virtualized' => (bool) ($details?->is_virtualized ?? false),
            'warranty_start_date' => $this->formatDate($details?->warranty_start_date),
            'warranty_end_date' => $this->formatDate($details?->warranty_end_date),
        ];
    }

    private function virtualMachineDetails(?CmdbAsset $asset): array
    {
        $details = $asset?->virtualMachineDetails;

        return [
            'host_asset_id' => $details?->host_asset_id,
            'cpu_cores' => $details?->cpu_cores,
            'memory_gb' => $details?->memory_gb,
            'storage_gb' => $details?->storage_gb,
            'hypervisor' => $details?->hypervisor,
            'datastore' => $details?->datastore,
        ];
    }

    private function databaseDetails(?CmdbAsset $asset): array
    {
        $details = $asset?->databaseDetails;

        return [
            'server_asset_id' => $details?->server_asset_id,
            'database_type' => $details?->database_type,
            'version' => $details?->version,
            'port' => $details?->port,
            'instance_name' => $details?->instance_name,
            'backup_enabled' => (bool) ($details?->backup_enabled ?? false),
            'replication_enabled' => (bool) ($details?->replication_enabled ?? false),
            'notes' => $details?->notes,
        ];
    }

    private function networkDeviceDetails(?CmdbAsset $asset): array
    {
        $details = $asset?->networkDeviceDetails;

        return [
            'device_type' => $details?->device_type,
            'firmware_version' => $details?->firmware_version,
            'ports_count' => $details?->ports_count,
            'poe_enabled' => (bool) ($details?->poe_enabled ?? false),
            'stack_enabled' => (bool) ($details?->stack_enabled ?? false),
            'management_ip' => $details?->management_ip,
        ];
    }

    private function firewallDetails(?CmdbAsset $asset): array
    {
        $details = $asset?->firewallDetails;

        return [
            'firewall_type' => $details?->firewall_type,
            'firmware_version' => $details?->firmware_version,
            'license_key' => $details?->license_key,
            'license_expiration_date' => $this->formatDate($details?->license_expiration_date),
            'ha_enabled' => (bool) ($details?->ha_enabled ?? false),
            'vpn_enabled' => (bool) ($details?->vpn_enabled ?? false),
            'vpn_count' => $details?->vpn_count,
        ];
    }

    private function storageDetails(?CmdbAsset $asset): array
    {
        $details = $asset?->storageDetails;

        return [
            'storage_type' => $details?->storage_type,
            'total_capacity_tb' => $details?->total_capacity_tb,
            'used_capacity_tb' => $details?->used_capacity_tb,
            'raid' => $details?->raid,
            'iscsi_enabled' => (bool) ($details?->iscsi_enabled ?? false),
            'nfs_enabled' => (bool) ($details?->nfs_enabled ?? false),
            'smb_enabled' => (bool) ($details?->smb_enabled ?? false),
        ];
    }

    private function upsDetails(?CmdbAsset $asset): array
    {
        $details = $asset?->upsDetails;

        return [
            'power_va' => $details?->power_va,
            'power_watts' => $details?->power_watts,
            'battery_quantity' => $details?->battery_quantity,
            'battery_type' => $details?->battery_type,
            'installation_date' => $this->formatDate($details?->installation_date),
            'last_battery_replacement_date' => $this->formatDate($details?->last_battery_replacement_date),
            'next_battery_replacement_date' => $this->formatDate($details?->next_battery_replacement_date),
            'estimated_runtime_minutes' => $details?->estimated_runtime_minutes,
            'warranty_start_date' => $this->formatDate($details?->warranty_start_date),
            'warranty_end_date' => $this->formatDate($details?->warranty_end_date),
            'rack_id' => $details?->rack_id,
        ];
    }

    private function syncDetails(?CmdbAsset $asset): array
    {
        $connectorName = null;
        if ($asset && Schema::hasTable('cmdb_connectors')) {
            $connectorName = $asset->sourceConnector?->name;
        }

        return [
            'source_type' => $asset?->source_type,
            'source_connector_id' => $asset?->source_connector_id,
            'source_connector_name' => $connectorName,
            'source_external_id' => $asset?->source_external_id,
            'last_synced_at' => $this->formatDateTime($asset?->last_synced_at),
            'sync_status' => $asset?->sync_status,
            'sync_error' => $asset?->sync_error,
            'sync_hash' => $asset?->sync_hash,
        ];
    }

    private function syncServer(CmdbAsset $asset, array $data): void
    {
        $details = CmdbServer::query()->updateOrCreate(
            ['asset_id' => $asset->id],
            [
                'service_id' => $this->integerOrNull($data['server_service_id'] ?? null),
                'power_state' => $this->stringOrNull($data['server_power_state'] ?? null),
                'operational_status' => $this->stringOrNull($data['server_operational_status'] ?? null),
                'cpu' => $this->stringOrNull($data['server_cpu'] ?? null),
                'memory_gb' => $this->integerOrNull($data['server_memory_gb'] ?? null),
                'storage_total_gb' => $this->integerOrNull($data['server_storage_total_gb'] ?? null),
                'used_space_gb' => $this->decimalOrNull($data['server_used_space_gb'] ?? null),
                'service_description' => $this->stringOrNull($data['server_service_description'] ?? null),
                'company_name' => $this->stringOrNull($data['server_company_name'] ?? null),
                'service_type' => $this->stringOrNull($data['server_service_type'] ?? null),
                'backup_policy' => $this->stringOrNull($data['server_backup_policy'] ?? null),
                'backup_retention' => $this->stringOrNull($data['server_backup_retention'] ?? null),
                'web_publication' => $this->booleanOrNull($data['server_web_publication'] ?? null),
                'raid' => $this->stringOrNull($data['server_raid'] ?? null),
                'hypervisor' => $this->stringOrNull($data['server_hypervisor'] ?? null),
                'is_virtualized' => $this->booleanOrFalse($data['server_is_virtualized'] ?? null),
                'warranty_start_date' => $this->dateOrNull($data['server_warranty_start_date'] ?? null),
                'warranty_end_date' => $this->dateOrNull($data['server_warranty_end_date'] ?? null),
            ]
        );

        $this->syncExpiration($asset, 'warranty', 'Garantia do servidor', $details->warranty_end_date, 30, 'Garantia registrada automaticamente a partir do cadastro do servidor.');
    }

    private function syncVirtualMachine(CmdbAsset $asset, array $data): void
    {
        CmdbVirtualMachine::query()->updateOrCreate(
            ['asset_id' => $asset->id],
            [
                'host_asset_id' => $this->integerOrNull($data['vm_host_asset_id'] ?? null),
                'cpu_cores' => $this->integerOrNull($data['vm_cpu_cores'] ?? null),
                'memory_gb' => $this->integerOrNull($data['vm_memory_gb'] ?? null),
                'storage_gb' => $this->integerOrNull($data['vm_storage_gb'] ?? null),
                'hypervisor' => $this->stringOrNull($data['vm_hypervisor'] ?? null),
                'datastore' => $this->stringOrNull($data['vm_datastore'] ?? null),
            ]
        );
    }

    private function syncVirtualMachineWithOperationalData(CmdbAsset $asset, array $data): void
    {
        $this->syncVirtualMachine($asset, $data);
        $this->syncServer($asset, $data);
    }

    private function syncDatabase(CmdbAsset $asset, array $data): void
    {
        CmdbDatabase::query()->updateOrCreate(
            ['asset_id' => $asset->id],
            [
                'server_asset_id' => $this->integerOrNull($data['db_server_asset_id'] ?? null),
                'database_type' => $this->stringOrNull($data['db_database_type'] ?? null),
                'version' => $this->stringOrNull($data['db_version'] ?? null),
                'port' => $this->integerOrNull($data['db_port'] ?? null),
                'instance_name' => $this->stringOrNull($data['db_instance_name'] ?? null),
                'backup_enabled' => $this->booleanOrFalse($data['db_backup_enabled'] ?? null),
                'replication_enabled' => $this->booleanOrFalse($data['db_replication_enabled'] ?? null),
                'notes' => $this->stringOrNull($data['db_notes'] ?? null),
            ]
        );
    }

    private function syncNetworkDevice(CmdbAsset $asset, array $data): void
    {
        CmdbNetworkDevice::query()->updateOrCreate(
            ['asset_id' => $asset->id],
            [
                'device_type' => $this->stringOrNull($data['network_device_type'] ?? null),
                'firmware_version' => $this->stringOrNull($data['network_firmware_version'] ?? null),
                'ports_count' => $this->integerOrNull($data['network_ports_count'] ?? null),
                'poe_enabled' => $this->booleanOrFalse($data['network_poe_enabled'] ?? null),
                'stack_enabled' => $this->booleanOrFalse($data['network_stack_enabled'] ?? null),
                'management_ip' => $this->stringOrNull($data['network_management_ip'] ?? null),
            ]
        );
    }

    private function syncFirewall(CmdbAsset $asset, array $data): void
    {
        $details = CmdbFirewall::query()->updateOrCreate(
            ['asset_id' => $asset->id],
            [
                'firewall_type' => $this->stringOrNull($data['firewall_firewall_type'] ?? null),
                'firmware_version' => $this->stringOrNull($data['firewall_firmware_version'] ?? null),
                'license_key' => $this->stringOrNull($data['firewall_license_key'] ?? null),
                'license_expiration_date' => $this->dateOrNull($data['firewall_license_expiration_date'] ?? null),
                'ha_enabled' => $this->booleanOrFalse($data['firewall_ha_enabled'] ?? null),
                'vpn_enabled' => $this->booleanOrFalse($data['firewall_vpn_enabled'] ?? null),
                'vpn_count' => $this->integerOrNull($data['firewall_vpn_count'] ?? null),
            ]
        );

        $this->syncExpiration($asset, 'license', 'Licença do firewall', $details->license_expiration_date, 30, 'Licença do firewall registrada automaticamente.');
    }

    private function syncStorage(CmdbAsset $asset, array $data): void
    {
        CmdbStorage::query()->updateOrCreate(
            ['asset_id' => $asset->id],
            [
                'storage_type' => $this->stringOrNull($data['storage_storage_type'] ?? null),
                'total_capacity_tb' => $this->decimalOrNull($data['storage_total_capacity_tb'] ?? null),
                'used_capacity_tb' => $this->decimalOrNull($data['storage_used_capacity_tb'] ?? null),
                'raid' => $this->stringOrNull($data['storage_raid'] ?? null),
                'iscsi_enabled' => $this->booleanOrFalse($data['storage_iscsi_enabled'] ?? null),
                'nfs_enabled' => $this->booleanOrFalse($data['storage_nfs_enabled'] ?? null),
                'smb_enabled' => $this->booleanOrFalse($data['storage_smb_enabled'] ?? null),
            ]
        );
    }

    private function syncUps(CmdbAsset $asset, array $data): void
    {
        $details = CmdbUps::query()->updateOrCreate(
            ['asset_id' => $asset->id],
            [
                'power_va' => $this->integerOrNull($data['ups_power_va'] ?? null),
                'power_watts' => $this->integerOrNull($data['ups_power_watts'] ?? null),
                'battery_quantity' => $this->integerOrNull($data['ups_battery_quantity'] ?? null),
                'battery_type' => $this->stringOrNull($data['ups_battery_type'] ?? null),
                'installation_date' => $this->dateOrNull($data['ups_installation_date'] ?? null),
                'last_battery_replacement_date' => $this->dateOrNull($data['ups_last_battery_replacement_date'] ?? null),
                'next_battery_replacement_date' => $this->dateOrNull($data['ups_next_battery_replacement_date'] ?? null),
                'estimated_runtime_minutes' => $this->integerOrNull($data['ups_estimated_runtime_minutes'] ?? null),
                'warranty_start_date' => $this->dateOrNull($data['ups_warranty_start_date'] ?? null),
                'warranty_end_date' => $this->dateOrNull($data['ups_warranty_end_date'] ?? null),
                'rack_id' => $this->integerOrNull($data['ups_rack_id'] ?? $data['rack_id'] ?? null),
            ]
        );

        $this->syncExpiration($asset, 'warranty', 'Garantia do nobreak', $details->warranty_end_date, 30, 'Garantia do nobreak registrada automaticamente.');
        $this->syncExpiration($asset, 'ups_battery', 'Próxima troca das baterias', $details->next_battery_replacement_date, 30, 'Vencimento da próxima troca das baterias registrado automaticamente.');
    }

    private function syncExpiration(CmdbAsset $asset, string $type, string $title, mixed $date, int $alertDaysBefore, ?string $description = null): void
    {
        $expiration = CmdbExpiration::query()
            ->where('asset_id', $asset->id)
            ->where('expiration_type', $type)
            ->where('title', $title)
            ->first();

        if (blank($date)) {
            if ($expiration) {
                $expiration->update([
                    'status' => 'cancelled',
                    'description' => $description ?? $expiration->description,
                ]);
            }

            return;
        }

        $dateValue = $this->dateOrNull($date);

        if (blank($dateValue)) {
            return;
        }

        CmdbExpiration::query()->updateOrCreate(
            [
                'asset_id' => $asset->id,
                'expiration_type' => $type,
                'title' => $title,
            ],
            [
                'description' => $description,
                'expiration_date' => $dateValue,
                'alert_days_before' => $alertDaysBefore,
                'status' => 'pending',
            ]
        );
    }

    private function stringOrNull(mixed $value): ?string
    {
        $value = is_string($value) ? trim($value) : $value;

        return filled($value) ? (string) $value : null;
    }

    private function integerOrNull(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_numeric($value)) {
            return (int) $value;
        }

        return null;
    }

    private function decimalOrNull(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $normalized = str_replace(',', '.', (string) $value);

        return is_numeric($normalized) ? (string) $normalized : null;
    }

    private function booleanOrFalse(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        return filter_var($value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) ?? false;
    }

    private function booleanOrNull(mixed $value): ?bool
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_bool($value)) {
            return $value;
        }

        return filter_var($value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);
    }

    private function dateOrNull(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            return Carbon::parse($value)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }

    private function formatDate(mixed $value): ?string
    {
        if (blank($value)) {
            return null;
        }

        try {
            return Carbon::parse($value)->toDateString();
        } catch (\Throwable) {
            return is_string($value) ? $value : null;
        }
    }

    private function formatDateTime(mixed $value): ?string
    {
        if (blank($value)) {
            return null;
        }

        try {
            return Carbon::parse($value)->format('d/m/Y H:i');
        } catch (\Throwable) {
            return is_string($value) ? $value : null;
        }
    }
}
