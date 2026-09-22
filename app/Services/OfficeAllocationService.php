<?php

namespace App\Services;

use App\Models\OfficeAllocationBatch;
use App\Models\OfficeAllocationRow;
use App\Models\OrganizationCostCenter;
use App\Models\OfficeLicense;
use App\Models\OfficeLicenseAssignment;
use App\Support\OfficeLicenseSkuCatalog;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

class OfficeAllocationService
{
    public const CRITICAL_PENDING_STATUSES = [
        'missing_price',
        'unknown_sku',
        'missing_collaborator',
        'ambiguous_match',
        'missing_unit',
        'missing_cost_center',
    ];

    public function __construct(
        private readonly OfficeLicensePriceService $priceService,
        private readonly OfficeAllocationClassifierService $classifier,
    ) {
    }

    public function generate(string $competence, ?int $userId = null): OfficeAllocationBatch
    {
        $this->assertCompetence($competence);
        $closedExists = OfficeAllocationBatch::query()
            ->where('competence', $competence)
            ->where('status', 'closed')
            ->exists();
        if ($closedExists) {
            throw new RuntimeException('Já existe rateio fechado para esta competência.');
        }

        return DB::transaction(function () use ($competence, $userId): OfficeAllocationBatch {
            $batch = OfficeAllocationBatch::query()
                ->where('competence', $competence)
                ->whereNotIn('status', ['closed', 'cancelled'])
                ->orderByDesc('version')
                ->lockForUpdate()
                ->first();

            if (! $batch) {
                $version = ((int) OfficeAllocationBatch::query()
                    ->where('competence', $competence)
                    ->max('version')) + 1;

                $batch = OfficeAllocationBatch::create([
                    'competence' => $competence,
                    'version' => max($version, 1),
                    'status' => 'processing',
                    'generated_by' => $userId,
                    'generated_at' => now(),
                ]);
            } else {
                $batch->update([
                    'status' => 'processing',
                    'generated_by' => $userId,
                    'generated_at' => now(),
                    'validated_by' => null,
                    'validated_at' => null,
                    'closed_by' => null,
                    'closed_at' => null,
                ]);
                $batch->rows()->delete();
            }

            $prices = $this->priceService->applicablePrices($competence);
            $rows = [];
            $licenses = $this->licensedUsersQuery()->with('assignments')->get();

            foreach ($licenses as $license) {
                $assignments = $license->assignments
                    ->where('included_in_report', true)
                    ->filter(fn (OfficeLicenseAssignment $assignment): bool => $this->isBillableAssignment($assignment));
                if ($assignments->isNotEmpty()) {
                    foreach ($assignments as $assignment) {
                        $sku = $this->skuFromAssignment($assignment);
                        $price = $prices[$sku['sku_id']] ?? $this->legacyPriceForAssignment($assignment, $prices);
                        $rows[] = $this->classifier->classify($license, $sku, $price);
                    }
                    continue;
                }

                // Compatibilidade com registros criados antes da tabela
                // normalizada ou com importação manual sem assignments.
                foreach (OfficeLicenseSkuCatalog::items() as $sku) {
                    if ((bool) $license->{$sku['column']} && $this->isBillableLegacySku($sku['sku_id'])) {
                        $rows[] = $this->classifier->classify($license, $sku, $prices[$sku['sku_id']] ?? null);
                    }
                }
            }

            foreach (array_chunk($rows, 500) as $chunk) {
                $batch->rows()->createMany($chunk);
            }

            return $this->refreshBatchTotals($batch, $competence);
        });
    }

    public function validate(OfficeAllocationBatch $batch, ?int $userId = null): OfficeAllocationBatch
    {
        $this->assertMutable($batch);
        if ($this->criticalPendingCount($batch) > 0) {
            throw new RuntimeException('Não é possível validar com pendências críticas.');
        }

        $batch->update([
            'status' => 'validated',
            'validated_by' => $userId,
            'validated_at' => now(),
        ]);

        return $batch->refresh();
    }

    public function close(OfficeAllocationBatch $batch, ?int $userId = null): OfficeAllocationBatch
    {
        $this->assertMutable($batch);
        if ($this->criticalPendingCount($batch) > 0) {
            throw new RuntimeException('Não é possível fechar com pendências críticas.');
        }
        if (OfficeAllocationBatch::query()
            ->where('competence', $batch->competence)
            ->where('status', 'closed')
            ->whereKeyNot($batch->id)
            ->exists()) {
            throw new RuntimeException('Já existe outro rateio fechado para esta competência.');
        }

        $batch->update([
            'status' => 'closed',
            'validated_by' => $batch->validated_by ?: $userId,
            'validated_at' => $batch->validated_at ?: now(),
            'closed_by' => $userId,
            'closed_at' => now(),
        ]);

        return $batch->refresh();
    }

