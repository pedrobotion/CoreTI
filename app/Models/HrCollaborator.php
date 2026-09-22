<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class HrCollaborator extends Model
{
    protected $fillable = [
        'source_system',
        'source_id',
        'employee_number',
        'name',
        'email',
        'cost_center_source_id',
        'unit_source_id',
        'area_source_id',
        'department_source_id',
        'sector_source_id',
        'position_source_id',
        'company_source_id',
        'manager_source_id',
        'active',
        'terminated',
        'termination_date',
        'excluded_from_protheus',
        'source_updated_at',
        'source_hash',
        'last_synced_at',
        'raw_data',
    ];

    protected $casts = [
        'active' => 'boolean',
        'terminated' => 'boolean',
        'termination_date' => 'date',
        'excluded_from_protheus' => 'boolean',
        'source_updated_at' => 'datetime',
        'last_synced_at' => 'datetime',
        'raw_data' => 'array',
    ];

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('active', true)->where('terminated', false);
    }
}
