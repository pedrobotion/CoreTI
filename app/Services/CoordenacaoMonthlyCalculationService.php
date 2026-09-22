<?php

namespace App\Services;

use App\Models\CoordenacaoProduct;
use Illuminate\Support\Collection;

class CoordenacaoMonthlyCalculationService
{
    public function __construct(
        private readonly CoordenacaoParticipantResolverService $participants,
        private readonly CoordenacaoProductRateService $rates,
    ) {
    }

    public function calculate(): array
    {
        $people = $this->participants->active();
        $allocations = collect();
        $userCosts = collect();
        $pending = collect();

        CoordenacaoProduct::query()->with(['rules', 'units', 'users'])->where('is_active', true)->get()->each(function (CoordenacaoProduct $product) use ($people, $allocations, $userCosts, $pending): void {
            $eligible = $this->eligiblePeople($product, $people);
            $monthly = $this->rates->monthlyAmount($product);
            if ($monthly <= 0) {
                $pending->push(['product_id' => $product->id, 'type' => 'missing_amount', 'severity' => 'warning', 'title' => 'Produto sem valor configurado', 'description' => $product->name]);
                return;
            }

            $byUnit = $eligible->groupBy(fn (array $person): string => trim((string) ($person['resolved_unit'] ?? '')) ?: 'Sem unidade');
            $unitCount = max($byUnit->count(), 1);
            $unitAmount = $product->distribution_method === 'equal_unit' ? $monthly / $unitCount : $monthly;
            foreach ($byUnit as $unit => $users) {
                $shared = $product->individual_appropriation_method === 'individual' || $product->distribution_method === 'per_collaborator'
                    ? 0.0 : round($unitAmount, 2);
                $individual = $shared > 0 ? 0.0 : round($unitAmount / max($users->count(), 1), 2);
                $allocations->push([
                    'product_id' => $product->id, 'unit_key' => $unit, 'eligible_users' => $users->count(),
                    'allocation_base' => $monthly, 'unit_amount' => round($unitAmount, 2),
                    'individual_amount' => $individual * $users->count(), 'shared_amount' => $shared,
                    'calculation_details' => ['method' => $product->distribution_method],
                ]);
                foreach ($users as $person) {
                    $amount = $shared > 0 ? round($shared / max($users->count(), 1), 2) : $individual;
                    $userCosts->push([
                        'product_id' => $product->id, 'user_key' => $person['email'] ?: $person['key'], 'unit_key' => $unit,
                        'cost_type' => $shared > 0 ? 'shared' : 'individual', 'amount' => $amount,
                        'calculation_details' => ['product' => $product->name],
                    ]);
                }
            }
        });

        return compact('allocations', 'userCosts', 'pending');
    }

    private function eligiblePeople(CoordenacaoProduct $product, Collection $people): Collection
    {
        $basis = $product->allocation_basis;
        $result = $people->filter(function (array $person) use ($basis, $product): bool {
            $match = match ($basis) {
                'office_users' => (bool) ($person['has_office_license'] ?? false),
                'email_users' => (bool) ($person['has_google_email'] ?? false),
                'manual_users' => $product->users->contains(fn ($row): bool => $row->participation_type === 'include' && $row->user_key === ($person['email'] ?: $person['key'])),
                default => true,
            };
            if (! $match) return false;
            $key = $person['email'] ?: $person['key'];
            return ! $product->users->contains(fn ($row): bool => $row->participation_type === 'exclude' && $row->user_key === $key);
        });

        $unitRules = $product->units;
        if ($unitRules->isNotEmpty()) {
            $included = $unitRules->where('participation_type', 'include')->pluck('unit_key')->map(fn ($v) => mb_strtolower(trim((string) $v)));
            $excluded = $unitRules->where('participation_type', 'exclude')->pluck('unit_key')->map(fn ($v) => mb_strtolower(trim((string) $v)));
            $result = $result->filter(function (array $person) use ($included, $excluded): bool {
                $unit = mb_strtolower(trim((string) ($person['resolved_unit'] ?? '')));
                return ($included->isEmpty() || $included->contains($unit)) && ! $excluded->contains($unit);
            });
        }
        return $result->values();
    }
}
