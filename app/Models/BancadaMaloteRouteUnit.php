<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BancadaMaloteRouteUnit extends Model
{
    protected $table = 'bancada_malote_route_units';

    protected $fillable = [
        'route_id',
        'unit_label',
        'ordem',
    ];

    public function route(): BelongsTo
    {
        return $this->belongsTo(BancadaMaloteRoute::class, 'route_id');
    }
}

