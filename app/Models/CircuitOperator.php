<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CircuitOperator extends Model
{
    protected $table = 'circuit_operadoras';

    protected $fillable = [
        'nome',
        'ativo',
    ];

    protected function casts(): array
    {
        return [
            'ativo' => 'boolean',
        ];
    }
}
