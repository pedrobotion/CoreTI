<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CoordenacaoProduct extends Model
{
    protected $table = 'coordenacao_products';

    protected $fillable = [
        'name', 'supplier', 'description', 'contract_amount', 'billing_period', 'period_months',
        'monthly_maintenance_amount', 'allocation_basis', 'distribution_method',
        'individual_appropriation_method', 'starts_at', 'ends_at', 'is_active',
        'configuration_status', 'notes',
    ];

    protected $casts = [
        'contract_amount' => 'decimal:2',
        'monthly_maintenance_amount' => 'decimal:2',
        'period_months' => 'integer',
        'starts_at' => 'date',
        'ends_at' => 'date',
        'is_active' => 'boolean',
    ];

    public function rules(): HasMany { return $this->hasMany(CoordenacaoProductRule::class, 'product_id'); }
    public function units(): HasMany { return $this->hasMany(CoordenacaoProductUnit::class, 'product_id'); }
    public function users(): HasMany { return $this->hasMany(CoordenacaoProductUser::class, 'product_id'); }
}
