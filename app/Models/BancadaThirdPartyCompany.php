<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BancadaThirdPartyCompany extends Model
{
    protected $fillable = [
        'name',
        'cnpj',
        'contact',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }
}
