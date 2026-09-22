<?php

namespace App\Services;

use App\Models\OfficeLicense;
use App\Models\OfficeLicensePrice;
use App\Models\OrganizationCostCenter;
use App\Models\OrganizationDepartment;
use App\Models\OrganizationUnit;

class OfficeAllocationClassifierService
{
    /**
     * @return array<string, mixed>
     */
    public function classify(OfficeLicense $license, array $sku, ?OfficeLicensePrice $price): array
    {
        $unit = $license->organization_unit_id
            ? OrganizationUnit::query()->find((int) $license->organization_unit_id)
            : null;
        $department = $license->organization_department_id
            ? OrganizationDepartment::query()->find((int) $license->organization_department_id)
            : null;
        $costCenter = $this->resolveCostCenter($license);
        $unicoop = $this->normalizeUnicoop($license->unicoop_office ?: $costCenter?->unit_source_id ?: $unit?->source_id);
        $area = $this->normalizeNumeric($license->area_office ?: $costCenter?->accounting_area_source_id);
        if ($unit === null && $unicoop !== null) {
            $unit = OrganizationUnit::query()
                ->where('source_system', 'intranet')
                ->where('source_id', (string) ((int) $unicoop))
                ->first();
        }
        $isHeadquarters = $unicoop === '01';
        if ($department === null && $isHeadquarters && $area !== null) {
            $department = OrganizationDepartment::query()
                ->where('source_system', 'intranet')
                ->where('source_area_id', $area)
                ->orderBy('id')
                ->first();
        }
        if ($department === null && $isHeadquarters && $this->isTechnologyCostCenter($costCenter?->name)) {
            $department = OrganizationDepartment::query()
                ->where('source_system', 'intranet')
                ->where('name', 'like', '%Tecnologia%')
                ->orderBy('id')
                ->first();
        }
        $messages = [];

        $classificationStatus = 'classified';
        $classificationType = $isHeadquarters ? 'headquarters_department' : 'unit';
        $hasManualCostCenter = ($license->organization_assignment_source ?? null) === 'manual'
            && (
                trim((string) ($license->centro_custo ?? '')) !== ''
                || (
                    trim((string) ($license->unicoop_office ?? '')) !== ''
                    && trim((string) ($license->area_office ?? '')) !== ''
                )
            );
        $hasAdCostCenter = ($license->organization_assignment_source ?? null) === 'ad'
            && trim((string) ($license->centro_custo ?? '')) !== '';
        $hasManualDestination = ($license->organization_assignment_source ?? null) === 'manual'
            && (! empty($license->organization_unit_id) || trim((string) ($license->unicoop_office ?? '')) !== '')
            && ($hasManualCostCenter || $hasAdCostCenter || $costCenter !== null || ! empty($license->organization_cost_center_id));
        $hasResolvedDestination = $hasManualDestination
            || (! empty($license->organization_unit_id) && ! empty($license->organization_cost_center_id));

        if ($price === null) {
            $classificationStatus = 'missing_price';
            $messages[] = 'Preço vigente não cadastrado para o produto.';
        } elseif (trim((string) ($license->colaborador_match_status ?? '')) === 'ambiguous' && ! $hasResolvedDestination) {
            $classificationStatus = 'ambiguous_match';
            $messages[] = 'Correspondência do colaborador está ambígua.';
        } elseif ($this->missingCollaborator($license) && ! $hasResolvedDestination) {
            $classificationStatus = 'missing_collaborator';
            $messages[] = 'Colaborador correspondente não encontrado no snapshot local.';
        } elseif ($unit === null) {
            $classificationStatus = 'missing_unit';
            $messages[] = 'Unidade canônica não encontrada.';
        } elseif (($costCenter === null && ! $hasManualCostCenter && ! $hasAdCostCenter) || (trim((string) $license->centro_custo) === '' && ! $hasResolvedDestination)) {
            $classificationStatus = 'missing_cost_center';
            $messages[] = 'Centro de custo não encontrado no catálogo local.';
        }

        // Unidade + centro de custo são suficientes para o rateio. O
        // departamento da Sede é enriquecimento e não bloqueia o cálculo.
        if ($classificationStatus === 'classified' && $isHeadquarters && $department === null) {
            $messages[] = 'Departamento da Sede não localizado; classificado pelo centro de custo.';
        }
        if ($classificationStatus === 'classified' && $hasResolvedDestination && $this->missingCollaborator($license)) {
            $messages[] = 'Destino organizacional manual utilizado; vínculo automático do colaborador permanece pendente.';
        }

        if ($classificationStatus !== 'classified') {
            $classificationType = 'pending';
        }

        $unitPrice = $price ? (float) $price->monthly_price : 0.0;
        $quantity = 1;

        return [
            'office_license_id' => $license->id,
            'office_user_external_id' => mb_strtolower(trim((string) $license->email)),
            'user_name' => (string) $license->nome,
            'user_email' => mb_strtolower(trim((string) $license->email)),
            'employee_source_id' => $this->nullableString($license->matricula),
            'employee_number' => $this->nullableString($license->matricula),
            'sku_id' => $sku['sku_id'],
            'sku_name' => $sku['name'],
            'organization_unit_id' => $unit?->id,
            'organization_department_id' => $isHeadquarters ? $department?->id : null,
            'organization_cost_center_id' => $costCenter?->id,
            'cost_center_source_id' => $costCenter?->source_id,
            'unit_name_snapshot' => $unit?->name ?? $this->nullableString($license->unidade_nome),
            'department_name_snapshot' => $isHeadquarters
                ? $this->normalizeDepartmentName($department?->name ?? $this->nullableString($license->departamento_nome))
                : null,
            'cost_center_code_snapshot' => $this->formatCostCenterSnapshot($license, $costCenter),
            'cost_center_name_snapshot' => $costCenter?->name ?? $this->nullableString($license->centro_custo_nome),
            'classification_type' => $classificationType,
            'classification_status' => $classificationStatus,
            'classification_message' => $messages !== [] ? implode(' ', $messages) : null,
            'unit_price' => $unitPrice,
            'quantity' => $quantity,
            'total_amount' => $classificationStatus === 'classified'
                ? round($unitPrice * $quantity, 2)
                : 0.0,
            'metadata' => [
                'sku_group' => $sku['group'],
                'source_unicoop' => $unicoop,
                'source_area' => $this->nullableString($license->area_office),
                'match_status' => $this->nullableString($license->colaborador_match_status),
                'match_message' => $this->nullableString($license->colaborador_match_message),
            ],
        ];
    }

