<?php

namespace App\Services;

use App\Models\OfficeLicensePrice;
use App\Support\OfficeLicenseSkuCatalog;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class OfficeLicensePriceService
{
    public function applicablePrice(string $skuId, string $competence): ?OfficeLicensePrice
    {
        $periodStart = CarbonImmutable::createFromFormat('Y-m-d', $competence . '-01')->startOfDay();
        $periodEnd = $periodStart->endOfMonth();

        return OfficeLicensePrice::query()
            ->where('sku_id', $skuId)
            ->where('active', true)
            ->whereDate('valid_from', '<=', $periodEnd->toDateString())
            ->where(function ($query) use ($periodStart): void {
                $query->whereNull('valid_until')
                    ->orWhereDate('valid_until', '>=', $periodStart->toDateString());
            })
            ->orderByDesc('valid_from')
            ->orderByDesc('id')
            ->first();
    }

    /**
     * @return array<string, OfficeLicensePrice|null>
     */
    public function applicablePrices(string $competence): array
    {
        return OfficeLicenseSkuCatalog::all()
            ->mapWithKeys(fn (array $sku): array => [
                $sku['sku_id'] => $this->applicablePrice($sku['sku_id'], $competence),
            ])
            ->all();
    }

    public function priceSnapshot(string $competence): array
    {
        return collect($this->applicablePrices($competence))
            ->map(fn (?OfficeLicensePrice $price, string $skuId): array => [
                'sku_id' => $skuId,
                'sku_name' => OfficeLicenseSkuCatalog::label($skuId),
                'price_id' => $price?->id,
                'monthly_price' => $price ? (float) $price->monthly_price : null,
                'currency' => $price?->currency,
                'valid_from' => $price?->valid_from?->toDateString(),
                'valid_until' => $price?->valid_until?->toDateString(),
            ])
            ->values()
            ->all();
    }

    public function activeHistory(): Collection
    {
        return OfficeLicensePrice::query()
            ->orderBy('sku_id')
            ->orderByDesc('valid_from')
            ->get();
    }
}
