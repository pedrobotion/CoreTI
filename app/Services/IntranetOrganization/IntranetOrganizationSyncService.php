<?php

namespace App\Services\IntranetOrganization;

use App\Models\OrganizationDepartment;
use App\Models\OrganizationSyncBatch;
use App\Models\OrganizationUnit;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Throwable;

class IntranetOrganizationSyncService
{
    public function __construct(
        private readonly IntranetUnitSourceRepository $units,
        private readonly IntranetDepartmentSourceRepository $departments,
    ) {
    }

    /**
     * @return array<string,mixed>
     */
    public function sync(bool $syncUnits = true, bool $syncDepartments = true, bool $dryRun = false, bool $force = false): array
    {
        $startedAt = now();
        $batch = null;

        if (! $dryRun) {
            $batch = OrganizationSyncBatch::create([
                'source_system' => 'intranet',
                'sync_type' => $syncUnits && $syncDepartments ? 'full' : ($syncUnits ? 'units' : 'departments'),
                'status' => 'running',
                'started_at' => $startedAt,
                'details' => [
                    'department_source' => $this->departments->metadata(),
                ],
            ]);
        }

        $summary = $this->emptySummary($dryRun);

        try {
            if ($syncUnits) {
                $summary = array_merge($summary, $this->syncUnits($dryRun, $force, $batch));
            }

            if ($syncDepartments) {
                $summary = array_merge($summary, $this->syncDepartments($dryRun, $force, $batch));
            }

            $status = $dryRun ? 'dry_run' : (($summary['warnings'] ?? []) !== [] ? 'warning' : 'success');

            if ($batch) {
                $batch->update(array_merge(
                    $this->batchCounters($summary),
                    [
                        'status' => $status,
                        'finished_at' => now(),
                        'details' => array_merge($batch->details ?? [], [
                            'warnings' => $summary['warnings'] ?? [],
                            'department_source' => $this->departments->metadata(),
                        ]),
                    ],
                ));
            }

            if (! $dryRun) {
                Cache::forget('network-datacenter.locations');
            }

            $summary['status'] = $status;
            $summary['batch_id'] = $batch?->id;
            $summary['duration_seconds'] = $startedAt->diffInSeconds(now());

            return $summary;
        } catch (Throwable $e) {
            if ($batch) {
                $batch->update([
                    'status' => 'failed',
                    'finished_at' => now(),
                    'error_message' => $e->getMessage(),
                ]);
            }

            throw $e;
        }
    }

    /**
     * @return array<string,mixed>
     */
    private function syncUnits(bool $dryRun, bool $force, ?OrganizationSyncBatch $batch): array
    {
        $rows = $this->units->all();
        $now = now();
        $currentSourceIds = $rows->map(fn ($row) => (string) $row->IdUnicoop)->filter()->values();
        $summary = [
            'units_found' => $rows->count(),
            'units_created' => 0,
            'units_updated' => 0,
            'units_unchanged' => 0,
            'units_missing' => 0,
            'units_failed' => 0,
            'warnings' => [],
        ];

        if ($warning = $this->suspiciousReductionWarning(OrganizationUnit::class, $rows->count(), 'unidades', $force)) {
            $summary['warnings'][] = $warning;
        }

        foreach ($rows as $row) {
            $attributes = $this->unitAttributes($row, $now, $batch?->id);
            $existing = OrganizationUnit::query()
                ->where('source_system', 'intranet')
                ->where('source_id', $attributes['source_id'])
                ->first();

            if (! $existing) {
                $summary['units_created']++;
                if (! $dryRun) {
                    OrganizationUnit::create($attributes);
                }
                continue;
            }

            if ((string) $existing->source_hash === (string) $attributes['source_hash'] && $existing->source_missing_at === null) {
                $summary['units_unchanged']++;
                if (! $dryRun) {
                    $existing->update([
                        'last_synced_at' => $now,
                        'sync_batch_id' => $batch?->id,
                    ]);
                }
                continue;
            }

            $summary['units_updated']++;
            if (! $dryRun) {
                $existing->update($attributes + ['source_missing_at' => null]);
            }
        }

        if (($summary['warnings'] ?? []) === [] || $force) {
            $missingQuery = OrganizationUnit::query()
                ->where('source_system', 'intranet')
                ->whereNull('source_missing_at')
                ->whereNotIn('source_id', $currentSourceIds);

            $summary['units_missing'] = (clone $missingQuery)->count();
            if (! $dryRun && $summary['units_missing'] > 0) {
                $missingQuery->update([
                    'source_missing_at' => $now,
                    'last_synced_at' => $now,
                    'sync_batch_id' => $batch?->id,
                ]);
            }
        }

        return $summary;
    }

