<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CmdbFirewall extends Model
{
    protected $table = 'cmdb_firewalls';

    protected $fillable = [
        'asset_id',
        'firewall_type',
        'firmware_version',
        'license_key',
        'license_expiration_date',
        'ha_enabled',
        'vpn_enabled',
        'vpn_count',
    ];

    protected function casts(): array
    {
        return [
            'license_expiration_date' => 'date',
            'ha_enabled' => 'boolean',
            'vpn_enabled' => 'boolean',
        ];
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(CmdbAsset::class, 'asset_id');
    }
}
