<?php

namespace App\Services\Cmdb\Discovery\Connectors;

use RuntimeException;

class VCenterApiException extends RuntimeException
{
    public function __construct(string $message, public readonly string $kind = 'api', int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
