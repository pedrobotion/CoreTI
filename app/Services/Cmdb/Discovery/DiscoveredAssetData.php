<?php

namespace App\Services\Cmdb\Discovery;

class DiscoveredAssetData
{
    public function __construct(
        public ?string $externalId = null,
        public ?string $hostname = null,
        public ?string $description = null,
        public ?string $objectId = null,
        public ?string $uptime = null,
        public ?string $contact = null,
        public ?string $location = null,
        public ?string $ip = null,
        public ?int $responseTimeMs = null,
        public ?int $matchedAssetId = null,
        public string $recommendation = 'review_required',
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            externalId: $data['external_id'] ?? null,
            hostname: $data['hostname'] ?? null,
            description: $data['description'] ?? null,
            objectId: $data['object_id'] ?? null,
            uptime: $data['uptime'] ?? null,
            contact: $data['contact'] ?? null,
            location: $data['location'] ?? null,
            ip: $data['ip'] ?? null,
            responseTimeMs: isset($data['response_time_ms']) ? (int) $data['response_time_ms'] : null,
            matchedAssetId: isset($data['matched_asset_id']) ? (int) $data['matched_asset_id'] : null,
            recommendation: $data['recommendation'] ?? 'review_required',
        );
    }

    public function toArray(): array
    {
        return [
            'external_id' => $this->externalId,
            'hostname' => $this->hostname,
            'description' => $this->description,
            'object_id' => $this->objectId,
            'uptime' => $this->uptime,
            'contact' => $this->contact,
            'location' => $this->location,
            'ip' => $this->ip,
            'response_time_ms' => $this->responseTimeMs,
            'matched_asset_id' => $this->matchedAssetId,
            'recommendation' => $this->recommendation,
        ];
    }
}
