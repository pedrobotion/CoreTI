<?php

namespace App\Services;

use App\Models\CoordenacaoMonthlyClosing;

class CoordenacaoUserCostService
{
    public function forClosing(CoordenacaoMonthlyClosing $closing)
    {
        return $closing->userCosts()->with('product')->orderBy('unit_key')->orderBy('user_key')->get();
    }
}
