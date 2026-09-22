<?php

namespace App\Services\Cmdb\Discovery;

use App\Models\CmdbConnector;

class DiscoveryEngine
{
    public function __construct(private readonly ConnectorManager $manager)
    {
    }

    public function testConnection(CmdbConnector $connector): DiscoveryResult
    {
        $result = $this->manager->resolve($connector)->testConnection($connector);

        return $this->resultFromArray($result);
    }

    public function discover(CmdbConnector $connector): DiscoveryResult
    {
        $result = $this->manager->resolve($connector)->discover($connector);

        return $this->resultFromArray($result);
    }

    public function sync(CmdbConnector $connector): DiscoveryResult
    {
        $result = $this->manager->resolve($connector)->sync($connector);

        return $this->resultFromArray($result);
    }

    private function resultFromArray(array $result): DiscoveryResult
    {
        return new DiscoveryResult(
            success: (bool) ($result['success'] ?? false),
            status: (string) ($result['status'] ?? (($result['success'] ?? false) ? 'success' : 'failed')),
            source: (string) ($result['source'] ?? 'simulated'),
            message: (string) ($result['message'] ?? ''),
            data: (array) ($result['data'] ?? []),
            items: (array) ($result['items'] ?? []),
            warnings: (array) ($result['warnings'] ?? []),
            meta: (array) ($result['meta'] ?? []),
            rawText: isset($result['raw_text']) ? (string) $result['raw_text'] : null,
        );
    }
}
