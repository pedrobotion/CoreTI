<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CmdbServiceSuggestionRule extends Model
{
    protected $table = 'cmdb_service_suggestion_rules';

    protected $fillable = ['pattern', 'match_type', 'service_id', 'priority', 'active', 'description', 'created_by', 'updated_by'];

    protected $casts = ['active' => 'boolean', 'priority' => 'integer'];

    public function service(): BelongsTo
    {
        return $this->belongsTo(CmdbService::class, 'service_id');
    }
}
