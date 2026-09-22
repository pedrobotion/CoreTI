<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class CmdbDiscoveredAsset extends Model
{
    use SoftDeletes;

    protected $table = 'cmdb_discovered_assets';

    protected $fillable = [
        'connector_id',
        'sync_log_id',
        'asset_id',
        'source_type',
        'object_type',
        'source_external_id',
        'hostname',
        'ip_address',
        'mac_address',
        'manufacturer',
        'model',
        'serial_number',
        'description',
        'object_id',
        'uptime',
        'contact',
        'location',
        'raw_data',
        'status',
        'reviewed_by',
        'reviewed_at',
        'review_notes',
        'discovered_at',
        'source_hash',
        'content_hash',
        'last_seen_at',
        'source_missing_at',
        'missing_count',
        'match_score',
        'match_reason',
    ];

    protected $casts = [
        'connector_id' => 'integer',
        'sync_log_id' => 'integer',
        'asset_id' => 'integer',
        'reviewed_by' => 'integer',
        'raw_data' => 'array',
        'reviewed_at' => 'datetime',
        'discovered_at' => 'datetime',
        'last_seen_at' => 'datetime',
        'source_missing_at' => 'datetime',
        'missing_count' => 'integer',
        'match_score' => 'integer',
    ];

    public function connector(): BelongsTo
    {
        return $this->belongsTo(CmdbConnector::class, 'connector_id');
    }

    public function syncLog(): BelongsTo
    {
        return $this->belongsTo(CmdbSyncLog::class, 'sync_log_id');
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(CmdbAsset::class, 'asset_id');
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
