<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class CmdbExpiration extends Model
{
    use SoftDeletes;

    protected $table = 'cmdb_expirations';

    protected $fillable = [
        'asset_id',
        'expiration_type',
        'title',
        'description',
        'expiration_date',
        'alert_days_before',
        'status',
        'jira_issue_key',
        'jira_issue_url',
        'notified_at',
    ];

    protected function casts(): array
    {
        return [
            'expiration_date' => 'date',
            'notified_at' => 'datetime',
        ];
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(CmdbAsset::class, 'asset_id');
    }
}
