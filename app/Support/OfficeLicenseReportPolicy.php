<?php

namespace App\Support;

class OfficeLicenseReportPolicy
{
    public static function excludedPatterns(): array
    {
        return [
            'fabric_free',
            'microsoft_fabric_free',
            'business_center',
            'microsoft_business_center',
            'power_automate_free',
            'microsoft_power_automate_free',
            'teams_exploratory',
            'microsoft_teams_exploratory',
        ];
    }

    public static function isExcluded(?string $skuPartNumber, ?string $skuName = null): bool
    {
        $value = self::normalize(($skuPartNumber ?: '') . ' ' . ($skuName ?: ''));

        foreach (self::excludedPatterns() as $pattern) {
            if (str_contains($value, self::normalize($pattern))) {
                return true;
            }
        }

        return false;
    }

    public static function exclusionReason(?string $skuPartNumber, ?string $skuName = null): ?string
    {
        if (! self::isExcluded($skuPartNumber, $skuName)) {
            return null;
        }

        $value = self::normalize(($skuPartNumber ?: '') . ' ' . ($skuName ?: ''));

        return str_contains($value, 'fabricfree') ? 'Microsoft Fabric (Free)' :
            (str_contains($value, 'businesscenter') ? 'Microsoft Business Center' :
            (str_contains($value, 'powerautomatefree') ? 'Microsoft Power Automate Free' : 'Microsoft Teams Exploratory'));
    }

    public static function displayName(?string $skuPartNumber, ?string $fallback = null): string
    {
        $part = self::normalize($skuPartNumber);

        return match (true) {
            str_contains($part, 'm365appsforenterprise') || str_contains($part, 'office365proplus') || str_contains($part, 'officesubscription') => 'Microsoft 365 Apps for enterprise',
            str_contains($part, 'm365appsforbusiness') || str_contains($part, 'o365business') => 'Microsoft 365 Apps for business',
            str_contains($part, 'powerbipro') => 'Power BI Pro',
            str_contains($part, 'powerappsfordeveloper') || str_contains($part, 'powerappsdeveloper') || str_contains($part, 'powerappsdev') => 'Microsoft Power Apps for Developer',
            str_contains($part, 'visioplan2') || str_contains($part, 'visioclient') => 'Visio Plan 2',
            default => trim((string) ($fallback ?: $skuPartNumber ?: '')),
        };
    }

    private static function normalize(?string $value): string
    {
        return preg_replace('/[^a-z0-9]+/', '', strtolower(trim((string) $value))) ?: '';
    }
}
