<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class CmdbAssetDocument extends Model
{
    use SoftDeletes;

    protected $table = 'cmdb_asset_documents';

    protected $fillable = [
        'asset_id',
        'user_id',
        'document_type',
        'title',
        'description',
        'file_path',
        'original_name',
        'mime_type',
        'file_size',
    ];

    public function asset(): BelongsTo
    {
        return $this->belongsTo(CmdbAsset::class, 'asset_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
