<?php

namespace App\Services;

use App\Models\CmdbAsset;
use App\Models\CmdbServer;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Throwable;

class CmdbServerSpreadsheetImportService
{
    private const VIRTUAL_MACHINE_UNIT_ID = 1;
    private const VIRTUAL_MACHINE_LOCATION = 'Rua Lord Lovat, 420, Jardim Esplanada';

    public function __construct(
        private readonly CmdbAssetHistoryService $historyService,
    ) {
    }

    public function import(string $path, ?User $user = null): array
    {
        $sheet = IOFactory::load($path)->getActiveSheet()->toArray(null, true, true, true);

        $summary = [
            'total_rows' => 0,
            'created' => 0,
            'updated' => 0,
            'errors' => 0,
            'skipped_blank' => 0,
            'issues' => [],
        ];

        foreach (array_slice($sheet, 1, null, true) as $rowNumber => $row) {
            $normalized = $this->normalizeRow($row);

            if ($this->shouldSkipRow($normalized)) {
                $summary['skipped_blank']++;

                continue;
            }

            $summary['total_rows']++;

            try {
                DB::transaction(function () use ($normalized, $user, &$summary): void {
                    $asset = $this->findExistingAsset($normalized['name'], $normalized['hostname']);
                    $isNew = $asset === null;

                    if (! $asset) {
                        $asset = new CmdbAsset();
                        $asset->asset_type = 'virtual_machine';
                    }

                    if ($asset->trashed()) {
                        $asset->restore();
                    }

                    $oldValues = $asset->exists ? $asset->toArray() : [];

                    $asset->fill([
                        'asset_type' => 'virtual_machine',
                        'name' => $normalized['hostname'] ?: $normalized['name'],
                        'hostname' => $normalized['hostname'],
                        'description' => $normalized['service_description'],
                        'status' => $normalized['asset_status'],
                        'criticality' => $normalized['criticality'],
                        'environment' => 'production',
                        'unit_id' => self::VIRTUAL_MACHINE_UNIT_ID,
                        'location' => self::VIRTUAL_MACHINE_LOCATION,
                        'rack_id' => null,
                        'notes' => $normalized['notes'],
                    ]);
                    $asset->save();

                    CmdbServer::query()->updateOrCreate(
                        ['asset_id' => $asset->id],
                        [
                            'power_state' => $normalized['power_state'],
                            'operational_status' => $normalized['operational_status'],
                            'used_space_gb' => $normalized['used_space_gb'],
                            'service_description' => $normalized['service_description'],
                            'company_name' => $normalized['company_name'],
                            'service_type' => $normalized['service_type'],
                            'backup_policy' => $normalized['backup_policy'],
                            'backup_retention' => $normalized['backup_retention'],
                            'web_publication' => $normalized['web_publication'],
                        ]
                    );

                    $this->historyService->record(
                        $asset,
                        $user,
                        $isNew ? 'created' : 'updated',
                        $isNew ? 'Máquina virtual importada pela planilha' : 'Máquina virtual atualizada pela planilha',
                        'Importação do layout Servidores_cocari.xlsx',
                        $oldValues,
                        $asset->fresh()->toArray()
                    );

                    if ($isNew) {
                        $summary['created']++;
                    } else {
                        $summary['updated']++;
                    }
                });
            } catch (Throwable $exception) {
                $summary['errors']++;
                $summary['issues'][] = [
                    'row' => $rowNumber,
                    'name' => $normalized['name'] ?: '-',
                    'message' => $exception->getMessage(),
                ];
            }
        }

        return $summary;
    }

