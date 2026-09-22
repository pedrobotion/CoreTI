<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CoordenacaoPendingItem extends Model
{
    protected $table = 'coordenacao_pending_items';
    protected $fillable = ['product_id', 'closing_id', 'type', 'severity', 'title', 'description', 'status', 'metadata'];
    protected $casts = ['metadata' => 'array'];
}
