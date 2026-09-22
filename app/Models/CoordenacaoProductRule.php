<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CoordenacaoProductRule extends Model
{
    protected $table = 'coordenacao_product_rules';
    protected $fillable = ['product_id', 'source_type', 'source_value', 'include_or_exclude', 'priority', 'metadata'];
    protected $casts = ['priority' => 'integer', 'metadata' => 'array'];
    public function product(): BelongsTo { return $this->belongsTo(CoordenacaoProduct::class, 'product_id'); }
}
