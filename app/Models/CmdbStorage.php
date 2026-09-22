<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CmdbStorage extends Model
{
    protected $table = 'cmdb_storages';

    protected $fillable = [
        'asset_id',
        'storage_type',
        'total_capacity_tb',
        'used_capacity_tb',
        'raid',
        'iscsi_enabled',
        'nfs_enabled',
        'smb_enabled',
    ];

    protected function casts(): array
    {
        return [
            'iscsi_enabled' => 'boolean',
            'nfs_enabled' => 'boolean',
            'smb_enabled' => 'boolean',
        ];
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(CmdbAsset::class, 'asset_id');
    }
}
