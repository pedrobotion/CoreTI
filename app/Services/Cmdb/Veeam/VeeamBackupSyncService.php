<?php

namespace App\Services\Cmdb\Veeam;

use App\Models\CmdbAssetBackup;
use App\Models\CmdbConnector;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class VeeamBackupSyncService
{
    public function __construct(
        private readonly VeeamApiClient $client,
        private readonly VeeamVmMatcherService $matcher,
        private readonly VeeamProtectionStatusService $protectionStatus,
    ) {
    }

    public function testConnection(CmdbConnector $connector): array
    {
        return $this->client->testConnection($connector);
    }

    public function sync(CmdbConnector $connector, array $options = []): array
    {
        $startedAt = now();
        $log = Schema::hasTable('cmdb_sync_logs') ? $connector->syncLogs()->create([
            'status' => 'running',
            'started_at' => $startedAt,
            'message' => 'Sincronização Veeam iniciada.',
        ]) : null;

        try {
            $records = $this->client->fetchProtectionData($connector, $options);
        } catch (\Throwable $exception) {
            $connector->update([
                'status' => 'error',
                'last_sync_at' => now(),
                'last_sync_status' => 'failed',
                'last_failed_sync_at' => now(),
                'last_error' => $exception->getMessage(),
            ]);
            $log?->update([
                'status' => 'failed',
                'finished_at' => now(),
                'duration_seconds' => $startedAt->diffInSeconds(now()),
                'message' => 'Sincronização Veeam falhou.',
                'error_message' => $exception->getMessage(),
            ]);
            throw $exception;
        }

        $summary = $this->persistRecords($connector, $records, $options);
        if (! ($options['dry_run'] ?? false)) {
            $connector->update([
                'status' => 'active',
                'last_sync_at' => now(),
                'last_sync_status' => 'success',
                'last_successful_sync_at' => now(),
                'last_error' => null,
            ]);
        }
        $log?->update([
            'status' => 'success',
            'finished_at' => now(),
            'duration_seconds' => $startedAt->diffInSeconds(now()),
            'assets_found' => $summary['found'] ?? 0,
            'assets_updated' => $summary['updated'] ?? 0,
            'assets_unchanged' => ($summary['ambiguous'] ?? 0) + ($summary['not_found'] ?? 0),
            'message' => 'Sincronização Veeam concluída.',
            'details' => $summary,
        ]);

        return $summary;
    }

    /**
     * Adapter seam for the version-specific official API implementation.
     * It is intentionally public so the API adapter can be tested without a live Veeam server.
     */
    public function persistRecords(CmdbConnector $connector, array $records, array $options = []): array
    {
        $summary = ['found' => count($records), 'matched' => 0, 'ambiguous' => 0, 'not_found' => 0, 'updated' => 0];

        if (($options['dry_run'] ?? false) === true) {
            return $summary;
        }

        DB::transaction(function () use ($connector, $records, &$summary): void {
            foreach ($records as $record) {
                $match = $this->matcher->match($record);
                if ($match['status'] !== 'matched') {
                    $summary[$match['status']] = ($summary[$match['status']] ?? 0) + 1;
                    continue;
                }

                $summary['matched']++;
                $asset = $match['asset'];
                $status = $this->protectionStatus->calculate(array_merge($record, ['match_status' => $match['status']]), $asset);
                $payload = array_merge($record, [
                    'asset_id' => $asset->id,
                    'connector_id' => $connector->id,
                    'platform' => 'veeam',
                    'match_method' => $match['method'],
                    'match_confidence' => $match['confidence'],
                    'protection_status' => $status['status'],
                    'is_protected' => $status['is_protected'],
                    'last_synced_at' => now(),
                ]);
                unset($payload['api_status'], $payload['match_status']);
                CmdbAssetBackup::updateOrCreate(
                    ['connector_id' => $connector->id, 'asset_id' => $asset->id],
                    $payload
                );
                $summary['updated']++;
            }
        });

        return $summary;
    }
}
