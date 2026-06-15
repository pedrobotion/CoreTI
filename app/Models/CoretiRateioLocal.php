<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CoretiRateioLocal extends Model
{
    protected $table = 'coreti_rateio_locais';

    protected $fillable = [
        'tipo_local',
        'nome_local',
        'nome_normalizado',
        'unicoop',
        'area',
        'centro_custo',
        'centro_custo_nome',
        'ativo',
        'observacao',
    ];

    protected $casts = [
        'ativo' => 'boolean',
    ];
}
