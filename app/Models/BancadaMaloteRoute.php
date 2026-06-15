<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BancadaMaloteRoute extends Model
{
    protected $fillable = [
        'nome',
        'dia_entrega',
        'dia_carrega',
        'dia_separa',
        'observacao',
        'ordem',
        'ativo',
    ];

    public function units(): HasMany
    {
        return $this->hasMany(BancadaMaloteRouteUnit::class, 'route_id')->orderBy('ordem');
    }
}