    private function resolveCostCenter(OfficeLicense $license): ?OrganizationCostCenter
    {
        if (! empty($license->organization_cost_center_id)) {
            $linked = OrganizationCostCenter::query()->find((int) $license->organization_cost_center_id);
            if ($linked !== null) {
                return $linked;
            }
        }

        $sourceId = $this->costCenterSourceIdFromCode($license->centro_custo);
        if ($sourceId === null) {
            $unicoop = $this->normalizeUnicoop($license->unicoop_office);
            $area = $this->normalizeNumeric($license->area_office);
            $sourceId = $unicoop !== null && $area !== null
                ? ((string) ((int) $unicoop)) . str_pad($area, 3, '0', STR_PAD_LEFT)
                : null;
        }

        if ($sourceId === null) {
            return null;
        }

        return OrganizationCostCenter::query()
            ->where('source_system', 'intranet')
            ->where('source_id', $sourceId)
            ->orderByRaw("CASE WHEN source_type = 'sic' THEN 0 ELSE 1 END")
            ->first();
    }

    private function costCenterSourceIdFromCode(?string $value): ?string
    {
        $value = trim((string) $value);
        if ($value === '' || $value === '-' || $value === 'N/D') {
            return null;
        }

        if (str_contains($value, '.')) {
            [$unit, $area] = array_pad(explode('.', $value, 2), 2, '');
            $unitDigits = preg_replace('/\D+/', '', $unit) ?: '';
            $areaDigits = preg_replace('/\D+/', '', $area) ?: '';
            if ($unitDigits !== '' && $areaDigits !== '') {
                return (string) ((int) $unitDigits) . str_pad((string) ((int) $areaDigits), 3, '0', STR_PAD_LEFT);
            }
        }

        $digits = preg_replace('/\D+/', '', $value) ?: '';

        return $digits !== '' ? (string) ((int) $digits) : null;
    }

    private function formatCostCenterSnapshot(OfficeLicense $license, ?OrganizationCostCenter $costCenter): ?string
    {
        $current = $this->nullableString($license->centro_custo);
        if ($current !== null && str_contains($current, '.')) {
            return $current;
        }

        $unit = $this->normalizeUnicoop($costCenter?->unit_source_id ?: $license->unicoop_office);
        $area = $this->normalizeNumeric($costCenter?->accounting_area_source_id ?: $license->area_office);

        return $unit !== null && $area !== null
            ? $unit . '.' . str_pad($area, 3, '0', STR_PAD_LEFT)
            : $current;
    }

    private function missingCollaborator(OfficeLicense $license): bool
    {
        $status = trim((string) ($license->colaborador_match_status ?? ''));

        return $status !== '' && ! in_array($status, ['matched', 'missing_matricula'], true);
    }

    private function isTechnologyCostCenter(?string $name): bool
    {
        return $name !== null && preg_match(
            '/processamento\s+de\s+dados|inform[aá]tica|tecnologia\s+da\s+inform[aá]cao/ui',
            $name
        ) === 1;
    }

    private function normalizeDepartmentName(?string $name): ?string
    {
        $name = $this->nullableString($name);
        if ($name === null) {
            return null;
        }

        return preg_match('/seguran[cç]a\s+do\s+trabalho/ui', $name)
            ? 'Segurança Do Trabalho'
            : $name;
    }

    private function normalizeUnicoop(mixed $value): ?string
    {
        $digits = $this->normalizeNumeric($value);
        if ($digits === null) {
            return null;
        }

        return str_pad($digits, 2, '0', STR_PAD_LEFT);
    }

    private function normalizeNumeric(mixed $value): ?string
    {
        $digits = preg_replace('/\D+/', '', trim((string) $value)) ?: '';
        $digits = ltrim($digits, '0');

        return $digits !== '' ? $digits : null;
    }

    private function nullableString(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value !== '' ? $value : null;
    }
}
