<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OrganizationUnit extends Model
{
    protected $fillable = [
        'source_system',
        'source_id',
        'code',
        'name',
        'type_id',
        'cnpj',
        'state_registration',
        'municipal_registration',
        'address',
        'address_complement',
        'municipality_id',
        'zip_code',
        'phone',
        'secondary_phone',
        'fax',
        'direct_extension',
        'email',
        'nfe_email',
        'regional_id',
        'active',
        'old_source_id',
        'opened_at',
        'closed_at',
        'nire',
        'latitude',
        'longitude',
        'source_updated_at',
        'source_hash',
        'last_synced_at',
        'source_missing_at',
        'sync_batch_id',
        'raw_data',
    ];

    protected $casts = [
        'active' => 'boolean',
        'opened_at' => 'date',
        'closed_at' => 'date',
        'latitude' => 'decimal:8',
        'longitude' => 'decimal:8',
        'source_updated_at' => 'datetime',
        'last_synced_at' => 'datetime',
        'source_missing_at' => 'datetime',
        'raw_data' => 'array',
    ];

    public function departments(): HasMany
    {
        return $this->hasMany(OrganizationDepartment::class, 'unit_id');
    }

    public function getUnidadeAttribute(): string
    {
        return (string) $this->name;
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
                ->orWhere('cnpj', 'like', "%{$term}%");
        });
    }
}
