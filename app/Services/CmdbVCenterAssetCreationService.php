<?php

namespace App\Services;

use App\Models\CmdbAsset;
use App\Models\CmdbDiscoveredAsset;
use App\Models\CmdbIpAddress;
use App\Models\CmdbServer;
use App\Models\CmdbVirtualMachine;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;

class CmdbVCenterAssetCreationService
{
    public function __construct(
        private readonly CmdbAssetHistoryService $historyService,
    ) {
    }

    public function createPendingServers(?int $connectorId = null, ?int $limit = null): array
    {
        $query = CmdbDiscoveredAsset::query()
            ->whereIn('source_type', ['vcenter_virtual_machine', 'vcenter_host'])
            ->whereNull('asset_id')
            ->whereIn('status', ['pending', 'linked', 'created']);

        if ($connectorId !== null) {
            $query->where('connector_id', $connectorId);
        }

        if ($limit !== null && $limit > 0) {
            $query->limit($limit);
        }

        $summary = [
            'found' => 0,
            'created' => 0,
            'already_linked' => 0,
            'failed' => 0,
            'issues' => [],
        ];

        foreach ($query->orderBy('id')->get() as $discovery) {
            $summary['found']++;

            try {
                $asset = DB::transaction(function () use ($discovery): CmdbAsset {
                    $sourceType = (string) $discovery->source_type;
                    $isVirtualMachine = $sourceType === 'vcenter_virtual_machine';
                    $source = (array) Arr::get($discovery->raw_data, 'payload.source', []);
                    $name = trim((string) ($discovery->hostname ?: Arr::get($source, 'name', $discovery->source_external_id)));
                    $powerState = trim((string) Arr::get($source, 'power_state', '')) ?: null;
                    $assetStatus = mb_strtoupper((string) $powerState) === 'POWERED_OFF' ? 'inactive' : 'active';

                    $asset = CmdbAsset::withTrashed()
                        ->where('source_connector_id', $discovery->connector_id)
                        ->where('source_external_id', $discovery->source_external_id)
                        ->first();

                    $isNew = $asset === null;
                    if (! $asset) {
                        $asset = new CmdbAsset();
                    }

                    if ($asset->trashed()) {
                        $asset->restore();
                    }

                    $asset->fill([
                        'asset_type' => $isVirtualMachine ? 'virtual_machine' : 'server',
                        'name' => $name !== '' ? $name : 'Ativo vCenter #' . $discovery->id,
                        'hostname' => $name !== '' ? $name : null,
                        'description' => $discovery->description,
                        'manufacturer' => $discovery->manufacturer ?: ($isVirtualMachine ? 'VMware' : null),
                        'model' => $discovery->model ?: ($isVirtualMachine ? 'Virtual Machine' : 'ESXi Host'),
                        'serial_number' => $discovery->serial_number,
                        'status' => $assetStatus,
                        'criticality' => $asset->criticality ?: 'medium',
                        'environment' => $asset->environment ?: 'production',
                        'location' => $discovery->location,
                        'main_ip' => $discovery->ip_address,
                        'source_type' => 'vcenter',
                        'source_connector_id' => $discovery->connector_id,
                        'source_external_id' => $discovery->source_external_id,
                        'last_synced_at' => $discovery->last_seen_at ?: now(),
                        'sync_status' => 'synced',
                        'sync_error' => null,
                        'sync_hash' => $discovery->source_hash,
                    ])->save();

                    $memoryGb = $this->memoryGb($source);
                    $storageGb = $this->storageGb($source);
                    $cpu = Arr::get($source, 'cpu_count', Arr::get($source, 'cpu.count'));

                    CmdbServer::query()->updateOrCreate(
                        ['asset_id' => $asset->id],
                        [
                            'power_state' => $powerState,
                            'operational_status' => $isVirtualMachine ? $powerState : Arr::get($source, 'connection_state'),
                            'cpu' => $cpu !== null ? (string) $cpu : null,
                            'memory_gb' => $memoryGb,
                            'storage_total_gb' => $storageGb,
                            'hypervisor' => $isVirtualMachine ? 'VMware' : 'VMware ESXi',
                            'is_virtualized' => $isVirtualMachine,
                        ]
                    );

                    $this->syncAssetIps($asset, $discovery, $name);

                    if ($isVirtualMachine) {
                        CmdbVirtualMachine::query()->updateOrCreate(
                            ['asset_id' => $asset->id],
                            [
                                'cpu_cores' => $this->integerOrNull($cpu),
                                'memory_gb' => $memoryGb,
                                'storage_gb' => $storageGb,
                                'hypervisor' => 'VMware',
                            ]
                        );
                    }

                    $discovery->forceFill([
                        'asset_id' => $asset->id,
                        'status' => 'created',
                        'reviewed_at' => now(),
                        'review_notes' => $isVirtualMachine
                            ? 'Máquina virtual criada automaticamente a partir da descoberta do vCenter.'
                            : 'Servidor ESXi criado automaticamente a partir da descoberta do vCenter.',
                    ])->save();

                    $this->historyService->record(
                        $asset,
                        null,
                        'vcenter_asset_created',
                        $isVirtualMachine ? 'Máquina virtual criada pelo vCenter' : 'Servidor criado pelo vCenter',
                        sprintf('Ativo criado a partir da descoberta #%d do vCenter. O serviço deve ser informado manualmente quando aplicável.', $discovery->id),
                        [],
                        ['discovery_id' => $discovery->id, 'source_type' => $sourceType]
                    );

                    return $asset;
                });

                $summary['created']++;
            } catch (\Throwable $exception) {
                $summary['failed']++;
                $summary['issues'][] = [
                    'discovery_id' => $discovery->id,
                    'name' => $discovery->hostname ?: $discovery->source_external_id,
                    'message' => $exception->getMessage(),
                ];
            }
        }

        return $summary;
    }