    /**
     * @return array<string,mixed>
     */
    private function syncDepartments(bool $dryRun, bool $force, ?OrganizationSyncBatch $batch): array
    {
        $rows = $this->departments->all();
        $now = now();
        $currentSourceIds = $rows->map(fn ($row) => (string) $row->IdDepartamento)->filter()->values();
        $summary = [
            'departments_found' => $rows->count(),
            'departments_created' => 0,
            'departments_updated' => 0,
            'departments_unchanged' => 0,
            'departments_missing' => 0,
            'departments_failed' => 0,
            'warnings' => [],
        ];

        if ($warning = $this->suspiciousReductionWarning(OrganizationDepartment::class, $rows->count(), 'departamentos', $force)) {
            $summary['warnings'][] = $warning;
        }

        foreach ($rows as $row) {
            $attributes = $this->departmentAttributes($row, $now, $batch?->id);
            $existing = OrganizationDepartment::query()
                ->where('source_system', 'intranet')
                ->where('source_id', $attributes['source_id'])
                ->first();

            if (! $existing) {
                $summary['departments_created']++;
                if (! $dryRun) {
                    OrganizationDepartment::create($attributes);
                }
                continue;
            }

            if ((string) $existing->source_hash === (string) $attributes['source_hash'] && $existing->source_missing_at === null) {
                $summary['departments_unchanged']++;
                if (! $dryRun) {
                    $existing->update([
                        'last_synced_at' => $now,
                        'sync_batch_id' => $batch?->id,
                    ]);
                }
                continue;
            }

            $summary['departments_updated']++;
            if (! $dryRun) {
                $existing->update($attributes + ['source_missing_at' => null]);
            }
        }

        if (($summary['warnings'] ?? []) === [] || $force) {
            $missingQuery = OrganizationDepartment::query()
                ->where('source_system', 'intranet')
                ->whereNull('source_missing_at')
                ->whereNotIn('source_id', $currentSourceIds);

            $summary['departments_missing'] = (clone $missingQuery)->count();
            if (! $dryRun && $summary['departments_missing'] > 0) {
                $missingQuery->update([
                    'source_missing_at' => $now,
                    'last_synced_at' => $now,
                    'sync_batch_id' => $batch?->id,
                ]);
            }
        }

        return $summary;
    }

    private function unitAttributes(object $row, Carbon $now, ?int $batchId): array
    {
        $raw = (array) $row;

        return [
            'source_system' => 'intranet',
            'source_id' => (string) $row->IdUnicoop,
            'code' => str_pad((string) $row->IdUnicoop, 2, '0', STR_PAD_LEFT),
            'name' => trim((string) $row->Nome),
            'type_id' => $this->nullableString($row->IdTipoUnicoop ?? null),
            'cnpj' => $this->nullableString($row->CNPJ ?? null),
            'state_registration' => $this->nullableString($row->InscricaoEstadual ?? null),
            'municipal_registration' => $this->nullableString($row->InscricaoMunicipal ?? null),
            'address' => $this->nullableString($row->Endereco ?? null),
            'address_complement' => $this->nullableString($row->Complemento ?? null),
            'municipality_id' => $this->nullableString($row->IdMunicipio ?? null),
            'zip_code' => $this->nullableString($row->CEP ?? null),
            'phone' => $this->nullableString($row->Fone ?? null),
            'secondary_phone' => $this->nullableString($row->FoneSecundario ?? null),
            'fax' => $this->nullableString($row->Fax ?? null),
            'direct_extension' => $this->nullableString($row->RamalDireto ?? null),
            'email' => $this->nullableString($row->Email ?? null),
            'nfe_email' => $this->nullableString($row->EmailNFE ?? null),
            'regional_id' => $this->nullableString($row->IdRegional ?? null),
            'active' => (bool) ($row->Ativo ?? false),
            'old_source_id' => $this->nullableString($row->IdUnicoopOld ?? null),
            'opened_at' => $this->nullableDate($row->DataAbertura ?? null),
            'closed_at' => $this->nullableDate($row->DataBaixa ?? null),
            'nire' => $this->nullableString($row->NIRE ?? null),
            'latitude' => $this->nullableDecimal($row->Latitude ?? null),
            'longitude' => $this->nullableDecimal($row->Longitude ?? null),
            'source_hash' => $this->hash($raw),
            'last_synced_at' => $now,
            'source_missing_at' => null,
            'sync_batch_id' => $batchId,
            'raw_data' => $raw,
        ];
    }

