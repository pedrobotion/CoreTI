<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class JiraProject extends Model
{
    protected $fillable = [
        'legacy_id',
        'email',
        'tipo_unidade',
        'unidade_nome',
        'centro_custo',
        'unicoop',
        'area',
        'projeto_grupo',
        'status',
        'obs',
        'data_inclusao',
        'data_desativacao',
        'excluido',
    ];

    protected function casts(): array
    {
        return [
            'data_inclusao' => 'date',
            'data_desativacao' => 'date',
            'excluido' => 'boolean',
        ];
    }
}

