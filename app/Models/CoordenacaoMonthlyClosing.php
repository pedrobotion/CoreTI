<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CoordenacaoMonthlyClosing extends Model
{
    protected $table = 'coordenacao_monthly_closings';
    protected $fillable = ['reference_month', 'status', 'calculated_at', 'reviewed_at', 'closed_at', 'exported_at', 'created_by', 'reviewed_by', 'closed_by', 'total_amount', 'total_individual_amount', 'total_shared_amount', 'metadata'];
    protected $casts = ['reference_month' => 'date', 'calculated_at' => 'datetime', 'reviewed_at' => 'datetime', 'closed_at' => 'datetime', 'exported_at' => 'datetime', 'total_amount' => 'decimal:2', 'total_individual_amount' => 'decimal:2', 'total_shared_amount' => 'decimal:2', 'metadata' => 'array'];
    public function allocations(): HasMany { return $this->hasMany(CoordenacaoMonthlyAllocation::class, 'closing_id'); }
    public function userCosts(): HasMany { return $this->hasMany(CoordenacaoMonthlyUserCost::class, 'closing_id'); }
    public function pendingItems(): HasMany { return $this->hasMany(CoordenacaoPendingItem::class, 'closing_id'); }
}
