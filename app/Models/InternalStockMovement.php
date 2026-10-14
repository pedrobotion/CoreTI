<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InternalStockMovement extends Model
{
    public const TIPO_ENTRADA = 'entrada';

    public const TIPO_BAIXA = 'baixa';

    public const TIPO_AJUSTE = 'ajuste';

    protected $fillable = [
        'internal_stock_product_id', 'internal_stock_unit_id', 'tipo', 'quantidade',
        'motivo', 'destino_tipo', 'destino_colaborador_id', 'destino_descricao',
        'referencia', 'saldo_apos', 'observacao', 'created_by',
    ];

    protected $casts = [
        'quantidade' => 'integer',
        'saldo_apos' => 'integer',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(InternalStockProduct::class, 'internal_stock_product_id');
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(InternalStockUnit::class, 'internal_stock_unit_id');
    }

    public function colaborador(): BelongsTo
    {
        return $this->belongsTo(HrCollaborator::class, 'destino_colaborador_id');
    }

    public function autor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
