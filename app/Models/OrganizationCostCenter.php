<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class OrganizationCostCenter extends Model
{
    protected $fillable = [
        'source_system',
        'source_type',
        'source_id',
        'name',
        'unit_source_id',
        'accounting_area_source_id',
        'active',
        'source_updated_at',
        'source_hash',
        'last_synced_at',
        'raw_data',
    ];

    protected $casts = [
        'active' => 'boolean',
        'source_updated_at' => 'datetime',
        'last_synced_at' => 'datetime',
        'raw_data' => 'array',
    ];

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('active', true);
    }
}
