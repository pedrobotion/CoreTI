<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Pedido de consumível aberto no portal.
 *
 * Espelha o pedido feito pelo CoreTI e também o aberto direto no portal —
 * a consulta do fornecedor devolve os dois. O rastreio vem junto, então dá
 * para acompanhar a entrega sem sair do sistema.
 */
class PrinterConsumableRequest extends Model
{
    protected $table = 'printer_consumable_requests';

    protected $fillable = [
        'insight_request_id', 'printer_id', 'status', 'status_date', 'status_comment',
        'solicitado_em', 'solicitante', 'motivo',
        'nivel_no_pedido', 'dias_restantes_no_pedido',
        'contador_mono_no_pedido', 'contador_color_no_pedido', 'substituido_em',
        'consumivel_descricao', 'consumivel_tipo', 'consumivel_cor',
        'transportadora', 'rastreio_codigo', 'rastreio_url', 'referencia_externa',
        'solicitado_por', 'raw_data', 'last_synced_at',
    ];

    protected function casts(): array
    {
        return [
            'status_date' => 'datetime',
            'solicitado_em' => 'datetime',
            'substituido_em' => 'datetime',
            'last_synced_at' => 'datetime',
            'raw_data' => 'array',
        ];
    }

    public function printer(): BelongsTo
    {
        return $this->belongsTo(Printer::class);
    }

    public function solicitadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'solicitado_por');
    }

    /** Pedido que ainda não chegou. */
    public function scopeEmAberto(Builder $query): Builder
    {
        return $query->whereNull('substituido_em');
    }

    public function foiAbertoPeloCoreTi(): bool
    {
        return $this->referencia_externa !== null;
    }
}
