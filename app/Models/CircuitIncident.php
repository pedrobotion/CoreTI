<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CircuitIncident extends Model
{
    protected $fillable = [
        'circuit_unit_id',
        'chamado_numero',
        'massiva_regiao',
        'unidade',
        'previsao_resolucao_horas',
        'opened_at',
        'resolved_at',
    ];

    protected $casts = [
        'massiva_regiao' => 'boolean',
        'opened_at' => 'datetime',
        'resolved_at' => 'datetime',
    ];

    public function circuitUnit(): BelongsTo
    {
        return $this->belongsTo(CircuitUnit::class, 'circuit_unit_id', 'id_circuitos');
    }
}

