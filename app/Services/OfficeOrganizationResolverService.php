<?php

namespace App\Services;

use App\Models\HrCollaborator;
use App\Models\OrganizationCostCenter;
use App\Models\OrganizationDepartment;
use App\Models\OrganizationUnit;
use Illuminate\Support\Collection;

class OfficeOrganizationResolverService
{
    private ?Collection $units = null;

    private ?Collection $departments = null;

    private ?Collection $costCenters = null;

    public function __construct(private readonly OrganizationSourceIdNormalizer $normalizer)
    {
    }

    /**
     * @return array<string, mixed>
     */
    public function resolve(mixed $collaborator): array
    {
        return $this->resolveFromSourceIds(
            data_get($collaborator, 'unit_source_id'),
            data_get($collaborator, 'area_source_id'),
            data_get($collaborator, 'department_source_id'),
            data_get($collaborator, 'cost_center_source_id')
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function resolveFromSourceIds(mixed $unitSourceId, mixed $areaSourceId, mixed $departmentSourceId = null, mixed $costCenterSourceId = null): array
    {
        $unitSourceId = $this->normalizer->normalizeNumericId($unitSourceId);
        $areaSourceId = $this->normalizer->normalizeNumericId($areaSourceId);
        $departmentSourceId = $this->normalizer->normalizeNumericId($departmentSourceId);
        $costCenterSourceId = $this->normalizer->normalizeNumericId($costCenterSourceId);
        if ($costCenterSourceId === null) {
            $costCenterSourceId = $this->normalizer->costCenterSourceId($unitSourceId, $areaSourceId);
        }

        $costCenter = $this->findCostCenter($costCenterSourceId, $unitSourceId);
        if ($costCenter) {
            $unitSourceId = $this->normalizer->normalizeNumericId($costCenter->unit_source_id) ?? $unitSourceId;
            $areaSourceId = $this->normalizer->normalizeNumericId($costCenter->accounting_area_source_id) ?? $areaSourceId;
        }

        $unit = $this->findUnit($unitSourceId);
        $department = $this->findDepartment($departmentSourceId);
        if (! $department && $areaSourceId !== null) {
            $department = $this->findDepartmentByArea($areaSourceId);
        }

        $warnings = [];
        if ($unitSourceId !== null && ! $unit) {
            $warnings[] = "Unidade source_id {$unitSourceId} não encontrada no snapshot local.";
        }
        if ($departmentSourceId !== null && ! $department) {
            $warnings[] = "Departamento source_id {$departmentSourceId} não encontrado no snapshot local.";
        }
        if ($costCenterSourceId !== null && ! $costCenter) {
            $warnings[] = "Centro de custo source_id {$costCenterSourceId} não encontrado no snapshot local.";
        }

        return [
            'organization_unit_id' => $unit?->id,
            'organization_department_id' => $department?->id,
            'organization_cost_center_id' => $costCenter?->id,
            'unit_source_id' => $unitSourceId,
            'department_source_id' => $departmentSourceId,
            'area_source_id' => $areaSourceId,
            'cost_center_source_id' => $costCenterSourceId,
            'unit_name' => $unit?->name,
            'department_name' => $department?->name,
            'cost_center_code' => $this->normalizer->formatCostCenter($unitSourceId, $areaSourceId),
            'cost_center_name' => $costCenter?->name,
            'unit_found' => $unit !== null,
            'department_found' => $department !== null,
            'cost_center_found' => $costCenter !== null,
            'cost_center_source_type' => $costCenter?->source_type,
            'warnings' => $warnings,
        ];
    }

    private function findUnit(?string $sourceId): ?OrganizationUnit
    {
        return $this->findBySourceId($this->units(), $sourceId);
    }

    private function findDepartment(?string $sourceId): ?OrganizationDepartment
    {
        return $this->findBySourceId($this->departments(), $sourceId);
    }

    private function findDepartmentByArea(?string $areaSourceId): ?OrganizationDepartment
    {
        if ($areaSourceId === null) {
            return null;
        }

        return $this->departments()
            ->where('source_area_id', $areaSourceId)
            ->sortBy('id')
            ->first();
    }

    private function findCostCenter(?string $sourceId, ?string $unitSourceId = null): ?OrganizationCostCenter
    {
        if ($sourceId === null) {
            return null;
        }

        $rows = $this->costCenters()
            ->where('source_id', $sourceId)
            ->sortBy(fn (OrganizationCostCenter $row): string => sprintf(
                '%d|%d|%s',
                $row->source_type === 'sic' ? 0 : ($row->source_type === 'rh' ? 1 : 2),
                $unitSourceId !== null && $this->normalizer->normalizeNumericId($row->unit_source_id) === $unitSourceId ? 0 : 1,
                (string) $row->id
            ))
            ->values();

        return $rows->first();
    }

    /** @return Collection<int, OrganizationUnit> */
    private function units(): Collection
    {
        return $this->units ??= OrganizationUnit::query()
            ->where('source_system', 'intranet')
            ->get();
    }

    /** @return Collection<int, OrganizationDepartment> */
    private function departments(): Collection
    {
        return $this->departments ??= OrganizationDepartment::query()
            ->where('source_system', 'intranet')
            ->get();
    }

    /** @return Collection<int, OrganizationCostCenter> */
    private function costCenters(): Collection
    {
        return $this->costCenters ??= OrganizationCostCenter::query()
            ->where('source_system', 'intranet')
            ->get();
    }

    /**
     * @template T of object
     * @param \Illuminate\Support\Collection<int, T> $rows
     * @return T|null
     */
    private function findBySourceId(Collection $rows, ?string $sourceId): ?object
    {
        if ($sourceId === null) {
            return null;
        }

        $exact = $rows->first(fn (object $row): bool => (string) $row->source_id === $sourceId);
        if ($exact) {
            return $exact;
        }

        $matches = $rows
            ->filter(fn (object $row): bool => $this->normalizer->normalizeNumericId($row->source_id) === $sourceId)
            ->values();

        return $matches->count() === 1 ? $matches->first() : null;
    }
}