    public function cancel(OfficeAllocationBatch $batch, string $reason, ?int $userId = null): OfficeAllocationBatch
    {
        $reason = trim($reason);
        if ($reason === '') {
            throw new InvalidArgumentException('Informe o motivo do cancelamento.');
        }

        $batch->update([
            'status' => 'cancelled',
            'cancelled_by' => $userId,
            'cancelled_at' => now(),
            'cancellation_reason' => $reason,
        ]);

        return $batch->refresh();
    }

    public function latestOrGenerateCandidate(?string $competence = null): ?OfficeAllocationBatch
    {
        $competence ??= now()->format('Y-m');

        return OfficeAllocationBatch::query()
            ->where('competence', $competence)
            ->orderByRaw("CASE WHEN status = 'closed' THEN 0 WHEN status = 'cancelled' THEN 2 ELSE 1 END")
            ->orderByDesc('version')
            ->first();
    }

    public function destinationSummary(OfficeAllocationBatch $batch)
    {
        return OfficeAllocationRow::query()
            ->where('batch_id', $batch->id)
            ->selectRaw('
                COALESCE(organization_unit_id, 0) as unit_key,
                COALESCE(organization_department_id, 0) as department_key,
                COALESCE(organization_cost_center_id, 0) as cost_center_key,
                MAX(unit_name_snapshot) as unit_name,
                MAX(department_name_snapshot) as department_name,
                MAX(cost_center_code_snapshot) as cost_center_code,
                MAX(cost_center_name_snapshot) as cost_center_name,
                COUNT(DISTINCT user_email) as users_count,
                SUM(CASE WHEN JSON_UNQUOTE(JSON_EXTRACT(metadata, "$.sku_group")) = "apps" THEN quantity ELSE 0 END) as apps_count,
                SUM(CASE WHEN JSON_UNQUOTE(JSON_EXTRACT(metadata, "$.sku_group")) = "business" THEN quantity ELSE 0 END) as business_count,
                SUM(CASE WHEN JSON_UNQUOTE(JSON_EXTRACT(metadata, "$.sku_group")) = "powerbi" THEN quantity ELSE 0 END) as powerbi_count,
                SUM(CASE WHEN JSON_UNQUOTE(JSON_EXTRACT(metadata, "$.sku_group")) = "visio" THEN quantity ELSE 0 END) as visio_count,
                SUM(CASE WHEN JSON_UNQUOTE(JSON_EXTRACT(metadata, "$.sku_group")) NOT IN ("apps", "business", "powerbi", "visio") THEN quantity ELSE 0 END) as other_count,
                SUM(total_amount) as total_amount
            ')
            ->where('classification_status', 'classified')
            // Um centro manual ainda pode não possuir ID no catálogo local.
            // Nesse caso, o código informado é a chave para não misturar
            // destinos diferentes no resumo do rateio.
            ->groupBy('unit_key', 'department_key', 'cost_center_key', 'cost_center_code_snapshot')
            ->orderBy('unit_name')
            ->orderBy('department_name')
            ->orderBy('cost_center_code')
            ->get();
    }

    public function skuSummary(OfficeAllocationBatch $batch)
    {
        return OfficeAllocationRow::query()
            ->where('batch_id', $batch->id)
            ->selectRaw('sku_id, sku_name, SUM(quantity) as quantity, MAX(unit_price) as unit_price, SUM(total_amount) as total_amount')
            ->groupBy('sku_id', 'sku_name')
            ->orderBy('sku_name')
            ->get();
    }

    public function unitSummary(OfficeAllocationBatch $batch)
    {
        return OfficeAllocationRow::query()
            ->where('batch_id', $batch->id)
            ->where('classification_status', 'classified')
            ->selectRaw('
                COALESCE(organization_unit_id, 0) as unit_key,
                MAX(unit_name_snapshot) as unit_name,
                COUNT(DISTINCT user_email) as users_count,
                SUM(CASE WHEN JSON_UNQUOTE(JSON_EXTRACT(metadata, "$.sku_group")) = "apps" THEN quantity ELSE 0 END) as apps_count,
                SUM(CASE WHEN JSON_UNQUOTE(JSON_EXTRACT(metadata, "$.sku_group")) = "business" THEN quantity ELSE 0 END) as business_count,
                SUM(CASE WHEN JSON_UNQUOTE(JSON_EXTRACT(metadata, "$.sku_group")) = "powerbi" THEN quantity ELSE 0 END) as powerbi_count,
                SUM(CASE WHEN JSON_UNQUOTE(JSON_EXTRACT(metadata, "$.sku_group")) = "visio" THEN quantity ELSE 0 END) as visio_count,
                SUM(CASE WHEN JSON_UNQUOTE(JSON_EXTRACT(metadata, "$.sku_group")) NOT IN ("apps", "business", "powerbi", "visio") THEN quantity ELSE 0 END) as other_count,
                SUM(total_amount) as total_amount
            ')
            ->groupBy('unit_key')
            ->orderBy('unit_name')
            ->get();
    }

    public function departmentSummary(OfficeAllocationBatch $batch)
    {
        $rows = OfficeAllocationRow::query()
            ->where('batch_id', $batch->id)
            ->where('classification_status', 'classified')
            ->with('officeLicense.organizationDepartment', 'officeLicense.organizationCostCenter')
            ->get();

        $summary = [];

        foreach ($rows as $row) {
            $departmentName = $this->departmentNameForAllocationRow($row);

            // The department view is for headquarters. Rows without a stored
            // department can still be classified safely by a headquarters
            // cost center, which is the source used by the legacy rateio.
            if ($departmentName === null || ! $this->isHeadquartersAllocationRow($row)) {
                continue;
            }

            $costCenter = trim((string) ($row->cost_center_code_snapshot ?: $row->officeLicense?->centro_custo));
            $key = mb_strtolower($departmentName) . '|' . mb_strtolower($costCenter ?: '-');
            $group = $summary[$key] ?? [
                'department_key' => $row->organization_department_id ?: $key,
                'department_name' => $departmentName,
                'cost_center' => $costCenter,
                'users' => [],
                'apps_count' => 0,
                'business_count' => 0,
                'powerbi_count' => 0,
                'visio_count' => 0,
                'other_count' => 0,
                'total_amount' => 0.0,
            ];

            $group['users'][mb_strtolower(trim((string) $row->user_email))] = true;
            $skuGroup = (string) data_get($row->metadata, 'sku_group', 'other');
            $counter = match ($skuGroup) {
                'apps' => 'apps_count',
                'business' => 'business_count',
                'powerbi' => 'powerbi_count',
                'visio' => 'visio_count',
                default => 'other_count',
            };
            $group[$counter] += (int) $row->quantity;
            $group['total_amount'] += (float) $row->total_amount;
            $summary[$key] = $group;
        }

        return collect($summary)
            ->map(function (array $row) {
                $row['users_count'] = count($row['users']);
                unset($row['users']);

                return (object) $row;
            })
            ->sortBy([
                ['department_name', 'asc'],
                ['cost_center', 'asc'],
            ])
            ->values();
    }

    public function departmentNameForAllocationRow(OfficeAllocationRow $row): ?string
    {
        $license = $row->officeLicense;
        $name = trim((string) ($row->department_name_snapshot
            ?: $license?->organizationDepartment?->name
            ?: $license?->departamento_nome));

        if ($name === '') {
            $costCenterName = trim((string) $license?->organizationCostCenter?->name);

            // Only apply a catalog alias when it is unambiguous. A generic
            // cost-center description must not be presented as a department.
            if (preg_match('/processamento\s+de\s+dados|inform[aá]tica|tecnologia\s+da\s+inform[aá]cao/ui', $costCenterName)) {
                return 'Tecnologia Da Informacao';
            }

            return null;
        }

        if (preg_match('/seguran[cç]a\s+do\s+trabalho/ui', $name)) {
            return 'Segurança Do Trabalho';
        }

        // The intranet catalog calls this cost center "Departamento de
        // Processamento de Dados" while the organizational department is
        // presented to users as Tecnologia da Informação.
        if (preg_match('/processamento\s+de\s+dados|inform[aá]tica|tecnologia\s+da\s+inform[aá]cao/ui', $name)) {
            return 'Tecnologia Da Informacao';
        }

        return preg_replace('/\s+/', ' ', $name) ?: null;
    }

    private function isHeadquartersAllocationRow(OfficeAllocationRow $row): bool
    {
        return trim((string) $row->department_name_snapshot) !== ''
            || str_starts_with(trim((string) $row->cost_center_code_snapshot), '01.');
    }

    public function pendingRowsQuery(OfficeAllocationBatch $batch)
    {
        return OfficeAllocationRow::query()
            ->where('batch_id', $batch->id)
            ->where('classification_status', '<>', 'classified');
    }

    public static function statusLabels(): array
    {
        return [
            'draft' => 'Rascunho',
            'processing' => 'Processando',
            'pending_review' => 'Pendente de revisão',
            'validated' => 'Validado',
            'closed' => 'Fechado',
            'cancelled' => 'Cancelado',
            'failed' => 'Falhou',
        ];
    }

    public static function classificationStatusLabels(): array
    {
        return [
            'classified' => 'Classificado',
            'missing_collaborator' => 'Colaborador não encontrado',
            'ambiguous_match' => 'Correspondência ambígua',
            'missing_unit' => 'Unidade não encontrada',
            'missing_headquarters_department' => 'Departamento da Sede não encontrado',
            'missing_cost_center' => 'Centro de custo não encontrado',
            'missing_price' => 'Preço não cadastrado',
            'unknown_sku' => 'Produto não reconhecido',
        ];
    }

    private function refreshBatchTotals(OfficeAllocationBatch $batch, string $competence): OfficeAllocationBatch
    {
        $rows = $batch->rows()->get();
        $classified = $rows->where('classification_status', 'classified');
        $pending = $rows->where('classification_status', '!=', 'classified');
        $summary = [
            'by_sku' => $rows
                ->groupBy('sku_id')
                ->map(fn ($group): array => [
                    'sku_name' => (string) $group->first()->sku_name,
                    'quantity' => (int) $group->sum('quantity'),
                    'total_amount' => round((float) $group->sum('total_amount'), 2),
                ])
                ->all(),
            'pending_by_status' => $pending
                ->groupBy('classification_status')
                ->map(fn ($group): int => $group->count())
                ->all(),
        ];

        $total = round((float) $classified->sum('total_amount'), 2);
        $batch->update([
            'status' => $pending->isNotEmpty() ? 'pending_review' : 'draft',
            'users_count' => $rows->pluck('user_email')->unique()->count(),
            'assignments_count' => $rows->count(),
            'classified_assignments_count' => $classified->count(),
            'pending_assignments_count' => $pending->count(),
            'total_amount' => $total,
            'annual_estimated_amount' => round($total * 12, 2),
            'price_snapshot' => $this->priceService->priceSnapshot($competence),
            'summary' => $summary,
        ]);

        return $batch->refresh();
    }

    private function licensedUsersQuery()
    {
        return OfficeLicense::query()
            ->where('ativo', true)
            ->where(function ($query): void {
                $query->whereHas('assignments', fn ($assignments) => $assignments->where('included_in_report', true));
                foreach (OfficeLicenseSkuCatalog::items() as $sku) {
                    $query->orWhere($sku['column'], true);
                }
            })
            ->orderBy('nome');
    }

    private function skuFromAssignment(OfficeLicenseAssignment $assignment): array
    {
        $category = (string) ($assignment->license_category ?? '');
        $group = str_contains($category, 'powerbi') ? 'powerbi' :
            (str_contains($category, 'office_apps') ? 'apps' :
            (str_contains($category, 'office_business') ? 'business' :
            (str_contains($category, 'visio') ? 'visio' : 'other')));

        return [
            'column' => '',
            'sku_id' => (string) $assignment->sku_id,
            'name' => (string) ($assignment->sku_name ?: $assignment->sku_part_number ?: $assignment->sku_id),
            'group' => $group,
        ];
    }

    private function legacyPriceForAssignment(OfficeLicenseAssignment $assignment, array $prices): mixed
    {
        $legacySkuId = match ((string) ($assignment->license_category ?? '')) {
            'office_apps' => 'OFFICE_APPS',
            'office_business' => 'OFFICE_BUSINESS',
            'powerbi_pro' => 'POWER_BI_PRO',
            'powerbi_premium' => 'POWER_BI_PREMIUM',
            'visio_plan' => 'VISIO_PLAN',
            default => null,
        };

        return $legacySkuId !== null ? ($prices[$legacySkuId] ?? null) : null;
    }

    private function isBillableAssignment(OfficeLicenseAssignment $assignment): bool
    {
        return in_array((string) ($assignment->license_category ?? ''), [
            'office_apps',
            'office_business',
            'powerbi_pro',
            'visio_plan',
        ], true);
    }

    private function isBillableLegacySku(string $skuId): bool
    {
        return in_array($skuId, ['OFFICE_APPS', 'OFFICE_BUSINESS', 'POWER_BI_PRO', 'VISIO_PLAN'], true);
    }

    private function criticalPendingCount(OfficeAllocationBatch $batch): int
    {
        return $batch->rows()
            ->whereIn('classification_status', self::CRITICAL_PENDING_STATUSES)
            ->count();
    }

    private function assertMutable(OfficeAllocationBatch $batch): void
    {
        if ($batch->status === 'closed') {
            throw new RuntimeException('Rateio fechado é imutável.');
        }
        if ($batch->status === 'cancelled') {
            throw new RuntimeException('Rateio cancelado não pode ser alterado.');
        }
    }

    private function assertCompetence(string $competence): void
    {
        if (! preg_match('/^\d{4}-\d{2}$/', $competence)) {
            throw new InvalidArgumentException('Competência inválida. Use o formato AAAA-MM.');
        }
    }
}
