<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CmdbSyncLog extends Model
{
    protected $table = 'cmdb_sync_logs';

    protected $fillable = [
        'connector_id',
        'sync_job_id',
        'status',
        'started_at',
        'finished_at',
        'duration_seconds',
        'assets_found',
        'assets_created',
        'assets_updated',
        'assets_unchanged',
        'assets_review',
        'assets_unmatched',
        'assets_failed',
        'message',
        'error_message',
        'details',
    ];

    protected $casts = [
        'connector_id' => 'integer',
        'sync_job_id' => 'integer',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
        'duration_seconds' => 'integer',
        'assets_found' => 'integer',
        'assets_created' => 'integer',
        'assets_updated' => 'integer',
        'assets_unchanged' => 'integer',
        'assets_failed' => 'integer',
        'details' => 'array',
    ];

    public function connector(): BelongsTo
    {
        return $this->belongsTo(CmdbConnector::class, 'connector_id');
    }

    public function syncJob(): BelongsTo
    {
        return $this->belongsTo(CmdbSyncJob::class, 'sync_job_id');
    }

    public function discoveredAssets(): HasMany
    {
        return $this->hasMany(CmdbDiscoveredAsset::class, 'sync_log_id');
    }
}
