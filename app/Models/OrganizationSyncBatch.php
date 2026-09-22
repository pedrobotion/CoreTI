<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OrganizationSyncBatch extends Model
{
    protected $fillable = [
        'source_system',
        'sync_type',
        'status',
        'started_at',
        'finished_at',
        'units_found',
        'units_created',
        'units_updated',
        'units_unchanged',
        'units_missing',
        'units_failed',
        'departments_found',
        'departments_created',
        'departments_updated',
        'departments_unchanged',
        'departments_missing',
        'departments_failed',
        'error_message',
        'details',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
        'details' => 'array',
    ];

    public function units(): HasMany
    {
        return $this->hasMany(OrganizationUnit::class, 'sync_batch_id');
    }

    public function departments(): HasMany
    {
        return $this->hasMany(OrganizationDepartment::class, 'sync_batch_id');
    }
}
