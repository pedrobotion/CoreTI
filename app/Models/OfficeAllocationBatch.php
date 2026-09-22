<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OfficeAllocationBatch extends Model
{
    protected $fillable = [
        'competence',
        'version',
        'status',
        'users_count',
        'assignments_count',
        'classified_assignments_count',
        'pending_assignments_count',
        'total_amount',
        'annual_estimated_amount',
        'generated_by',
        'generated_at',
        'validated_by',
        'validated_at',
        'closed_by',
        'closed_at',
        'cancelled_by',
        'cancelled_at',
        'cancellation_reason',
        'price_snapshot',
        'summary',
    ];

    protected $casts = [
        'version' => 'integer',
        'users_count' => 'integer',
        'assignments_count' => 'integer',
        'classified_assignments_count' => 'integer',
        'pending_assignments_count' => 'integer',
        'total_amount' => 'decimal:2',
        'annual_estimated_amount' => 'decimal:2',
        'generated_by' => 'integer',
        'generated_at' => 'datetime',
        'validated_by' => 'integer',
        'validated_at' => 'datetime',
        'closed_by' => 'integer',
        'closed_at' => 'datetime',
        'cancelled_by' => 'integer',
        'cancelled_at' => 'datetime',
        'price_snapshot' => 'array',
        'summary' => 'array',
    ];

    public function rows(): HasMany
    {
        return $this->hasMany(OfficeAllocationRow::class, 'batch_id');
    }
}
