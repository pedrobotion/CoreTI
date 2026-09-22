<?php

namespace App\Services;

use App\Models\CmdbAsset;
use App\Models\CmdbManufacturer;
use App\Models\CmdbModel;
use App\Models\CmdbRack;
use App\Models\CmdbImportBatch;
use App\Models\CmdbImportRow;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class CmdbImportService
{
    public function __construct(
        private readonly CmdbImportValidatorService $validator,
        private readonly CmdbQuickCreateService $quickCreateService,
        private readonly CmdbAssetHistoryService $historyService,
    ) {
    }

    public function preview(UploadedFile $file, array $options, ?User $user = null): CmdbImportBatch
    {
        $path = $file->storeAs('cmdb/imports', $file->getClientOriginalName());
        $fullPath = Storage::path($path);

        $batch = CmdbImportBatch::create([
            'user_id' => $user?->id,
            'original_name' => $file->getClientOriginalName(),
            'status' => 'previewed',
            'options' => $options,
            'started_at' => now(),
        ]);

        $rows = $this->readSpreadsheet($fullPath);
        $total = count($rows);
        $summary = [
            'valid' => 0,
            'warning' => 0,
            'error' => 0,
        ];

        foreach ($rows as $index => $row) {
            $validated = $this->validator->validateRow($row, $options, $index + 2);
            $summary[$validated['status']]++;

            CmdbImportRow::create([
                'batch_id' => $batch->id,
                'row_number' => $index + 2,
                'status' => $validated['status'],
                'action' => $validated['action'],
                'data' => [
                    'raw' => $row,
                    'normalized' => $validated['data'],
                ],
                'messages' => $validated['messages'],
            ]);
        }

        $batch->update([
            'total_rows' => $total,
            'valid_rows' => $summary['valid'],
            'warning_rows' => $summary['warning'],
            'error_rows' => $summary['error'],
            'failed_rows' => $summary['error'],
            'finished_at' => now(),
        ]);

        return $batch->fresh('rows');
    }

    public function confirm(CmdbImportBatch $batch, ?User $user = null): array
    {
        return $this->processBatch($batch, $user, false);
    }

    public function reprocess(CmdbImportBatch $batch, ?User $user = null): array
    {
        return $this->processBatch($batch, $user, true, true);
    }

    public function templateSpreadsheet(): Spreadsheet
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $headers = $this->templateHeaders();

        $sheet->fromArray($headers, null, 'A1');
        $sheet->fromArray([
            'Servidor',
            'Servidor de Aplicação',
            'server-01',
            'Dell',
            'R740',
        ], null, 'A2');

        return $spreadsheet;
    }

    public function templateCsv(): string
    {
        $csv = fopen('php://temp', 'w+');
        fputcsv($csv, $this->templateHeaders(), ';');
        rewind($csv);
        return stream_get_contents($csv) ?: '';
    }

    private function readSpreadsheet(string $path): array
    {
        $reader = IOFactory::createReaderForFile($path);
        if (method_exists($reader, 'setReadDataOnly')) {
            $reader->setReadDataOnly(true);
        }

        $spreadsheet = $reader->load($path);
        $sheet = $spreadsheet->getActiveSheet();
        $rows = $sheet->toArray(null, true, true, true);

        if ($rows === []) {
            return [];
        }

        $headers = array_shift($rows);
        $headers = $this->validator->normalizeHeaders(array_values($headers));

        $payload = [];
        foreach ($rows as $row) {
            $values = array_values($row);
            $mapped = [];
            foreach ($headers as $index => $header) {
                $mapped[$header] = $values[$index] ?? null;
            }

            if (collect($mapped)->filter(fn ($value) => filled($value))->isNotEmpty()) {
                $payload[] = $mapped;
            }
        }

        return $payload;
    }

    private function templateHeaders(): array
    {
        return [
            'tipo_ativo',
            'nome',
            'hostname',
            'fabricante',
            'modelo',
            'serial',
            'patrimonio',
            'status',
            'criticidade',
            'ambiente',
            'unidade',
            'localizacao',
            'rack',
            'ip_principal',
            'mascara',
            'gateway',
            'dns',
            'vlan',
            'mac',
            'sistema_operacional',
            'cpu',
            'memoria_gb',
            'armazenamento_gb',
            'raid',
            'hypervisor',
            'portas',
            'poe',
            'firmware',
            'licenca',
            'expiracao_licenca',
            'vpn_habilitada',
            'potencia_va',
            'potencia_watts',
            'quantidade_baterias',
            'ultima_troca_bateria',
            'proxima_troca_bateria',
            'autonomia_minutos',
            'capacidade_total_tb',
            'garantia_inicio',
            'garantia_fim',
            'observacoes',
        ];
    }

    private function assetPayload(array $data): array
    {
        return [
            'asset_type' => $data['asset_type'] ?? 'other',
            'name' => $data['name'] ?? null,
            'hostname' => $data['hostname'] ?? null,
            'description' => $data['description'] ?? null,
            'manufacturer_id' => $data['manufacturer_id'] ?? null,
            'manufacturer' => $data['manufacturer'] ?? null,
            'model_id' => $data['model_id'] ?? null,
            'model' => $data['model'] ?? null,
            'serial_number' => $data['serial_number'] ?? null,
            'patrimony_number' => $data['patrimony_number'] ?? null,
            'status' => $data['status'] ?? 'active',
            'criticality' => $data['criticality'] ?? 'medium',
            'environment' => $data['environment'] ?? 'production',
            'company_id' => $data['company_id'] ?? null,
            'unit_id' => $data['unit_id'] ?? null,
            'location' => $data['location'] ?? null,
            'rack_id' => $data['rack_id'] ?? null,
            'main_ip' => $data['main_ip'] ?? null,
            'operating_system' => $data['operating_system'] ?? null,
            'responsible_user_id' => $data['responsible_user_id'] ?? null,
            'notes' => $data['notes'] ?? null,
            'source_type' => $data['source_type'] ?? 'manual',
            'source_connector_id' => $data['source_connector_id'] ?? null,
            'source_external_id' => $data['source_external_id'] ?? null,
        ];
    }

    private function resolveCatalogRelations(array $data, array $options): array
    {
        if (! blank($data['manufacturer'] ?? null) && blank($data['manufacturer_id'] ?? null)) {
            $manufacturer = CmdbManufacturer::query()
                ->where(function ($query) use ($data): void {
                    $query->whereRaw('LOWER(name) = ?', [mb_strtolower((string) $data['manufacturer'])])
                        ->orWhereRaw('LOWER(slug) = ?', [mb_strtolower(\Illuminate\Support\Str::slug((string) $data['manufacturer']))]);
                })
                ->first();

            if (! $manufacturer && ($options['create_manufacturers'] ?? false)) {
                $manufacturer = CmdbManufacturer::create([
                    'name' => $data['manufacturer'],
                    'slug' => \Illuminate\Support\Str::slug((string) $data['manufacturer']),
                    'is_active' => true,
                ]);
            }

            if ($manufacturer) {
                $data['manufacturer_id'] = $manufacturer->id;
                $data['manufacturer'] = $manufacturer->name;
            }
        }

        if (! blank($data['model'] ?? null) && blank($data['model_id'] ?? null)) {
            $modelQuery = CmdbModel::query();
            if (! blank($data['manufacturer_id'] ?? null)) {
                $modelQuery->where('manufacturer_id', $data['manufacturer_id']);
            }

            $model = $modelQuery
                ->where(function ($query) use ($data): void {
                    $query->whereRaw('LOWER(name) = ?', [mb_strtolower((string) $data['model'])])
                        ->orWhereRaw('LOWER(slug) = ?', [mb_strtolower(\Illuminate\Support\Str::slug((string) $data['model']))]);
                })
                ->first();

            if (! $model && ($options['create_models'] ?? false) && ! blank($data['manufacturer_id'] ?? null)) {
                $model = CmdbModel::create([
                    'manufacturer_id' => $data['manufacturer_id'],
                    'name' => $data['model'],
                    'slug' => \Illuminate\Support\Str::slug((string) $data['model']),
                    'asset_type' => $data['asset_type'] ?? null,
                    'is_active' => true,
                ]);
            }

            if ($model) {
                $data['model_id'] = $model->id;
                $data['model'] = $model->name;
                $data['manufacturer_id'] = $data['manufacturer_id'] ?: $model->manufacturer_id;
            }
        }

        if (! blank($data['rack_name'] ?? null) && blank($data['rack_id'] ?? null)) {
            $rack = CmdbRack::query()
                ->whereRaw('LOWER(name) = ?', [mb_strtolower((string) $data['rack_name'])])
                ->first();
            if (! $rack && ($options['create_racks'] ?? false)) {
                $rack = CmdbRack::create([
                    'name' => $data['rack_name'],
                    'unit_id' => $data['unit_id'] ?? null,
                    'location' => $data['location'] ?? null,
                    'room' => null,
                    'floor' => null,
                    'capacity_u' => null,
                    'notes' => null,
                ]);
            }

            if ($rack) {
                $data['rack_id'] = $rack->id;
            }
        }

        return $data;
    }

    private function processBatch(CmdbImportBatch $batch, ?User $user, bool $revalidateRows = false, bool $onlyProblemRows = false): array
    {
        $options = $batch->options ?? [];
        $summary = [
            'imported' => 0,
            'updated' => 0,
            'failed' => 0,
            'skipped' => 0,
        ];

        $rows = $batch->rows()->orderBy('row_number')->get();

        DB::transaction(function () use ($rows, $batch, $options, $user, $revalidateRows, $onlyProblemRows, &$summary): void {
            foreach ($rows as $row) {
                $shouldProcess = ! $onlyProblemRows || in_array($row->status, ['error', 'failed', 'warning', 'skipped'], true);
                if (! $shouldProcess) {
                    $summary['skipped']++;
                    continue;
                }

                $normalized = $revalidateRows
                    ? $this->revalidateRowData($row, $options)
                    : (array) data_get($row->data, 'normalized', []);

                $normalized = $this->resolveCatalogRelations($normalized, $options);

                if (($row->status === 'error' || $row->status === 'failed') && ! ($options['ignore_errors'] ?? false) && ! $revalidateRows) {
                    $summary['failed']++;
                    $row->update([
                        'status' => 'failed',
                        'messages' => array_values(array_unique(array_merge((array) ($row->messages ?? []), ['Linha com erro ignorada na confirmação.']))),
                    ]);
                    continue;
                }

                if (($row->status === 'error' || $row->status === 'failed') && ! ($options['ignore_errors'] ?? false) && $revalidateRows) {
                    $summary['failed']++;
                    $row->update([
                        'status' => 'failed',
                    ]);
                    continue;
                }

                $result = $this->processRow($batch, $row, $normalized, $user, $options);
                $summary[$result]++;
            }

            $finalRows = $batch->rows();
            $summary['imported'] = (int) $finalRows->where('status', 'imported')->count();
            $summary['updated'] = (int) $finalRows->where('status', 'updated')->count();
            $summary['failed'] = (int) $finalRows->where('status', 'failed')->count();

            $batch->update([
                'status' => 'completed',
                'imported_rows' => $summary['imported'],
                'updated_rows' => $summary['updated'],
                'failed_rows' => $summary['failed'],
                'finished_at' => now(),
            ]);
        });

        return $summary;
    }

    private function revalidateRowData(CmdbImportRow $row, array $options): array
    {
        $raw = (array) data_get($row->data, 'raw', []);
        $validated = $this->validator->validateRow($raw, $options, $row->row_number);

        $row->update([
            'status' => $validated['status'],
            'action' => $validated['action'],
            'data' => [
                'raw' => $raw,
                'normalized' => $validated['data'],
            ],
            'messages' => $validated['messages'],
        ]);

        return (array) $validated['data'];
    }

    private function processRow(CmdbImportBatch $batch, CmdbImportRow $row, array $normalized, ?User $user, array $options): string
    {
        $isUpdate = (($row->action ?? 'create') === 'update') && ! empty($normalized['existing_asset_id']);
        $asset = null;

        if ($isUpdate) {
            $asset = CmdbAsset::query()->find($normalized['existing_asset_id']);
            if ($asset) {
                $oldValues = $asset->toArray();
                $asset->update($this->assetPayload($normalized));
                $this->quickCreateService->applyDependencies($asset, $normalized);
                $this->historyService->record(
                    $asset,
                    $user,
                    'updated',
                    sprintf('Ativo atualizado pela importação em massa no lote #%d.', $batch->id),
                    null,
                    $oldValues,
                    $asset->fresh()->toArray()
                );
                $row->update([
                    'asset_id' => $asset->id,
                    'status' => 'updated',
                    'action' => 'update',
                ]);

                return 'updated';
            }
        }

        $asset = $this->quickCreateService->create(
            $normalized,
            $user,
            sprintf('Ativo criado pela importação em massa no lote #%d.', $batch->id)
        );

        $row->update([
            'asset_id' => $asset->id,
            'status' => 'imported',
            'action' => 'create',
        ]);

        return 'imported';
    }
}
