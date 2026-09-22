<?php

namespace App\Services\Cmdb\Discovery\Connectors;

use Illuminate\Cache\Lock;
use Illuminate\Support\Facades\Cache;

class VCenterSyncLock
{
    public function acquire(int $connectorId, int $seconds = 900): Lock
    {
        return Cache::lock('cmdb:vcenter-sync:' . $connectorId, $seconds);
    }
}
