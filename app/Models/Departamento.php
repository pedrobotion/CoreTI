<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Departamento extends Model
{
    protected $table = 'departamentos';

    protected $fillable = [
        'nome',
        'unicoop',
        'area',
        'ativo',
        'origem',
    ];

    protected $casts = [
        'ativo' => 'boolean',
    ];
}

