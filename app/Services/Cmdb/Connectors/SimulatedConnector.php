<?php

namespace App\Services\Cmdb\Connectors;

use App\Models\CmdbAsset;
use App\Models\CmdbConnector;
use Illuminate\Support\Str;

class SimulatedConnector implements CmdbConnectorInterface
{
    public function testConnection(CmdbConnector $connector): array
    {
        return [
            'status' => 'simulated',
            'success' => true,
            'message' => 'Conexão simulada com sucesso.',
            'details' => [
                'connector_type' => $connector->connector_type,
                'host' => $connector->host,
                'port' => $connector->port,
            ],
        ];
    }

    public function discover(CmdbConnector $connector): array
    {
        $assets = CmdbAsset::query()->orderBy('id')->limit(5)->get();
        $items = [];

        foreach ($assets as $asset) {
            $items[] = [
                'external_id' => $asset->source_external_id ?: 'sim-' . $asset->id,
                'name' => $asset->name,
                'hostname' => $asset->hostname,
                'asset_type' => $asset->asset_type,
                'main_ip' => $asset->main_ip,
                'source_type' => $connector->connector_type,
                'payload' => [
                    'status' => $asset->status,
                    'criticality' => $asset->criticality,
                    'environment' => $asset->environment,
                    'rack' => $asset->rack?->name,
                ],
            ];
        }

        if ($items === []) {
            $items[] = [
                'external_id' => 'simulated-' . Str::lower(Str::random(8)),
                'name' => 'Ativo simulado',
                'hostname' => 'simulado.local',
                'asset_type' => 'other',
                'main_ip' => '192.0.2.10',
                'source_type' => $connector->connector_type,
                'payload' => [
                    'status' => 'active',
                    'criticality' => 'medium',
                    'environment' => 'production',
                ],
            ];
        }

        return [
            'status' => 'simulated',
            'success' => true,
            'message' => 'Descoberta simulada gerada com sucesso.',
            'items' => $items,
            'count' => count($items),
        ];
    }

    public function sync(CmdbConnector $connector): array
    {
        return $this->discover($connector);
    }
}
