<?php

namespace App\Services\Cmdb\Discovery\Connectors;

use App\Models\CmdbAsset;
use App\Models\CmdbConnector;

class SimulatedConnector extends AbstractConnector
{
    public function testConnection(CmdbConnector $connector): array
    {
        return [
            'success' => true,
            'status' => 'success',
            'source' => 'simulated',
            'message' => 'Teste simulado executado com sucesso.',
            'data' => [
                'hostname' => $connector->host ?: $connector->name,
                'description' => 'Conector simulado disponível para validação.',
                'object_id' => null,
                'uptime' => null,
                'contact' => null,
                'location' => null,
                'ip' => $connector->host,
                'response_time_ms' => 0,
            ],
            'items' => [],
            'warnings' => [],
            'meta' => ['connector_type' => $connector->connector_type],
        ];
    }

    public function discover(CmdbConnector $connector): array
    {
        $assets = CmdbAsset::query()->orderByDesc('updated_at')->limit(5)->get();
        $items = $assets->map(static function (CmdbAsset $asset) use ($connector): array {
            return [
                'external_id' => 'sim-' . $asset->id,
                'hostname' => $asset->hostname ?: $asset->name,
                'description' => $asset->description ?: $asset->notes,
                'object_id' => $asset->asset_type,
                'uptime' => null,
                'contact' => null,
                'location' => $asset->location,
                'ip' => $asset->main_ip,
                'response_time_ms' => 0,
                'source_type' => $connector->connector_type,
            ];
        })->all();

        if ($items === []) {
            $items[] = [
                'external_id' => 'sim-placeholder',
                'hostname' => $connector->host ?: $connector->name,
                'description' => 'Nenhum ativo disponível. Item simulado gerado para validação.',
                'object_id' => 'placeholder',
                'uptime' => null,
                'contact' => null,
                'location' => null,
                'ip' => $connector->host,
                'response_time_ms' => 0,
                'source_type' => $connector->connector_type,
            ];
        }

        return [
            'success' => true,
            'status' => 'simulated',
            'source' => 'simulated',
            'message' => 'Descoberta simulada executada com sucesso.',
            'data' => [],
            'items' => $items,
            'warnings' => [],
            'meta' => ['connector_type' => $connector->connector_type],
        ];
    }

    public function sync(CmdbConnector $connector): array
    {
        return $this->discover($connector);
    }
}
