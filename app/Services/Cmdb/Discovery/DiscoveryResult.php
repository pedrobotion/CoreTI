<?php

namespace App\Services\Cmdb\Discovery;

class DiscoveryResult
{
    public function __construct(
        public bool $success = false,
        public string $status = 'failed',
        public string $source = 'simulated',
        public string $message = '',
        public array $data = [],
        public array $items = [],
        public array $warnings = [],
        public array $meta = [],
        public ?string $rawText = null,
    ) {
    }

    public static function success(string $message = '', array $data = [], array $items = [], string $source = 'simulated', array $warnings = [], array $meta = []): self
    {
        return new self(true, 'success', $source, $message, $data, $items, $warnings, $meta);
    }

    public static function failure(string $message, string $source = 'simulated', array $data = [], array $warnings = [], array $meta = []): self
    {
        return new self(false, 'failed', $source, $message, $data, [], $warnings, $meta);
    }

    public function toArray(): array
    {
        return [
            'success' => $this->success,
            'status' => $this->status,
            'source' => $this->source,
            'message' => $this->message,
            'data' => $this->data,
            'items' => $this->items,
            'warnings' => $this->warnings,
            'meta' => $this->meta,
            'raw_text' => $this->rawText,
        ];
    }
}
