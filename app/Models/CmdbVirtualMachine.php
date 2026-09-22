<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CmdbVirtualMachine extends Model
{
    protected $table = 'cmdb_virtual_machines';

    protected $fillable = [
        'asset_id',
        'host_asset_id',
        'cpu_cores',
        'memory_gb',
        'storage_gb',
        'hypervisor',
        'datastore',
    ];

    public function asset(): BelongsTo
    {
        return $this->belongsTo(CmdbAsset::class, 'asset_id');
    }

    public function hostAsset(): BelongsTo
    {
        return $this->belongsTo(CmdbAsset::class, 'host_asset_id');
    }
}