    public function syncExistingServerIps(?int $connectorId = null): array
    {
        $query = CmdbDiscoveredAsset::query()
            ->whereIn('source_type', ['vcenter_virtual_machine', 'vcenter_host'])
            ->whereNotNull('asset_id');
        if ($connectorId !== null) $query->where('connector_id', $connectorId);

        $summary = ['found' => 0, 'updated' => 0, 'without_ip' => 0];
        foreach ($query->with('asset')->orderBy('id')->get() as $discovery) {
            $summary['found']++;
            if (! $discovery->asset) continue;
            $ips = $this->syncAssetIps($discovery->asset, $discovery, $discovery->asset->hostname);
            if ($ips->isEmpty()) $summary['without_ip']++; else $summary['updated']++;
        }

        return $summary;
    }

    private function syncAssetIps(CmdbAsset $asset, CmdbDiscoveredAsset $discovery, ?string $dns = null): Collection
    {
        $ips = collect((array) Arr::get($discovery->raw_data, 'payload.ips', []))
            ->map(static fn ($ip): string => trim((string) (is_array($ip) ? ($ip['ip_address'] ?? '') : $ip)))
            ->filter(static fn (string $ip): bool => filter_var($ip, FILTER_VALIDATE_IP) !== false)
            ->unique()
            ->values();

        foreach ($ips as $index => $ip) {
            $ipRecord = CmdbIpAddress::query()->firstOrNew(['asset_id' => $asset->id, 'ip_address' => $ip]);
            $ipRecord->fill([
                'dns' => $dns ?: $asset->hostname,
                'is_primary' => $index === 0,
                'notes' => 'IP coletado automaticamente pelo vCenter.',
            ])->save();
        }
        if ($ips->isNotEmpty()) {
            CmdbIpAddress::query()->where('asset_id', $asset->id)->whereNotIn('ip_address', [$ips->first()])->update(['is_primary' => false]);
            $asset->forceFill(['main_ip' => $ips->first()])->save();
        }

        return $ips;
    }

    private function memoryGb(array $source): ?int
    {
        $mib = Arr::get($source, 'memory_size_MiB', Arr::get($source, 'memory.size_MiB'));

        return is_numeric($mib) ? max(1, (int) round(((float) $mib) / 1024)) : null;
    }

    private function storageGb(array $source): ?int
    {
        $bytes = collect((array) Arr::get($source, 'disks', []))
            ->sum(static fn (array $disk): float => (float) Arr::get($disk, 'capacity', 0));

        return $bytes > 0 ? (int) round($bytes / 1024 / 1024 / 1024) : null;
    }

    private function integerOrNull(mixed $value): ?int
    {
        return is_numeric($value) ? (int) $value : null;
    }
}