    /**
     * Complements existing server/VM records without creating new assets.
     * This is intentionally separate from the legacy import flow, which can
     * create virtual machines when used for an initial inventory load.
     */
    public function updateExisting(string $path, ?User $user = null): array
    {
        $sheet = IOFactory::load($path)->getActiveSheet()->toArray(null, true, true, true);

        $summary = [
            'total_rows' => 0,
            'updated' => 0,
            'unchanged' => 0,
            'unmatched' => 0,
            'ambiguous' => 0,
            'skipped_blank' => 0,
            'issues' => [],
        ];

        foreach (array_slice($sheet, 1, null, true) as $rowNumber => $row) {
            $normalized = $this->normalizeRow($row);

            if ($this->shouldSkipRow($normalized)) {
                $summary['skipped_blank']++;

                continue;
            }

            $summary['total_rows']++;
            $matches = $this->findExistingAssetsByIdentity($normalized['name']);

            if ($matches->count() === 0) {
                $summary['unmatched']++;
                $summary['issues'][] = [
                    'row' => $rowNumber,
                    'name' => $normalized['name'],
                    'message' => 'Nenhum servidor ou máquina virtual correspondente encontrado no CMDB.',
                ];

                continue;
            }

            if ($matches->count() > 1) {
                $summary['ambiguous']++;
                $summary['issues'][] = [
                    'row' => $rowNumber,
                    'name' => $normalized['name'],
                    'message' => 'Mais de um ativo corresponde ao mesmo nome; linha não alterada.',
                ];

                continue;
            }

            try {
                DB::transaction(function () use ($matches, $normalized, $user, &$summary): void {
                    /** @var CmdbAsset $asset */
                    $asset = $matches->first();
                    $server = $asset->serverDetails()->firstOrCreate(['asset_id' => $asset->id]);
                    $oldAsset = $asset->toArray();
                    $oldServer = $server->toArray();

                    $assetValues = array_filter([
                        'criticality' => $normalized['criticality'],
                        'notes' => $normalized['notes'],
                    ], static fn (mixed $value): bool => $value !== null);

                    $serverValues = array_filter([
                        'power_state' => $normalized['power_state'],
                        'operational_status' => $normalized['operational_status'],
                        'used_space_gb' => $normalized['used_space_gb'],
                        'service_description' => $normalized['service_description'],
                        'company_name' => $normalized['company_name'],
                        'service_type' => $normalized['service_type'],
                        'backup_policy' => $normalized['backup_policy'],
                        'backup_retention' => $normalized['backup_retention'],
                        'web_publication' => $normalized['web_publication'],
                    ], static fn (mixed $value): bool => $value !== null);

                    $asset->fill($assetValues);
                    $server->fill($serverValues);

                    $assetChanged = $asset->isDirty();
                    $serverChanged = $server->isDirty();

                    if (! $assetChanged && ! $serverChanged) {
                        $summary['unchanged']++;

                        return;
                    }

                    $asset->save();
                    $server->save();
                    $summary['updated']++;

                    $this->historyService->record(
                        $asset,
                        $user,
                        'updated',
                        'Servidor/VM complementado pela planilha',
                        'Atualização somente de dados complementares da planilha Servidores COCARI.xlsx.',
                        [
                            'asset' => $oldAsset,
                            'server' => $oldServer,
                        ],
                        [
                            'asset' => $asset->fresh()->toArray(),
                            'server' => $server->fresh()->toArray(),
                        ]
                    );
                });
            } catch (Throwable $exception) {
                $summary['issues'][] = [
                    'row' => $rowNumber,
                    'name' => $normalized['name'],
                    'message' => $exception->getMessage(),
                ];
            }
        }

        return $summary;
    }

    private function findExistingAsset(?string $name, ?string $hostname): ?CmdbAsset
    {
        return CmdbAsset::withTrashed()
            ->whereIn('asset_type', ['server', 'virtual_machine'])
            ->where(function ($query) use ($name, $hostname): void {
                if ($hostname) {
                    $query->where('hostname', $hostname);
                }

                if ($name) {
                    $query->orWhere('name', $name);
                }
            })
            ->first();
    }

    private function findExistingAssetsByIdentity(?string $name)
    {
        $key = $this->normalizeIdentity($name);

        if ($key === '') {
            return collect();
        }

        return CmdbAsset::withTrashed()
            ->whereIn('asset_type', ['server', 'virtual_machine'])
            ->where(function ($query) use ($key): void {
                $query->whereRaw('LOWER(TRIM(name)) = ?', [$key])
                    ->orWhereRaw('LOWER(TRIM(hostname)) = ?', [$key]);
            })
            ->get()
            ->unique('id')
            ->values();
    }

