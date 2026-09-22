<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CmdbImportBatch extends Model
{
    protected $table = 'cmdb_import_batches';

    protected $fillable = [
        'user_id',
        'original_name',
        'status',
        'total_rows',
        'valid_rows',
        'warning_rows',
        'error_rows',
        'imported_rows',
        'updated_rows',
        'failed_rows',
        'options',
        'started_at',
        'finished_at',
    ];

    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'total_rows' => 'integer',
            'valid_rows' => 'integer',
            'warning_rows' => 'integer',
            'error_rows' => 'integer',
            'imported_rows' => 'integer',
            'updated_rows' => 'integer',
            'failed_rows' => 'integer',
            'options' => 'array',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function rows(): HasMany
    {
        return $this->hasMany(CmdbImportRow::class, 'batch_id');
    }
}
