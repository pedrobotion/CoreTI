<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InternalStockUnit extends Model
{
    public const ESTADOS = ['Novo', 'Usado'];

    public const STATUS_EM_ESTOQUE = 'em_estoque';

    public const STATUS_ENTREGUE = 'entregue';

    public const STATUS_BAIXADO = 'baixado';

    protected $fillable = [
        'internal_stock_product_id', 'serial_number', 'plaqueta', 'modelo',
        'estado', 'status', 'observacao', 'created_by',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(InternalStockProduct::class, 'internal_stock_product_id');
    }

    public function scopeEmEstoque(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_EM_ESTOQUE);
    }
}
