<?php

namespace App\Services;

use App\Models\CmdbAsset;
use App\Models\CmdbConnector;
use App\Models\CmdbSyncJob;
use App\Models\CmdbSyncLog;
use App\Models\User;
use App\Services\CmdbDiscoveredAssetService;
use App\Services\CmdbDiscoveryQueryService;
use App\Services\Cmdb\Discovery\DiscoveredAssetData;
use App\Services\Cmdb\Discovery\DiscoveryEngine;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Throwable;

class CmdbSyncService
{
    public function __construct(
        private readonly CmdbAssetHistoryService $historyService,
        private readonly CmdbDiscoveredAssetService $discoveredAssetService,
        private readonly DiscoveryEngine $discoveryEngine,
        private readonly CoreTiSyncNotificationService $notificationService,
    ) {
    }

    public function testConnection(CmdbConnector $connector, ?User $user = null): array
    {
        if (! Schema::hasTable('cmdb_sync_logs')) {
            return [
                'status' => 'failed',
                'message' => 'Estrutura de logs do CMDB ainda não está disponível.',
                'data' => [],
            ];
        }

        $startedAt = now();
        $log = CmdbSyncLog::create([
            'connector_id' => $connector->id,
            'status' => 'running',
            'started_at' => $startedAt,
            'assets_found' => 0,
            'assets_created' => 0,
            'assets_updated' => 0,
            'assets_unchanged' => 0,
            'assets_failed' => 0,
            'message' => 'Teste de conexão iniciado.',
        ]);

        try {
            $result = $this->discoveryEngine->testConnection($connector);
            $finishedAt = now();

            $log->update([
                'status' => $result->status === 'failed' ? 'failed' : 'success',
                'finished_at' => $finishedAt,
                'duration_seconds' => $startedAt->diffInSeconds($finishedAt),
                'message' => $result->message ?: ($result->status === 'failed' ? 'Teste falhou.' : 'Teste concluído com sucesso.'),
                'error_message' => $result->status === 'failed' ? ($result->data['error'] ?? $result->message) : null,
                'details' => [
                    'connector' => [
                        'id' => $connector->id,
                        'name' => $connector->name,
                        'type' => $connector->connector_type,
                    ],
                    'test' => $result->toArray(),
                ],
            ]);

            $this->updateConnectorState(
                $connector,
                $result->status === 'failed' ? 'error' : 'active',
                $result->status,
                $result->status === 'failed' ? ($result->data['error'] ?? $result->message) : null,
                $finishedAt
            );
            $connector->forceFill([
                'last_connection_test_at' => now(),
                'last_connection_test_status' => $result->status === 'failed' ? 'failed' : 'success',
                'last_connection_test_message' => $result->message,
            ])->save();

            return [
                'status' => $result->status === 'failed' ? 'failed' : 'success',
                'message' => $result->message,
                'data' => $result->data,
                'log' => $log->fresh(['connector', 'syncJob']),
            ];
        } catch (Throwable $throwable) {
            $this->updateConnectorState($connector, 'error', 'failed', $throwable->getMessage(), now());
            $connector->forceFill([
                'last_connection_test_at' => now(),
                'last_connection_test_status' => 'failed',
                'last_connection_test_message' => 'Falha no teste de conexão.',
            ])->save();

            $log->update([
                'status' => 'failed',
                'finished_at' => now(),
                'duration_seconds' => $startedAt->diffInSeconds(now()),
                'assets_failed' => 1,
                'error_message' => $throwable->getMessage(),
                'message' => 'Falha no teste de conexão.',
            ]);

            return [
                'status' => 'failed',
                'message' => $throwable->getMessage(),
                'data' => [],
                'log' => $log->fresh(['connector', 'syncJob']),
            ];
        }
    }

    public function discover(CmdbConnector $connector): array
    {
        return $this->discoveryEngine->discover($connector)->toArray();
    }

