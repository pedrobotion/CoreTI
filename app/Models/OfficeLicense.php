<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OfficeLicense extends Model
{
    protected $fillable = [
        'matricula',
        'nome',
        'email',
        'departamento_unidade',
        'unicoop_office',
        'area_office',
        'office_apps',
        'office_business',
        'powerbi_pro',
        'powerbi_premium',
        'visio_plan',
        'ativo',
    ];

    protected $casts = [
        'office_apps' => 'boolean',
        'office_business' => 'boolean',
        'powerbi_pro' => 'boolean',
        'powerbi_premium' => 'boolean',
        'visio_plan' => 'boolean',
        'ativo' => 'boolean',
    ];
}
