<?php

namespace App\Services;

use App\Models\CoordenacaoProduct;

class CoordenacaoProductRateService
{
    public function monthlyAmount(CoordenacaoProduct $product): float
    {
        $months = $product->billing_period === 'annual' ? 12 : (int) ($product->period_months ?: 1);
        $base = match ($product->billing_period) {
            'one_time' => 0.0,
            'annual' => (float) $product->contract_amount / 12,
            default => (float) $product->contract_amount / max($months, 1),
        };

        return round($base + (float) $product->monthly_maintenance_amount, 2);
    }
}
