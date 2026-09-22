<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CoordenacaoCostRule extends Model
{
    use HasFactory;

    protected $table = 'coordenacao_cost_rules';

    protected $fillable = [
        'name',
        'source_type',
        'match_value',
        'billing_cycle',
        'amount',
        'is_active',
        'sort_order',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }
}
