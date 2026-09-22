<?php

namespace App\Jobs;

use App\Models\CmdbConnector;
use App\Services\Cmdb\Discovery\Connectors\VCenterSyncLock;
use App\Services\CmdbSyncService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SyncVCenterConnectorJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public int $connectorId)
    {
    }

    public function handle(CmdbSyncService $syncService, VCenterSyncLock $lock): void
    {
        $connector = CmdbConnector::query()->find($this->connectorId);
        if (! $connector || ! in_array($connector->connector_type, ['vmware', 'vcenter'], true) || ! $connector->is_active) {
            return;
        }

        $mutex = $lock->acquire($connector->id);
        if (! $mutex->get()) {
            return;
        }

        try {
            $syncService->syncConnector($connector);
        } finally {
            optional($mutex)->release();
        }
    }
}
