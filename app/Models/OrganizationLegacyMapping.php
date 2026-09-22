<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class OrganizationLegacyMapping extends Model
{
    protected $fillable = [
        'entity_type',
        'legacy_table',
        'legacy_id',
        'organization_id',
        'source_id',
        'match_type',
        'confidence',
        'status',
        'notes',
    ];

    protected $casts = [
        'confidence' => 'decimal:2',
    ];

    public function scopeMatched(Builder $query): Builder
    {
        return $query->where('status', 'matched');
    }
}