    private function departmentAttributes(object $row, Carbon $now, ?int $batchId): array
    {
        $raw = (array) $row;

        return [
            'source_system' => 'intranet',
            'source_id' => (string) $row->IdDepartamento,
            'source_unit_id' => null,
            'unit_id' => null,
            'source_area_id' => $this->nullableString($row->IdArea ?? null),
            'code' => $this->nullableString($row->IdDepartamentoOld ?? null) ?: (string) $row->IdDepartamento,
            'name' => trim((string) $row->Nome),
            'description' => null,
            'active' => (bool) ($row->Ativo ?? false),
            'source_hash' => $this->hash($raw),
            'last_synced_at' => $now,
            'source_missing_at' => null,
            'sync_batch_id' => $batchId,
            'raw_data' => $raw,
        ];
    }

    private function suspiciousReductionWarning(string $modelClass, int $sourceCount, string $label, bool $force): ?string
    {
        if ($force || $sourceCount === 0) {
            return null;
        }

        $localCount = $modelClass::query()
            ->where('source_system', 'intranet')
            ->whereNull('source_missing_at')
            ->count();

        if ($localCount <= 0) {
            return null;
        }

        $reduction = 1 - ($sourceCount / max($localCount, 1));

        return $reduction > 0.20
            ? "Redução suspeita em {$label}: origem={$sourceCount}, snapshot={$localCount}. Ausentes não foram marcados; use --force após validar."
            : null;
    }

    private function batchCounters(array $summary): array
    {
        return collect($this->emptySummary(false))
            ->keys()
            ->filter(fn ($key) => ! in_array($key, ['dry_run', 'warnings'], true))
            ->mapWithKeys(fn ($key) => [$key => (int) ($summary[$key] ?? 0)])
            ->all();
    }

    private function emptySummary(bool $dryRun): array
    {
        return [
            'dry_run' => $dryRun,
            'units_found' => 0,
            'units_created' => 0,
            'units_updated' => 0,
            'units_unchanged' => 0,
            'units_missing' => 0,
            'units_failed' => 0,
            'departments_found' => 0,
            'departments_created' => 0,
            'departments_updated' => 0,
            'departments_unchanged' => 0,
            'departments_missing' => 0,
            'departments_failed' => 0,
            'warnings' => [],
        ];
    }

    private function hash(array $payload): string
    {
        ksort($payload);

        return hash('sha256', json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    private function nullableString(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value !== '' ? $value : null;
    }

    /**
     * Coordenada da origem, em formato que o cast decimal aceite.
     *
     * is_numeric() é mais tolerante que o cast: aceita espaço em volta do
     * número, e o BigDecimal por trás de 'decimal:8' não aceita. Um espaço
     * digitado na Intranet derrubava a sincronização inteira — nenhuma unidade
     * e nenhum departamento entravam por causa de uma coordenada. A origem é
     * texto livre e não está sob nosso controle; normalizar aqui é mais barato
     * que depender de quem preenche.
     */
    private function nullableDecimal(mixed $value): ?string
    {
        $value = trim((string) $value);

        if ($value === '' || ! is_numeric($value)) {
            return null;
        }

        return $value;
    }

    private function nullableDate(mixed $value): ?string
    {
        $value = trim((string) $value);
        if ($value === '' || $value === '0000-00-00') {
            return null;
        }

        try {
            return Carbon::parse($value)->toDateString();
        } catch (Throwable) {
            return null;
        }
    }
}
