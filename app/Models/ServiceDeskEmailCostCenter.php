<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ServiceDeskEmailCostCenter extends Model
{
    protected $fillable = [
        'scope',
        'name',
        'unicoop',
        'area',
        'source_table',
        'source_id',
    ];

    public function emails(): HasMany
    {
        return $this->hasMany(ServiceDeskEmail::class);
    }
}
