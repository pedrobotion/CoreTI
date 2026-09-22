<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class CmdbSyncJob extends Model
{
    use SoftDeletes;

    public const SCHEDULE_TYPES = [
        'manual' => 'Manual',
        'hourly' => 'A cada hora',
        'daily' => 'Diário',
        'weekly' => 'Semanal',
        'custom' => 'Personalizado',
    ];

    protected $table = 'cmdb_sync_jobs';

    protected $fillable = [
        'connector_id',
        'name',
        'schedule_type',
        'frequency_minutes',
        'run_at',
        'is_active',
        'last_run_at',
        'last_status',
        'last_error',
        'settings',
    ];

    protected $casts = [
        'connector_id' => 'integer',
        'frequency_minutes' => 'integer',
        'is_active' => 'boolean',
        'last_run_at' => 'datetime',
        'settings' => 'array',
    ];

    public function connector(): BelongsTo
    {
        return $this->belongsTo(CmdbConnector::class, 'connector_id');
    }

    public function syncLogs(): HasMany
    {
        return $this->hasMany(CmdbSyncLog::class, 'sync_job_id')->orderByDesc('started_at');
    }
}
