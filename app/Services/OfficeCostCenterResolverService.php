<?php

namespace App\Services;

class OfficeCostCenterResolverService
{
    /**
     * @return array{
     *     unit_found:bool,
     *     department_found:bool,
     *     unit_id:?int,
     *     organization_unit_id:?int,
     *     unit_name:?string,
     *     department_id:?int,
     *     organization_department_id:?int,
     *     department_name:?string,
     *     area_name:?string,
     *     setor_name:?string,
     *     message:?string
     * }
     */
    public function resolve(
        ?string $unicoop,
        ?string $area,
        ?string $nomeUnicoop = null,
        ?string $nomeArea = null,
        ?string $nomeDepartamento = null,
        ?string $nomeSetor = null,
        bool $strictDepartmentLookup = true,
    ): array {
        $normalizedUnicoop = $this->normalizeUnicoop($unicoop);
        $normalizedArea = $this->normalizeNumericComponent($area);

        $unit = null;
        if ($normalizedUnicoop !== null) {
            $unit = app(OrganizationLookupService::class)->findUnitByUnicoop($normalizedUnicoop);
        }

        $department = null;
        if ($normalizedUnicoop !== null && $normalizedArea !== null) {
            $department = app(OrganizationLookupService::class)->findDepartmentByUnicoopArea($normalizedUnicoop, $normalizedArea);
        }

        $unitName = $unit ? $this->nullableString($unit->name ?? null) : $this->nullableString($nomeUnicoop);
        $departmentName = $department ? $this->nullableString($department->name ?? null) : $this->nullableString($nomeDepartamento);
        $areaName = $this->nullableString($nomeArea);
        $setorName = $this->nullableString($nomeSetor);

        $warnings = [];
        if ($normalizedUnicoop === null) {
            $warnings[] = 'IdUnicoop ausente para resolver unidade interna.';
        } elseif (! $unit) {
            $warnings[] = "Unidade interna não encontrada para unicoop {$normalizedUnicoop}.";
        }

        if ($strictDepartmentLookup) {
            if ($normalizedUnicoop === null || $normalizedArea === null) {
                $warnings[] = 'IdArea ausente para resolver departamento interno.';
            } elseif (! $department) {
                $warnings[] = "Departamento interno não encontrado para unicoop {$normalizedUnicoop} e área {$normalizedArea}.";
            }
        }

        return [
            'unit_found' => $unit !== null,
            'department_found' => $department !== null,
            'unit_id' => null,
            'organization_unit_id' => $unit ? (int) ($unit->organization_unit_id ?? $unit->id ?? 0) : null,
            'unit_name' => $unitName,
            'department_id' => null,
            'organization_department_id' => $department ? (int) ($department->organization_department_id ?? $department->id ?? 0) : null,
            'department_name' => $departmentName,
            'area_name' => $areaName,
            'setor_name' => $setorName,
            'message' => $warnings !== [] ? implode(' ', $warnings) : null,
        ];
    }

    public function normalizeUnicoop(?string $value): ?string
    {
        $digits = $this->normalizeNumericComponent($value);
        if ($digits === null) {
            return null;
        }

        return strlen($digits) === 1 ? str_pad($digits, 2, '0', STR_PAD_LEFT) : $digits;
    }

    private function normalizeNumericComponent(?string $value): ?string
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

        return $digits !== '' ? $digits : '0';
    }

    private function nullableString(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value !== '' ? $value : null;
    }
}
