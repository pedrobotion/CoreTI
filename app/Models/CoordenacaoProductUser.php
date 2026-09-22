<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CoordenacaoProductUser extends Model
{
    protected $table = 'coordenacao_product_users';
    protected $fillable = ['product_id', 'user_key', 'participation_type', 'notes'];
    public function product(): BelongsTo { return $this->belongsTo(CoordenacaoProduct::class, 'product_id'); }
}
