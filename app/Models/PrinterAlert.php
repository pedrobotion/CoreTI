<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Alerta reportado pela impressora (prtAlert, RFC 3805).
 *
 * severity_level segue a MIB: 1 = other, 3 = critical, 4 = warning.
 */
class PrinterAlert extends Model
{
    public const SEVERIDADE_CRITICA = 3;

    protected $table = 'printer_alerts';

    protected $fillable = [
        'printer_id', 'detectado_em', 'resolvido_em', 'engine_cycles',
        'training_level', 'severity_level', 'alert_class', 'mib_code',
        'descricao', 'raw_data',
    ];

    protected function casts(): array
    {
        return [
            'detectado_em' => 'datetime',
            'resolvido_em' => 'datetime',
            'raw_data' => 'array',
        ];
    }

    public function printer(): BelongsTo
    {
        return $this->belongsTo(Printer::class);
    }

    /** @param  Builder<static>  $query */
    public function scopeAbertos(Builder $query): Builder
    {
        return $query->whereNull('resolvido_em');
    }

    /** @param  Builder<static>  $query */
    public function scopeCriticos(Builder $query): Builder
    {
        return $query->where('severity_level', self::SEVERIDADE_CRITICA);
    }
}
