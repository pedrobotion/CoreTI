<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrganizationDepartment extends Model
{
    protected $fillable = [
        'source_system',
        'source_id',
        'source_unit_id',
        'unit_id',
        'source_area_id',
        'code',
        'name',
        'description',
        'active',
        'source_updated_at',
        'source_hash',
        'last_synced_at',
        'source_missing_at',
        'sync_batch_id',
        'raw_data',
    ];

    protected $casts = [
        'active' => 'boolean',
        'source_updated_at' => 'datetime',
        'last_synced_at' => 'datetime',
        'source_missing_at' => 'datetime',
        'raw_data' => 'array',
    ];

    public function unit(): BelongsTo
    {
        return $this->belongsTo(OrganizationUnit::class, 'unit_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('active', true)->whereNull('source_missing_at');
    }

    public function scopeBySourceId(Builder $query, int|string $sourceId, string $sourceSystem = 'intranet'): Builder
    {
        return $query
            ->where('source_system', $sourceSystem)
            ->where('source_id', (string) $sourceId);
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        $term = trim((string) $term);
        if ($term === '') {
            return $query;
        }

        return $query->where(function (Builder $query) use ($term): void {
            $query->where('name', 'like', "%{$term}%")
                ->orWhere('code', 'like', "%{$term}%")
                ->orWhere('source_area_id', 'like', "%{$term}%");
        });
    }
}
