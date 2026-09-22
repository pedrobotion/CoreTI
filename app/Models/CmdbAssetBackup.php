<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CmdbAssetBackup extends Model
{
    protected $table = 'cmdb_asset_backups';

    protected $fillable = [
        'asset_id', 'connector_id', 'platform', 'external_vm_id', 'external_object_id',
        'job_id', 'job_name', 'is_in_backup_job', 'is_protected', 'protection_status',
        'last_session_status', 'last_session_started_at', 'last_session_finished_at',
        'last_successful_backup_at', 'latest_restore_point_at', 'restore_points_count',
        'repository_name', 'retention_days', 'schedule_description', 'next_run_at',
        'last_error', 'last_synced_at', 'sync_hash', 'match_method', 'match_confidence',
        'metadata',
    ];

    protected $casts = [
        'asset_id' => 'integer',
        'connector_id' => 'integer',
        'is_in_backup_job' => 'boolean',
        'is_protected' => 'boolean',
        'restore_points_count' => 'integer',
        'retention_days' => 'integer',
        'match_confidence' => 'decimal:2',
        'last_session_started_at' => 'datetime',
        'last_session_finished_at' => 'datetime',
        'last_successful_backup_at' => 'datetime',
        'latest_restore_point_at' => 'datetime',
        'next_run_at' => 'datetime',
        'last_synced_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function asset(): BelongsTo
    {
        return $this->belongsTo(CmdbAsset::class, 'asset_id');
    }

    public function connector(): BelongsTo
    {
        return $this->belongsTo(CmdbConnector::class, 'connector_id');
    }
}
