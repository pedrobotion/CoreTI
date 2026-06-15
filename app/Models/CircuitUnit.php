<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class CircuitUnit extends Model
{
    protected $table = 'circuitos_unidades';
    protected $primaryKey = 'id_circuitos';
    public $timestamps = false;

    protected $fillable = [
        'operadora',
        'unidades_circuitos',
        'uf',
        'servico',
        'endereco',
        'contato',
        'contato_whatsapp',
        'informacoes_adicionais',
        'id_unidades',
        'chamado_numero',
        'massiva_regiao',
        'unidade_operacional',
        'previsao_resolucao_horas',
    ];

    protected $casts = [
        'contato_whatsapp' => 'boolean',
        'massiva_regiao' => 'boolean',
    ];

    public function unidade(): BelongsTo
    {
        return $this->belongsTo(Unidade::class, 'id_unidades', 'id_unidades');
    }

    public function getRouteKeyName(): string
    {
        return 'id_circuitos';
    }

    public function incidents(): HasMany
    {
        return $this->hasMany(CircuitIncident::class, 'circuit_unit_id', 'id_circuitos');
    }

    public function openIncident(): HasOne
    {
        return $this->hasOne(CircuitIncident::class, 'circuit_unit_id', 'id_circuitos')
            ->whereNull('resolved_at')
            ->latestOfMany('opened_at');
    }

    public function whatsappUrl(): ?string
    {
        if (! $this->contato_whatsapp) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', (string) $this->contato);
        if (! $digits) {
            return null;
        }

        if (! str_starts_with($digits, '55')) {
            $digits = '55' . $digits;
        }

        return 'https://wa.me/' . $digits;
    }
}
