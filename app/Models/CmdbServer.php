<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;

class CmdbServer extends Model
{
    protected $table = 'cmdb_servers';

    protected $fillable = [
        'asset_id',
        'service_id',
        'power_state',
        'operational_status',
        'cpu',
        'memory_gb',
        'storage_total_gb',
        'used_space_gb',
        'service_description',
        'company_name',
        'service_type',
        'backup_policy',
        'backup_retention',
        'web_publication',
        'raid',
        'hypervisor',
        'is_virtualized',
        'warranty_start_date',
        'warranty_end_date',
    ];

    protected function casts(): array
    {
        return [
            'is_virtualized' => 'boolean',
            'used_space_gb' => 'decimal:2',
            'web_publication' => 'boolean',
            'warranty_start_date' => 'date',
            'warranty_end_date' => 'date',
        ];
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(CmdbAsset::class, 'asset_id');
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(CmdbService::class, 'service_id');
    }

    public function scopeWithoutCanonicalService(Builder $query): Builder
    {
        return $query->whereNull('service_id');
    }

    public function scopeWithLegacyServiceOnly(Builder $query): Builder
    {
        return $query->whereNull('service_id')->where(function (Builder $builder): void {
            $builder->whereNotNull('service_type')->where('service_type', '<>', '')
                ->orWhereNotNull('service_description')->where('service_description', '<>', '');
        });
    }
}