    public function syncConnector(CmdbConnector $connector, ?User $user = null, ?CmdbSyncJob $job = null): array
    {
        if (! Schema::hasTable('cmdb_assets') || ! Schema::hasTable('cmdb_sync_logs')) {
            return [
                'status' => 'failed',
                'message' => 'Estrutura do CMDB ainda não está disponível.',
                'summary' => [],
            ];
        }

        $requiredAssetColumns = ['source_type', 'source_connector_id', 'source_external_id', 'last_synced_at', 'sync_status', 'sync_error', 'sync_hash'];
        foreach ($requiredAssetColumns as $column) {
            if (! Schema::hasColumn('cmdb_assets', $column)) {
                return [
                    'status' => 'failed',
                    'message' => 'As colunas de sincronização do CMDB ainda não foram criadas.',
                    'summary' => [],
                ];
            }
        }

        $startedAt = now();
        $log = CmdbSyncLog::create([
            'connector_id' => $connector->id,
            'sync_job_id' => $job?->id,
            'status' => 'running',
            'started_at' => $startedAt,
            'assets_found' => 0,
            'assets_created' => 0,
            'assets_updated' => 0,
            'assets_unchanged' => 0,
            'assets_failed' => 0,
            'message' => 'Sincronização iniciada.',
        ]);

        try {
            $discovery = $this->discoveryEngine->sync($connector);
            $items = array_values((array) $discovery->items);
            $summary = [
                'assets_found' => count($items),
                'assets_created' => 0,
                'assets_updated' => 0,
                'assets_unchanged' => 0,
                // Enviado para a fila de revisão. É o caminho normal do
                // vCenter e do SNMP: o ativo nasce por aprovação, não sozinho.
                'assets_review' => 0,
                // Sem ativo correspondente e sem ir para revisão — este sim é
                // um item que ninguém vai olhar se não for sinalizado.
                'assets_unmatched' => 0,
                'assets_failed' => 0,
            ];
            $details = [
                'connector' => [
                    'id' => $connector->id,
                    'name' => $connector->name,
                    'type' => $connector->connector_type,
                ],
                'discovery' => $discovery->toArray(),
                'items' => [],
                'matched_assets' => [],
            ];

            foreach ($items as $item) {
                $rawDiscovered = (array) $item;
                $discovered = DiscoveredAssetData::fromArray($rawDiscovered)->toArray();
                if (in_array($connector->connector_type, ['snmp', 'vmware', 'vcenter'], true)) {
                    $reviewItem = $this->discoveredAssetService->recordDiscovery(
                        $connector,
                        $log,
                        in_array($connector->connector_type, ['vmware', 'vcenter'], true) ? $rawDiscovered : $discovered,
                        $user
                    );
                    $details['items'][] = [
                        'discovered' => $discovered,
                        'discovered_asset_id' => $reviewItem?->id,
                        'status' => $reviewItem?->status ?: 'pending',
                        'recommendation' => 'review_required',
                    ];
                    /*
                     | Só conta como "para revisão" o que realmente espera
                     | alguém: item de tipo que vira ativo e ainda pendente.
                     |
                     | Descoberta já aprovada continua sendo registrada a cada
                     | ciclo, para manter last_seen_at em dia — mas contá-la
                     | como revisão inflava o número (332 quando só 1 esperava
                     | decisão) e escondia o que importa.
                     */
                    $statusItem = $reviewItem?->status ?: 'pending';
                    $tipoItem = $reviewItem?->source_type ?: '';

                    $esperaDecisao = in_array($statusItem, ['pending', 'suggested', 'reopened'], true)
                        && ! in_array($tipoItem, CmdbDiscoveryQueryService::tiposDeReferencia(), true);

                    if ($esperaDecisao) {
                        $summary['assets_review']++;
                    } else {
                        $summary['assets_unchanged']++;
                    }

                    continue;
                }

                $asset = $this->matchAsset($connector, $discovered);

                $details['items'][] = [
                    'discovered' => $discovered,
                    'matched_asset_id' => $asset?->id,
                    'recommendation' => $asset ? 'linked_to_existing_asset' : 'review_required',
                ];

                if (! $asset) {
                    $summary['assets_unmatched']++;
                    continue;
                }

                $assetData = $this->assetSyncPayload($asset, $connector, $discovered);
                $before = $asset->only(array_keys($assetData));

                $asset->forceFill($assetData)->save();

                $changed = $this->arrayDiffers($before, $assetData);
                $eventType = $connector->connector_type === 'snmp' ? 'sync_snmp_discovered' : 'sync_completed';
                $title = $connector->connector_type === 'snmp' ? 'Sincronização SNMP' : 'Sincronização concluída';
                $description = $connector->connector_type === 'snmp'
                    ? 'Dados descobertos via SNMP pelo conector ' . $connector->name . '.'
                    : 'Ativo validado pela sincronização sem alterações estruturais.';

                $this->historyService->record(
                    $asset,
                    $user,
                    $eventType,
                    $title,
                    $description,
                    $before,
                    $assetData
                );

                if ($changed) {
                    $summary['assets_updated']++;
                } else {
                    $summary['assets_unchanged']++;
                }

                $details['matched_assets'][] = [
                    'asset_id' => $asset->id,
                    'asset_name' => $asset->name,
                    'external_id' => $assetData['source_external_id'] ?? null,
                ];
            }

            $finishedAt = now();
            /*
             | O aviso agora depende de problema real, não da estrutura do
             | conector.
             |
             | A regra anterior comparava o total encontrado com a lista de
             | ativos casados. Para vCenter e SNMP essa lista é sempre vazia —
             | eles mandam tudo para a fila de revisão por desenho —, então o
             | sync terminava "Com avisos" toda vez, mesmo perfeito. Alerta que
             | nunca desliga ensina a ignorar o painel.
             */
            $hasWarnings = $discovery->status === 'warning' || $summary['assets_unmatched'] > 0;
            $status = $summary['assets_failed'] > 0 ? 'failed' : ($hasWarnings ? 'warning' : 'success');
            $message = match ($connector->connector_type) {
                'snmp' => 'Sincronização SNMP concluída.',
                'vmware', 'vcenter' => 'Inventário VMware concluído e enviado para revisão.',
                default => 'Sincronização concluída com sucesso.',
            };

            $log->update([
                'status' => $status,
                'finished_at' => $finishedAt,
                'duration_seconds' => $startedAt->diffInSeconds($finishedAt),
                'assets_found' => $summary['assets_found'],
                'assets_created' => $summary['assets_created'],
                'assets_updated' => $summary['assets_updated'],
                'assets_unchanged' => $summary['assets_unchanged'],
                'assets_review' => $summary['assets_review'],
                'assets_unmatched' => $summary['assets_unmatched'],
                'assets_failed' => $summary['assets_failed'],
                'message' => $message,
                'details' => array_merge($details, ['summary' => $summary]),
            ]);

            $this->updateConnectorState($connector, $status === 'failed' ? 'error' : 'active', $status, null, $finishedAt);
            $connector->forceFill([
                $status === 'failed' ? 'last_failed_sync_at' : 'last_successful_sync_at' => $finishedAt,
            ])->save();
            if ($job) {
                $this->updateJobState($job, $status, null, $finishedAt);
            }

            $this->notificationService->notify(
                'CMDB / ' . $connector->name,
                $summary,
                $status,
                $message,
                [
                    'url' => url('/cmdb'),
                    'details' => ['tipo_conector' => $connector->connector_type],
                ]
            );

            return [
                'status' => $status,
                'message' => $message,
                'summary' => $summary,
                'log' => $log->fresh(['connector', 'syncJob']),
            ];
        } catch (Throwable $throwable) {
            $this->updateConnectorState($connector, 'error', 'failed', $throwable->getMessage(), now());
            $connector->forceFill(['last_failed_sync_at' => now()])->save();
            if ($job) {
                $this->updateJobState($job, 'failed', $throwable->getMessage(), now());
            }

            $log->update([
                'status' => 'failed',
                'finished_at' => now(),
                'duration_seconds' => $startedAt->diffInSeconds(now()),
                'assets_failed' => 1,
                'error_message' => $throwable->getMessage(),
                'message' => 'Falha na sincronização.',
            ]);

            $this->notificationService->notify(
                'CMDB / ' . $connector->name,
                ['errors' => 1],
                'failed',
                $throwable->getMessage(),
                [
                    'url' => url('/cmdb'),
                    'details' => ['tipo_conector' => $connector->connector_type],
                ]
            );

            return [
                'status' => 'failed',
                'message' => $throwable->getMessage(),
                'summary' => [],
                'log' => $log->fresh(['connector', 'syncJob']),
            ];
        }
    }

