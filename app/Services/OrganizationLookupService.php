<?php

namespace App\Services;

use App\Models\OrganizationDepartment;
use App\Models\OrganizationCostCenter;
use App\Models\OrganizationUnit;
use App\Models\HrCollaborator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class OrganizationLookupService
{
    /** @var array<string, Collection> */
    private array $optionCache = [];

    /** @var array<string, object|null> */
    private array $departmentByLocationCache = [];

    public function resolveRateioByEmployeeNumber(string $employeeNumber): ?array
    {
        $employeeNumber = trim($employeeNumber);
        if ($employeeNumber === '' || ! Schema::hasTable('hr_collaborators')) {
            return null;
        }

        $employeeDigits = preg_replace('/\D+/', '', $employeeNumber) ?: '';
        $employeeNumberNormalized = ltrim($employeeDigits, '0') ?: '0';

        $collaborator = HrCollaborator::query()
            ->where(function ($query) use ($employeeNumber, $employeeNumberNormalized): void {
                $query->where('employee_number', $employeeNumber)
                    ->orWhere('source_id', $employeeNumber)
                    ->orWhereRaw('CAST(employee_number AS UNSIGNED) = ?', [(int) $employeeNumberNormalized])
                    ->orWhereRaw('CAST(source_id AS UNSIGNED) = ?', [(int) $employeeNumberNormalized]);
            })
            ->where('active', true)
            ->where('terminated', false)
            ->orderByDesc('updated_at')
            ->first([
                'source_id',
                'employee_number',
                'name',
                'unit_source_id',
                'area_source_id',
                'cost_center_source_id',
            ]);

        return $this->buildRateioForCollaborator($collaborator);
    }

    public function resolveRateioByCollaboratorName(string $name): ?array
    {
        $normalizedName = $this->normalizeName($name);
        if ($normalizedName === '' || ! Schema::hasTable('hr_collaborators')) {
            return null;
        }

        $matches = HrCollaborator::query()
            ->where('active', true)
            ->where('terminated', false)
            ->where('excluded_from_protheus', false)
            ->get(['source_id', 'employee_number', 'name', 'unit_source_id', 'area_source_id', 'cost_center_source_id'])
            ->filter(fn (HrCollaborator $item): bool => $this->normalizeName((string) $item->name) === $normalizedName)
            ->values();

        return $matches->count() === 1
            ? $this->buildRateioForCollaborator($matches->first())
            : null;
    }

    private function buildRateioForCollaborator(?HrCollaborator $collaborator): ?array
    {
        if (! $collaborator || trim((string) $collaborator->cost_center_source_id) === '') {
            return null;
        }

        $costCenter = OrganizationCostCenter::query()
            ->active()
            ->where('source_id', trim((string) $collaborator->cost_center_source_id))
            ->whereNotNull('accounting_area_source_id')
            ->orderByRaw("CASE WHEN source_type = 'sic' THEN 0 WHEN source_type = 'rh' THEN 1 ELSE 2 END")
            ->orderByDesc('updated_at')
            ->first([
                'id',
                'source_id',
                'source_type',
                'name',
                'unit_source_id',
                'accounting_area_source_id',
            ]);

        if (! $costCenter) {
            return null;
        }

        $unicoop = $this->formatUnicoop($collaborator->unit_source_id ?: $costCenter->unit_source_id);
        $area = $this->formatArea($costCenter->accounting_area_source_id ?: $collaborator->area_source_id);

        if ($unicoop === null || $area === null || $area === '000') {
            return null;
        }

        return [
            'matricula' => trim((string) ($collaborator->employee_number ?: $collaborator->source_id)),
            'colaborador_nome' => trim((string) $collaborator->name) ?: null,
            'centro_custo' => trim((string) $costCenter->name) ?: null,
            'centro_custo_nome' => trim((string) $costCenter->name) ?: null,
            'centro_custo_codigo' => $unicoop . '.' . $area,
            'unicoop_sede' => $unicoop,
            'area_sede' => $area,
            'organization_cost_center_id' => $costCenter->id,
            'source_type' => $costCenter->source_type,
        ];
    }

    public function costCenterOptions(bool $activeOnly = true): Collection
    {
        return $this->unitOptions($activeOnly)
            ->merge($this->departmentOptions($activeOnly))
            ->sortBy('sort_key')
            ->unique(fn ($item) => mb_strtolower($item->name) . '|' . trim((string) $item->unicoop) . '|' . trim((string) $item->area) . '|' . $item->source)
            ->values();
    }

    public function unitOptions(bool $activeOnly = true): Collection
    {
        $cacheKey = 'units:' . ($activeOnly ? 'active' : 'all');
        if (isset($this->optionCache[$cacheKey])) {
            return $this->optionCache[$cacheKey];
        }

        if (! Schema::hasTable('organization_units')) {
            return collect();
        }

        $options = OrganizationUnit::query()
            ->when($activeOnly, fn ($query) => $query->active())
            ->orderBy('name')
            ->get(['id', 'source_id', 'code', 'name', 'address', 'active'])
            ->map(function (OrganizationUnit $unit) {
                $unicoop = $this->formatUnicoop($unit->source_id);
                $costCenter = $this->findCostCenterFor($unit->source_id, '154');

                return (object) [
                    'id' => $unit->id,
                    'legacy_id' => (int) $unit->source_id,
                    'name' => trim((string) $unit->name),
                    'display_name' => trim((string) $unit->name) . ' (Unidade)',
                    'unicoop' => $unicoop,
                    'area' => '154',
                    'source' => 'unidade',
                    'organization_unit_id' => $unit->id,
                    'organization_department_id' => null,
                    'organization_cost_center_id' => $costCenter?->id,
                    'cost_center_name' => $costCenter?->name,
                    'address' => $unit->address,
                    'active' => $unit->active,
                    'sort_key' => Str::ascii(mb_strtolower(trim((string) $unit->name))),
                ];
            });

        return $this->optionCache[$cacheKey] = $options;
    }

    public function departmentOptions(bool $activeOnly = true): Collection
    {
        $cacheKey = 'departments:' . ($activeOnly ? 'active' : 'all');
        if (isset($this->optionCache[$cacheKey])) {
            return $this->optionCache[$cacheKey];
        }

        if (! Schema::hasTable('organization_departments')) {
            return collect();
        }

        $options = OrganizationDepartment::query()
            ->when($activeOnly, fn ($query) => $query->active())
            ->orderBy('name')
            ->get(['id', 'source_id', 'source_unit_id', 'unit_id', 'source_area_id', 'name', 'active'])
            ->map(function (OrganizationDepartment $department) {
                $unicoop = $this->formatUnicoop($department->source_unit_id) ?: '01';
                $area = $this->formatArea($department->source_area_id);
                $unit = $department->source_unit_id ? $this->findUnitBySourceId($department->source_unit_id) : null;
                $costCenter = $this->findCostCenterFor($department->source_unit_id ?: '1', $department->source_area_id);

                return (object) [
                    'id' => $department->id,
                    'legacy_id' => (int) $department->source_id,
                    'name' => trim((string) $department->name),
                    'display_name' => trim((string) $department->name) . ' (Departamento)',
                    'unicoop' => $unicoop,
                    'area' => $area,
                    'source' => 'departamento',
                    'organization_unit_id' => $unit?->id,
                    'organization_department_id' => $department->id,
                    'organization_cost_center_id' => $costCenter?->id,
                    'cost_center_name' => $costCenter?->name,
                    'address' => null,
                    'active' => $department->active,
                    'sort_key' => Str::ascii(mb_strtolower(trim((string) $department->name))),
                ];
            });

        return $this->optionCache[$cacheKey] = $options;
    }

    public function findUnitBySourceId(int|string|null $sourceId): ?OrganizationUnit
    {
        $sourceId = trim((string) $sourceId);
        if ($sourceId === '' || ! Schema::hasTable('organization_units')) {
            return null;
        }

        return OrganizationUnit::query()->bySourceId($sourceId)->first();
    }

    public function findDepartmentBySourceId(int|string|null $sourceId): ?OrganizationDepartment
    {
        $sourceId = trim((string) $sourceId);
        if ($sourceId === '' || ! Schema::hasTable('organization_departments')) {
            return null;
        }

        return OrganizationDepartment::query()->bySourceId($sourceId)->first();
    }

    public function findCostCenterByName(string $name, ?string $area = null): ?object
    {
        $name = trim($name);
        if ($name === '') {
            return null;
        }

        $area = $this->normalizeNumeric($area);

        $normalizedName = $this->normalizeName($name);
        $canonicalName = $this->canonicalAlias($normalizedName);

        $legacyMatch = $this->findLegacyCostCenterByName($canonicalName, $area);
        if ($legacyMatch !== null) {
            return $legacyMatch;
        }

        $match = $this->costCenterOptions(false)->first(function ($item) use ($canonicalName, $area): bool {
            if ($this->canonicalAlias($this->normalizeName((string) $item->name)) !== $canonicalName) {
                return false;
            }

            return $area === null || $this->normalizeNumeric($item->area) === $area;
        });

        return $match;
    }

    /**
     * Resolve nomes vindos do AD contra o catalogo antigo ainda utilizado por
     * algumas instalacoes. O AD pode enviar abreviacoes, sem acentos ou nomes
     * de departamentos no mesmo campo da unidade.
     */
    private function findLegacyCostCenterByName(string $canonicalName, ?string $area = null): ?object
    {
        if (! Schema::hasTable('unidades') || ! Schema::hasTable('departamentos')) {
            return null;
        }

        $unit = DB::table('unidades')->get()->first(function (object $row) use ($canonicalName): bool {
            return $this->canonicalAlias($this->normalizeName((string) $row->unidade)) === $canonicalName;
        });

        if ($unit !== null) {
            $unicoop = $this->normalizeNumeric($unit->unicoop);
            $unitArea = $this->normalizeNumeric($area) ?? $this->normalizeNumeric($unit->area) ?? '154';
            $orgUnit = $unicoop !== null ? $this->findUnitBySourceId($unicoop) : null;
            $costCenter = $unicoop !== null ? $this->findCostCenterFor($unicoop, $unitArea) : null;

            return (object) [
                'name' => trim((string) $unit->unidade),
                'unicoop' => $this->formatUnicoop($unicoop),
                'area' => $this->formatArea($unitArea),
                'source' => 'unidade_legacy_alias',
                'organization_unit_id' => $orgUnit?->id,
                'organization_department_id' => null,
                'organization_cost_center_id' => $costCenter?->id,
                'cost_center_name' => $costCenter?->name,
            ];
        }

        $department = DB::table('departamentos')->where('ativo', 1)->get()->first(function (object $row) use ($canonicalName): bool {
            return $this->canonicalAlias($this->normalizeName((string) $row->nome)) === $canonicalName;
        });

        if ($department === null) {
            return null;
        }

        $unicoop = $this->normalizeNumeric($department->unicoop) ?? '1';
        $departmentArea = $this->normalizeNumeric($area) ?? $this->normalizeNumeric($department->area);
        $orgUnit = $this->findUnitBySourceId($unicoop);
        $orgDepartment = $departmentArea !== null
            ? OrganizationDepartment::query()
                ->where('source_system', 'intranet')
                ->where('source_area_id', $departmentArea)
                ->when($unicoop !== '1', fn ($query) => $query->where('source_unit_id', $unicoop))
                ->orderBy('id')
                ->first()
            : null;
        $costCenter = $departmentArea !== null ? $this->findCostCenterFor($unicoop, $departmentArea) : null;

        return (object) [
            'name' => trim((string) $department->nome),
            'unicoop' => $this->formatUnicoop($unicoop),
            'area' => $this->formatArea($departmentArea),
            'source' => 'departamento_legacy_alias',
            'organization_unit_id' => $orgUnit?->id,
            'organization_department_id' => $orgDepartment?->id,
            'organization_cost_center_id' => $costCenter?->id,
            'cost_center_name' => $costCenter?->name,
        ];
    }

    private function normalizeName(string $value): string
    {
        $value = Str::ascii(mb_strtolower(trim($value)));
        $value = preg_replace('/[\-_]+/', ' ', $value) ?: $value;
        $value = preg_replace('/\s+/', ' ', $value) ?: $value;

        return trim($value);
    }

    private function canonicalAlias(string $value): string
    {
        return [
            'rh' => 'recursos humanos',
            'dh' => 'desenvolvimento humano',
            'desenvolvimento' => 'desenvolvimento humano',
            'ti' => 'tecnologia da informacao',
            'comercializacao' => 'comercializacao',
        ][$value] ?? $value;
    }

    public function findByUnicoopArea(?string $unicoop, ?string $area): ?object
    {
        $unicoop = $this->normalizeNumeric($unicoop);
        $area = $this->normalizeNumeric($area);

        if ($unicoop === null) {
            return null;
        }

        return $this->costCenterOptions(false)->first(function ($item) use ($unicoop, $area): bool {
            if ($this->normalizeNumeric($item->unicoop) !== $unicoop) {
                return false;
            }

            return $area === null || $this->normalizeNumeric($item->area) === $area;
        });
    }

    public function findUnitByUnicoop(?string $unicoop): ?object
    {
        $unicoop = $this->normalizeNumeric($unicoop);
        if ($unicoop === null) {
            return null;
        }

        return $this->unitOptions(false)->first(
            fn ($item): bool => $this->normalizeNumeric($item->unicoop) === $unicoop
        );
    }

    public function findDepartmentByUnicoopArea(?string $unicoop, ?string $area): ?object
    {
        $unicoop = $this->normalizeNumeric($unicoop);
        $area = $this->normalizeNumeric($area);
        if ($unicoop === null || $area === null) {
            return null;
        }

        $cacheKey = $unicoop . '|' . $area;
        if (array_key_exists($cacheKey, $this->departmentByLocationCache)) {
            return $this->departmentByLocationCache[$cacheKey];
        }

        $query = OrganizationDepartment::query()
            ->where('source_system', 'intranet')
            ->where('source_area_id', $area);

        if ($unicoop !== '1') {
            $query->where('source_unit_id', $unicoop);
        }

        $department = $query->orderBy('id')->first();
        if (! $department && $unicoop === '1') {
            $department = OrganizationDepartment::query()
                ->where('source_system', 'intranet')
                ->where('source_area_id', $area)
                ->orderBy('id')
                ->first();
        }

        if (! $department) {
            return $this->departmentByLocationCache[$cacheKey] = null;
        }

        return $this->departmentByLocationCache[$cacheKey] = (object) [
            'id' => $department->id,
            'organization_department_id' => $department->id,
            'name' => $department->name,
            'area' => $this->formatArea($department->source_area_id),
            'unicoop' => $this->formatUnicoop($department->source_unit_id) ?: '01',
        ];
    }

    private function findCostCenterFor(int|string|null $unitSourceId, int|string|null $areaSourceId): ?OrganizationCostCenter
    {
        $unit = $this->normalizeNumeric($unitSourceId);
        $area = $this->formatArea($areaSourceId);
        if ($unit === null || $area === null) {
            return null;
        }

        return OrganizationCostCenter::query()
            ->where('source_system', 'intranet')
            ->where('source_id', $unit . $area)
            ->orderByRaw("CASE WHEN source_type = 'sic' THEN 0 WHEN source_type = 'rh' THEN 1 ELSE 2 END")
            ->orderBy('id')
            ->first();
    }

    private function formatUnicoop(int|string|null $value): ?string
    {
        $digits = $this->normalizeNumeric($value);

        return $digits !== null ? str_pad($digits, 2, '0', STR_PAD_LEFT) : null;
    }

    private function formatArea(int|string|null $value): ?string
    {
        $digits = $this->normalizeNumeric($value);

        return $digits !== null ? str_pad($digits, 3, '0', STR_PAD_LEFT) : null;
    }

    private function normalizeNumeric(int|string|null $value): ?string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $value) ?: '';
        $digits = ltrim($digits, '0');

        return $digits !== '' ? $digits : '0';
    }
}
