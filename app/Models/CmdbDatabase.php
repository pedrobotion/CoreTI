<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CmdbDatabase extends Model
{
    protected $table = 'cmdb_databases';

    protected $fillable = [
        'asset_id',
        'server_asset_id',
        'database_type',
        'version',
        'port',
        'instance_name',
        'backup_enabled',
        'replication_enabled',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'backup_enabled' => 'boolean',
            'replication_enabled' => 'boolean',
        ];
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(CmdbAsset::class, 'asset_id');
    }

    public function serverAsset(): BelongsTo
    {
        return $this->belongsTo(CmdbAsset::class, 'server_asset_id');
    }
}
