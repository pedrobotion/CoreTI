<?php

namespace App\Services\Cmdb\Discovery;

use App\Models\CmdbConnector;
use App\Services\Cmdb\Discovery\Connectors\ConnectorInterface;
use App\Services\Cmdb\Discovery\Connectors\SimulatedConnector;
use App\Services\Cmdb\Discovery\Connectors\SnmpConnector;
use App\Services\Cmdb\Discovery\Connectors\VCenterConnector;

class ConnectorManager
{
    public function resolve(CmdbConnector $connector): ConnectorInterface
    {
        return match ($connector->connector_type) {
            'snmp' => new SnmpConnector(),
            'vmware', 'vcenter' => new VCenterConnector(),
            default => new SimulatedConnector(),
        };
    }
}
