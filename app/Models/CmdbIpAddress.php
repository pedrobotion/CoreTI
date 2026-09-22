<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CmdbIpAddress extends Model
{
    protected $table = 'cmdb_ip_addresses';

    protected $fillable = [
        'asset_id',
        'ip_address',
        'subnet_mask',
        'gateway',
        'dns',
        'vlan',
        'mac_address',
        'is_primary',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'is_primary' => 'boolean',
        ];
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(CmdbAsset::class, 'asset_id');
    }
}
