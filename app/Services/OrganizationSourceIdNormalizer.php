<?php

namespace App\Services;

class OrganizationSourceIdNormalizer
{
    public function normalizeNumericId(mixed $value): ?string
    {
        $value = trim((string) $value);
        if ($value === '' || $value === '\\N') {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $value) ?: '';
        if ($digits === '') {
            return null;
        }

        $digits = ltrim($digits, '0');

        if ($digits === '' || $digits === '0') {
            return null;
        }

        return $digits;
    }

    public function formatUnicoop(mixed $value): ?string
    {
        $normalized = $this->normalizeNumericId($value);

        return $normalized !== null ? str_pad($normalized, 2, '0', STR_PAD_LEFT) : null;
    }

    public function formatArea(mixed $value): ?string
    {
        $normalized = $this->normalizeNumericId($value);

        return $normalized !== null ? str_pad($normalized, 3, '0', STR_PAD_LEFT) : null;
    }

    public function formatCostCenter(mixed $unitSourceId, mixed $areaSourceId): ?string
    {
        $unit = $this->formatUnicoop($unitSourceId);
        $area = $this->formatArea($areaSourceId);

        return $unit !== null && $area !== null ? "{$unit}.{$area}" : null;
    }

    public function costCenterSourceId(mixed $unitSourceId, mixed $areaSourceId): ?string
    {
        $unit = $this->normalizeNumericId($unitSourceId);
        $area = $this->formatArea($areaSourceId);

        return $unit !== null && $area !== null ? $unit . $area : null;
    }
}
