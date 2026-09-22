<?php

namespace App\Http\Controllers;

use App\Services\ServiceDeskAdDirectoryService;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\View\View;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ServiceDeskAdController extends Controller
{
    public function index(Request $request, ServiceDeskAdDirectoryService $directory): View
    {
        $allUsers = $directory->users();
        $category = (string) $request->query('category', '');
        $industry = (string) $request->query('industry', '');
        $status = (string) $request->query('status', 'active');
        if (! in_array($status, ['active', 'inactive', 'all'], true)) {
            $status = 'active';
        }
        $search = trim((string) $request->query('q', ''));
        $units = $this->normalizeUnitFilters($request->query('units', []));

        // The dashboard remains global; category is reserved for the detailed query.
        $dashboardUsers = $this->filterUsers($allUsers, '', $industry, $status, $search, $units);
        $filtered = $this->filterUsers($allUsers, $category, $industry, $status, $search, $units);
        $summary = $directory->summary($dashboardUsers);
        $breakdown = $directory->breakdown($dashboardUsers);
        $industryBreakdown = $directory->industryBreakdown($dashboardUsers);
        $groupsBreakdown = $directory->groupsBreakdown($dashboardUsers);
        $dataQuality = $directory->dataQuality($dashboardUsers);
        $latestSync = $allUsers->pluck('last_sync')->filter()->sortDesc()->first();

        $perPage = min(max((int) $request->query('per_page', 25), 10), 100);
        $page = max((int) $request->query('page', 1), 1);
        $users = new LengthAwarePaginator(
            $filtered->forPage($page, $perPage)->values(),
            $filtered->count(),
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()]
        );

        return view('service-desk.ad.index', [
            'users' => $users,
            'summary' => $summary,
            'breakdown' => $breakdown,
            'industryBreakdown' => $industryBreakdown,
            'groupsBreakdown' => $groupsBreakdown,
            'dataQuality' => $dataQuality,
            'latestSync' => $latestSync,
            'categories' => ServiceDeskAdDirectoryService::CATEGORIES,
            'industries' => ServiceDeskAdDirectoryService::INDUSTRIES,
            'category' => $category,
            'industry' => $industry,
            'status' => $status,
            'search' => $search,
            'units' => $units,
            'availableUnits' => $this->availableUnits($allUsers),
        ]);
    }

    public function export(Request $request, ServiceDeskAdDirectoryService $directory): StreamedResponse
    {
        $allUsers = $directory->users();
        $category = (string) $request->query('category', '');
        $industry = (string) $request->query('industry', '');
        $status = (string) $request->query('status', 'active');
        if (! in_array($status, ['active', 'inactive', 'all'], true)) {
            $status = 'active';
        }
        $search = trim((string) $request->query('q', ''));
        $units = $this->normalizeUnitFilters($request->query('units', []));
        $users = $this->filterUsers($allUsers, $category, $industry, $status, $search, $units);
        $summary = $directory->summary($users);
        $breakdown = $directory->breakdown($users);
        $spreadsheet = new Spreadsheet();

        $this->writeSummarySheet($spreadsheet, $summary, $category, $industry, $status, $search, $units);
        $this->writeUsersSheet($spreadsheet, $users);
        $this->writeBreakdownSheet($spreadsheet, 'Sede', $breakdown['sede']);
        $this->writeBreakdownSheet($spreadsheet, 'Unidades PR', $breakdown['pr']);
        $this->writeBreakdownSheet($spreadsheet, 'Unidades GO', $breakdown['go']);
        $this->writeBreakdownSheet($spreadsheet, 'Outras unidades', $breakdown['outras']);
        $this->writeIndustrySheet($spreadsheet, $users);

        $filename = 'servicedesk_ad_' . now()->format('Ymd_His') . '.xlsx';
        return response()->streamDownload(function () use ($spreadsheet): void {
            (new Xlsx($spreadsheet))->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    private function filterUsers($users, string $category, string $industry, string $status, string $search, array $units = [])
    {
        return $users
            ->when($category !== '' && array_key_exists($category, ServiceDeskAdDirectoryService::CATEGORIES), fn ($items) => $items->where('ad_category', $category))
            ->when($industry !== '' && array_key_exists($industry, ServiceDeskAdDirectoryService::INDUSTRIES), fn ($items) => $items->where('ad_industry', $industry))
            ->when($units !== [], fn ($items) => $items->filter(fn ($user): bool => in_array(trim((string) $user->unidade_setor), $units, true)))
            ->when(in_array($status, ['active', 'inactive'], true), fn ($items) => $items->where('e_ativo', $status === 'active'))
            ->when($search !== '', function ($items) use ($search) {
                $needle = mb_strtolower($search);
                return $items->filter(fn ($user): bool => str_contains(mb_strtolower((string) $user->nome_completo), $needle)
                    || str_contains(mb_strtolower((string) $user->email), $needle)
                    || str_contains(mb_strtolower((string) $user->nome_usuario), $needle)
                    || str_contains(mb_strtolower((string) $user->unidade_setor), $needle));
            })->values();
    }

    private function normalizeUnitFilters(mixed $value): array
    {
        if (! is_array($value)) {
            $value = [$value];
        }

        return collect($value)
            ->map(fn ($unit): string => trim((string) $unit))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    private function availableUnits($users): array
    {
        return $users
            ->map(fn ($user): string => trim((string) $user->unidade_setor))
            ->filter()
            ->unique()
            ->sort(fn (string $a, string $b): int => strnatcasecmp($a, $b))
            ->values()
            ->all();
    }

    private function writeSummarySheet(Spreadsheet $spreadsheet, array $summary, string $category, string $industry, string $status, string $search, array $units = []): void
    {
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Resumo');
        $sheet->fromArray([
            ['Relatório Active Directory - ServiceDesk'],
            ['Gerado em', now()->format('d/m/Y H:i:s')],
            ['Filtros aplicados', trim(implode(' | ', array_filter([
                $category !== '' ? 'Região: ' . (ServiceDeskAdDirectoryService::CATEGORIES[$category] ?? $category) : null,
                $industry !== '' ? 'Indústria: ' . (ServiceDeskAdDirectoryService::INDUSTRIES[$industry] ?? $industry) : null,
                $units !== [] ? 'Unidades: ' . count($units) . ' selecionada(s)' : null,
                $status !== '' ? 'Status: ' . $status : null,
                $search !== '' ? 'Busca: ' . $search : null,
            ]))) ?: 'Nenhum'],
            [],
            ['Indicador', 'Quantidade'],
            ['Total de usuários', $summary['total']],
            ['Ativos', $summary['ativos']],
            ['Inativos', $summary['inativos']],
            ['Sede', $summary['sede']],
            ['Unidades PR', $summary['pr']],
            ['Unidades GO', $summary['go']],
            ['Outras unidades', $summary['outras']],
            ['Sem unidade definida', $summary['sem_unidade']],
            ['Indústrias', $summary['industrias']],
        ], null, 'A1');
        $this->styleSheet($sheet, 'A5:B5');
        $sheet->getColumnDimension('A')->setWidth(28);
        $sheet->getColumnDimension('B')->setWidth(18);
    }

    private function writeUsersSheet(Spreadsheet $spreadsheet, $users): void
    {
        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle('Usuários AD');
        $sheet->fromArray([['ID', 'Nome', 'E-mail', 'Login', 'Unidade / setor', 'Região', 'Indústria', 'Status', 'Grupos AD', 'Última sincronização']], null, 'A1');
        $rows = $users->map(fn ($user): array => [
            $user->id,
            $user->nome_completo ?: $user->nome_exibicao,
            $user->email,
            $user->nome_usuario,
            $user->unidade_setor,
            $user->ad_category_label,
            $user->ad_industry_label ?: '',
            $user->e_ativo ? 'Ativo' : 'Inativo',
            implode(' | ', $user->ad_groups_list),
            $user->last_sync?->format('d/m/Y H:i:s') ?: '',
        ])->all();
        if ($rows !== []) {
            $sheet->fromArray($rows, null, 'A2');
        }
        $this->styleSheet($sheet, 'A1:J1');
        foreach (range('A', 'J') as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }
    }

    private function writeBreakdownSheet(Spreadsheet $spreadsheet, string $title, array $rows): void
    {
        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle(mb_substr($title, 0, 31));
        $sheet->fromArray([['Unidade / departamento', 'Quantidade', 'Percentual relativo']], null, 'A1');
        $values = array_map(fn (array $row): array => [$row['label'], $row['count'], $row['percent'] . '%'], $rows);
        if ($values !== []) {
            $sheet->fromArray($values, null, 'A2');
        }
        $this->styleSheet($sheet, 'A1:C1');
        $sheet->getColumnDimension('A')->setWidth(42);
        $sheet->getColumnDimension('B')->setWidth(16);
        $sheet->getColumnDimension('C')->setWidth(22);
    }

    private function writeIndustrySheet(Spreadsheet $spreadsheet, $users): void
    {
        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle('Indústrias');
        $sheet->fromArray([['Indústria', 'Quantidade']], null, 'A1');
        $rows = $users->filter(fn ($user): bool => filled($user->ad_industry_label))
            ->groupBy('ad_industry_label')->map(fn ($items, $label): array => [$label, $items->count()])->sortBy(fn (array $row): string => $row[0])->values()->all();
        if ($rows !== []) {
            $sheet->fromArray($rows, null, 'A2');
        }
        $this->styleSheet($sheet, 'A1:B1');
        $sheet->getColumnDimension('A')->setWidth(24);
        $sheet->getColumnDimension('B')->setWidth(16);
    }

    private function styleSheet($sheet, string $headerRange): void
    {
        $sheet->getStyle($headerRange)->getFont()->setBold(true)->getColor()->setARGB('FFFFFFFF');
        $sheet->getStyle($headerRange)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('0F172A');
        $sheet->getStyle($headerRange)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->freezePane('A2');
        $sheet->setAutoFilter($headerRange);
    }
}
