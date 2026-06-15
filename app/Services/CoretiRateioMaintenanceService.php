<?php

namespace App\Services;

use App\Models\CoretiRateioLocal;
use App\Models\JiraProject;
use App\Models\OfficeLicense;
use App\Models\ServiceDeskEmail;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class CoretiRateioMaintenanceService
{
    public function __construct(
        private readonly CoretiRateioLocalService $rateioLocalService
    ) {
    }

    public function importCsv(string $path): array
    {
        $fullPath = $this->resolvePath($path);
        if (! is_file($fullPath)) {
            throw new \RuntimeException("Arquivo não encontrado: {$fullPath}");
        }

        $handle = fopen($fullPath, 'r');
        if (! $handle) {
            throw new \RuntimeException('Não foi possível abrir o CSV.');
        }

        $headers = null;
        $inserted = 0;
        $updated = 0;
        $ignored = 0;
        $errors = 0;

        while (($row = fgetcsv($handle, 0, ';')) !== false) {
            if ($row === [null] || $row === false) {
                continue;
            }

            if ($headers === null) {
                $headers = array_map(static fn ($value) => mb_strtolower(trim((string) $value)), $row);
                continue;
            }

            $data = [];
            foreach ($headers as $index => $header) {
                $data[$header] = $row[$index] ?? null;
            }

            $tipo = $this->rateioLocalService->normalizeTypeLocal($data['tipo_local'] ?? null);
            $nome = trim((string) ($data['nome_local'] ?? ''));
            $nomeNormalizado = trim((string) ($data['nome_normalizado'] ?? ''));
            $unicoop = $this->rateioLocalService->normalizeCenterComponent($data['unicoop'] ?? null);
            $area = $this->rateioLocalService->normalizeCenterComponent($data['area'] ?? null);
            $centroCusto = trim((string) ($data['centro_custo'] ?? ''));
            $centroCusto = $centroCusto !== '' ? $centroCusto : $this->rateioLocalService->normalizeCentroCusto($unicoop, $area);
            $centroCustoNome = trim((string) ($data['centro_custo_nome'] ?? ''));
            $ativo = filter_var($data['ativo'] ?? true, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);
            $observacao = trim((string) ($data['observacao'] ?? ''));

            if ($tipo === null || $nome === '') {
                $ignored++;
                continue;
            }

            if ($nomeNormalizado === '') {
                $nomeNormalizado = $this->rateioLocalService->normalizeLocalName($nome);
            }

            try {
                $existing = CoretiRateioLocal::query()
                    ->where('tipo_local', $tipo)
                    ->where('nome_normalizado', $nomeNormalizado)
                    ->where('centro_custo', $centroCusto)
                    ->first();

                $payload = [
                    'tipo_local' => $tipo,
                    'nome_local' => mb_substr($nome, 0, 255),
                    'nome_normalizado' => mb_substr($nomeNormalizado, 0, 255),
                    'unicoop' => $unicoop !== null ? mb_substr($unicoop, 0, 10) : null,
                    'area' => $area !== null ? mb_substr($area, 0, 20) : null,
                    'centro_custo' => $centroCusto !== '' ? mb_substr($centroCusto, 0, 50) : null,
                    'centro_custo_nome' => $centroCustoNome !== '' ? mb_substr($centroCustoNome, 0, 255) : mb_substr($nome, 0, 255),
                    'ativo' => $ativo === null ? true : (bool) $ativo,
                    'observacao' => $observacao !== '' ? $observacao : null,
                    'updated_at' => now(),
                ];

                if ($existing) {
                    $existing->update($payload);
                    $updated++;
                } else {
                    CoretiRateioLocal::query()->create($payload + ['created_at' => now()]);
                    $inserted++;
                }
            } catch (\Throwable) {
                $errors++;
            }
        }

        fclose($handle);

        return compact('inserted', 'updated', 'ignored', 'errors');
    }

    public function auditRateioFile(?string $path = null): array
    {
        $dataset = $path ? $this->readSpreadsheetRows($this->resolvePath($path)) : $this->buildLiveAuditDataset();

        $report = new Spreadsheet();
        $summarySheet = $report->getActiveSheet();
        $summarySheet->setTitle('Resumo');

        $divergencesSheet = $report->createSheet();
        $divergencesSheet->setTitle('Divergencias');

        $manualSheet = $report->createSheet();
        $manualSheet->setTitle('Cristalina II');

        $headers = [
            'module', 'nome_origem', 'tipo_origem', 'unicoop_origem', 'area_origem', 'centro_custo_origem',
            'nome_master', 'tipo_master', 'unicoop_master', 'area_master', 'centro_custo_master',
            'status', 'motivo',
        ];

        $summary = [
            'total' => 0,
            'matched' => 0,
            'pending' => 0,
            'manual' => 0,
            'divergent' => 0,
        ];

        $divergenceRows = [];
        $manualRows = [];

        foreach ($dataset as $item) {
            $summary['total']++;

            $resolved = $this->rateioLocalService->resolveCandidate([
                'nome' => $item['nome'] ?? '',
                'tipo' => $item['tipo'] ?? null,
                'unicoop' => $item['unicoop'] ?? null,
                'area' => $item['area'] ?? null,
                'centro_custo' => $item['centro_custo'] ?? null,
            ]);

            if ($resolved['manual']) {
                $summary['manual']++;
                $manualRows[] = [
                    $item['module'],
                    $item['nome'] ?? '-',
                    $item['tipo'] ?? '-',
                    $item['unicoop'] ?? '-',
                    $item['area'] ?? '-',
                    $item['centro_custo'] ?? '-',
                    '-',
                    '-',
                    '-',
                    '-',
                    '-',
                    'MANUAL',
                    $resolved['reason'],
                ];
                continue;
            }

            if ($resolved['matched']) {
                /** @var CoretiRateioLocal $local */
                $local = $resolved['local'];
                $isExact = $this->isExactMatch($item, $local);
                if ($isExact) {
                    $summary['matched']++;
                    continue;
                }

                $summary['divergent']++;
                $divergenceRows[] = [
                    $item['module'],
                    $item['nome'] ?? '-',
                    $item['tipo'] ?? '-',
                    $item['unicoop'] ?? '-',
                    $item['area'] ?? '-',
                    $item['centro_custo'] ?? '-',
                    $local->nome_local,
                    $local->tipo_local,
                    $local->unicoop,
                    $local->area,
                    $local->centro_custo,
                    'DIVERGENTE',
                    $resolved['reason'],
                ];
                continue;
            }

            $summary['pending']++;
            $divergenceRows[] = [
                $item['module'],
                $item['nome'] ?? '-',
                $item['tipo'] ?? '-',
                $item['unicoop'] ?? '-',
                $item['area'] ?? '-',
                $item['centro_custo'] ?? '-',
                '-',
                '-',
                '-',
                '-',
                '-',
                'PENDENTE',
                $resolved['reason'],
            ];
        }

        $this->writeSheet($summarySheet, 'A1', [
            ['Indicador', 'Valor'],
            ['Linhas analisadas', $summary['total']],
            ['Em conformidade com a base', $summary['matched']],
            ['Divergentes com correspondência segura', $summary['divergent']],
            ['Sem correspondência segura', $summary['pending']],
            ['Cristalina II - validação manual', $summary['manual']],
        ], '#0f172a');

        $this->writeSheet($divergencesSheet, 'A1', array_merge([$headers], $divergenceRows), '#7c3aed');
        $this->writeSheet($manualSheet, 'A1', array_merge([$headers], $manualRows), '#f59e0b');

        $fileName = 'rateio_auditoria_' . now()->format('Ymd_His') . '.xlsx';
        $outputPath = storage_path('app/reports/' . $fileName);
        File::ensureDirectoryExists(dirname($outputPath));
        $writer = new Xlsx($report);
        $writer->save($outputPath);

        return [
            'summary' => $summary,
            'report_path' => $outputPath,
            'report_file' => $fileName,
        ];
    }

    public function correctRateioData(bool $dryRun = true): array
    {
        $changes = [];
        $pending = [];
        $manual = [];

        foreach ($this->buildSourceRows() as $row) {
            $resolved = $this->rateioLocalService->resolveCandidate([
                'nome' => $row['source_name'] ?? '',
                'tipo' => $row['type_hint'] ?? null,
                'unicoop' => $row['current_unicoop'] ?? null,
                'area' => $row['current_area'] ?? null,
                'centro_custo' => $row['current_centro_custo'] ?? null,
            ]);

            if ($resolved['manual']) {
                $manual[] = array_merge($row, ['reason' => $resolved['reason']]);
                continue;
            }

            if (! $resolved['matched']) {
                $pending[] = array_merge($row, ['reason' => $resolved['reason']]);
                continue;
            }

            /** @var CoretiRateioLocal $local */
            $local = $resolved['local'];
            $new = $this->payloadForRow($row['module'], $local);

            $diff = $this->diffRow($row, $new);
            if ($diff === []) {
                continue;
            }

            $changes[] = array_merge($row, $new, [
                'new_nome_local' => $local->nome_local,
                'new_centro_custo_nome' => $local->centro_custo_nome ?? $local->nome_local,
                'reason' => $resolved['reason'],
            ]);

            if (! $dryRun) {
                DB::table($row['table'])->where('id', $row['id'])->update($new + ['updated_at' => now()]);
            }
        }

        $report = $this->writeCorrectionReport($changes, $pending, $manual, $dryRun);

        return [
            'updated' => count($changes),
            'pending' => count($pending),
            'manual' => count($manual),
            'report_path' => $report['path'],
            'report_file' => $report['file'],
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function buildSourceRows(): array
    {
        $rows = [];

        foreach (ServiceDeskEmail::query()->select(['id', 'centro_custo', 'unicoop_sede', 'area_sede'])->get() as $item) {
            $rows[] = [
                'module' => 'email',
                'id' => $item->id,
                'table' => 'service_desk_emails',
                'source_name' => (string) $item->centro_custo,
                'type_hint' => null,
                'current_unicoop' => (string) $item->unicoop_sede,
                'current_area' => (string) $item->area_sede,
                'current_centro_custo' => (string) $item->centro_custo,
            ];
        }

        foreach (OfficeLicense::query()->select(['id', 'departamento_unidade', 'unicoop_office', 'area_office'])->get() as $item) {
            $rows[] = [
                'module' => 'office',
                'id' => $item->id,
                'table' => 'office_licenses',
                'source_name' => (string) $item->departamento_unidade,
                'type_hint' => null,
                'current_unicoop' => (string) $item->unicoop_office,
                'current_area' => (string) $item->area_office,
                'current_centro_custo' => trim((string) $item->unicoop_office) !== '' && trim((string) $item->area_office) !== ''
                    ? trim((string) $item->unicoop_office) . '.' . trim((string) $item->area_office)
                    : null,
            ];
        }

        foreach (JiraProject::query()->select(['id', 'unidade_nome', 'centro_custo', 'projeto_grupo'])->get() as $item) {
            $rows[] = [
                'module' => 'jira',
                'id' => $item->id,
                'table' => 'jira_projects',
                'source_name' => (string) $item->unidade_nome,
                'type_hint' => null,
                'current_unicoop' => null,
                'current_area' => null,
                'current_centro_custo' => (string) $item->centro_custo,
            ];
        }

        return $rows;
    }

    /**
     * @param array<string, mixed> $row
     * @param array<string, mixed> $new
     * @return array<string, array{old:mixed,new:mixed}>
     */
    private function diffRow(array $row, array $new): array
    {
        $diff = [];
        foreach ($new as $field => $value) {
            $oldKey = match ($field) {
                'centro_custo' => 'current_centro_custo',
                'unicoop_sede', 'unicoop_office' => 'current_unicoop',
                'area_sede', 'area_office' => 'current_area',
                'departamento_unidade', 'unidade_nome' => 'source_name',
                default => $field,
            };

            $old = $row[$oldKey] ?? null;
            if ((string) $old !== (string) $value) {
                $diff[$field] = ['old' => $old, 'new' => $value];
            }
        }

        return $diff;
    }

    private function resolvePath(string $path): string
    {
        $path = trim($path);
        if ($path === '') {
            return '';
        }

        return str_starts_with($path, '/') ? $path : base_path($path);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function readSpreadsheetRows(string $fullPath): array
    {
        if (! is_file($fullPath)) {
            throw new \RuntimeException("Arquivo não encontrado: {$fullPath}");
        }

        $spreadsheet = IOFactory::load($fullPath);
        $sheet = $spreadsheet->getSheetByName('Rateio_Atual_Analisado')
            ?? $spreadsheet->getSheetByName('Rateio Atual Analisado')
            ?? $spreadsheet->getSheet(0);
        $data = $sheet->toArray(null, true, true, true);

        if ($data === []) {
            return [];
        }

        $headers = [];
        $rows = [];

        foreach ($data as $rowIndex => $row) {
            if ($rowIndex === 1) {
                $headers = array_map(static fn ($value) => mb_strtolower(trim((string) $value)), $row);
                continue;
            }

            $mapped = [];
            foreach ($headers as $column => $header) {
                $mapped[$header] = $row[$column] ?? null;
            }

            $nome = $mapped['unidade'] ?? $mapped['unidade atual no rateio'] ?? $mapped['unidade/descrição atual'] ?? $mapped['unidade/descricao atual'] ?? $mapped['unidade atual'] ?? $mapped['nome'] ?? null;
            $tipo = $mapped['tipo'] ?? $mapped['tipo local'] ?? $mapped['tipo_local'] ?? null;
            $unicoop = $mapped['unicoop'] ?? $mapped['unicoop atual'] ?? null;
            $area = $mapped['área'] ?? $mapped['area'] ?? $mapped['área atual'] ?? $mapped['area atual'] ?? null;
            $centro = $mapped['centro de custo'] ?? $mapped['centro atual'] ?? $mapped['centro'] ?? $mapped['centro_custo'] ?? null;

            $nome = trim((string) $nome);
            if ($nome === '' && trim((string) $centro) === '') {
                continue;
            }

            $rows[] = [
                'module' => 'rateio_file',
                'nome' => $nome !== '' ? $nome : (string) $centro,
                'tipo' => $tipo !== null ? trim((string) $tipo) : null,
                'unicoop' => $unicoop !== null ? trim((string) $unicoop) : null,
                'area' => $area !== null ? trim((string) $area) : null,
                'centro_custo' => $centro !== null ? trim((string) $centro) : null,
            ];
        }

        return $rows;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function buildLiveAuditDataset(): array
    {
        $rows = [];

        foreach (ServiceDeskEmail::query()->where('ativo', true)->get(['id', 'centro_custo', 'unicoop_sede', 'area_sede']) as $item) {
            $rows[] = [
                'module' => 'email',
                'id' => $item->id,
                'table' => 'service_desk_emails',
                'source_name' => (string) $item->centro_custo,
                'type_hint' => null,
                'current_unicoop' => (string) $item->unicoop_sede,
                'current_area' => (string) $item->area_sede,
                'current_centro_custo' => (string) $item->centro_custo,
            ];
        }

        foreach (OfficeLicense::query()->where('ativo', true)->get(['id', 'departamento_unidade', 'unicoop_office', 'area_office']) as $item) {
            $rows[] = [
                'module' => 'office',
                'id' => $item->id,
                'table' => 'office_licenses',
                'source_name' => (string) $item->departamento_unidade,
                'type_hint' => null,
                'current_unicoop' => (string) $item->unicoop_office,
                'current_area' => (string) $item->area_office,
                'current_centro_custo' => trim((string) $item->unicoop_office) !== '' && trim((string) $item->area_office) !== ''
                    ? trim((string) $item->unicoop_office) . '.' . trim((string) $item->area_office)
                    : null,
            ];
        }

        foreach (JiraProject::query()->where('excluido', false)->where('status', 'Ativo')->get(['id', 'unidade_nome', 'centro_custo', 'projeto_grupo']) as $item) {
            $rows[] = [
                'module' => 'jira',
                'id' => $item->id,
                'table' => 'jira_projects',
                'source_name' => (string) $item->unidade_nome,
                'type_hint' => null,
                'current_unicoop' => null,
                'current_area' => null,
                'current_centro_custo' => (string) $item->centro_custo,
            ];
        }

        return $rows;
    }

    private function isExactMatch(array $item, CoretiRateioLocal $local): bool
    {
        $name = $this->rateioLocalService->normalizeLocalName((string) ($item['nome'] ?? ''));
        $u = $this->rateioLocalService->normalizeCenterComponent($item['unicoop'] ?? null);
        $a = $this->rateioLocalService->normalizeCenterComponent($item['area'] ?? null);
        $c = $this->rateioLocalService->parseCentroCusto($item['centro_custo'] ?? null)['centro_custo'] ?? null;

        return $name !== ''
            && $name === ($local->nome_normalizado ?? '')
            && ($u === null || $u === $local->unicoop)
            && ($a === null || $a === $local->area)
            && ($c === null || $c === $local->centro_custo);
    }

    private function payloadForRow(string $module, CoretiRateioLocal $local): array
    {
        return match ($module) {
            'email' => [
                'centro_custo' => $local->centro_custo_nome ?? $local->nome_local,
                'unicoop_sede' => $local->unicoop,
                'area_sede' => $local->area,
            ],
            'office' => [
                'departamento_unidade' => $local->centro_custo_nome ?? $local->nome_local,
                'unicoop_office' => $local->unicoop,
                'area_office' => $local->area,
            ],
            'jira' => [
                'unidade_nome' => $local->centro_custo_nome ?? $local->nome_local,
                'centro_custo' => $local->centro_custo,
            ],
            default => [],
        };
    }

    /**
     * @param array<int, array<string, mixed>> $changes
     * @param array<int, array<string, mixed>> $pending
     * @param array<int, array<string, mixed>> $manual
     */
    private function writeCorrectionReport(array $changes, array $pending, array $manual, bool $dryRun): array
    {
        $spreadsheet = new Spreadsheet();
        $summary = $spreadsheet->getActiveSheet();
        $summary->setTitle('Resumo');

        $headers = [
            'module', 'id', 'nome_origem', 'unicoop_antigo', 'area_antiga', 'centro_antigo',
            'nome_novo', 'unicoop_novo', 'area_nova', 'centro_novo', 'motivo',
        ];

        $this->writeSheet($summary, 'A1', [
            ['Indicador', 'Valor'],
            ['Modo', $dryRun ? 'dry-run' : 'apply'],
            ['Registros corrigidos', count($changes)],
            ['Registros pendentes', count($pending)],
            ['Cristalina II - validação manual', count($manual)],
        ], '#0f172a');

        $changesSheet = $spreadsheet->createSheet();
        $changesSheet->setTitle('Alteracoes');
        $this->writeSheet($changesSheet, 'A1', array_merge([$headers], array_map(function (array $row): array {
            return [
                $row['module'] ?? '-',
                $row['id'] ?? '-',
                $row['source_name'] ?? '-',
                $row['current_unicoop'] ?? '-',
                $row['current_area'] ?? '-',
                $row['current_centro_custo'] ?? '-',
                $row['new_nome_local'] ?? ($row['new_centro_custo_nome'] ?? '-'),
                $row['unicoop'] ?? '-',
                $row['area'] ?? '-',
                $row['centro_custo'] ?? '-',
                $row['reason'] ?? '-',
            ];
        }, $changes)), '#0f172a');

        $pendingSheet = $spreadsheet->createSheet();
        $pendingSheet->setTitle('Pendentes');
        $this->writeSheet($pendingSheet, 'A1', array_merge([$headers], array_map(function (array $row): array {
            return [
                $row['module'] ?? '-',
                $row['id'] ?? '-',
                $row['source_name'] ?? '-',
                $row['current_unicoop'] ?? '-',
                $row['current_area'] ?? '-',
                $row['current_centro_custo'] ?? '-',
                '-',
                '-',
                '-',
                '-',
                $row['reason'] ?? '-',
            ];
        }, $pending)), '#7c3aed');

        $manualSheet = $spreadsheet->createSheet();
        $manualSheet->setTitle('Cristalina II');
        $this->writeSheet($manualSheet, 'A1', array_merge([$headers], array_map(function (array $row): array {
            return [
                $row['module'] ?? '-',
                $row['id'] ?? '-',
                $row['source_name'] ?? '-',
                $row['current_unicoop'] ?? '-',
                $row['current_area'] ?? '-',
                $row['current_centro_custo'] ?? '-',
                '-',
                '-',
                '-',
                '-',
                $row['reason'] ?? '-',
            ];
        }, $manual)), '#f59e0b');

        $file = 'rateio_correcoes_' . now()->format('Ymd_His') . '.xlsx';
        $path = storage_path('app/reports/' . $file);
        File::ensureDirectoryExists(dirname($path));
        (new Xlsx($spreadsheet))->save($path);

        return ['path' => $path, 'file' => $file];
    }

    /**
     * @param array<int, array<int, mixed>> $rows
     */
    private function writeSheet($sheet, string $startCell, array $rows, string $headerColor): void
    {
        if ($rows === []) {
            return;
        }

        $sheet->fromArray($rows, null, $startCell);
        $highestColumn = $sheet->getHighestColumn();
        $highestRow = $sheet->getHighestRow();

        $sheet->getStyle('A1:' . $highestColumn . '1')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => ltrim($headerColor, '#')]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '000000']]],
        ]);

        if ($highestRow > 1) {
            $sheet->getStyle('A2:' . $highestColumn . $highestRow)->applyFromArray([
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT, 'vertical' => Alignment::VERTICAL_CENTER],
                'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'D1D5DB']]],
            ]);
        }

        foreach (range('A', $highestColumn) as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }
    }
}
