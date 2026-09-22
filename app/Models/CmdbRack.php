<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CmdbRack extends Model
{
    protected $table = 'cmdb_racks';

    protected $fillable = [
        'name',
        'unit_id',
        'organization_unit_id',
        'organization_department_id',
        'location',
        'room',
        'floor',
        'capacity_u',
        'notes',
    ];

    public function assets(): HasMany
    {
        return $this->hasMany(CmdbAsset::class, 'rack_id');
    }

    public function ups(): HasMany
    {
        return $this->hasMany(CmdbUps::class, 'rack_id');
    }

    public function organizationUnit(): BelongsTo
    {
        return $this->belongsTo(OrganizationUnit::class, 'organization_unit_id');
    }

    public function organizationDepartment(): BelongsTo
    {
        return $this->belongsTo(OrganizationDepartment::class, 'organization_department_id');
    }
}
