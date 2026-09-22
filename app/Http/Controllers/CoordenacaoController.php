<?php

namespace App\Http\Controllers;

use App\Models\CoordenacaoCostRule;
use App\Models\CoordenacaoMonthlyClosing;
use App\Models\CoordenacaoPendingItem;
use App\Models\CoordenacaoProduct;
use App\Services\CoordenacaoCostAnalysisService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CoordenacaoController extends Controller
{
    public function dashboard(Request $request, CoordenacaoCostAnalysisService $service): View
    {
        $report = $service->buildReport($request->only(['q', 'unit', 'status']));

        return view('coordenacao.dashboard', [
            'report' => $report,
            'filters' => $request->only(['q', 'unit', 'status']),
            'unitsFilter' => $report['units']->pluck('unit')->filter()->sort()->values(),
            'productsCount' => CoordenacaoProduct::query()->where('is_active', true)->count(),
            'pendingCount' => CoordenacaoPendingItem::query()->where('status', 'open')->count(),
            'latestClosing' => CoordenacaoMonthlyClosing::query()->latest('reference_month')->first(),
            'closingsCount' => CoordenacaoMonthlyClosing::query()->count(),
        ]);
    }

    public function collaborators(Request $request, CoordenacaoCostAnalysisService $service): View
    {
        $report = $service->buildReport($request->only(['q', 'unit', 'status']));

        return view('coordenacao.collaborators', [
            'report' => $report,
            'filters' => $request->only(['q', 'unit', 'status']),
            'people' => $this->paginateCollection($report['people'], (int) $request->integer('per_page', 25), 'page'),
            'unitsFilter' => $report['units']->pluck('unit')->filter()->sort()->values(),
        ]);
    }

    public function units(Request $request, CoordenacaoCostAnalysisService $service): View
    {
        $report = $service->buildReport($request->only(['q', 'unit', 'status']));

        return view('coordenacao.units', [
            'report' => $report,
            'filters' => $request->only(['q', 'unit', 'status']),
            'units' => $this->paginateCollection($report['units'], (int) $request->integer('per_page', 25), 'page'),
        ]);
    }

    public function rules(Request $request): View
    {
        $rules = CoordenacaoCostRule::query()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $editingRule = null;
        if ($request->filled('edit')) {
            $editingRule = $rules->firstWhere('id', (int) $request->integer('edit'));
        }

        return view('coordenacao.rules', [
            'rules' => $rules,
            'editingRule' => $editingRule,
            'sourceTypes' => [
                'office_flag' => 'Flag do Office',
                'ad_group_contains' => 'Grupo AD contém',
                'ad_group_exact' => 'Grupo AD exato',
            ],
            'billingCycles' => [
                'monthly' => 'Mensal',
                'annual' => 'Anual',
                'one_time' => 'Pontual',
            ],
        ]);
    }

    public function storeRule(Request $request)
    {
        $data = $this->validateRule($request);

        CoordenacaoCostRule::create($data);

        return redirect()->route('coordenacao.rules')->with('success', 'Regra de custo cadastrada com sucesso.');
    }

    public function updateRule(Request $request, CoordenacaoCostRule $rule)
    {
        $data = $this->validateRule($request);

        $rule->update($data);

        return redirect()->route('coordenacao.rules')->with('success', 'Regra de custo atualizada com sucesso.');
    }

    public function toggleRule(CoordenacaoCostRule $rule)
    {
        $rule->update(['is_active' => ! $rule->is_active]);

        return redirect()->route('coordenacao.rules')->with('success', 'Status da regra atualizado com sucesso.');
    }

    public function export(Request $request, CoordenacaoCostAnalysisService $service): StreamedResponse
    {
        $report = $service->exportRows($request->only(['q', 'unit', 'status']));

        $spreadsheet = new Spreadsheet();
        $spreadsheet->getProperties()
            ->setCreator(config('app.name'))
            ->setTitle('Relatório de Coordenação')
            ->setSubject('Coordenação')
            ->setDescription('Relatório de custos estimados por colaborador, unidade e regra.');

        $this->writeSummarySheet($spreadsheet, $report);
        $this->writeCollaboratorsSheet($spreadsheet, $report['people']);
        $this->writeUnitsSheet($spreadsheet, $report['units']);
        $this->writeRulesSheet($spreadsheet, $report['rules'], $report['ruleSummary']);
        $this->writeGroupsSheet($spreadsheet, $report['groupFrequency'], $report['candidates']);
        $this->writeWarningsSheet($spreadsheet, $report['warnings'] ?? collect());
        $this->writeProductsSheet($spreadsheet);

        $closing = CoordenacaoMonthlyClosing::query()->latest('reference_month')->first();
        if ($closing) {
            $this->writeMonthlyRateioSheet($spreadsheet, $closing);
            $this->writeUserCostsSheet($spreadsheet, $closing);
        }

        $filename = 'coordenacao_relatorio_' . now()->format('Ymd_His') . '.xlsx';

        return response()->streamDownload(function () use ($spreadsheet): void {
            $writer = new Xlsx($spreadsheet);
            $writer->setPreCalculateFormulas(false);
            $writer->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    private function validateRule(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'source_type' => ['required', 'string', 'in:office_flag,ad_group_contains,ad_group_exact'],
            'match_value' => ['nullable', 'string', 'max:255'],
            'billing_cycle' => ['required', 'string', 'in:monthly,annual,one_time'],
            'amount' => ['required', 'numeric', 'min:0'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string'],
        ]) + [
            'is_active' => $request->boolean('is_active'),
            'sort_order' => (int) $request->input('sort_order', 0),
        ];
    }

    private function writeProductsSheet(Spreadsheet $spreadsheet): void
    {
        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle('Produtos');
        $headers = ['Produto', 'Fornecedor', 'Valor contrato', 'Cobrança', 'Base de rateio', 'Distribuição', 'Ativo', 'Configuração'];
        foreach ($headers as $column => $header) $sheet->setCellValueByColumnAndRow($column + 1, 1, $header);
        foreach (\App\Models\CoordenacaoProduct::query()->orderBy('name')->get() as $index => $product) {
            $row = $index + 2;
            foreach ([$product->name, $product->supplier, (float) $product->contract_amount, $product->billing_period, $product->allocation_basis, $product->distribution_method, $product->is_active ? 'Ativo' : 'Inativo', $product->configuration_status] as $column => $value) $sheet->setCellValueByColumnAndRow($column + 1, $row, $value);
        }
        $this->styleHeaderRow($sheet, 1, count($headers));
        foreach (range('A', 'H') as $column) $sheet->getColumnDimension($column)->setAutoSize(true);
    }

    private function writeMonthlyRateioSheet(Spreadsheet $spreadsheet, CoordenacaoMonthlyClosing $closing): void
    {
        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle('Rateio mensal');
        $headers = ['Competência', 'Unidade', 'Produto', 'Participantes', 'Individual', 'Compartilhado', 'Total'];
        foreach ($headers as $column => $header) $sheet->setCellValueByColumnAndRow($column + 1, 1, $header);
        foreach ($closing->allocations()->with('product')->orderBy('unit_key')->get() as $index => $row) {
            $line = $index + 2;
            foreach ([$closing->reference_month->format('Y-m'), $row->unit_key, $row->product->name, $row->eligible_users, (float) $row->individual_amount, (float) $row->shared_amount, (float) $row->individual_amount + (float) $row->shared_amount] as $column => $value) $sheet->setCellValueByColumnAndRow($column + 1, $line, $value);
        }
        $this->styleHeaderRow($sheet, 1, count($headers));
        foreach (range('A', 'G') as $column) $sheet->getColumnDimension($column)->setAutoSize(true);
    }

    private function writeUserCostsSheet(Spreadsheet $spreadsheet, CoordenacaoMonthlyClosing $closing): void
    {
        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle('Custos individuais');
        $headers = ['Competência', 'Usuário', 'Unidade', 'Produto', 'Tipo', 'Valor'];
        foreach ($headers as $column => $header) $sheet->setCellValueByColumnAndRow($column + 1, 1, $header);
        foreach ($closing->userCosts()->with('product')->orderBy('user_key')->get() as $index => $row) {
            $line = $index + 2;
            foreach ([$closing->reference_month->format('Y-m'), $row->user_key, $row->unit_key, $row->product->name, $row->cost_type, (float) $row->amount] as $column => $value) $sheet->setCellValueByColumnAndRow($column + 1, $line, $value);
        }
        $this->styleHeaderRow($sheet, 1, count($headers));
        foreach (range('A', 'F') as $column) $sheet->getColumnDimension($column)->setAutoSize(true);
    }

    private function paginateCollection(Collection $items, int $perPage, string $pageName = 'page'): \Illuminate\Pagination\LengthAwarePaginator
    {
        $perPage = max($perPage, 1);
        $page = max((int) request()->query($pageName, 1), 1);
        $items = $items->values();
        $total = $items->count();
        $results = $items->slice(($page - 1) * $perPage, $perPage)->values();

        $paginator = new \Illuminate\Pagination\LengthAwarePaginator(
            $results,
            $total,
            $perPage,
            $page,
            [
                'path' => request()->url(),
                'pageName' => $pageName,
            ]
        );

        return $paginator->appends(request()->except($pageName));
    }

    private function writeSummarySheet(Spreadsheet $spreadsheet, array $report): void
    {
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Resumo');

        $rows = [
            ['Indicador', 'Valor'],
            ['Colaboradores considerados', $report['stats']['people_total']],
            ['Com Office', $report['stats']['people_with_office']],
            ['Com grupos com custo', $report['stats']['people_with_group_costs']],
            ['Regras ativas', $report['stats']['rules_active']],
            ['Regras totais', $report['stats']['rules_total']],
            ['Custo mensal estimado', $report['stats']['monthly_cost_total']],
            ['Custo não recorrente', $report['stats']['one_time_cost_total']],
            ['Custo Office mensal', $report['stats']['office_cost_total']],
            ['Custo de grupos mensal', $report['stats']['groups_cost_total']],
            ['Unidades', $report['stats']['units_total']],
            ['Grupos AD detectados', $report['stats']['detected_groups_total']],
            ['Avisos / órfãos', $report['stats']['warning_total'] ?? 0],
            ['Órfãos e-mail', $report['stats']['warning_email_total'] ?? 0],
            ['Órfãos Office', $report['stats']['warning_office_total'] ?? 0],
        ];

        foreach ($rows as $index => $row) {
            $sheet->setCellValue("A" . ($index + 1), $row[0]);
            $sheet->setCellValue("B" . ($index + 1), $row[1]);
        }

        $this->styleHeaderRow($sheet, 1, 2);
        $sheet->getColumnDimension('A')->setWidth(34);
        $sheet->getColumnDimension('B')->setWidth(18);
    }

    private function writeCollaboratorsSheet(Spreadsheet $spreadsheet, Collection $people): void
    {
        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle('Colaboradores');

        $headers = [
            'E-mail',
            'Nome',
            'Unidade',
            'Unidade AD',
            'Status',
            'Office',
            'Grupos AD',
            'Regras encontradas',
            'Custo mensal Office',
            'Custo mensal grupos',
            'Custo mensal total',
            'Custo não recorrente',
        ];

        foreach ($headers as $col => $header) {
            $sheet->setCellValueByColumnAndRow($col + 1, 1, $header);
        }

        foreach ($people->values() as $rowIndex => $person) {
            $line = $rowIndex + 2;
            $sheet->setCellValueByColumnAndRow(1, $line, $person['email'] ?? '');
            $sheet->setCellValueByColumnAndRow(2, $line, $person['name'] ?? '');
            $sheet->setCellValueByColumnAndRow(3, $line, $person['resolved_unit'] ?? '');
            $sheet->setCellValueByColumnAndRow(4, $line, $person['ad_unit_name'] ?? '');
            $sheet->setCellValueByColumnAndRow(5, $line, $person['status'] ?? '');
            $sheet->setCellValueByColumnAndRow(6, $line, $this->formatOfficeFlags($person['office_flags'] ?? []));
            $sheet->setCellValueByColumnAndRow(7, $line, implode(' | ', $person['ad_groups_display'] ?? []));
            $sheet->setCellValueByColumnAndRow(8, $line, implode(' | ', $person['matched_rule_names'] ?? []));
            $sheet->setCellValueByColumnAndRow(9, $line, (float) ($person['office_cost'] ?? 0));
            $sheet->setCellValueByColumnAndRow(10, $line, (float) ($person['groups_cost'] ?? 0));
            $sheet->setCellValueByColumnAndRow(11, $line, (float) ($person['monthly_cost'] ?? 0));
            $sheet->setCellValueByColumnAndRow(12, $line, (float) ($person['one_time_cost'] ?? 0));
        }

        $this->styleHeaderRow($sheet, 1, count($headers));
        foreach (range('A', 'L') as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }
    }

    private function writeUnitsSheet(Spreadsheet $spreadsheet, Collection $units): void
    {
        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle('Unidades');

        $headers = [
            'Unidade',
            'Colaboradores',
            'Custo mensal Office',
            'Custo mensal grupos',
            'Custo mensal total',
            'Custo não recorrente',
        ];

        foreach ($headers as $col => $header) {
            $sheet->setCellValueByColumnAndRow($col + 1, 1, $header);
        }

        foreach ($units->values() as $rowIndex => $unit) {
            $line = $rowIndex + 2;
            $sheet->setCellValueByColumnAndRow(1, $line, $unit['unit'] ?? '');
            $sheet->setCellValueByColumnAndRow(2, $line, (int) ($unit['users'] ?? 0));
            $sheet->setCellValueByColumnAndRow(3, $line, (float) ($unit['office_cost'] ?? 0));
            $sheet->setCellValueByColumnAndRow(4, $line, (float) ($unit['groups_cost'] ?? 0));
            $sheet->setCellValueByColumnAndRow(5, $line, (float) ($unit['monthly_cost'] ?? 0));
            $sheet->setCellValueByColumnAndRow(6, $line, (float) ($unit['one_time_cost'] ?? 0));
        }

        $this->styleHeaderRow($sheet, 1, count($headers));
        foreach (range('A', 'F') as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }
    }

    private function writeRulesSheet(Spreadsheet $spreadsheet, Collection $rules, Collection $ruleSummary): void
    {
        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle('Regras');

        $headers = [
            'Nome',
            'Tipo',
            'Padrão',
            'Frequência',
            'Valor',
            'Valor mensal equivalente',
            'Ativo',
            'Usuários atingidos',
            'Custo mensal total',
            'Custo não recorrente',
        ];

        foreach ($headers as $col => $header) {
            $sheet->setCellValueByColumnAndRow($col + 1, 1, $header);
        }

        foreach ($ruleSummary->values() as $rowIndex => $rule) {
            $line = $rowIndex + 2;
            $sheet->setCellValueByColumnAndRow(1, $line, $rule['name']);
            $sheet->setCellValueByColumnAndRow(2, $line, $rule['source_type']);
            $sheet->setCellValueByColumnAndRow(3, $line, $rule['match_value']);
            $sheet->setCellValueByColumnAndRow(4, $line, $rule['billing_cycle']);
            $sheet->setCellValueByColumnAndRow(5, $line, (float) $rule['amount']);
            $sheet->setCellValueByColumnAndRow(6, $line, (float) $rule['monthly_equivalent']);
            $sheet->setCellValueByColumnAndRow(7, $line, $rule['is_active'] ? 'Sim' : 'Não');
            $sheet->setCellValueByColumnAndRow(8, $line, (int) $rule['matched_users']);
            $sheet->setCellValueByColumnAndRow(9, $line, (float) $rule['monthly_total']);
            $sheet->setCellValueByColumnAndRow(10, $line, (float) $rule['one_time_total']);
        }

        $this->styleHeaderRow($sheet, 1, count($headers));
        foreach (range('A', 'J') as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }
    }

    private function writeGroupsSheet(Spreadsheet $spreadsheet, Collection $groups, Collection $candidates): void
    {
        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle('Grupos AD');

        $headers = ['Grupo', 'Usuários', 'Candidato a custo'];

        foreach ($headers as $col => $header) {
            $sheet->setCellValueByColumnAndRow($col + 1, 1, $header);
        }

        foreach ($groups->values() as $rowIndex => $group) {
            $line = $rowIndex + 2;
            $sheet->setCellValueByColumnAndRow(1, $line, $group->group);
            $sheet->setCellValueByColumnAndRow(2, $line, (int) $group->users);
            $sheet->setCellValueByColumnAndRow(3, $line, $candidates->contains(fn (object $candidate): bool => $candidate->normalized === $group->normalized) ? 'Sim' : 'Não');
        }

        $this->styleHeaderRow($sheet, 1, count($headers));
        foreach (range('A', 'C') as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }
    }

    private function writeWarningsSheet(Spreadsheet $spreadsheet, Collection $warnings): void
    {
        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle('Avisos');

        $headers = ['Origem', 'Nome', 'E-mail', 'Identificador', 'Matrícula', 'Status', 'Motivo'];

        foreach ($headers as $col => $header) {
            $sheet->setCellValueByColumnAndRow($col + 1, 1, $header);
        }

        foreach ($warnings->values() as $rowIndex => $warning) {
            $line = $rowIndex + 2;
            $sheet->setCellValueByColumnAndRow(1, $line, $warning['label'] ?? '');
            $sheet->setCellValueByColumnAndRow(2, $line, $warning['name'] ?? '');
            $sheet->setCellValueByColumnAndRow(3, $line, $warning['email'] ?? '');
            $sheet->setCellValueByColumnAndRow(4, $line, $warning['identifier'] ?? '');
            $sheet->setCellValueByColumnAndRow(5, $line, $warning['matricula'] ?? '');
            $sheet->setCellValueByColumnAndRow(6, $line, $warning['status'] ?? '');
            $sheet->setCellValueByColumnAndRow(7, $line, $warning['reason'] ?? '');
        }

        $this->styleHeaderRow($sheet, 1, count($headers));
        foreach (range('A', 'G') as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }
    }

    private function styleHeaderRow(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet, int $row, int $lastColumn): void
    {
        $endColumn = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($lastColumn);
        $range = "A{$row}:{$endColumn}{$row}";

        $sheet->getStyle($range)->applyFromArray([
            'font' => [
                'bold' => true,
                'color' => ['rgb' => 'FFFFFF'],
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '0F172A'],
            ],
            'borders' => [
                'bottom' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => 'CBD5E1'],
                ],
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_LEFT,
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
        ]);
    }

    private function formatOfficeFlags(array $flags): string
    {
        $labels = [
            'office_apps' => 'Apps',
            'office_business' => 'Business',
            'powerbi_pro' => 'Power BI Pro',
            'powerbi_premium' => 'Power BI Premium',
            'visio_plan' => 'Visio Plan',
        ];

        $enabled = [];
        foreach ($labels as $key => $label) {
            if (! empty($flags[$key])) {
                $enabled[] = $label;
            }
        }

        return implode(' | ', $enabled);
    }
}
