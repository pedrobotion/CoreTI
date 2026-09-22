<?php

namespace App\Http\Controllers;

use App\Models\CmdbImportBatch;
use App\Services\CmdbImportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CmdbImportBatchController extends Controller
{
    private const BATCH_STATUS_LABELS = [
        'previewed' => 'Prévia',
        'processing' => 'Processando',
        'completed' => 'Concluído',
        'failed' => 'Falhou',
    ];

    private const ROW_STATUS_LABELS = [
        'valid' => 'Válida',
        'warning' => 'Com alerta',
        'error' => 'Com erro',
        'imported' => 'Importada',
        'updated' => 'Atualizada',
        'failed' => 'Falha',
        'skipped' => 'Ignorada',
    ];

    private const ACTION_LABELS = [
        'create' => 'Criar',
        'update' => 'Atualizar',
        'skip' => 'Ignorar',
        'error' => 'Erro',
    ];

    public function __construct(
        private readonly CmdbImportService $importService,
    ) {
    }

    public function index(Request $request): View
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
            'status' => ['nullable', 'string', 'max:30'],
            'per_page' => ['nullable', 'integer', 'in:10,15,25,50'],
        ]);

        $query = CmdbImportBatch::query()->with('user');

        if ($search = trim((string) ($filters['q'] ?? ''))) {
            $query->where(function ($builder) use ($search): void {
                $builder->where('original_name', 'like', '%' . $search . '%')
                    ->orWhere(function ($subQuery) use ($search): void {
                        if (is_numeric($search)) {
                            $subQuery->where('id', (int) $search);
                        }
                    });
            });
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        $batches = $query
            ->orderByDesc('started_at')
            ->paginate((int) ($filters['per_page'] ?? 15))
            ->withQueryString();

        $totals = [
            'batches' => (int) CmdbImportBatch::query()->count(),
            'completed' => (int) CmdbImportBatch::query()->where('status', 'completed')->count(),
            'failed' => (int) CmdbImportBatch::query()->where(function ($query): void {
                $query->where('status', 'failed')
                    ->orWhere('error_rows', '>', 0)
                    ->orWhere('failed_rows', '>', 0);
            })->count(),
            'imported_rows' => (int) CmdbImportBatch::query()->sum('imported_rows') + (int) CmdbImportBatch::query()->sum('updated_rows'),
            'error_rows' => (int) CmdbImportBatch::query()->sum('error_rows'),
        ];

        return view('cmdb.import.batches.index', [
            'setupPending' => false,
            'filters' => $filters,
            'batches' => $batches,
            'totals' => $totals,
            'batchStatusLabels' => self::BATCH_STATUS_LABELS,
        ]);
    }

    public function show(Request $request, CmdbImportBatch $batch): View
    {
        $filters = $request->validate([
            'status' => ['nullable', 'string', 'max:20'],
            'action' => ['nullable', 'string', 'max:20'],
            'row_number' => ['nullable', 'integer', 'min:1'],
            'name' => ['nullable', 'string', 'max:120'],
            'hostname' => ['nullable', 'string', 'max:120'],
            'asset_type' => ['nullable', 'string', 'max:60'],
            'message' => ['nullable', 'string', 'max:120'],
            'per_page' => ['nullable', 'integer', 'in:10,15,25,50'],
        ]);

        $rows = $this->batchRowsQuery($batch, $filters)
            ->orderBy('row_number')
            ->paginate((int) ($filters['per_page'] ?? 15))
            ->withQueryString();

        return view('cmdb.import.batches.show', [
            'setupPending' => false,
            'batch' => $batch->load('user'),
            'filters' => $filters,
            'rows' => $rows,
            'summary' => $this->batchSummary($batch),
            'statusLabels' => self::ROW_STATUS_LABELS,
            'actionLabels' => self::ACTION_LABELS,
        ]);
    }

    public function export(Request $request, CmdbImportBatch $batch)
    {
        $filters = $request->validate([
            'type' => ['nullable', 'in:all,errors,warnings'],
            'status' => ['nullable', 'string', 'max:20'],
            'action' => ['nullable', 'string', 'max:20'],
            'row_number' => ['nullable', 'integer', 'min:1'],
            'name' => ['nullable', 'string', 'max:120'],
            'hostname' => ['nullable', 'string', 'max:120'],
            'asset_type' => ['nullable', 'string', 'max:60'],
            'message' => ['nullable', 'string', 'max:120'],
        ]);

        $type = $filters['type'] ?? 'all';
        $fileName = match ($type) {
            'errors' => sprintf('cmdb_importacao_lote_%d_erros.csv', $batch->id),
            'warnings' => sprintf('cmdb_importacao_lote_%d_alertas.csv', $batch->id),
            default => sprintf('cmdb_importacao_lote_%d_completo.csv', $batch->id),
        };

        $rows = $this->batchRowsQuery($batch, $filters)
            ->when($type === 'errors', static fn ($query) => $query->whereIn('status', ['error', 'failed']))
            ->when($type === 'warnings', static fn ($query) => $query->where('status', 'warning'))
            ->orderBy('row_number')
            ->get();

        return response()->streamDownload(static function () use ($rows): void {
            $output = fopen('php://output', 'w');
            fwrite($output, "\xEF\xBB\xBF");
            fputcsv($output, ['batch_id', 'row_number', 'status', 'action', 'asset_id', 'tipo_ativo', 'nome', 'hostname', 'ip_principal', 'fabricante', 'modelo', 'mensagens', 'payload_json', 'created_at'], ';');

            foreach ($rows as $row) {
                $normalized = (array) data_get($row->data, 'normalized', []);
                fputcsv($output, [
                    $row->batch_id,
                    $row->row_number,
                    $row->status,
                    $row->action,
                    $row->asset_id,
                    data_get($normalized, 'asset_type', '-'),
                    data_get($normalized, 'name', '-'),
                    data_get($normalized, 'hostname', '-'),
                    data_get($normalized, 'main_ip', '-'),
                    data_get($normalized, 'manufacturer', '-'),
                    data_get($normalized, 'model', '-'),
                    implode(' | ', (array) ($row->messages ?? [])),
                    json_encode($row->data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    optional($row->created_at)?->format('d/m/Y H:i:s'),
                ], ';');
            }

            fclose($output);
        }, $fileName, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function reprocess(Request $request, CmdbImportBatch $batch): RedirectResponse
    {
        $summary = $this->importService->reprocess($batch, $request->user());

        return redirect()
            ->route('cmdb.import.batches.result', $batch)
            ->with('success', sprintf(
                'Lote reprocessado. Importados: %d | Atualizados: %d | Falhas: %d.',
                $summary['imported'],
                $summary['updated'],
                $summary['failed']
            ));
    }

    public function result(CmdbImportBatch $batch): View
    {
        $batch->load('user');

        return view('cmdb.import.batches.result', [
            'setupPending' => false,
            'batch' => $batch,
            'summary' => $this->batchSummary($batch),
            'statusLabels' => self::ROW_STATUS_LABELS,
            'actionLabels' => self::ACTION_LABELS,
        ]);
    }

    private function batchSummary(CmdbImportBatch $batch): array
    {
        $rows = $batch->rows();

        return [
            'total_rows' => (int) $batch->total_rows,
            'valid_rows' => (int) $batch->valid_rows,
            'warning_rows' => (int) $batch->warning_rows,
            'error_rows' => (int) $batch->error_rows,
            'imported_rows' => (int) $batch->imported_rows,
            'updated_rows' => (int) $batch->updated_rows,
            'failed_rows' => (int) $batch->failed_rows,
            'success_rows' => (int) $rows->whereIn('status', ['imported', 'updated'])->count(),
            'problem_rows' => (int) $rows->whereIn('status', ['error', 'failed', 'warning', 'skipped'])->count(),
        ];
    }

    private function batchRowsQuery(CmdbImportBatch $batch, array $filters)
    {
        $query = $batch->rows()->with('asset');

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['action'])) {
            $query->where('action', $filters['action']);
        }

        if (! empty($filters['row_number'])) {
            $query->where('row_number', (int) $filters['row_number']);
        }

        if ($search = trim((string) ($filters['name'] ?? ''))) {
            $query->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(data, '$.normalized.name')) LIKE ?", ['%' . $search . '%']);
        }

        if ($search = trim((string) ($filters['hostname'] ?? ''))) {
            $query->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(data, '$.normalized.hostname')) LIKE ?", ['%' . $search . '%']);
        }

        if ($search = trim((string) ($filters['asset_type'] ?? ''))) {
            $query->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(data, '$.normalized.asset_type')) LIKE ?", ['%' . $search . '%']);
        }

        if ($search = trim((string) ($filters['message'] ?? ''))) {
            $query->where(function ($builder) use ($search): void {
                $builder->whereRaw('CAST(messages AS CHAR) LIKE ?', ['%' . $search . '%'])
                    ->orWhereRaw("JSON_UNQUOTE(JSON_EXTRACT(data, '$.normalized.name')) LIKE ?", ['%' . $search . '%'])
                    ->orWhereRaw("JSON_UNQUOTE(JSON_EXTRACT(data, '$.normalized.hostname')) LIKE ?", ['%' . $search . '%']);
            });
        }

        return $query;
    }
}
