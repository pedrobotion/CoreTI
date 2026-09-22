<?php

namespace App\Services;

use App\Models\CoordenacaoMonthlyClosing;
use Illuminate\Support\Facades\DB;

class CoordenacaoClosingService
{
    public function __construct(private readonly CoordenacaoMonthlyCalculationService $calculator)
    {
    }

    public function calculate(CoordenacaoMonthlyClosing $closing, ?int $userId = null): CoordenacaoMonthlyClosing
    {
        if (in_array($closing->status, ['closed', 'exported'], true)) {
            throw new \RuntimeException('Fechamentos encerrados não podem ser recalculados.');
        }
        $result = $this->calculator->calculate();
        return DB::transaction(function () use ($closing, $result, $userId): CoordenacaoMonthlyClosing {
            $closing->allocations()->delete();
            $closing->userCosts()->delete();
            $closing->pendingItems()->delete();
            $closing->allocations()->createMany($result['allocations']->all());
            $closing->userCosts()->createMany($result['userCosts']->all());
            foreach ($result['pending'] as $pending) {
                $closing->pendingItems()->create($pending + ['status' => 'open']);
            }
            $individual = (float) $result['allocations']->sum('individual_amount');
            $shared = (float) $result['allocations']->sum('shared_amount');
            $closing->update(['status' => 'calculated', 'calculated_at' => now(), 'total_amount' => $individual + $shared, 'total_individual_amount' => $individual, 'total_shared_amount' => $shared, 'metadata' => ['pending' => $result['pending']->values()->all(), 'calculated_by' => $userId]]);
            return $closing->fresh(['allocations.product', 'userCosts.product']);
        });
    }
}
