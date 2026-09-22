<?php

namespace App\Services\Cmdb\Connectors;

use App\Models\CmdbConnector;

interface CmdbConnectorInterface
{
    public function testConnection(CmdbConnector $connector): array;

    public function discover(CmdbConnector $connector): array;

    public function sync(CmdbConnector $connector): array;
}
