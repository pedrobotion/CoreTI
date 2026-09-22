<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class OrganizationPosition extends Model
{
    protected $fillable = [
        'source_system', 'source_id', 'name', 'rh_code', 'rh_level_code', 'category_source_id',
        'active', 'protheus_position_code', 'system', 'cbo', 'details', 'source_updated_at',
        'source_hash', 'last_synced_at', 'source_missing_at', 'raw_data',
    ];

    protected $casts = ['active' => 'boolean', 'source_updated_at' => 'datetime', 'last_synced_at' => 'datetime', 'source_missing_at' => 'datetime', 'raw_data' => 'array'];

    public function scopeBySourceId(Builder $query, int|string $sourceId, string $sourceSystem = 'intranet'): Builder
    {
        return $query->where('source_system', $sourceSystem)->where('source_id', (string) $sourceId);
    }
}
