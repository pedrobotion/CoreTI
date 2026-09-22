<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CmdbUps extends Model
{
    protected $table = 'cmdb_ups';

    protected $fillable = [
        'asset_id',
        'power_va',
        'power_watts',
        'battery_quantity',
        'battery_type',
        'installation_date',
        'last_battery_replacement_date',
        'next_battery_replacement_date',
        'estimated_runtime_minutes',
        'warranty_start_date',
        'warranty_end_date',
        'rack_id',
    ];

    protected function casts(): array
    {
        return [
            'installation_date' => 'date',
            'last_battery_replacement_date' => 'date',
            'next_battery_replacement_date' => 'date',
            'warranty_start_date' => 'date',
            'warranty_end_date' => 'date',
        ];
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(CmdbAsset::class, 'asset_id');
    }

    public function rack(): BelongsTo
    {
        return $this->belongsTo(CmdbRack::class, 'rack_id');
    }
}
