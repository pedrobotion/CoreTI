<?php

namespace App\Services;

use App\Models\CmdbAsset;
use App\Models\CmdbManufacturer;
use App\Models\CmdbModel;
use App\Models\CmdbRack;
use App\Models\Departamento;
use App\Models\Unidade;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class CmdbImportValidatorService
{
    private const TYPE_MAP = [
        'servidor' => 'server',
        'servidor fisico' => 'server',
        'máquina virtual' => 'virtual_machine',
        'maquina virtual' => 'virtual_machine',
        'banco de dados' => 'database',
        'switch' => 'switch',
        'equipamento de rede' => 'switch',
        'firewall' => 'firewall',
        'sonicwall' => 'sonicwall',
        'nobreak' => 'ups',
        'ups' => 'ups',
        'storage' => 'storage',
        'rack' => 'rack',
        'link' => 'link',
        'link de internet' => 'link',
        'circuito' => 'circuit',
        'certificado' => 'certificate',
        'certificado ssl' => 'certificate',
        'backup' => 'backup',
        'outro' => 'other',
    ];

    private const VALID_STATUSES = ['active', 'inactive', 'maintenance', 'decommissioned', 'planned'];
    private const VALID_CRITICALITIES = ['low', 'medium', 'high', 'critical'];
    private const VALID_ENVIRONMENTS = ['production', 'homologation', 'development', 'test'];

    public function normalizeType(?string $value): ?string
    {
        $value = $this->cleanText($value);
        if ($value === null) {
            return null;
        }

        $normalized = Str::of($value)->lower()->ascii()->replace(['_', '-', '.'], ' ')->squish()->toString();

        if (array_key_exists($normalized, self::TYPE_MAP)) {
            return self::TYPE_MAP[$normalized];
        }

        $reverse = array_search($normalized, self::TYPE_MAP, true);

        return $reverse !== false ? (string) $reverse : $normalized;
    }

    public function validateRow(array $row, array $options = [], int $rowNumber = 0): array
    {
        $messages = [];
        $warnings = [];
        $errors = [];

        $data = [
            'asset_type' => $this->normalizeType($row['tipo_ativo'] ?? $row['asset_type'] ?? null),
            'name' => $this->cleanText($row['nome'] ?? null),
            'hostname' => $this->cleanText($row['hostname'] ?? null),
            'manufacturer' => $this->cleanText($row['fabricante'] ?? $row['manufacturer'] ?? null),
            'model' => $this->cleanText($row['modelo'] ?? $row['model'] ?? null),
            'serial_number' => $this->cleanText($row['serial'] ?? $row['serial_number'] ?? null),
            'patrimony_number' => $this->cleanText($row['patrimonio'] ?? $row['patrimony_number'] ?? null),
            'status' => $this->normalizeEnum($row['status'] ?? null, self::VALID_STATUSES, 'active'),
            'criticality' => $this->normalizeEnum($row['criticidade'] ?? $row['criticality'] ?? null, self::VALID_CRITICALITIES, 'medium'),
            'environment' => $this->normalizeEnum($row['ambiente'] ?? $row['environment'] ?? null, self::VALID_ENVIRONMENTS, 'production'),
            'company_id' => $this->integerOrNull($row['company_id'] ?? null),
            'unit_id' => $this->resolveUnitId($row['unidade'] ?? $row['unit_id'] ?? null),
            'unit_name' => $this->cleanText($row['unidade'] ?? $row['unit_id'] ?? null),
            'location' => $this->cleanText($row['localizacao'] ?? $row['location'] ?? null),
            'rack_id' => $this->resolveRackId($row['rack'] ?? $row['rack_id'] ?? null, (bool) ($options['create_racks'] ?? false), $warnings, $errors),
            'rack_name' => $this->cleanText($row['rack'] ?? $row['rack_id'] ?? null),
            'main_ip' => $this->cleanText($row['ip_principal'] ?? $row['main_ip'] ?? null),
            'subnet_mask' => $this->cleanText($row['mascara'] ?? $row['subnet_mask'] ?? null),
            'gateway' => $this->cleanText($row['gateway'] ?? null),
            'dns' => $this->cleanText($row['dns'] ?? null),
            'vlan' => $this->cleanText($row['vlan'] ?? null),
            'mac_address' => $this->cleanText($row['mac'] ?? $row['mac_address'] ?? null),
            'operating_system' => $this->cleanText($row['sistema_operacional'] ?? $row['operating_system'] ?? null),
            'notes' => $this->cleanText($row['observacoes'] ?? $row['notes'] ?? null),
            'source_type' => $this->cleanText($row['source_type'] ?? null) ?? 'manual',
            'source_connector_id' => $this->integerOrNull($row['source_connector_id'] ?? null),
            'source_external_id' => $this->cleanText($row['source_external_id'] ?? null),
            'server_cpu' => $this->cleanText($row['cpu'] ?? null),
            'server_memory_gb' => $this->integerOrNull($row['memoria_gb'] ?? null),
            'server_storage_total_gb' => $this->integerOrNull($row['armazenamento_gb'] ?? null),
            'server_raid' => $this->cleanText($row['raid'] ?? null),
            'server_hypervisor' => $this->cleanText($row['hypervisor'] ?? null),
            'server_is_virtualized' => $this->booleanOrNull($row['is_virtualized'] ?? null),
            'server_warranty_end_date' => $this->dateOrNull($row['garantia_fim'] ?? $row['warranty_end_date'] ?? null),
            'vm_host_asset_id' => $this->integerOrNull($row['host_asset_id'] ?? null),
            'vm_cpu_cores' => $this->integerOrNull($row['cpu_cores'] ?? null),
            'vm_memory_gb' => $this->integerOrNull($row['vm_memory_gb'] ?? null),
            'vm_storage_gb' => $this->integerOrNull($row['storage_gb'] ?? null),
            'vm_hypervisor' => $this->cleanText($row['vm_hypervisor'] ?? null),
            'vm_datastore' => $this->cleanText($row['datastore'] ?? null),
            'db_server_asset_id' => $this->integerOrNull($row['server_asset_id'] ?? null),
            'db_database_type' => $this->cleanText($row['database_type'] ?? null),
            'db_version' => $this->cleanText($row['version'] ?? null),
            'db_port' => $this->integerOrNull($row['port'] ?? null),
            'db_instance_name' => $this->cleanText($row['instance_name'] ?? null),
            'db_backup_enabled' => $this->booleanOrNull($row['backup_enabled'] ?? null),
            'db_replication_enabled' => $this->booleanOrNull($row['replication_enabled'] ?? null),
            'db_notes' => $this->cleanText($row['db_notes'] ?? null),
            'network_device_type' => $this->cleanText($row['device_type'] ?? null),
            'network_firmware_version' => $this->cleanText($row['firmware'] ?? $row['firmware_version'] ?? null),
            'network_ports_count' => $this->integerOrNull($row['portas'] ?? $row['ports_count'] ?? null),
            'network_poe_enabled' => $this->booleanOrNull($row['poe'] ?? $row['poe_enabled'] ?? null),
            'network_stack_enabled' => $this->booleanOrNull($row['stack_enabled'] ?? null),
            'network_management_ip' => $this->cleanText($row['ip_gerenciamento'] ?? $row['management_ip'] ?? null),
            'firewall_firewall_type' => $this->cleanText($row['firewall_type'] ?? null),
            'firewall_firmware_version' => $this->cleanText($row['firewall_firmware'] ?? null),
            'firewall_license_key' => $this->cleanText($row['licenca'] ?? $row['license_key'] ?? null),
            'firewall_license_expiration_date' => $this->dateOrNull($row['expiracao_licenca'] ?? $row['license_expiration_date'] ?? null),
            'firewall_ha_enabled' => $this->booleanOrNull($row['ha_enabled'] ?? null),
            'firewall_vpn_enabled' => $this->booleanOrNull($row['vpn_habilitada'] ?? $row['vpn_enabled'] ?? null),
            'firewall_vpn_count' => $this->integerOrNull($row['vpn_count'] ?? null),
            'storage_storage_type' => $this->cleanText($row['storage_type'] ?? null),
            'storage_total_capacity_tb' => $this->decimalOrNull($row['capacidade_total_tb'] ?? $row['total_capacity_tb'] ?? null),
            'storage_used_capacity_tb' => $this->decimalOrNull($row['used_capacity_tb'] ?? null),
            'storage_raid' => $this->cleanText($row['storage_raid'] ?? null),
            'storage_iscsi_enabled' => $this->booleanOrNull($row['iscsi_enabled'] ?? null),
            'storage_nfs_enabled' => $this->booleanOrNull($row['nfs_enabled'] ?? null),
            'storage_smb_enabled' => $this->booleanOrNull($row['smb_enabled'] ?? null),
            'ups_power_va' => $this->integerOrNull($row['potencia_va'] ?? $row['power_va'] ?? null),
            'ups_power_watts' => $this->integerOrNull($row['potencia_watts'] ?? $row['power_watts'] ?? null),
            'ups_battery_quantity' => $this->integerOrNull($row['quantidade_baterias'] ?? $row['battery_quantity'] ?? null),
            'ups_battery_type' => $this->cleanText($row['battery_type'] ?? null),
            'ups_installation_date' => $this->dateOrNull($row['installation_date'] ?? null),
            'ups_last_battery_replacement_date' => $this->dateOrNull($row['ultima_troca_bateria'] ?? $row['last_battery_replacement_date'] ?? null),
            'ups_next_battery_replacement_date' => $this->dateOrNull($row['proxima_troca_bateria'] ?? $row['next_battery_replacement_date'] ?? null),
            'ups_estimated_runtime_minutes' => $this->integerOrNull($row['autonomia_minutos'] ?? $row['estimated_runtime_minutes'] ?? null),
            'ups_warranty_end_date' => $this->dateOrNull($row['garantia_fim'] ?? null),
            'copy_relationships' => $this->booleanOrNull($row['copy_relationships'] ?? null),
        ];

        if (blank($data['asset_type'])) {
            $errors[] = 'tipo_ativo é obrigatório.';
        }

        if (blank($data['name'])) {
            $errors[] = 'nome é obrigatório.';
        }

        if (! blank($data['main_ip']) && ! $this->validIp($data['main_ip'])) {
            $errors[] = 'IP principal inválido.';
        }

        if (! blank($data['mac_address']) && ! $this->validMac($data['mac_address'])) {
            $errors[] = 'MAC Address inválido.';
        }

        if (! blank($data['status']) && ! in_array($data['status'], self::VALID_STATUSES, true)) {
            $errors[] = 'Status inválido.';
        }

        if (! blank($data['criticality']) && ! in_array($data['criticality'], self::VALID_CRITICALITIES, true)) {
            $errors[] = 'Criticidade inválida.';
        }

        if (! blank($data['environment']) && ! in_array($data['environment'], self::VALID_ENVIRONMENTS, true)) {
            $errors[] = 'Ambiente inválido.';
        }

        if (! blank($data['manufacturer'])) {
            $manufacturer = $this->findManufacturer($data['manufacturer']);
            if ($manufacturer) {
                $data['manufacturer_id'] = $manufacturer->id;
                $data['manufacturer'] = $manufacturer->name;
            } elseif ($options['create_manufacturers'] ?? false) {
                $warnings[] = 'Fabricante inexistente e será criado.';
            } else {
                $errors[] = 'Fabricante não encontrado e criação automática desativada.';
            }
        }

        if (! blank($data['model'])) {
            $model = $this->findModel($data['model'], $data['manufacturer_id'] ?? null);
            if ($model) {
                $data['model_id'] = $model->id;
                $data['model'] = $model->name;
                $data['manufacturer_id'] = $data['manufacturer_id'] ?: $model->manufacturer_id;
            } elseif ($options['create_models'] ?? false) {
                $warnings[] = 'Modelo inexistente e será criado.';
            } else {
                $errors[] = 'Modelo não encontrado e criação automática desativada.';
            }
        }

        if (! blank($row['hostname'] ?? null) && $this->hasSimilarHostname($data['hostname'], null)) {
            $warnings[] = 'Hostname parecido já existe.';
        }

        $duplicate = $this->findDuplicateAsset($data);
        if ($duplicate) {
            if ($options['update_existing'] ?? false) {
                $data['existing_asset_id'] = $duplicate->id;
                $warnings[] = 'Ativo existente será atualizado.';
            } else {
                $errors[] = 'Duplicidade encontrada por hostname, serial, patrimônio ou IP.';
            }
        }

        if (! blank($data['rack_id']) && ! is_numeric($data['rack_id'])) {
            $warnings[] = 'Rack informado será resolvido ou criado na confirmação.';
        }

        $status = $errors ? 'error' : ($warnings ? 'warning' : 'valid');

        return [
            'row_number' => $rowNumber,
            'status' => $status,
            'action' => $data['existing_asset_id'] ?? null ? 'update' : 'create',
            'messages' => array_merge($warnings, $errors),
            'warnings' => $warnings,
            'errors' => $errors,
            'data' => $data,
        ];
    }

    public function normalizeHeaders(array $headers): array
    {
        $normalized = [];

        foreach ($headers as $header) {
            $normalized[] = $this->slugHeader((string) $header);
        }

        return $normalized;
    }

    public function rowFromSpreadsheet(array $headers, array $values): array
    {
        $row = [];

        foreach ($headers as $index => $header) {
            $row[$header] = $values[$index] ?? null;
        }

        return $row;
    }

    private function slugHeader(string $header): string
    {
        return Str::of($header)
            ->lower()
            ->ascii()
            ->replace(['/', '\\', '-', '.'], ' ')
            ->replaceMatches('/[^a-z0-9 ]+/', ' ')
            ->squish()
            ->replace(' ', '_')
            ->toString();
    }

    private function cleanText(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        if (is_numeric($value)) {
            return (string) $value;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function normalizeEnum(mixed $value, array $allowed, string $fallback): string
    {
        $value = $this->cleanText($value);
        if ($value === null) {
            return $fallback;
        }

        $normalized = Str::of($value)->lower()->ascii()->replace([' ', '-'], '_')->squish()->toString();
        $mapped = array_search($normalized, array_map(static fn ($item) => Str::of($item)->lower()->ascii()->replace([' ', '-'], '_')->squish()->toString(), $allowed), true);

        return $mapped === false ? $fallback : $allowed[$mapped];
    }

    private function integerOrNull(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return is_numeric($value) ? (int) $value : null;
    }

    private function decimalOrNull(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $value = str_replace(',', '.', trim((string) $value));

        return is_numeric($value) ? (string) $value : null;
    }

    private function booleanOrNull(mixed $value): ?bool
    {
        if ($value === null || $value === '') {
            return null;
        }

        return filter_var($value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);
    }

    private function dateOrNull(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            return \Illuminate\Support\Carbon::parse($value)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }

    private function validIp(?string $value): bool
    {
        return is_string($value) && filter_var($value, FILTER_VALIDATE_IP) !== false;
    }

    private function validMac(?string $value): bool
    {
        return is_string($value) && (bool) preg_match('/^([0-9A-F]{2}[:-]){5}[0-9A-F]{2}$/i', $value);
    }

    private function resolveUnitId(mixed $value): ?int
    {
        $value = $this->cleanText($value);
        if ($value === null) {
            return null;
        }

        if (is_numeric($value)) {
            return (int) $value;
        }

        if (Schema::hasTable('unidades')) {
            $unit = Unidade::query()->where('unidade', 'like', '%' . $value . '%')->first();
            if ($unit) {
                return (int) $unit->id_unidades;
            }
        }

        if (Schema::hasTable('departamentos')) {
            $department = Departamento::query()->where('nome', 'like', '%' . $value . '%')->first();
            if ($department) {
                return (int) $department->id;
            }
        }

        return null;
    }

    private function resolveRackId(mixed $value, bool $createAutomatically, array &$warnings, array &$errors): ?int
    {
        $value = $this->cleanText($value);
        if ($value === null) {
            return null;
        }

        if (is_numeric($value)) {
            if (Schema::hasTable('cmdb_racks') && CmdbRack::query()->whereKey((int) $value)->exists()) {
                return (int) $value;
            }

            if ($createAutomatically) {
                $warnings[] = 'Rack inexistente e será criado.';
                return null;
            }

            $errors[] = 'Rack informado não existe.';

            return null;
        }

        if (! Schema::hasTable('cmdb_racks')) {
            $errors[] = 'Tabela de racks indisponível.';

            return null;
        }

        $rack = CmdbRack::query()->where('name', 'like', '%' . $value . '%')->first();
        if ($rack) {
            return (int) $rack->id;
        }

        if ($createAutomatically) {
            $warnings[] = 'Rack inexistente e será criado.';

            return null;
        }

        $errors[] = 'Rack informado não existe.';

        return null;
    }

    private function findManufacturer(string $name): ?CmdbManufacturer
    {
        if (! Schema::hasTable('cmdb_manufacturers')) {
            return null;
        }

        return CmdbManufacturer::query()
            ->where(function ($query) use ($name): void {
                $query->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])
                    ->orWhereRaw('LOWER(slug) = ?', [mb_strtolower(Str::slug($name))]);
            })
            ->first();
    }

    private function findModel(string $name, ?int $manufacturerId = null): ?CmdbModel
    {
        if (! Schema::hasTable('cmdb_models')) {
            return null;
        }

        return CmdbModel::query()
            ->where(function ($query) use ($name, $manufacturerId): void {
                if ($manufacturerId) {
                    $query->where('manufacturer_id', $manufacturerId);
                }

                $query->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])
                    ->orWhereRaw('LOWER(slug) = ?', [mb_strtolower(Str::slug($name))]);
            })
            ->first();
    }

    private function hasSimilarHostname(?string $hostname, ?int $ignoreAssetId = null): bool
    {
        if (blank($hostname)) {
            return false;
        }

        return CmdbAsset::query()
            ->when($ignoreAssetId, static fn ($query) => $query->where('id', '!=', $ignoreAssetId))
            ->whereRaw('LOWER(hostname) LIKE ?', ['%' . mb_strtolower($hostname) . '%'])
            ->exists();
    }

    private function findDuplicateAsset(array $data): ?CmdbAsset
    {
        $query = CmdbAsset::query();

        $conditions = [];
        foreach (['hostname', 'serial_number', 'patrimony_number', 'main_ip'] as $field) {
            if (! blank($data[$field] ?? null)) {
                $conditions[] = [strtolower((string) $data[$field]), $field];
            }
        }

        if ($conditions === []) {
            return null;
        }

        $query->where(function ($subQuery) use ($conditions): void {
            foreach ($conditions as [$value, $field]) {
                $subQuery->orWhereRaw('LOWER(' . $field . ') = ?', [$value]);
            }
        });

        return $query->first();
    }
}
