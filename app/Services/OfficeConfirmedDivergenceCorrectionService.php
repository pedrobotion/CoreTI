<?php

namespace App\Services;

use App\Models\HrCollaborator;
use App\Models\OfficeLicense;
use App\Models\OrganizationCostCenter;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class OfficeConfirmedDivergenceCorrectionService
{
    private const EXCLUDED_NAMES = [
        'lorival sertorio',
        'lucas oliveira da silva',
    ];

    /**
     * Build a reviewable correction plan from the audited CSV.
     * The CSV is evidence only; values are validated against local snapshots.
     *
     * @return array<int, array<string, mixed>>
     */
    public function buildPlan(string $csvPath = 'comparacao_divergencias_80.csv'): array
    {
        $handle = fopen($csvPath, 'rb');
        if ($handle === false) {
            throw new \RuntimeException("Arquivo de divergências não encontrado: {$csvPath}");
        }

        $headers = fgetcsv($handle, 0, ';');
        if ($headers === false) {
            fclose($handle);
            throw new \RuntimeException('Arquivo de divergências vazio.');
        }
        $headers[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $headers[0]);

        $plan = [];
        while (($values = fgetcsv($handle, 0, ';')) !== false) {
            if (count($values) < count($headers)) {
                continue;
            }

            $row = array_combine($headers, $values);
            if (($row['Tem_Divergencia'] ?? '') !== 'SIM') {
                continue;
            }

            $name = trim((string) ($row['Nome_lic'] ?? ''));
            $email = mb_strtolower(trim((string) ($row['Email_lic'] ?? '')));
            $method = trim((string) ($row['Metodo_match'] ?? ''));
            $base = [
                'name' => $name,
                'email' => $email,
                'method' => $method,
                'old_matricula' => $this->nullable($row['Matricula_lic'] ?? null),
                'old_unicoop' => $this->nullable($row['Unicoop_lic'] ?? null),
                'old_area' => $this->nullable($row['Area_lic'] ?? null),
                'old_cost_center' => $this->nullable($row['CentroCusto_lic'] ?? null),
                'new_matricula' => $this->nullable($row['Matricula_cad'] ?? null),
                'source_cost_center' => $this->normalizeSourceId($row['CentroCusto_cad'] ?? null),
                'source' => 'comparacao_divergencias_80.csv + snapshots locais',
            ];

            if (in_array(mb_strtolower($name), self::EXCLUDED_NAMES, true)) {
                $plan[] = $base + ['status' => 'excluded', 'reason' => 'Correspondência explicitamente não confirmada.'];
                continue;
            }

            if ($method === 'nome_fuzzy_80') {
                $plan[] = $base + ['status' => 'excluded', 'reason' => 'Match fuzzy não confirmado para atualização automática.'];
                continue;
            }

            $license = $email !== '' ? OfficeLicense::query()->whereRaw('LOWER(email) = ?', [$email])->first() : null;
            if ($license === null) {
                $plan[] = $base + ['status' => 'unresolved', 'reason' => 'Licença não encontrada por e-mail exato.'];
                continue;
            }

            $costCenter = $this->findCostCenter($base['source_cost_center']);
            if ($costCenter === null || $costCenter->unit_source_id === null || $costCenter->accounting_area_source_id === null) {
                $plan[] = $base + ['status' => 'unresolved', 'reason' => 'Centro de custo não encontrado com unidade e área no snapshot local.'];
                continue;
            }

            $employee = $this->findEmployee($base['new_matricula']);
            if ($base['new_matricula'] !== null && $employee === null) {
                $plan[] = $base + ['status' => 'unresolved', 'reason' => 'Matrícula nova não encontrada no snapshot hr_collaborators.'];
                continue;
            }

            $unit = str_pad((string) ((int) $costCenter->unit_source_id), 2, '0', STR_PAD_LEFT);
            $area = str_pad((string) ((int) $costCenter->accounting_area_source_id), 3, '0', STR_PAD_LEFT);
            $newCostCenter = $unit . '.' . $area;
            $changes = [];

            if ($base['new_matricula'] !== null && trim((string) $license->matricula) !== $base['new_matricula']) {
                $changes['matricula'] = [$license->matricula, $base['new_matricula']];
            }
            if ((string) $license->unicoop_office !== $unit) {
                $changes['unicoop_office'] = [$license->unicoop_office, $unit];
            }
            if ((string) $license->area_office !== $area) {
                $changes['area_office'] = [$license->area_office, $area];
            }
            if ((string) $license->centro_custo !== $newCostCenter) {
                $changes['centro_custo'] = [$license->centro_custo, $newCostCenter];
            }
            if ((int) $license->organization_cost_center_id !== (int) $costCenter->id) {
                $changes['organization_cost_center_id'] = [$license->organization_cost_center_id, $costCenter->id];
            }

            $plan[] = $base + [
                'status' => $changes === [] ? 'unchanged' : 'apply',
                'reason' => $changes === [] ? 'Registro já está alinhado.' : 'Divergência confirmada pela auditoria.',
                'license_id' => $license->id,
                'employee_source_id' => $employee?->source_id,
                'new_unicoop' => $unit,
                'new_area' => $area,
                'new_cost_center' => $newCostCenter,
                'new_cost_center_name' => $costCenter->name,
                'changes' => $changes,
            ];
        }

        fclose($handle);

        return $plan;
    }

    /** @param array<int, array<string, mixed>> $plan */
    public function writePreview(array $plan, string $prefix = 'office_correcoes_preview'): string
    {
        return $this->writeReport($plan, $prefix . '_' . now()->format('Ymd_His') . '.csv');
    }

    /** @param array<int, array<string, mixed>> $plan */
    public function apply(array $plan): array
    {
        $apply = array_values(array_filter($plan, fn (array $row): bool => ($row['status'] ?? '') === 'apply'));
        $backup = [];
        $updated = 0;

        DB::transaction(function () use ($apply, &$backup, &$updated): void {
            foreach ($apply as $row) {
                $license = OfficeLicense::query()->lockForUpdate()->find((int) $row['license_id']);
                if ($license === null) {
                    throw new \RuntimeException('Licença desapareceu durante a aplicação: ' . $row['license_id']);
                }

                $backup[] = [
                    'license_id' => $license->id,
                    'email' => $license->email,
                    'nome' => $license->nome,
                    'matricula' => $license->matricula,
                    'unicoop_office' => $license->unicoop_office,
                    'area_office' => $license->area_office,
                    'centro_custo' => $license->centro_custo,
                    'centro_custo_nome' => $license->centro_custo_nome,
                    'organization_cost_center_id' => $license->organization_cost_center_id,
                ];

                $license->update([
                    'matricula' => $row['new_matricula'],
                    'unicoop_office' => $row['new_unicoop'],
                    'area_office' => $row['new_area'],
                    'centro_custo' => $row['new_cost_center'],
                    'centro_custo_nome' => $row['new_cost_center_name'],
                    'organization_cost_center_id' => $row['changes']['organization_cost_center_id'][1] ?? $license->organization_cost_center_id,
                ]);
                $updated++;
            }
        });

        $backupPath = $this->writeReport($backup, 'office_correcoes_backup_' . now()->format('Ymd_His') . '.csv');

        return ['updated' => $updated, 'backup' => $backupPath];
    }

    private function findCostCenter(?string $sourceId): ?OrganizationCostCenter
    {
        if ($sourceId === null) {
            return null;
        }

        return OrganizationCostCenter::query()
            ->where('source_system', 'intranet')
            ->where('source_id', $sourceId)
            ->orderByRaw("CASE WHEN source_type = 'sic' THEN 0 ELSE 1 END")
            ->orderBy('id')
            ->first();
    }

    private function findEmployee(?string $employeeNumber): ?HrCollaborator
    {
        if ($employeeNumber === null) {
            return null;
        }

        $rows = HrCollaborator::query()
            ->where(function ($query) use ($employeeNumber): void {
                $query->where('employee_number', $employeeNumber)->orWhere('source_id', $employeeNumber);
            })
            ->get();

        if ($rows->count() === 1) {
            return $rows->first();
        }

        $active = $rows->where('active', true)->where('terminated', false);

        return $active->count() === 1 ? $active->first() : null;
    }

    private function normalizeSourceId(mixed $value): ?string
    {
        $digits = preg_replace('/\D+/', '', trim((string) $value)) ?: '';

        return $digits !== '' ? (string) ((int) $digits) : null;
    }

    private function nullable(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value !== '' && $value !== '-' ? $value : null;
    }

    /** @param array<int, array<string, mixed>> $rows */
    private function writeReport(array $rows, string $filename): string
    {
        $disk = Storage::disk('local');
        $disk->makeDirectory('reports');
        $path = 'reports/' . $filename;
        $handle = fopen($disk->path($path), 'wb');
        fputcsv($handle, ['status', 'license_id', 'nome', 'email', 'matricula_anterior', 'matricula_nova', 'unicoop_anterior', 'unicoop_nova', 'area_anterior', 'area_nova', 'centro_custo_anterior', 'centro_custo_novo', 'motivo', 'alteracoes'], ';');
        foreach ($rows as $row) {
            fputcsv($handle, [
                $row['status'] ?? 'backup', $row['license_id'] ?? '', $row['name'] ?? ($row['nome'] ?? ''), $row['email'] ?? '',
                $row['old_matricula'] ?? ($row['matricula'] ?? ''), $row['new_matricula'] ?? '', $row['old_unicoop'] ?? ($row['unicoop_office'] ?? ''),
                $row['new_unicoop'] ?? '', $row['old_area'] ?? ($row['area_office'] ?? ''), $row['new_area'] ?? '',
                $row['old_cost_center'] ?? ($row['centro_custo'] ?? ''), $row['new_cost_center'] ?? '', $row['reason'] ?? '', json_encode($row['changes'] ?? [], JSON_UNESCAPED_UNICODE),
            ], ';');
        }
        fclose($handle);

        return $disk->path($path);
    }
}
