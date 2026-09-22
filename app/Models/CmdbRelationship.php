<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CmdbRelationship extends Model
{
    protected $table = 'cmdb_relationships';

    protected $fillable = [
        'source_asset_id',
        'target_asset_id',
        'relationship_type',
        'description',
    ];

    public function sourceAsset(): BelongsTo
    {
        return $this->belongsTo(CmdbAsset::class, 'source_asset_id');
    }

    public function targetAsset(): BelongsTo
    {
        return $this->belongsTo(CmdbAsset::class, 'target_asset_id');
    }
}
