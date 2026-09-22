<?php

namespace App\Services;

use App\Models\CmdbAsset;
use App\Models\CmdbConnector;
use App\Models\CmdbDiscoveredAsset;
use App\Models\CmdbSyncLog;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class CmdbDiscoveredAssetService
{
    public function __construct(
        private readonly CmdbAssetHistoryService $historyService,
    ) {
    }

    public function recordDiscovery(CmdbConnector $connector, ?CmdbSyncLog $syncLog, array $discovered, ?User $user = null): ?CmdbDiscoveredAsset
    {
        if (! Schema::hasTable('cmdb_discovered_assets')) {
            return null;
        }

        $payload = $this->normalizeDiscoveryPayload($connector, $discovered);
        $existing = CmdbDiscoveredAsset::query()->firstOrNew(['source_hash' => $payload['source_hash']]);
        $isNew = ! $existing->exists;

        $existing->fill($payload + [
            'connector_id' => $connector->id,
            'sync_log_id' => $syncLog?->id,
            'discovered_at' => $existing->discovered_at ?: now(),
        ]);

        if ($isNew) {
            $existing->status = 'pending';
        } elseif (! in_array($existing->status, ['pending', 'linked', 'created', 'ignored', 'duplicate', 'error'], true)) {
            $existing->status = 'pending';
        }

        $existing->save();

        return $existing->fresh(['connector', 'syncLog', 'asset', 'reviewedBy']);
    }

    public function suggestAssets(CmdbDiscoveredAsset $discoveredAsset): Collection
    {
        $hostname = trim((string) $discoveredAsset->hostname);
        $ipAddress = trim((string) $discoveredAsset->ip_address);
        $serialNumber = trim((string) $discoveredAsset->serial_number);
        $externalId = trim((string) $discoveredAsset->source_external_id);
        $manufacturer = trim((string) $discoveredAsset->manufacturer);
        $model = trim((string) $discoveredAsset->model);

        $matches = [];

        $register = static function (CmdbAsset $asset, string $reason) use (&$matches): void {
            if (! isset($matches[$asset->id])) {
                $matches[$asset->id] = [
                    'asset' => $asset,
                    'reasons' => [],
                ];
            }

            $matches[$asset->id]['reasons'][] = $reason;
        };

        if ($hostname !== '') {
            CmdbAsset::query()
                ->where(function ($query) use ($hostname): void {
                    $query->whereRaw('LOWER(hostname) = ?', [Str::lower($hostname)])
                        ->orWhereRaw('LOWER(name) = ?', [Str::lower($hostname)])
                        ->orWhereRaw('LOWER(hostname) LIKE ?', ['%' . Str::lower($hostname) . '%']);
                })
                ->limit(8)
                ->get()
                ->each(static function (CmdbAsset $asset) use ($register): void {
                    $register($asset, 'Hostname compatível');
                });
        }

        if ($ipAddress !== '') {
            CmdbAsset::query()
                ->where('main_ip', $ipAddress)
                ->orWhereHas('ipAddresses', static function ($query) use ($ipAddress): void {
                    $query->where('ip_address', $ipAddress);
                })
                ->limit(8)
                ->get()
                ->each(static function (CmdbAsset $asset) use ($register): void {
                    $register($asset, 'IP compatível');
                });
        }

        if ($serialNumber !== '') {
            CmdbAsset::query()
                ->whereRaw('LOWER(serial_number) = ?', [Str::lower($serialNumber)])
                ->limit(8)
                ->get()
                ->each(static function (CmdbAsset $asset) use ($register): void {
                    $register($asset, 'Serial compatível');
                });
        }

        if ($externalId !== '') {
            CmdbAsset::query()
                ->whereRaw('LOWER(source_external_id) = ?', [Str::lower($externalId)])
                ->limit(8)
                ->get()
                ->each(static function (CmdbAsset $asset) use ($register): void {
                    $register($asset, 'Identificador externo compatível');
                });
        }

        if ($manufacturer !== '' || $model !== '') {
            CmdbAsset::query()
                ->where(function ($query) use ($manufacturer, $model): void {
                    if ($manufacturer !== '' && $model !== '') {
                        $query->whereRaw("LOWER(COALESCE(manufacturer, '')) LIKE ?", ['%' . Str::lower($manufacturer) . '%'])
                            ->orWhereRaw("LOWER(COALESCE(model, '')) LIKE ?", ['%' . Str::lower($model) . '%']);
                    } elseif ($manufacturer !== '') {
                        $query->whereRaw("LOWER(COALESCE(manufacturer, '')) LIKE ?", ['%' . Str::lower($manufacturer) . '%']);
                    } elseif ($model !== '') {
                        $query->whereRaw("LOWER(COALESCE(model, '')) LIKE ?", ['%' . Str::lower($model) . '%']);
                    }
                })
                ->limit(8)
                ->get()
                ->each(static function (CmdbAsset $asset) use ($register): void {
                    $register($asset, 'Fabricante ou modelo compatível');
                });
        }

        return collect($matches)
            ->map(static fn (array $match): array => [
                'asset' => $match['asset'],
                'reasons' => array_values(array_unique($match['reasons'])),
                'score' => count(array_unique($match['reasons'])),
            ])
            ->sortByDesc('score')
            ->values();
    }

    public function markVCenterMissing(CmdbConnector $connector, \Illuminate\Support\Carbon $startedAt, array $objectTypes): int
    {
        if (! Schema::hasTable('cmdb_discovered_assets') || ! Schema::hasColumn('cmdb_discovered_assets', 'last_seen_at')) return 0;
        $sourceTypes = collect($objectTypes)->map(static fn (string $type): string => 'vcenter_' . $type)->all();
        if ($sourceTypes === []) return 0;

        $missing = CmdbDiscoveredAsset::query()
            ->where('connector_id', $connector->id)
            ->whereIn('source_type', $sourceTypes)
            ->where(function ($query) use ($startedAt): void {
                $query->whereNull('last_seen_at')->orWhere('last_seen_at', '<', $startedAt);
            })->get();

        foreach ($missing as $item) {
            $count = (int) $item->missing_count + 1;
            $item->forceFill([
                'missing_count' => $count,
                'source_missing_at' => $item->source_missing_at ?: now(),
                'status' => $count >= 2 && $item->status !== 'ignored' ? 'missing' : $item->status,
            ])->save();
        }

        return $missing->count();
    }

    public function link(CmdbDiscoveredAsset $discoveredAsset, CmdbAsset $asset, ?User $user = null, ?string $reviewNotes = null): CmdbDiscoveredAsset
    {
        return DB::transaction(function () use ($discoveredAsset, $asset, $user, $reviewNotes): CmdbDiscoveredAsset {
            $before = $asset->only(['source_type', 'source_connector_id', 'source_external_id', 'last_synced_at', 'sync_status', 'sync_error']);

            $this->applyAssetSource($asset, $discoveredAsset);

            $this->syncReviewedDiscovery($discoveredAsset, $asset, $user, 'linked', $reviewNotes ?: sprintf('Vinculado ao ativo #%d.', $asset->id));

            $this->historyService->record(
                $asset,
                $user,
                'discovery_linked',
                'Descoberta vinculada ao ativo',
                $reviewNotes ?: 'Descoberta SNMP vinculada ao ativo pela revisão.',
                $before,
                $asset->only(['source_type', 'source_connector_id', 'source_external_id', 'last_synced_at', 'sync_status', 'sync_error'])
            );

            return $discoveredAsset->fresh(['connector', 'syncLog', 'asset', 'reviewedBy']);
        });
    }

    public function markCreated(CmdbDiscoveredAsset $discoveredAsset, CmdbAsset $asset, ?User $user = null, ?string $reviewNotes = null): CmdbDiscoveredAsset
    {
        return DB::transaction(function () use ($discoveredAsset, $asset, $user, $reviewNotes): CmdbDiscoveredAsset {
            $before = $asset->only(['source_type', 'source_connector_id', 'source_external_id', 'last_synced_at', 'sync_status', 'sync_error']);

            $this->applyAssetSource($asset, $discoveredAsset);

            $this->syncReviewedDiscovery($discoveredAsset, $asset, $user, 'created', $reviewNotes ?: sprintf('Ativo criado a partir da descoberta SNMP e vinculado ao item #%d.', $discoveredAsset->id));

            $this->historyService->record(
                $asset,
                $user,
                'discovery_created',
                'Ativo criado a partir da descoberta',
                $reviewNotes ?: 'Ativo criado a partir da descoberta SNMP.',
                $before,
                $asset->only(['source_type', 'source_connector_id', 'source_external_id', 'last_synced_at', 'sync_status', 'sync_error'])
            );

            return $discoveredAsset->fresh(['connector', 'syncLog', 'asset', 'reviewedBy']);
        });
    }

    public function ignore(CmdbDiscoveredAsset $discoveredAsset, ?User $user = null, ?string $reviewNotes = null): CmdbDiscoveredAsset
    {
        $discoveredAsset->forceFill([
            'asset_id' => null,
            'status' => 'ignored',
            'reviewed_by' => $user?->id,
            'reviewed_at' => now(),
            'review_notes' => $reviewNotes ?: 'Descoberta ignorada.',
        ])->save();

        return $discoveredAsset->fresh(['connector', 'syncLog', 'asset', 'reviewedBy']);
    }

    public function reopen(CmdbDiscoveredAsset $discoveredAsset, ?User $user = null, ?string $reviewNotes = null): CmdbDiscoveredAsset
    {
        $discoveredAsset->forceFill([
            'asset_id' => null,
            'status' => 'pending',
            'reviewed_by' => $user?->id,
            'reviewed_at' => now(),
            'review_notes' => $reviewNotes ?: 'Descoberta reaberta para nova revisão.',
        ])->save();

        return $discoveredAsset->fresh(['connector', 'syncLog', 'asset', 'reviewedBy']);
    }

    private function applyAssetSource(CmdbAsset $asset, CmdbDiscoveredAsset $discoveredAsset): void
    {
        $sourceExternalId = trim((string) ($discoveredAsset->source_external_id ?: $discoveredAsset->object_id ?: $discoveredAsset->hostname));

        $asset->forceFill([
            'source_type' => $discoveredAsset->source_type ?: 'snmp',
            'source_connector_id' => $discoveredAsset->connector_id,
            'source_external_id' => $sourceExternalId !== '' ? $sourceExternalId : $asset->source_external_id,
            'last_synced_at' => now(),
            'sync_status' => 'synced',
            'sync_error' => null,
        ])->save();
    }

    private function syncReviewedDiscovery(CmdbDiscoveredAsset $discoveredAsset, CmdbAsset $asset, ?User $user, string $status, string $reviewNotes): void
    {
        $discoveredAsset->forceFill([
            'asset_id' => $asset->id,
            'status' => $status,
            'reviewed_by' => $user?->id,
            'reviewed_at' => now(),
            'review_notes' => $reviewNotes,
        ])->save();
    }

    private function normalizeDiscoveryPayload(CmdbConnector $connector, array $discovered): array
    {
        $sourceType = trim((string) Arr::get($discovered, 'source_type', $connector->connector_type ?: 'snmp'));
        $hostname = trim((string) Arr::get($discovered, 'hostname', ''));
        $ipAddress = trim((string) Arr::get($discovered, 'ip', ''));
        $objectId = trim((string) Arr::get($discovered, 'object_id', ''));
        $serialNumber = trim((string) Arr::get($discovered, 'serial_number', ''));
        $sourceExternalId = trim((string) Arr::get($discovered, 'external_id', $objectId ?: $hostname ?: $ipAddress));
        $macAddress = trim((string) Arr::get($discovered, 'mac_address', ''));
        $manufacturer = trim((string) Arr::get($discovered, 'manufacturer', ''));
        $model = trim((string) Arr::get($discovered, 'model', ''));
        $objectType = trim((string) Arr::get($discovered, 'object_type', Arr::get($discovered, 'payload.object_type', '')));

        $hash = trim((string) Arr::get($discovered, 'source_hash', ''));
        if ($hash === '') {
            $hash = sha1(implode('|', array_filter([
                Str::lower($sourceType),
                (string) $connector->id,
                Str::lower($sourceExternalId),
                Str::lower($hostname),
                Str::lower($ipAddress),
                Str::lower($macAddress),
                Str::lower($objectId),
                Str::lower($serialNumber),
            ], static fn ($value): bool => trim((string) $value) !== '')));
        }

        return [
            'connector_id' => $connector->id,
            'source_type' => $sourceType ?: 'snmp',
            'object_type' => $objectType !== '' ? $objectType : null,
            'source_external_id' => $sourceExternalId !== '' ? $sourceExternalId : null,
            'hostname' => $hostname !== '' ? $hostname : null,
            'ip_address' => $ipAddress !== '' ? $ipAddress : null,
            'mac_address' => $macAddress !== '' ? $macAddress : null,
            'manufacturer' => $manufacturer !== '' ? $manufacturer : null,
            'model' => $model !== '' ? $model : null,
            'serial_number' => $serialNumber !== '' ? $serialNumber : null,
            'description' => trim((string) Arr::get($discovered, 'description', '')) ?: null,
            'object_id' => $objectId !== '' ? $objectId : null,
            'uptime' => trim((string) Arr::get($discovered, 'uptime', '')) ?: null,
            'contact' => trim((string) Arr::get($discovered, 'contact', '')) ?: null,
            'location' => trim((string) Arr::get($discovered, 'location', '')) ?: null,
            'raw_data' => $discovered,
            'source_hash' => $hash,
            'content_hash' => hash('sha256', json_encode(Arr::get($discovered, 'payload', $discovered), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)),
            'last_seen_at' => now(),
            'source_missing_at' => null,
            'missing_count' => 0,
        ];
    }
}
