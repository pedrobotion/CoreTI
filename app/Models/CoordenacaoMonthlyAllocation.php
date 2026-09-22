<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CoordenacaoMonthlyAllocation extends Model
{
    protected $table = 'coordenacao_monthly_allocations';
    protected $fillable = ['closing_id', 'product_id', 'unit_key', 'eligible_users', 'allocation_base', 'unit_amount', 'individual_amount', 'shared_amount', 'calculation_details'];
    protected $casts = ['eligible_users' => 'integer', 'allocation_base' => 'decimal:2', 'unit_amount' => 'decimal:2', 'individual_amount' => 'decimal:2', 'shared_amount' => 'decimal:2', 'calculation_details' => 'array'];
    public function product(): BelongsTo { return $this->belongsTo(CoordenacaoProduct::class, 'product_id'); }
    public function closing(): BelongsTo { return $this->belongsTo(CoordenacaoMonthlyClosing::class, 'closing_id'); }
}
