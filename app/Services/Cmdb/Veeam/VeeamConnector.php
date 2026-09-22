<?php

namespace App\Services\Cmdb\Veeam;

use App\Models\CmdbConnector;

class VeeamConnector
{
    public function __construct(private readonly VeeamBackupSyncService $syncService)
    {
    }

    public function test(CmdbConnector $connector): array
    {
        return $this->syncService->testConnection($connector);
    }

    public function sync(CmdbConnector $connector, array $options = []): array
    {
        return $this->syncService->sync($connector, $options);
    }
}
