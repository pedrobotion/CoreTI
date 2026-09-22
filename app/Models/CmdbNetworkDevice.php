<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CmdbNetworkDevice extends Model
{
    protected $table = 'cmdb_network_devices';

    protected $fillable = [
        'asset_id',
        'device_type',
        'firmware_version',
        'ports_count',
        'poe_enabled',
        'stack_enabled',
        'management_ip',
    ];

    protected function casts(): array
    {
        return [
            'poe_enabled' => 'boolean',
            'stack_enabled' => 'boolean',
        ];
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(CmdbAsset::class, 'asset_id');
    }
}
