<?php

namespace App\Services\IntranetOrganization;

use App\Models\HrCollaborator;
use App\Models\OrganizationCostCenter;
use App\Models\OrganizationPosition;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class IntranetHrSnapshotSyncService
{
    public function __construct(
        private readonly IntranetHrCollaboratorSourceRepository $collaborators,
        private readonly IntranetCostCenterSourceRepository $costCenters,
        private readonly IntranetPositionSourceRepository $positions,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function sync(bool $syncCollaborators = true, bool $syncCostCenters = true, bool $dryRun = false): array
    {
        $startedAt = now();
        $summary = [
            'dry_run' => $dryRun,
            'collaborators_found' => 0,
            'collaborators_created' => 0,
            'collaborators_updated' => 0,
            'collaborators_unchanged' => 0,
            'cost_centers_found' => 0,
            'cost_centers_created' => 0,
            'cost_centers_updated' => 0,
            'cost_centers_unchanged' => 0,
            'positions_found' => 0,
            'positions_created' => 0,
            'positions_updated' => 0,
            'positions_unchanged' => 0,
            'duration_seconds' => 0,
        ];

        if ($syncCostCenters) {
            $summary = array_merge($summary, $this->syncCostCenters($dryRun));
        }

        if ($syncCollaborators) {
            $summary = array_merge($summary, $this->syncPositions($dryRun));
            $summary = array_merge($summary, $this->syncCollaborators($dryRun));
        }

        $summary['duration_seconds'] = $startedAt->diffInSeconds(now());

        return $summary;
    }

    /** @return array<string, int> */
    private function syncPositions(bool $dryRun): array
    {
        $now = now();
        $rows = $this->positions->all()->map(function (object $row) use ($now): array {
            $raw = (array) $row;
            return [
                'source_system' => 'intranet',
                'source_id' => (string) ($row->IdCargo ?? ''),
                'name' => $this->nullableString($row->Nome ?? null),
                'rh_code' => $this->nullableString($row->CodigoRH ?? null),
                'rh_level_code' => $this->nullableString($row->CodigoNivelRH ?? null),
                'category_source_id' => $this->nullableString($row->IdCategoriaCargo ?? null),
                'active' => (bool) ($row->Ativo ?? true),
                'protheus_position_code' => $this->nullableString($row->IdCargoRH ?? null),
                'system' => $this->nullableString($row->Sistema ?? null),
                'cbo' => $this->nullableString($row->CBO ?? null),
                'details' => $this->nullableString($row->Detalhes ?? null),
                'source_updated_at' => $this->parseDateTime($row->DataIntegracao ?? null),
                'last_synced_at' => $now,
                'raw_data' => $raw,
                'source_hash' => hash('sha256', json_encode($raw, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: ''),
            ];
        })->filter(fn (array $row): bool => $row['source_id'] !== '')->values();

        $summary = ['positions_found' => $rows->count(), 'positions_created' => 0, 'positions_updated' => 0, 'positions_unchanged' => 0];
        foreach ($rows as $attributes) {
            $existing = OrganizationPosition::query()->where('source_system', 'intranet')->where('source_id', $attributes['source_id'])->first();
            if (! $existing) {
                $summary['positions_created']++;
                if (! $dryRun) OrganizationPosition::create($attributes);
            } elseif ((string) $existing->source_hash === (string) $attributes['source_hash']) {
                $summary['positions_unchanged']++;
                if (! $dryRun) $existing->update(['last_synced_at' => $now]);
            } else {
                $summary['positions_updated']++;
                if (! $dryRun) $existing->update($attributes);
            }
        }
        return $summary;
    }

    /**
     * @return array<string, int>
     */
    private function syncCostCenters(bool $dryRun): array
    {
        $now = now();
        $rows = $this->costCenters->accounting()
            ->map(fn (object $row): array => $this->costCenterAttributes($row, 'sic', $now))
            ->merge($this->costCenters->rh()->map(fn (object $row): array => $this->costCenterAttributes($row, 'rh', $now)));

        $summary = [
            'cost_centers_found' => $rows->count(),
            'cost_centers_created' => 0,
            'cost_centers_updated' => 0,
            'cost_centers_unchanged' => 0,
        ];

        foreach ($rows as $attributes) {
            $existing = OrganizationCostCenter::query()
                ->where('source_system', $attributes['source_system'])
                ->where('source_type', $attributes['source_type'])
                ->where('source_id', $attributes['source_id'])
                ->first();

            if (! $existing) {
                $summary['cost_centers_created']++;
                if (! $dryRun) {
                    OrganizationCostCenter::create($attributes);
                }
                continue;
            }

            if ((string) $existing->source_hash === (string) $attributes['source_hash']) {
                $summary['cost_centers_unchanged']++;
                if (! $dryRun) {
                    $existing->update(['last_synced_at' => $now]);
                }
                continue;
            }

            $summary['cost_centers_updated']++;
            if (! $dryRun) {
                $existing->update($attributes);
            }
        }

        return $summary;
    }

    /**
     * @return array<string, int>
     */
    private function syncCollaborators(bool $dryRun): array
    {
        $now = now();
        $rows = $this->collaborators->all()
            ->map(fn (object $row): array => $this->collaboratorAttributes($row, $now));

        $summary = [
            'collaborators_found' => $rows->count(),
            'collaborators_created' => 0,
            'collaborators_updated' => 0,
            'collaborators_unchanged' => 0,
        ];

        foreach ($rows as $attributes) {
            $existing = HrCollaborator::query()
                ->where('source_system', $attributes['source_system'])
                ->where('source_id', $attributes['source_id'])
                ->first();

            if (! $existing) {
                $summary['collaborators_created']++;
                if (! $dryRun) {
                    HrCollaborator::create($attributes);
                }
                continue;
            }

            if ((string) $existing->source_hash === (string) $attributes['source_hash']) {
                $summary['collaborators_unchanged']++;
                if (! $dryRun) {
                    $existing->update(['last_synced_at' => $now]);
                }
                continue;
            }

            $summary['collaborators_updated']++;
            if (! $dryRun) {
                $existing->update($attributes);
            }
        }

        return $summary;
    }

    /**
     * @return array<string, mixed>
     */
    private function costCenterAttributes(object $row, string $sourceType, Carbon $now): array
    {
        $raw = (array) $row;
        $sourceId = $sourceType === 'sic'
            ? $this->nullableString($row->IdCCusto ?? null)
            : $this->nullableString($row->IdCentroCusto ?? null);

        $attributes = [
            'source_system' => 'intranet',
            'source_type' => $sourceType,
            'source_id' => (string) $sourceId,
            'name' => $this->nullableString($row->Nome ?? null),
            'unit_source_id' => $this->nullableString($row->IdUnicoop ?? null),
            'accounting_area_source_id' => $sourceType === 'sic'
                ? $this->nullableString($row->IdAreaContabil ?? null)
                : null,
            'active' => $sourceType === 'sic' ? (bool) ($row->Ativo ?? false) : true,
            'source_updated_at' => null,
            'last_synced_at' => $now,
            'raw_data' => $raw,
        ];
        $attributes['source_hash'] = $this->sourceHash($raw);

        return $attributes;
    }

    /**
     * @return array<string, mixed>
     */
    private function collaboratorAttributes(object $row, Carbon $now): array
    {
        $raw = (array) $row;
        $terminated = (bool) ($row->Desligado ?? false);
        $terminationDate = $this->parseDate($row->DataDesligamento ?? null);

        $attributes = [
            'source_system' => 'intranet',
            'source_id' => (string) $this->nullableString($row->IdPessoa ?? null),
            'employee_number' => $this->nullableString($row->Matricula ?? null),
            'name' => trim((string) ($row->Nome ?? '')),
            'email' => $this->normalizeEmail($row->Email ?? null),
            'cost_center_source_id' => $this->nullableString($row->CentroCusto ?? null),
            'unit_source_id' => $this->nullableString($row->IdUnicoop ?? null),
            'area_source_id' => $this->nullableString($row->IdArea ?? null),
            'department_source_id' => $this->nullableString($row->IdDepartamento ?? null),
            'sector_source_id' => $this->nullableString($row->IdSetor ?? null),
            'position_source_id' => $this->nullableString($row->IdCargo ?? null),
            'company_source_id' => $this->nullableString($row->IdEmpresaCocari ?? null),
            'manager_source_id' => $this->nullableString($row->IdPessoaSuperior ?? null),
            'active' => (bool) ($row->Ativo ?? false),
            'terminated' => $terminated,
            'termination_date' => $terminationDate,
            'excluded_from_protheus' => (bool) ($row->ExcluidoProtheus ?? false),
            'source_updated_at' => $this->parseDateTime($row->DataUltimaAtualizacao ?? null),
            'last_synced_at' => $now,
            'raw_data' => $raw,
        ];
        $attributes['source_hash'] = $this->sourceHash($raw);

        return $attributes;
    }

    private function nullableString(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function normalizeEmail(mixed $value): ?string
    {
        $value = mb_strtolower(trim((string) $value));

        return filter_var($value, FILTER_VALIDATE_EMAIL) ? $value : null;
    }

    private function parseDate(mixed $value): ?string
    {
        $value = trim((string) $value);
        if ($value === '' || $value === '0000-00-00') {
            return null;
        }

        return Carbon::parse($value)->toDateString();
    }

    private function parseDateTime(mixed $value): ?Carbon
    {
        $value = trim((string) $value);
        if ($value === '' || $value === '0000-00-00 00:00:00') {
            return null;
        }

        return Carbon::parse($value);
    }

    private function sourceHash(array $raw): string
    {
        ksort($raw);

        return hash('sha256', json_encode($raw, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: Str::random(16));
    }
}
