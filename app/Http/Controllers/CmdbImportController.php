<?php

namespace App\Http\Controllers;

use App\Models\CmdbImportBatch;
use App\Services\CmdbImportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class CmdbImportController extends Controller
{
    public function __construct(
        private readonly CmdbImportService $importService,
    ) {
    }

    public function index(Request $request): View
    {
        $batch = null;
        $rows = collect();

        if ($request->filled('batch') && Schema::hasTable('cmdb_import_batches')) {
            $batch = CmdbImportBatch::query()->with('user')->find($request->integer('batch'));
            if ($batch) {
                $rows = $batch->rows()->orderBy('row_number')->limit(50)->get();
            }
        }

        return view('cmdb.import.index', [
            'setupPending' => false,
            'batch' => $batch,
            'rows' => $rows,
            'recentBatches' => Schema::hasTable('cmdb_import_batches')
                ? CmdbImportBatch::query()->with('user')->orderByDesc('started_at')->limit(8)->get()
                : collect(),
        ]);
    }

    public function preview(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt,xlsx,xls', 'max:20480'],
            'create_manufacturers' => ['nullable', 'boolean'],
            'create_models' => ['nullable', 'boolean'],
            'create_racks' => ['nullable', 'boolean'],
            'ignore_errors' => ['nullable', 'boolean'],
            'update_existing' => ['nullable', 'boolean'],
        ]);

        $batch = $this->importService->preview($request->file('file'), $data, $request->user());

        return redirect()->route('cmdb.import.index', ['batch' => $batch->id])->with('success', 'Prévia gerada com sucesso.');
    }

    public function confirm(Request $request, CmdbImportBatch $batch): RedirectResponse
    {
        $summary = $this->importService->confirm($batch, $request->user());

        return redirect()
            ->route('cmdb.import.batches.result', $batch)
            ->with('success', sprintf('Importação concluída. Inseridos: %d | Atualizados: %d | Falhas: %d.', $summary['imported'], $summary['updated'], $summary['failed']));
    }

    public function template(Request $request)
    {
        $format = strtolower((string) $request->query('format', 'xlsx'));

        if ($format === 'csv') {
            $csv = $this->importService->templateCsv();

            return Response::streamDownload(static function () use ($csv): void {
                echo $csv;
            }, 'cmdb_import_template.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
        }

        $spreadsheet = $this->importService->templateSpreadsheet();
        $writer = new Xlsx($spreadsheet);

        return Response::streamDownload(static function () use ($writer): void {
            $writer->save('php://output');
        }, 'cmdb_import_template.xlsx');
    }
}
