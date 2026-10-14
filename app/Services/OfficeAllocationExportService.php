<?php

namespace App\Services;

use App\Models\OfficeAllocationBatch;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class OfficeAllocationExportService
{
    public function __construct(private readonly OfficeAllocationService $allocationService)
    {
    }

    public function export(OfficeAllocationBatch $batch): StreamedResponse
    {
        $spreadsheet = new Spreadsheet();
        $this->fillLegacyRateioSheet($spreadsheet, $batch);
        $this->fillUnitSummarySheet($spreadsheet, $batch);
        $this->fillDepartmentSummarySheet($spreadsheet, $batch);
        $this->fillAssignmentSheet($spreadsheet, $batch);

        $filename = 'rateio_office_' . now()->format('Ymd_His') . '.xlsx';
        $writer = new Xlsx($spreadsheet);

        return response()->streamDownload(function () use ($writer): void {
            $writer->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    private function fillLegacyRateioSheet(Spreadsheet $spreadsheet, OfficeAllocationBatch $batch): void
    {
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Rateio Office');
        $headers = ['Unidade', 'Unicoop', 'Área', 'Quantidade de Licenças', 'Custo Total (R$)'];
        $sheet->fromArray($headers, null, 'A1');
        $this->styleHeader($sheet, 'A1:E1');

        $rowNum = 2;
        foreach ($this->legacyRows($batch) as $row) {
            $sheet->setCellValue("A{$rowNum}", $row['unidade']);
            $sheet->setCellValueExplicit("B{$rowNum}", $row['unicoop'], DataType::TYPE_STRING);
            $sheet->setCellValueExplicit("C{$rowNum}", $row['area'], DataType::TYPE_STRING);
            $sheet->setCellValue("D{$rowNum}", $row['quantidade']);
            $sheet->setCellValue("E{$rowNum}", $row['custo_total']);
            $rowNum++;
        }

        $this->moneyColumns($sheet, ['E'], $rowNum);
        $this->autoSize($sheet, 'A', 'E');
    }

    private function fillAssignmentSheet(Spreadsheet $spreadsheet, OfficeAllocationBatch $batch): void
    {
        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle('Colaboradores por Licença');
        $headers = [
            'Matrícula', 'Colaborador', 'E-mail', 'SKU', 'Licença',
            'Unidade', 'Departamento', 'Centro de custo', 'Nome centro de custo',
            'Status classificação', 'Mensagem', 'Preço unitário', 'Total',
        ];
        $sheet->fromArray($headers, null, 'A1');
        $this->styleHeader($sheet, 'A1:M1');

        $rowNum = 2;
        foreach ($batch->rows()
            ->with('officeLicense.organizationDepartment', 'officeLicense.organizationCostCenter')
            ->orderBy('user_name')
            ->orderBy('sku_name')
            ->get() as $row) {
            // Nome pelo catálogo atual (chave = código); mantém o do snapshot
            // só quando o código não existe/é ambíguo no catálogo novo.
            $nomeCatalogo = $this->allocationService->nomeCatalogoPorCodigo($row->cost_center_code_snapshot);
            $sheet->fromArray([
                $row->employee_number,
                $row->user_name,
                $row->user_email,
                $row->sku_id,
                $row->sku_name,
                $nomeCatalogo ?: $row->unit_name_snapshot,
                $nomeCatalogo ?: $this->allocationService->departmentNameForAllocationRow($row),
                $row->cost_center_code_snapshot,
                $nomeCatalogo ?: $row->cost_center_name_snapshot,
                $row->classification_status,
                $row->classification_message,
                (float) $row->unit_price,
                (float) $row->total_amount,
            ], null, "A{$rowNum}");
            $rowNum++;
        }

        $this->moneyColumns($sheet, ['L', 'M'], $rowNum);
        $this->autoSize($sheet, 'A', 'M');
    }

    private function fillUnitSummarySheet(Spreadsheet $spreadsheet, OfficeAllocationBatch $batch): void
    {
        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle('Resumo por Unidade');
        $headers = ['Unidade', 'Usuários', 'Office Apps', 'Office Business', 'Power BI', 'Visio', 'Outras', 'Total Licenças', 'Custo Mensal (R$)'];
        $sheet->fromArray($headers, null, 'A1');
        $this->styleHeader($sheet, 'A1:I1');

        $rowNum = 2;
        foreach ($this->allocationService->unitSummary($batch) as $row) {
            $total = (int) $row->apps_count
                + (int) $row->business_count
                + (int) $row->powerbi_count
                + (int) $row->visio_count
                + (int) $row->other_count;

            $sheet->fromArray([
                $row->unit_name ?: 'Pendente de mapeamento',
                (int) $row->users_count,
                (int) $row->apps_count,
                (int) $row->business_count,
                (int) $row->powerbi_count,
                (int) $row->visio_count,
                (int) $row->other_count,
                $total,
                (float) $row->total_amount,
            ], null, "A{$rowNum}");
            $rowNum++;
        }

        $this->moneyColumns($sheet, ['I'], $rowNum);
        $this->autoSize($sheet, 'A', 'I');
    }

    private function fillDepartmentSummarySheet(Spreadsheet $spreadsheet, OfficeAllocationBatch $batch): void
    {
        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle('Resumo por Departamento');
        $headers = ['Departamento', 'Centro de custo', 'Usuários', 'Office Apps', 'Office Business', 'Power BI', 'Visio', 'Outras', 'Total Licenças', 'Custo Mensal (R$)'];
        $sheet->fromArray($headers, null, 'A1');
        $this->styleHeader($sheet, 'A1:J1');

        $rowNum = 2;
        foreach ($this->allocationService->departmentSummary($batch) as $row) {
            $total = (int) $row->apps_count
                + (int) $row->business_count
                + (int) $row->powerbi_count
                + (int) $row->visio_count
                + (int) $row->other_count;

            $sheet->fromArray([
                $row->department_name,
                $row->cost_center ?: '-',
                (int) $row->users_count,
                (int) $row->apps_count,
                (int) $row->business_count,
                (int) $row->powerbi_count,
                (int) $row->visio_count,
                (int) $row->other_count,
                $total,
                (float) $row->total_amount,
            ], null, "A{$rowNum}");
            $rowNum++;
        }

        $this->moneyColumns($sheet, ['J'], $rowNum);
        $this->autoSize($sheet, 'A', 'J');
    }

    private function styleHeader($sheet, string $range): void
    {
        $sheet->getStyle($range)->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '0F172A']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'CBD5E1']]],
        ]);
        $sheet->freezePane('A2');
    }

    private function moneyColumns($sheet, array $columns, int $lastRow): void
    {
        foreach ($columns as $column) {
            $sheet->getStyle($column . '2:' . $column . max(2, $lastRow - 1))
                ->getNumberFormat()
                ->setFormatCode('"R$" #,##0.00');
        }
    }

    private function autoSize($sheet, string $from, string $to): void
    {
        for ($column = $from; $column <= $to; $column++) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }
    }

    public function legacyRows(OfficeAllocationBatch $batch)
    {
        return $this->allocationService->consolidado($batch);
    }
}
