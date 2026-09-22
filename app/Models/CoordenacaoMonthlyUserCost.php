<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CoordenacaoMonthlyUserCost extends Model
{
    protected $table = 'coordenacao_monthly_user_costs';
    protected $fillable = ['closing_id', 'product_id', 'user_key', 'unit_key', 'cost_type', 'amount', 'calculation_details'];
    protected $casts = ['amount' => 'decimal:2', 'calculation_details' => 'array'];
    public function product(): BelongsTo { return $this->belongsTo(CoordenacaoProduct::class, 'product_id'); }
    public function closing(): BelongsTo { return $this->belongsTo(CoordenacaoMonthlyClosing::class, 'closing_id'); }
}