    public function syncJob(CmdbSyncJob $job, ?User $user = null): array
    {
        $connector = $job->connector;
        if (! $connector) {
            return [
                'status' => 'failed',
                'message' => 'O job não possui conector vinculado.',
                'summary' => [],
            ];
        }

        return $this->syncConnector($connector, $user, $job);
    }

    private function updateConnectorState(CmdbConnector $connector, string $connectorStatus, string $syncStatus, ?string $error, Carbon $syncAt): void
    {
        if (! Schema::hasTable('cmdb_connectors')) {
            return;
        }

        $connector->update([
            'last_sync_at' => $syncAt,
            'last_sync_status' => $syncStatus,
            'last_error' => $error,
            'status' => $connectorStatus,
        ]);
    }

    private function updateJobState(CmdbSyncJob $job, string $status, ?string $error, Carbon $syncAt): void
    {
        $job->update([
            'last_run_at' => $syncAt,
            'last_status' => $status,
            'last_error' => $error,
        ]);
    }

    private function matchAsset(CmdbConnector $connector, array $item): ?CmdbAsset
    {
        $externalId = trim((string) Arr::get($item, 'external_id', ''));
        if ($externalId !== '') {
            $asset = CmdbAsset::query()
                ->where('source_connector_id', $connector->id)
                ->where('source_external_id', $externalId)
                ->first();

            if ($asset) {
                return $asset;
            }
        }

        $hostname = trim((string) Arr::get($item, 'hostname', ''));
        if ($hostname !== '') {
            $asset = CmdbAsset::query()->whereRaw('LOWER(hostname) = ?', [Str::lower($hostname)])->first();
            if ($asset) {
                return $asset;
            }
        }

        $name = trim((string) Arr::get($item, 'name', ''));
        if ($name !== '') {
            return CmdbAsset::query()->whereRaw('LOWER(name) = ?', [Str::lower($name)])->first();
        }

        return null;
    }

