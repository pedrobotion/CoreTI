<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Nível de um consumível.
 *
 * O portal entrega percentual, dias restantes e páginas restantes. Os dois
 * últimos são o que permite pedir toner antes de acabar, em vez de depois —
 * o monitoramento por SNMP do gestaoTI só tem o percentual.
 */
class PrinterSupply extends Model
{
    protected $table = 'printer_supplies';

    protected $fillable = [
        'printer_id', 'insight_consumable_id', 'indice', 'tipo', 'cor', 'descricao',
        'serial_number', 'sku', 'reorder_part',
        'percent_left', 'days_left', 'pages_left',
        'rendimento', 'max_level', 'raw_level', 'days_monitored',
        'first_read', 'last_read', 'raw_data',
    ];

    protected function casts(): array
    {
        return [
            'first_read' => 'datetime',
            'last_read' => 'datetime',
            'raw_data' => 'array',
        ];
    }

    public function printer(): BelongsTo
    {
        return $this->belongsTo(Printer::class);
    }

    /** @param  Builder<static>  $query */
    public function scopeAcabando(Builder $query, int $limite = Printer::NIVEL_ALERTA): Builder
    {
        return $query->whereNotNull('percent_left')->where('percent_left', '<=', $limite);
    }

    public function getSituacaoAttribute(): string
    {
        return match (true) {
            $this->percent_left === null => 'desconhecido',
            $this->percent_left <= Printer::NIVEL_CRITICO => 'critico',
            $this->percent_left <= Printer::NIVEL_ALERTA => 'atencao',
            default => 'ok',
        };
    }
}
