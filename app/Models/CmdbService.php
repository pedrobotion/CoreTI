<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CmdbService extends Model
{
    protected $table = 'cmdb_services';

    protected $fillable = [
        'name',
        'description',
        'category',
        'owner_id',
        'criticality',
        'active',
    ];

    protected $casts = ['active' => 'boolean'];

    public function servers(): HasMany
    {
        return $this->hasMany(CmdbServer::class, 'service_id');
    }
}
