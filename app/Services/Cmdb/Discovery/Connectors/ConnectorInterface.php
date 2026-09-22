<?php

namespace App\Services\Cmdb\Discovery\Connectors;

use App\Models\CmdbConnector;

interface ConnectorInterface
{
    public function testConnection(CmdbConnector $connector): array;

    public function discover(CmdbConnector $connector): array;

    public function sync(CmdbConnector $connector): array;
}