    private function normalizeRow(array $row): array
    {
        $name = $this->cleanString($row['A'] ?? null);
        $powerState = $this->cleanString($row['B'] ?? null);
        $operationalStatus = $this->cleanString($row['C'] ?? null);
        $usedSpace = $this->parseDecimal($row['D'] ?? null);
        $serviceDescription = $this->cleanString($row['E'] ?? null);
        $companyName = $this->cleanString($row['F'] ?? null);
        $serviceType = $this->cleanString($row['G'] ?? null);
        $backupPolicy = $this->cleanString($row['H'] ?? null);
        $backupRetention = $this->cleanString($row['I'] ?? null);
        $notes = $this->cleanString($row['J'] ?? null);
        $criticalityRaw = $this->cleanString($row['K'] ?? null);
        $webPublicationRaw = $this->cleanString($row['L'] ?? null);

        return [
            'name' => $name,
            'hostname' => $this->inferHostname($name),
            'power_state' => $powerState,
            'operational_status' => $operationalStatus,
            'used_space_gb' => $usedSpace,
            'service_description' => $serviceDescription,
            'company_name' => $companyName,
            'service_type' => $serviceType,
            'backup_policy' => $backupPolicy,
            'backup_retention' => $backupRetention,
            'notes' => $notes,
            'criticality' => $this->mapCriticality($criticalityRaw),
            'asset_status' => $this->mapAssetStatus($operationalStatus, $serviceType, $companyName),
            'web_publication' => $this->parseBoolean($webPublicationRaw),
        ];
    }

    private function shouldSkipRow(array $row): bool
    {
        return blank($row['name']);
    }

    private function cleanString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);

        $value = preg_replace('/\s+/', ' ', $value);

        if ($value === '' || preg_match('/^-{2,}$/', $value) === 1) {
            return null;
        }

        return $value;
    }

    private function normalizeIdentity(?string $value): string
    {
        return mb_strtolower(trim((string) preg_replace('/\s+/', ' ', (string) $value)));
    }

    private function parseDecimal(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_numeric($value)) {
            return number_format((float) $value, 2, '.', '');
        }

        if (preg_match('/([\d]+(?:[.,]\d+)?)/', (string) $value, $matches) === 1) {
            return number_format((float) str_replace(',', '.', $matches[1]), 2, '.', '');
        }

        return null;
    }

    private function parseBoolean(?string $value): ?bool
    {
        if ($value === null) {
            return null;
        }

        $normalized = mb_strtolower($value);

        return match ($normalized) {
            'sim', 'yes', 'true', '1' => true,
            'nao', 'não', 'no', 'false', '0' => false,
            default => null,
        };
    }

    private function inferHostname(?string $name): ?string
    {
        if ($name === null) {
            return null;
        }

        if (preg_match('/^[a-z0-9.-]+\.[a-z]{2,}$/i', $name) === 1) {
            return mb_strtolower($name);
        }

        if (preg_match('/^[a-z0-9.-]+$/i', $name) === 1 && ! str_contains($name, ' ')) {
            return mb_strtolower($name);
        }

        return null;
    }

    private function mapCriticality(?string $value): string
    {
        return match (mb_strtolower((string) $value)) {
            'alto', 'alta', 'high' => 'high',
            'critico', 'crítico', 'critical' => 'critical',
            'baixo', 'baixa', 'low' => 'low',
            default => 'medium',
        };
    }

    private function mapAssetStatus(?string $operationalStatus, ?string $serviceType, ?string $companyName): string
    {
        $haystack = mb_strtolower(trim(($operationalStatus ?? '') . ' ' . ($serviceType ?? '') . ' ' . ($companyName ?? '')));

        if ($haystack !== '' && str_contains($haystack, 'desativad')) {
            return 'inactive';
        }

        if ($haystack !== '' && str_contains($haystack, 'pendenc')) {
            return 'maintenance';
        }

        return 'active';
    }
}
