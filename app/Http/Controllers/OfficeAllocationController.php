<?php

namespace App\Http\Controllers;

use App\Models\OfficeAllocationBatch;
use App\Models\OfficeLicensePrice;
use App\Services\OfficeAllocationExportService;
use App\Services\OfficeAllocationService;
use App\Support\OfficeLicenseSkuCatalog;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\StreamedResponse;

class OfficeAllocationController extends Controller
{
    public function __construct(private readonly OfficeAllocationService $allocationService)
    {
    }

    public function index(Request $request): View
    {
        $filters = $request->validate([
            'competence' => ['nullable', 'regex:/^\d{4}-\d{2}$/'],
            'status' => ['nullable', 'string', 'max:80'],
            'q' => ['nullable', 'string', 'max:120'],
            'pending' => ['nullable', 'boolean'],
        ]);

        $competence = (string) ($filters['competence'] ?? now()->format('Y-m'));
        $batch = $this->allocationService->latestOrGenerateCandidate($competence);
        $consolidado = $batch ? $this->allocationService->consolidado($batch) : collect();
        $skuSummary = $batch ? $this->allocationService->skuSummary($batch) : collect();
        $unitSummary = $batch ? $this->allocationService->unitSummary($batch) : collect();
        $departmentSummary = $batch ? $this->allocationService->departmentSummary($batch) : collect();
        $pendingRows = $batch
            ? $this->allocationService->pendingRowsQuery($batch)->orderBy('user_name')->limit(20)->get()
            : collect();

        $detailRows = collect();
        if ($batch) {
            $detailRows = $batch->rows()
                ->when($request->boolean('pending'), fn ($query) => $query->where('classification_status', '<>', 'classified'))
                ->when(trim((string) ($filters['status'] ?? '')) !== '', fn ($query) => $query->where('classification_status', $filters['status']))
                ->when(trim((string) ($filters['q'] ?? '')) !== '', function ($query) use ($filters): void {
                    $term = trim((string) $filters['q']);
                    $query->where(function ($query) use ($term): void {
                        $query->where('user_name', 'like', "%{$term}%")
                            ->orWhere('user_email', 'like', "%{$term}%")
                            ->orWhere('sku_name', 'like', "%{$term}%")
                            ->orWhere('cost_center_code_snapshot', 'like', "%{$term}%")
                            ->orWhere('unit_name_snapshot', 'like', "%{$term}%")
                            ->orWhere('department_name_snapshot', 'like', "%{$term}%");
                    });
                })
                ->orderBy('user_name')
                ->limit(200)
                ->get();

            // Nome atual do centro de custo (catálogo /centros-custo) pelo
            // código, para o detalhamento não exibir o nome antigo obsoleto.
            $detailRows->each(function ($row): void {
                $row->catalog_cost_center_name = $this->allocationService->nomeCatalogoPorCodigo($row->cost_center_code_snapshot);
            });
        }

        // Os custos são informados por categoria canônica, não por cada
        // identificador técnico retornado pelo Microsoft Graph.
        $skuCatalog = OfficeLicenseSkuCatalog::billable();

        return view('service-desk.licensing.office-rateio', [
            'batch' => $batch,
            'competence' => $competence,
            'consolidado' => $consolidado,
            'skuSummary' => $skuSummary,
            'unitSummary' => $unitSummary,
            'departmentSummary' => $departmentSummary,
            'pendingRows' => $pendingRows,
            'detailRows' => $detailRows,
            'statusLabels' => OfficeAllocationService::statusLabels(),
            'classificationLabels' => OfficeAllocationService::classificationStatusLabels(),
            'skuCatalog' => $skuCatalog,
            'latestPrices' => OfficeLicensePrice::query()
                ->active()
                ->whereNull('valid_until')
                ->orderByDesc('valid_from')
                ->get()
                ->unique('sku_id')
                ->keyBy('sku_id'),
            'filters' => $filters,
        ]);
    }

    public function generate(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'competence' => ['required', 'regex:/^\d{4}-\d{2}$/'],
        ]);

        try {
            $batch = $this->allocationService->generate((string) $data['competence'], Auth::id());
        } catch (\Throwable $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()
            ->route('administrativo.licensing.office', ['competence' => $batch->competence])
            ->with('success', 'Prévia do Rateio Office gerada para a competência ' . $batch->competence . '.');
    }

    public function reprocess(OfficeAllocationBatch $batch): RedirectResponse
    {
        try {
            $batch = $this->allocationService->generate($batch->competence, Auth::id());
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()
            ->route('administrativo.licensing.office', ['competence' => $batch->competence])
            ->with('success', 'Rateio reprocessado.');
    }

    public function validateBatch(OfficeAllocationBatch $batch): RedirectResponse
    {
        try {
            $batch = $this->allocationService->validate($batch, Auth::id());
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()
            ->route('administrativo.licensing.office', ['competence' => $batch->competence])
            ->with('success', 'Rateio validado.');
    }

    public function close(OfficeAllocationBatch $batch): RedirectResponse
    {
        try {
            $batch = $this->allocationService->close($batch, Auth::id());
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()
            ->route('administrativo.licensing.office', ['competence' => $batch->competence])
            ->with('success', 'Rateio fechado. Os snapshots desta competência foram preservados.');
    }

    public function cancel(Request $request, OfficeAllocationBatch $batch): RedirectResponse
    {
        $data = $request->validate([
            'cancellation_reason' => ['required', 'string', 'max:1000'],
        ]);

        try {
            $batch = $this->allocationService->cancel($batch, (string) $data['cancellation_reason'], Auth::id());
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()
            ->route('administrativo.licensing.office', ['competence' => $batch->competence])
            ->with('success', 'Rateio cancelado.');
    }

    public function export(OfficeAllocationBatch $batch, OfficeAllocationExportService $exportService): StreamedResponse
    {
        return $exportService->export($batch);
    }
}
