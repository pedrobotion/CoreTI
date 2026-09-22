<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CoordenacaoProductUnit extends Model
{
    protected $table = 'coordenacao_product_units';
    protected $fillable = ['product_id', 'unit_key', 'participation_type', 'fixed_amount'];
    protected $casts = ['fixed_amount' => 'decimal:2'];
    public function product(): BelongsTo { return $this->belongsTo(CoordenacaoProduct::class, 'product_id'); }
}
