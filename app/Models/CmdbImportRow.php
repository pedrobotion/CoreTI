<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CmdbImportRow extends Model
{
    protected $table = 'cmdb_import_rows';

    protected $fillable = [
        'batch_id',
        'row_number',
        'status',
        'action',
        'asset_id',
        'data',
        'messages',
    ];

    protected function casts(): array
    {
        return [
            'batch_id' => 'integer',
            'row_number' => 'integer',
            'asset_id' => 'integer',
            'data' => 'array',
            'messages' => 'array',
        ];
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(CmdbImportBatch::class, 'batch_id');
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(CmdbAsset::class, 'asset_id');
    }
}
