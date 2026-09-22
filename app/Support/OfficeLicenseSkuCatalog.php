<?php

namespace App\Support;

use Illuminate\Support\Collection;

class OfficeLicenseSkuCatalog
{
    /**
     * @return array<string, array{column:string, sku_id:string, name:string, group:string}>
     */
    public static function items(): array
    {
        return [
            'office_apps' => [
                'column' => 'office_apps',
                'sku_id' => 'OFFICE_APPS',
                'name' => 'Microsoft 365 Apps',
                'group' => 'apps',
            ],
            'office_business' => [
                'column' => 'office_business',
                'sku_id' => 'OFFICE_BUSINESS',
                'name' => 'Microsoft 365 Business',
                'group' => 'business',
            ],
            'powerbi_pro' => [
                'column' => 'powerbi_pro',
                'sku_id' => 'POWER_BI_PRO',
                'name' => 'Power BI Pro',
                'group' => 'powerbi',
            ],
            'powerbi_premium' => [
                'column' => 'powerbi_premium',
                'sku_id' => 'POWER_BI_PREMIUM',
                'name' => 'Power BI Premium',
                'group' => 'powerbi',
            ],
            'visio_plan' => [
                'column' => 'visio_plan',
                'sku_id' => 'VISIO_PLAN',
                'name' => 'Visio Plan 2',
                'group' => 'visio',
            ],
        ];
    }

    public static function all(): Collection
    {
        return collect(self::items())->values();
    }

    public static function billable(): Collection
    {
        return self::all()->whereIn('sku_id', [
            'OFFICE_APPS',
            'OFFICE_BUSINESS',
            'POWER_BI_PRO',
            'VISIO_PLAN',
        ])->values();
    }

    public static function find(string $skuId): ?array
    {
        return self::all()->first(fn (array $item): bool => $item['sku_id'] === $skuId);
    }

    public static function label(string $skuId): string
    {
        return self::find($skuId)['name'] ?? $skuId;
    }
}