    private function assetSyncPayload(CmdbAsset $asset, CmdbConnector $connector, array $item): array
    {
        $sourceExternalId = trim((string) Arr::get($item, 'external_id', ''));
        if ($sourceExternalId === '') {
            $sourceExternalId = 'snmp-' . $asset->id;
        }

        $hashSource = [
            'name' => $asset->name,
            'hostname' => $asset->hostname,
            'asset_type' => $asset->asset_type,
            'connector_type' => $connector->connector_type,
            'external_id' => $sourceExternalId,
            'discovered_hostname' => $item['hostname'] ?? null,
            'discovered_description' => $item['description'] ?? null,
            'discovered_object_id' => $item['object_id'] ?? null,
            'discovered_location' => $item['location'] ?? null,
        ];

        return [
            'source_type' => $connector->connector_type,
            'source_connector_id' => $connector->id,
            'source_external_id' => $sourceExternalId,
            'last_synced_at' => now(),
            'sync_status' => 'synced',
            'sync_error' => null,
            'sync_hash' => sha1(json_encode($hashSource, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)),
        ];
    }

    private function arrayDiffers(array $before, array $after): bool
    {
        foreach ($after as $key => $value) {
            if ((string) ($before[$key] ?? '') !== (string) ($value ?? '')) {
                return true;
            }
        }

        return false;
    }
}
