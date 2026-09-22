<?php

namespace App\Services;

use App\Models\CmdbAsset;
use App\Models\CmdbIpAddress;
use App\Models\User;

class CmdbServerIpSyncService
{
    public function __construct(
        private readonly CmdbAssetHistoryService $historyService,
    ) {
    }

    public function sync(
        string $subnetMask = '255.255.255.0',
        string $gateway = '172.17.255.254',
        ?User $user = null
    ): array {
        $summary = [
            'total_hosts' => 0,
            'resolved' => 0,
            'created' => 0,
            'updated' => 0,
            'unchanged' => 0,
            'unresolved' => [],
        ];

        $assets = CmdbAsset::query()
            ->whereIn('asset_type', ['server', 'virtual_machine'])
            ->whereNotNull('hostname')
            ->where('hostname', '<>', '')
            ->orderBy('name')
            ->get();

        foreach ($assets as $asset) {
            $summary['total_hosts']++;

            $ipAddress = gethostbyname($asset->hostname);
            if ($ipAddress === $asset->hostname || ! filter_var($ipAddress, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                $summary['unresolved'][] = [
                    'asset_id' => $asset->id,
                    'name' => $asset->name,
                    'hostname' => $asset->hostname,
                ];

                continue;
            }

            $summary['resolved']++;

            $existing = CmdbIpAddress::query()
                ->where('asset_id', $asset->id)
                ->where('is_primary', true)
                ->first();

            if (! $existing) {
                $existing = CmdbIpAddress::query()
                    ->where('asset_id', $asset->id)
                    ->where('ip_address', $ipAddress)
                    ->first();
            }

            $payload = [
                'asset_id' => $asset->id,
                'ip_address' => $ipAddress,
                'subnet_mask' => $subnetMask,
                'gateway' => $gateway,
                'dns' => $asset->hostname,
                'is_primary' => true,
                'notes' => 'IP resolvido automaticamente pelo hostname do servidor.',
            ];

            if (! $existing) {
                CmdbIpAddress::query()
                    ->where('asset_id', $asset->id)
                    ->update(['is_primary' => false]);

                $created = CmdbIpAddress::create($payload);
                $asset->update(['main_ip' => $ipAddress]);
                $summary['created']++;

                $this->historyService->record(
                    $asset,
                    $user,
                    'ip_added',
                    'IP principal resolvido automaticamente',
                    "Hostname {$asset->hostname} resolvido para {$ipAddress}.",
                    [],
                    $created->toArray()
                );

                continue;
            }

            $before = $existing->toArray();
            $changed = false;

            foreach ($payload as $field => $value) {
                if ($existing->{$field} !== $value) {
                    $existing->{$field} = $value;
                    $changed = true;
                }
            }

            if ($changed) {
                CmdbIpAddress::query()
                    ->where('asset_id', $asset->id)
                    ->where('id', '!=', $existing->id)
                    ->update(['is_primary' => false]);

                $existing->save();
                $asset->update(['main_ip' => $ipAddress]);
                $summary['updated']++;

                $this->historyService->record(
                    $asset,
                    $user,
                    'ip_updated',
                    'IP principal atualizado automaticamente',
                    "Hostname {$asset->hostname} resolvido para {$ipAddress}.",
                    $before,
                    $existing->fresh()->toArray()
                );
            } else {
                if ($asset->main_ip !== $ipAddress) {
                    $asset->update(['main_ip' => $ipAddress]);
                }

                $summary['unchanged']++;
            }
        }

        return $summary;
    }
}
