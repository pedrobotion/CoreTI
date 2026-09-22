<?php

namespace App\Http\Controllers;

use App\Models\JiraProject;
use App\Models\CoretiRateioLocal;
use App\Models\CoretiGoogleEmail;
use App\Models\CoretiCostCenter;
use App\Models\OfficeLicense;
use App\Models\OfficeAllocationBatch;
use App\Models\OrganizationCostCenter;
use App\Models\OrganizationDepartment;
use App\Models\OrganizationUnit;
use App\Models\ServiceDeskEmail;
use App\Services\CoretiRateioLocalService;
use App\Services\OfficeLicenseImportService;
use App\Services\OfficeUserSyncService;
use App\Services\GoogleWorkspaceDryRunSyncService;
use App\Support\OfficeLicenseReportPolicy;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class LicensingController extends Controller
{
    public function email(Request $request, GoogleWorkspaceDryRunSyncService $workspaceSync): View|StreamedResponse
    {
        $cost = (float) $request->query('valor_fatura', 0);
        $availability = $this->resolveWorkspaceEmailAvailability($workspaceSync);
        $extra = (int) $availability['available'];

        $costCenterNames = CoretiCostCenter::query()
            ->active()
            ->get(['code', 'name'])
            ->keyBy(fn (CoretiCostCenter $center): string => $this->normalizeCostCenterCode($center->code));

        $rows = ServiceDeskEmail::query()
            ->where('ativo', true)
            ->get(['centro_custo', 'unicoop_sede', 'area_sede'])
            ->map(function ($row) use ($costCenterNames): array {
                $unicoop = trim((string) ($row->unicoop_sede ?? ''));
                $area = trim((string) ($row->area_sede ?? ''));
                $code = ($unicoop !== '' && $area !== '')
                    ? $this->normalizeCostCenterCode($unicoop . '.' . $area)
                    : '';
                $catalogName = $costCenterNames->get($code)?->name;

                /*
                 | Sem nome no cadastro, mostra o próprio código em vez de "-".
                 |
                 | Um traço na coluna Unidade junta combinações diferentes sob o
                 | mesmo rótulo na leitura e deixa a linha impossível de ratear:
                 | não dá para lançar custo em "-". O código identifica a linha
                 | e aponta o que falta cadastrar.
                 */
                $semCadastro = ! filled($catalogName);
                $rotulo = match (true) {
                    ! $semCadastro => (string) $catalogName,
                    $code !== '' => $code,
                    default => 'Sem unicoop/área',
                };

                return [
                    'unidade' => $rotulo,
                    'unicoop' => $unicoop !== '' ? $unicoop : '-',
                    'area' => $area !== '' ? $area : '-',
                    'sem_cadastro' => $semCadastro,
                    'codigo' => $code,
                ];
            })
            ->groupBy(fn (array $row) => mb_strtolower($row['unidade']) . '|' . $row['unicoop'] . '|' . $row['area'])
            ->map(function ($group): object {
                $first = $group->first();
                return (object) [
                    'unidade' => $first['unidade'],
                    'unicoop' => $first['unicoop'],
                    'area' => $first['area'],
                    'sem_cadastro' => (bool) $first['sem_cadastro'],
                    'codigo' => $first['codigo'],
                    'total' => $group->count(),
                ];
            })
            ->sortBy('unidade')
            ->values();
        $activeEmails = (int) ServiceDeskEmail::query()->where('ativo', true)->count();
        $inactiveEmails = (int) ServiceDeskEmail::query()->where('ativo', false)->count();
        $totalLicensesConsidered = $activeEmails + max($extra, 0);
        $unitCost = $totalLicensesConsidered > 0 ? $cost / $totalLicensesConsidered : 0.0;
        $extraCostPool = max($extra, 0) * $unitCost;

        if ($request->query('export') === 'csv') {
            return $this->emailRateioSpreadsheet($rows, $unitCost, $activeEmails, $extraCostPool);
        }

        return view('service-desk.licensing.email', [
            'rows' => $rows,
            'totals' => [
                'emails' => (int) ServiceDeskEmail::query()->where('ativo', true)->count(),
                'emails_inactive' => $inactiveEmails,
                'cost' => $cost,
                'extra' => $extra,
                'unit_cost' => $unitCost,
                'extra_cost_pool' => $extraCostPool,
                'workspace_used' => (int) $availability['used'],
                'workspace_total' => $availability['total'] !== null ? (int) $availability['total'] : null,
                'workspace_source' => (string) $availability['source'],
                'workspace_warning' => $availability['warning'],
                // Quanto do rateio está apoiado em código que ninguém cadastrou.
                'linhas_sem_cadastro' => $rows->where('sem_cadastro', true)->count(),
                'emails_sem_cadastro' => (int) $rows->where('sem_cadastro', true)->sum('total'),
            ],
        ]);
    }

    private function resolveWorkspaceEmailAvailability(GoogleWorkspaceDryRunSyncService $workspaceSync): array
    {
        $warning = null;
        // A página é uma consulta de licenciamento e não deve disparar uma
        // chamada ao Google Workspace durante uma requisição web. A prévia é
        // atualizada pelo fluxo de sincronização agendado/manual.
        $preview = $workspaceSync->cachedPreview();
        if (! is_array($preview) || ! isset($preview['summary'])) {
            $warning = 'A prévia do Google Workspace ainda não está disponível. Execute a sincronização para atualizar os totais.';
        }

        $summary = is_array($preview['summary'] ?? null) ? $preview['summary'] : [];
        // Regra de faturamento: usuário suspenso com licença atribuída continua consumindo custo.
        // Por isso usamos o total de contas do Workspace presentes no snapshot (ativas + suspensas).
        $used = (int) ($summary['google_total'] ?? 0);
        if ($used === 0 && ! is_array($preview)) {
            $used = (int) CoretiGoogleEmail::query()->count();
        }
        $suspended = (int) ($summary['google_suspended'] ?? 0);

        $configuredTotal = config('services.google_workspace.email_total_licenses');
        if ($configuredTotal !== null && $configuredTotal !== '') {
            $total = max((int) $configuredTotal, 0);
            return [
                'used' => $used,
                'total' => $total,
                'available' => max($total - $used, 0),
                'source' => 'GOOGLE_WORKSPACE_EMAIL_TOTAL_LICENSES - google_total (ativas + suspensas)',
                'warning' => $warning,
            ];
        }

        return [
            'used' => $used,
            'total' => null,
            'available' => 0,
            'source' => 'Sem total contratado configurado (fallback: 0 disponíveis)',
            'warning' => $warning,
        ];
    }

    public function jira(Request $request): View|StreamedResponse
    {
        $rateioLocalService = app(CoretiRateioLocalService::class);
        $costUsd = (float) $request->query('valor_custo_usd', 0);
        $apiUsdBrl = $this->fetchUsdBrlRate();
        $usdBrl = $apiUsdBrl ?? 5.0;

        $rows = JiraProject::query()
            ->where('excluido', false)
            ->where('status', 'Ativo')
            ->get(['unidade_nome', 'centro_custo', 'projeto_grupo'])
            ->map(function ($row) use ($rateioLocalService): array {
                return $this->resolveRateioBucket([
                    'nome' => (string) ($row->unidade_nome ?? ''),
                    'tipo' => null,
                    'centro_custo' => $row->centro_custo,
                ], $rateioLocalService) + [
                    'projeto_grupo' => trim((string) ($row->projeto_grupo ?? '')) !== '' ? trim((string) $row->projeto_grupo) : '-',
                ];
            })
            ->groupBy(fn (array $row) => $row['unidade'] . '|' . $row['unicoop'] . '|' . $row['area'] . '|' . $row['projeto_grupo'])
            ->map(function ($group): object {
                $first = $group->first();
                return (object) [
                    'unidade' => $first['unidade'],
                    'centro_custo' => $first['centro_custo'],
                    'projeto_grupo' => $first['projeto_grupo'],
                    'total' => $group->count(),
                ];
            })
            ->sortBy('unidade')
            ->values();

        $totalProjetos = (int) JiraProject::query()
            ->where('excluido', false)
            ->where('status', 'Ativo')
            ->count();

        $totalCostBrl = $costUsd * $usdBrl;
        $unitCost = $totalProjetos > 0 ? $totalCostBrl / $totalProjetos : 0.0;

        if ($request->query('export') === 'xlsx') {
            return $this->jiraRateioXlsx($rows, $unitCost);
        }

        return view('service-desk.licensing.jira', [
            'rows' => $rows,
            'totals' => [
                'projetos' => $totalProjetos,
                'cost_usd' => $costUsd,
                'usd_brl' => $usdBrl,
                'usd_brl_api' => $apiUsdBrl,
                'cost_brl' => $totalCostBrl,
                'unit_cost' => $unitCost,
            ],
        ]);
    }

    public function officeLicensing(Request $request): View
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'per_page' => ['nullable', 'integer', 'in:10,25,50'],
            'status' => ['nullable', 'string', 'in:todos,ativo,inativo,sem_unidade,matched,manual_assignment,not_found,ambiguous,invalid_centro_custo'],
            'division' => ['nullable', 'string', 'in:todos,sede,unidades,cerrado,genericos'],
        ]);

        $query = $this->officeLicensesBaseQuery($filters);
        $status = (string) ($filters['status'] ?? 'todos');
        $search = trim((string) ($filters['q'] ?? ''));
        $division = (string) ($filters['division'] ?? 'todos');

        $perPage = (int) ($filters['per_page'] ?? 10);

        $licensesCount = (int) OfficeLicense::query()->where('ativo', true)->count();

        $licenses = $query->orderBy('nome')->paginate($perPage)->withQueryString();
        $costCenterNames = CoretiCostCenter::query()
            ->active()
            ->get(['code', 'name'])
            ->keyBy(fn (CoretiCostCenter $center): string => $this->normalizeCostCenterCode($center->code));
        $licenses->getCollection()->transform(function (OfficeLicense $license) use ($costCenterNames): OfficeLicense {
            $license->catalog_cost_center_name = $costCenterNames->get($this->normalizeCostCenterCode($license->centro_custo))?->name;

            return $license;
        });

        $divisionStats = OfficeLicense::query()
            ->where('ativo', true)
            ->get(['matricula', 'unicoop_office', 'account_classification'])
            ->groupBy(fn (OfficeLicense $license): string => $this->officeDivisionFor($license))
            ->map->count()
            ->all();

        $pendingRateioBatch = OfficeAllocationBatch::query()
            ->where('competence', now()->format('Y-m'))
            ->whereNotIn('status', ['cancelled'])
            ->orderByRaw("CASE WHEN status = 'closed' THEN 0 ELSE 1 END DESC")
            ->orderByDesc('version')
            ->first();
        $pendingRateioRows = $pendingRateioBatch
            ? $pendingRateioBatch->rows()
                ->where('classification_status', '<>', 'classified')
                ->orderBy('user_name')
                ->orderBy('sku_name')
                ->get()
            : collect();

        // Os dois modais usam o mesmo catalogo canonico. As opcoes sao
        // derivadas de centros de custo validos, evitando areas 000 e
        // preservando departamentos com mais de uma alocacao.
        $officeEditOptions = $this->officeOrganizationOptions();
        $officeUnitOptions = $officeEditOptions;

        $officeCostCenterOptions = OrganizationCostCenter::query()
            ->where('source_system', 'intranet')
            ->where('active', true)
            ->whereNotNull('accounting_area_source_id')
            ->orderBy('name')
            ->orderBy('source_id')
            ->get(['id', 'source_id', 'name', 'unit_source_id', 'accounting_area_source_id'])
            ->values();

        return view('service-desk.licensing.office', [
            'licenses' => $licenses,
            'officeUnitOptions' => $officeUnitOptions,
            'officeEditOptions' => $officeEditOptions,
            'officeCostCenterOptions' => $officeCostCenterOptions,
            'search' => $search,
            'perPage' => $perPage,
            'status' => $status,
            'division' => $division,
            'divisionStats' => array_merge(['sede' => 0, 'unidades' => 0, 'cerrado' => 0, 'genericos' => 0], $divisionStats),
            'pendingRateioBatch' => $pendingRateioBatch,
            'pendingRateioRows' => $pendingRateioRows,
            'pendingRateioLabels' => \App\Services\OfficeAllocationService::classificationStatusLabels(),
            'canManage' => Auth::user()?->role === 'admin',
            'stats' => [
                'total' => $licensesCount,
                'office_apps' => (int) OfficeLicense::query()->where('ativo', true)->where('office_apps', true)->count(),
                'office_business' => (int) OfficeLicense::query()->where('ativo', true)->where('office_business', true)->count(),
                'powerbi' => (int) OfficeLicense::query()->where('ativo', true)->where(function ($q): void {
                    $q->where('powerbi_pro', true)->orWhere('powerbi_premium', true);
                })->count(),
                'visio' => (int) OfficeLicense::query()->where('ativo', true)->where('visio_plan', true)->count(),
            ],
        ]);
    }

    private function officeOrganizationOptions()
    {
        $normalizer = static function ($value): ?string {
            $digits = preg_replace('/\D+/', '', trim((string) $value)) ?: '';
            $digits = ltrim($digits, '0');

            return $digits !== '' ? $digits : null;
        };
        $formatUnit = static fn ($value): ?string => ($digits = $normalizer($value)) !== null
            ? str_pad($digits, 2, '0', STR_PAD_LEFT)
            : null;
        $formatArea = static fn ($value): ?string => ($digits = $normalizer($value)) !== null
            ? str_pad($digits, 3, '0', STR_PAD_LEFT)
            : null;

        $units = OrganizationUnit::query()
            ->where('source_system', 'intranet')
            ->where('active', true)
            ->get(['id', 'source_id', 'name']);
        $departments = OrganizationDepartment::query()
            ->where('source_system', 'intranet')
            ->where('active', true)
            ->get(['id', 'source_id', 'source_unit_id', 'source_area_id', 'name']);
        $costCenters = OrganizationCostCenter::query()
            ->where('source_system', 'intranet')
            ->where('active', true)
            ->whereNotNull('accounting_area_source_id')
            ->get(['id', 'source_id', 'name', 'unit_source_id', 'accounting_area_source_id']);
        $coretiNames = CoretiCostCenter::query()
            ->active()
            ->get(['code', 'name'])
            ->keyBy(fn (CoretiCostCenter $center): string => $this->normalizeCostCenterCode($center->code));

        $options = collect();
        foreach ($costCenters as $costCenter) {
            $unicoop = $formatUnit($costCenter->unit_source_id);
            $area = $formatArea($costCenter->accounting_area_source_id);
            if ($unicoop === null || $area === null || $area === '000') {
                continue;
            }

            $unit = $units->first(fn ($item) => $normalizer($item->source_id) === $normalizer($costCenter->unit_source_id));
            $department = $departments->first(function ($item) use ($normalizer, $costCenter): bool {
                return $normalizer($item->source_unit_id) === $normalizer($costCenter->unit_source_id)
                    && $normalizer($item->source_area_id) === $normalizer($costCenter->accounting_area_source_id);
            });
            $code = $unicoop . '.' . $area;
            $name = trim((string) ($coretiNames->get($code)?->name ?: $department?->name ?: $unit?->name ?: $costCenter->name));
            if ($name === '') {
                continue;
            }

            $options->push((object) [
                'id' => $department?->id ?: $unit?->id,
                'name' => $name,
                'display_name' => $name . ' - ' . $unicoop . '.' . $area,
                'unicoop' => $unicoop,
                'area' => $area,
                'source' => $department ? 'departamento' : 'unidade',
                'organization_unit_id' => $unit?->id,
                'organization_department_id' => $department?->id,
                'organization_cost_center_id' => $costCenter->id,
                'office_key' => mb_strtolower($name) . '|' . $unicoop . '|' . $area,
                'sort_key' => mb_strtolower($name) . '|' . $unicoop . '|' . $area,
            ]);
        }

        foreach ($departments as $department) {
            $name = trim((string) $department->name);
            if ($name === '') {
                continue;
            }

            $unicoop = $formatUnit($department->source_unit_id);
            $area = $formatArea($department->source_area_id);
            $options->push((object) [
                'id' => $department->id,
                'name' => $name,
                'display_name' => $name . ' (Departamento)',
                'unicoop' => $unicoop,
                'area' => $area !== '000' ? $area : null,
                'source' => 'departamento',
                'organization_unit_id' => null,
                'organization_department_id' => $department->id,
                'organization_cost_center_id' => null,
                'office_key' => mb_strtolower($name) . '|' . ($unicoop ?? '') . '|' . ($area ?? ''),
                'sort_key' => mb_strtolower($name) . '|zz',
            ]);
        }

        return $options
            ->unique(fn ($item) => $item->name . '|' . $item->unicoop . '|' . $item->area . '|' . $item->organization_cost_center_id)
            ->sortBy('sort_key')
            ->values();
    }

    private function normalizeCostCenterCode(?string $code): string
    {
        if (! preg_match('/^\s*(\d{1,2})\s*[.\/-]\s*(\d{1,3})\s*$/', (string) $code, $matches)) {
            return '';
        }

        return str_pad((string) ((int) $matches[1]), 2, '0', STR_PAD_LEFT)
            . '.'
            . str_pad((string) ((int) $matches[2]), 3, '0', STR_PAD_LEFT);
    }

    public function exportOfficeLicenses(Request $request): StreamedResponse
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'string', 'in:todos,ativo,inativo,sem_unidade,matched,manual_assignment,not_found,ambiguous,invalid_centro_custo'],
            'division' => ['nullable', 'string', 'in:todos,sede,unidades,cerrado,genericos'],
        ]);

        $licenses = $this->officeLicensesBaseQuery($filters)
            ->with('assignments')
            ->orderBy('nome')
            ->get([
                'id',
                'matricula',
                'nome',
                'email',
                'departamento_unidade',
                'unicoop_office',
                'area_office',
                'cargo_nome',
                'office_apps',
                'office_business',
                'powerbi_pro',
                'powerbi_premium',
                'visio_plan',
                'account_enabled',
                'ativo',
                'microsoft_last_sign_in_at',
                'microsoft_last_non_interactive_sign_in_at',
            ])
            ->flatMap(function ($license): array {
                $allAssignments = $license->assignments;
                $assignments = $allAssignments->where('included_in_report', true);
                if ($assignments->isEmpty() && $allAssignments->isNotEmpty()) {
                    return [];
                }
                if ($assignments->isEmpty()) {
                    // Compatibilidade para registros antigos até a primeira
                    // sincronização após a nova tabela normalizada.
                    $assignments = collect([null]);
                }

                return $assignments->map(function ($assignment) use ($license): array {
                    $lastActivity = collect([
                        $license->microsoft_last_sign_in_at,
                        $license->microsoft_last_non_interactive_sign_in_at,
                    ])->filter()
                        ->map(fn ($value) => \Carbon\Carbon::parse($value))
                        ->sortDesc()
                        ->first();

                    return [
                    'matricula' => (string) ($license->matricula ?? ''),
                    'nome' => (string) ($license->nome ?? ''),
                    'cargo' => $this->lookupOfficeCargoForExport($license),
                    'email' => (string) ($license->email ?? ''),
                    'departamento_unidade' => (string) ($license->departamento_unidade ?? ''),
                    'unicoop_office' => (string) ($license->unicoop_office ?? ''),
                    'area_office' => (string) ($license->area_office ?? ''),
                    'sku_id' => (string) ($assignment?->sku_id ?? ''),
                    'sku_part_number' => (string) ($assignment?->sku_part_number ?? ''),
                    'sku_name' => OfficeLicenseReportPolicy::displayName(
                        $assignment?->sku_part_number,
                        $assignment?->sku_name
                    ),
                    'license_category' => (string) ($assignment?->license_category ?? ''),
                    'account_enabled' => ($assignment?->account_enabled ?? $license->account_enabled) ? 'Sim' : 'Não',
                    'ativo' => $license->ativo ? 'Ativo' : 'Inativo',
                    'ultima_atividade_microsoft_365' => $lastActivity?->format('d/m/Y H:i') ?? 'Não disponível',
                    ];
                })->all();
            })
            ->all();

        $filename = 'office_licencas_' . now()->format('Ymd_His') . '.csv';

        return response()->streamDownload(function () use ($licenses): void {
            $out = fopen('php://output', 'w');
            fprintf($out, "\xEF\xBB\xBF");

            fputcsv($out, [
                'Matrícula',
                'Nome',
                'Cargo',
                'E-mail',
                'Unidade/Departamento',
                'Unicoop',
                'Área',
                'SKU ID',
                'SKU/Produto',
                'Nome da licença',
                'Categoria',
                'Conta habilitada',
                'Status',
                'Último acesso Microsoft 365',
            ], ';');

            foreach ($licenses as $license) {
                fputcsv($out, [
                    $license['matricula'],
                    $license['nome'],
                    $license['cargo'],
                    $license['email'],
                    $license['departamento_unidade'],
                    $license['unicoop_office'],
                    $license['area_office'],
                    $license['sku_id'],
                    $license['sku_part_number'],
                    $license['sku_name'],
                    $license['license_category'],
                    $license['account_enabled'],
                    $license['ativo'],
                    $license['ultima_atividade_microsoft_365'],
                ], ';');
            }

            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function officeRateio(Request $request): View|StreamedResponse
    {
        $filters = $request->validate([
            'custo_office_apps' => ['nullable', 'string', 'max:30'],
            'custo_office_business' => ['nullable', 'string', 'max:30'],
            'custo_powerbi_pro' => ['nullable', 'string', 'max:30'],
            'custo_visio_plan' => ['nullable', 'string', 'max:30'],
            'export' => ['nullable', 'string'],
        ]);

        $rawCosts = [
            'office_apps' => trim((string) ($filters['custo_office_apps'] ?? '')),
            'office_business' => trim((string) ($filters['custo_office_business'] ?? '')),
            'powerbi_pro' => trim((string) ($filters['custo_powerbi_pro'] ?? '')),
            'visio_plan' => trim((string) ($filters['custo_visio_plan'] ?? '')),
        ];

        $costs = [
            'office_apps' => $this->parseMoneyBr($rawCosts['office_apps']),
            'office_business' => $this->parseMoneyBr($rawCosts['office_business']),
            'powerbi_pro' => $this->parseMoneyBr($rawCosts['powerbi_pro']),
            'visio_plan' => $this->parseMoneyBr($rawCosts['visio_plan']),
        ];

        $licensesCount = (int) OfficeLicense::query()
            ->where('ativo', true)
            ->where(function ($query): void {
                $query->where('office_apps', true)
                    ->orWhere('office_business', true)
                    ->orWhere('powerbi_pro', true)
                    ->orWhere('visio_plan', true);
            })
            ->count();

        $grouped = OfficeLicense::query()
            ->where('ativo', true)
            ->get(['departamento_unidade', 'unicoop_office', 'area_office', 'office_apps', 'office_business', 'powerbi_pro', 'visio_plan'])
            ->map(function ($row): array {
                $unidade = trim((string) ($row->departamento_unidade ?? ''));
                $unicoop = trim((string) ($row->unicoop_office ?? ''));
                $area = trim((string) ($row->area_office ?? ''));

                return [
                    'unidade' => $unidade !== '' ? $unidade : 'Pendente de mapeamento',
                    'unicoop' => $unicoop !== '' ? $unicoop : '-',
                    'area' => $area !== '' ? $area : '-',
                    'total_office_apps' => (int) ($row->office_apps ? 1 : 0),
                    'total_office_business' => (int) ($row->office_business ? 1 : 0),
                    'total_powerbi_pro' => (int) ($row->powerbi_pro ? 1 : 0),
                    'total_visio_plan' => (int) ($row->visio_plan ? 1 : 0),
                ];
            })
            ->groupBy(fn (array $row) => $row['unidade'] . '|' . $row['unicoop'] . '|' . $row['area'])
            ->map(function ($group): object {
                $first = $group->first();
                return (object) [
                    'unidade' => $first['unidade'],
                    'unicoop' => $first['unicoop'],
                    'area' => $first['area'],
                    'total_office_apps' => $group->sum('total_office_apps'),
                    'total_office_business' => $group->sum('total_office_business'),
                    'total_powerbi_pro' => $group->sum('total_powerbi_pro'),
                    'total_visio_plan' => $group->sum('total_visio_plan'),
                ];
            })
            ->sortBy(fn ($row) => mb_strtolower(trim((string) $row->unidade)) . '|' . trim((string) $row->unicoop) . '|' . trim((string) $row->area))
            ->values();

        $grouped = $grouped->map(function ($row) use ($costs) {
            $row->total_licencas = (int) $row->total_office_apps
                + (int) $row->total_office_business
                + (int) $row->total_powerbi_pro
                + (int) $row->total_visio_plan;

            $row->custo_total = ((int) $row->total_office_apps * $costs['office_apps'])
                + ((int) $row->total_office_business * $costs['office_business'])
                + ((int) $row->total_powerbi_pro * $costs['powerbi_pro'])
                + ((int) $row->total_visio_plan * $costs['visio_plan']);

            return $row;
        });

        if (($filters['export'] ?? '') === 'xlsx') {
            return $this->officeRateioXlsx($grouped);
        }

        return view('service-desk.licensing.office-rateio', [
            'costs' => $rawCosts,
            'grouped' => $grouped,
            'stats' => [
                'total' => $licensesCount,
                'total_office_apps' => (int) $grouped->sum('total_office_apps'),
                'total_office_business' => (int) $grouped->sum('total_office_business'),
                'total_powerbi_pro' => (int) $grouped->sum('total_powerbi_pro'),
                'total_visio_plan' => (int) $grouped->sum('total_visio_plan'),
                'total_cost' => $grouped->sum('custo_total'),
            ],
        ]);
    }

    public function storeOffice(Request $request)
    {
        $data = $request->validate([
            'matricula' => ['required', 'string', 'max:50'],
            'nome' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'departamento_unidade' => ['required', 'string', 'max:255'],
            'unicoop_office' => ['nullable', 'string', 'max:255'],
            'area_office' => ['nullable', 'string', 'max:255'],
            'organization_unit_id' => ['nullable', 'integer', 'exists:organization_units,id'],
            'organization_department_id' => ['nullable', 'integer', 'exists:organization_departments,id'],
            'organization_cost_center_id' => ['nullable', 'integer', 'exists:organization_cost_centers,id'],
            'office_apps' => ['nullable', 'boolean'],
            'office_business' => ['nullable', 'boolean'],
            'powerbi_pro' => ['nullable', 'boolean'],
            'powerbi_premium' => ['nullable', 'boolean'],
            'visio_plan' => ['nullable', 'boolean'],
        ]);

        [$resolvedUnicoop, $resolvedArea] = $this->resolveOfficeOrgMeta(
            $data['departamento_unidade'],
            $data['unicoop_office'] ?? null,
            $data['area_office'] ?? null
        );
        $manualOrganization = $this->resolveManualOfficeOrganization($data, $resolvedUnicoop, $resolvedArea);

        OfficeLicense::updateOrCreate(
            ['email' => mb_strtolower(trim($data['email']))],
            [
                'matricula' => trim((string) $data['matricula']),
                'nome' => $data['nome'],
                'email' => mb_strtolower(trim($data['email'])),
                'departamento_unidade' => $data['departamento_unidade'],
                'unicoop_office' => $manualOrganization['unicoop'],
                'area_office' => $manualOrganization['area'],
                'unidade_nome' => $manualOrganization['unit_name'],
                'organization_unit_id' => $manualOrganization['unit_id'],
                'organization_department_id' => $manualOrganization['department_id'],
                'organization_cost_center_id' => $manualOrganization['cost_center_id'],
                'centro_custo' => $manualOrganization['code'],
                'centro_custo_nome' => $manualOrganization['cost_center_name'],
                'organization_assignment_source' => 'manual',
                'organization_unit_source' => 'manual',
                'organization_department_source' => 'manual',
                'organization_cost_center_source' => 'manual',
                'organization_position_source' => 'manual',
                'organization_assignment_locked_at' => now(),
                'organization_assignment_locked_by' => Auth::id(),
                'organization_assignment_updated_at' => now(),
                'organization_assignment_updated_by' => Auth::id(),
                'colaborador_match_status' => 'manual_assignment',
                'colaborador_match_message' => 'Organização definida manualmente pelo usuário.',
                'office_apps' => (bool) ($data['office_apps'] ?? false),
                'office_business' => (bool) ($data['office_business'] ?? false),
                'powerbi_pro' => (bool) ($data['powerbi_pro'] ?? false),
                'powerbi_premium' => (bool) ($data['powerbi_premium'] ?? false),
                'visio_plan' => (bool) ($data['visio_plan'] ?? false),
                'ativo' => true,
            ]
        );

        $this->syncOfficeOrgForDepartment($data['departamento_unidade'], $resolvedUnicoop, $resolvedArea);
        $this->refreshCurrentOfficeAllocation();

        return back()->with('success', 'Licença Office salva com sucesso.');
    }

    public function updateOffice(Request $request, OfficeLicense $officeLicense)
    {
        $data = $request->validate([
            'matricula' => ['nullable', 'string', 'max:50'],
            'nome' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:office_licenses,email,' . $officeLicense->id],
            'departamento_unidade' => ['required', 'string', 'max:255'],
            'unicoop_office' => ['nullable', 'string', 'max:255'],
            'area_office' => ['nullable', 'string', 'max:255'],
            'organization_unit_id' => ['nullable', 'integer', 'exists:organization_units,id'],
            'organization_department_id' => ['nullable', 'integer', 'exists:organization_departments,id'],
            'organization_cost_center_id' => ['nullable', 'integer', 'exists:organization_cost_centers,id'],
            'office_apps' => ['nullable', 'boolean'],
            'office_business' => ['nullable', 'boolean'],
            'powerbi_pro' => ['nullable', 'boolean'],
            'powerbi_premium' => ['nullable', 'boolean'],
            'visio_plan' => ['nullable', 'boolean'],
        ]);

        [$resolvedUnicoop, $resolvedArea] = $this->resolveOfficeOrgMeta(
            $data['departamento_unidade'],
            $data['unicoop_office'] ?? null,
            $data['area_office'] ?? null
        );
        $manualOrganization = $this->resolveManualOfficeOrganization($data, $resolvedUnicoop, $resolvedArea);

        $officeLicense->update([
            'matricula' => trim((string) ($data['matricula'] ?? '')) ?: null,
            'nome' => $data['nome'],
            'email' => mb_strtolower(trim($data['email'])),
            'departamento_unidade' => $data['departamento_unidade'],
            'unicoop_office' => $manualOrganization['unicoop'],
            'area_office' => $manualOrganization['area'],
            'unidade_nome' => $manualOrganization['unit_name'],
            'organization_unit_id' => $manualOrganization['unit_id'],
            'organization_department_id' => $manualOrganization['department_id'],
            'organization_cost_center_id' => $manualOrganization['cost_center_id'],
            'centro_custo' => $manualOrganization['code'],
            'centro_custo_nome' => $manualOrganization['cost_center_name'],
            'organization_assignment_source' => 'manual',
            'organization_unit_source' => 'manual',
            'organization_department_source' => 'manual',
            'organization_cost_center_source' => 'manual',
            'organization_position_source' => 'manual',
            'organization_assignment_locked_at' => now(),
            'organization_assignment_locked_by' => Auth::id(),
            'organization_assignment_updated_at' => now(),
            'organization_assignment_updated_by' => Auth::id(),
            'colaborador_match_status' => 'manual_assignment',
            'colaborador_match_message' => 'Organização definida manualmente pelo usuário.',
            'office_apps' => (bool) ($data['office_apps'] ?? false),
            'office_business' => (bool) ($data['office_business'] ?? false),
            'powerbi_pro' => (bool) ($data['powerbi_pro'] ?? false),
            'powerbi_premium' => (bool) ($data['powerbi_premium'] ?? false),
            'visio_plan' => (bool) ($data['visio_plan'] ?? false),
        ]);

        $this->syncOfficeOrgForDepartment($data['departamento_unidade'], $resolvedUnicoop, $resolvedArea);
        $this->refreshCurrentOfficeAllocation();

        return back()->with('success', 'Licença Office atualizada com sucesso.');
    }

    public function updateOfficeOrganization(Request $request, OfficeLicense $officeLicense)
    {
        $data = $request->validate([
            'departamento_unidade' => ['required', 'string', 'max:255'],
            'unicoop_office' => ['nullable', 'string', 'max:255'],
            'area_office' => ['nullable', 'string', 'max:255'],
            'organization_unit_id' => ['nullable', 'integer', 'exists:organization_units,id'],
            'organization_department_id' => ['nullable', 'integer', 'exists:organization_departments,id'],
            'organization_cost_center_id' => ['nullable', 'integer', 'exists:organization_cost_centers,id'],
        ]);

        [$resolvedUnicoop, $resolvedArea] = $this->resolveOfficeOrgMeta(
            $data['departamento_unidade'],
            $data['unicoop_office'] ?? null,
            $data['area_office'] ?? null
        );

        $manualOrganization = $this->resolveManualOfficeOrganization($data, $resolvedUnicoop, $resolvedArea);

        $officeLicense->update([
            'departamento_unidade' => $data['departamento_unidade'],
            'unicoop_office' => $manualOrganization['unicoop'],
            'area_office' => $manualOrganization['area'],
            'unidade_nome' => $manualOrganization['unit_name'],
            'organization_unit_id' => $manualOrganization['unit_id'],
            'organization_department_id' => $manualOrganization['department_id'],
            'organization_cost_center_id' => $manualOrganization['cost_center_id'],
            'centro_custo' => $manualOrganization['code'],
            'centro_custo_nome' => $manualOrganization['cost_center_name'],
            'organization_assignment_source' => 'manual',
            'organization_unit_source' => 'manual',
            'organization_department_source' => 'manual',
            'organization_cost_center_source' => 'manual',
            'organization_assignment_locked_at' => now(),
            'organization_assignment_locked_by' => Auth::id(),
            'organization_assignment_updated_at' => now(),
            'organization_assignment_updated_by' => Auth::id(),
            'colaborador_match_status' => 'manual_assignment',
            'colaborador_match_message' => 'Organização definida manualmente pelo usuário.',
        ]);

        $this->syncOfficeOrgForDepartment($data['departamento_unidade'], $resolvedUnicoop, $resolvedArea);
        $this->refreshCurrentOfficeAllocation();

        return back()->with('success', 'Ajuste organizacional manual salvo. A sincronização Microsoft preservará este ajuste.');
    }

    private function resolveOfficeOrgMeta(string $department, ?string $preferredUnicoop = null, ?string $preferredArea = null): array
    {
        $resolved = app(OfficeLicenseImportService::class)->resolveOfficeOrganizationFromDepartment(
            $department,
            $preferredUnicoop,
            $preferredArea
        );

        return [
            $resolved['unicoop'] ?? null,
            $resolved['area'] ?? null,
        ];
    }

    /** @return array{unicoop:?string,area:?string,unit_id:?int,unit_name:string,cost_center_id:?int,code:?string,cost_center_name:string} */
    private function resolveManualOfficeOrganization(array $data, ?string $unicoop, ?string $area): array
    {
        $department = ! empty($data['organization_department_id'])
            ? OrganizationDepartment::query()->find((int) $data['organization_department_id'])
            : null;
        $unit = ! empty($data['organization_unit_id'])
            ? OrganizationUnit::query()->find((int) $data['organization_unit_id'])
            : null;
        $costCenter = ! empty($data['organization_cost_center_id'])
            ? OrganizationCostCenter::query()->find((int) $data['organization_cost_center_id'])
            : null;

        // The selected catalog records are authoritative for manual changes.
        // Rebuilding them only from the displayed unit/area can select a
        // different cost center when the same name has multiple allocations.
        if ($unit === null && $department?->source_unit_id !== null) {
            $unit = OrganizationUnit::query()
                ->where('source_system', 'intranet')
                ->where('source_id', (string) $department->source_unit_id)
                ->first();
        }

        $unitDigits = preg_replace('/\D+/', '', (string) $unicoop) ?: '';
        $areaDigits = preg_replace('/\D+/', '', (string) $area) ?: '';

        if ($costCenter !== null) {
            $unitDigits = preg_replace('/\D+/', '', (string) ($costCenter->unit_source_id ?: $unitDigits)) ?: $unitDigits;
            $areaDigits = preg_replace('/\D+/', '', (string) ($costCenter->accounting_area_source_id ?: $areaDigits)) ?: $areaDigits;
        } elseif ($department !== null) {
            $unitDigits = preg_replace('/\D+/', '', (string) ($department->source_unit_id ?: $unitDigits)) ?: $unitDigits;
            $areaDigits = preg_replace('/\D+/', '', (string) ($department->source_area_id ?: $areaDigits)) ?: $areaDigits;
        }

        if ($costCenter === null && $unitDigits !== '' && $areaDigits !== '') {
            $costCenter = OrganizationCostCenter::query()
                ->where('source_system', 'intranet')
                ->where('source_id', (string) ((int) $unitDigits) . str_pad((string) ((int) $areaDigits), 3, '0', STR_PAD_LEFT))
                ->whereNotNull('accounting_area_source_id')
                ->orderBy('id')
                ->first();
        }
        if ($unit === null && $unitDigits !== '') {
            $unit = OrganizationUnit::query()
                ->where('source_system', 'intranet')
                ->where('source_id', (string) ((int) $unitDigits))
                ->first();
        }

        $normalizedUnicoop = $unitDigits !== '' ? str_pad((string) ((int) $unitDigits), 2, '0', STR_PAD_LEFT) : null;
        $normalizedArea = $areaDigits !== '' ? str_pad((string) ((int) $areaDigits), 3, '0', STR_PAD_LEFT) : null;

        $costCenterCode = null;
        if ($costCenter !== null) {
            $costCenterSourceId = preg_replace('/\D+/', '', (string) $costCenter->source_id) ?: '';
            $costCenterCode = $normalizedUnicoop !== null && $normalizedArea !== null
                ? $normalizedUnicoop . '.' . $normalizedArea
                : ($costCenterSourceId !== '' ? $costCenterSourceId : null);
        }

        return [
            'unicoop' => $normalizedUnicoop,
            'area' => $normalizedArea,
            'unit_id' => $unit?->id,
            'unit_name' => (string) ($unit?->name ?: ($data['departamento_unidade'] ?? '')),
            'cost_center_id' => $costCenter?->id,
            'code' => $costCenterCode,
            'cost_center_name' => (string) ($costCenter?->name ?: ($data['departamento_unidade'] ?? '')),
            'department_id' => $department?->id,
            'department_name' => (string) ($department?->name ?: ''),
        ];
    }

    private function refreshCurrentOfficeAllocation(): void
    {
        $competence = now()->format('Y-m');
        $batch = OfficeAllocationBatch::query()
            ->where('competence', $competence)
            ->whereNotIn('status', ['closed', 'cancelled'])
            ->exists();

        if ($batch) {
            app(\App\Services\OfficeAllocationService::class)->generate($competence, Auth::id());
        }
    }

    private function syncOfficeOrgForDepartment(string $department, ?string $unicoop, ?string $area): void
    {
        $department = trim($department);
        if ($department === '') {
            return;
        }

        $resolved = app(OfficeLicenseImportService::class)->resolveOfficeOrganizationFromDepartment($department, $unicoop, $area);
        $unicoop = $resolved['unicoop'] ?? $unicoop;
        $area = $resolved['area'] ?? $area;

        OfficeLicense::query()
            ->where('departamento_unidade', $department)
            ->where(function ($query): void {
                $query->whereNull('unicoop_office')
                    ->orWhereRaw('TRIM(COALESCE(unicoop_office, "")) = ""')
                    ->orWhereNull('area_office')
                    ->orWhereRaw('TRIM(COALESCE(area_office, "")) = ""');
            })
            ->update([
                'unicoop_office' => $unicoop,
                'area_office' => $area,
                'updated_at' => now(),
            ]);
    }

    private function isCristalinaDepartment(string $department): bool
    {
        $normalized = mb_strtolower(trim($department));
        $normalized = Str::of($normalized)->ascii()->replaceMatches('/[^a-z0-9]+/', ' ')->squish()->value();

        return $normalized === 'cristalina';
    }

    public function lookupOfficeByMatricula(Request $request)
    {
        $matricula = trim((string) $request->query('matricula', ''));

        if ($matricula === '') {
            return response()->json([
                'found' => false,
                'message' => 'Informe uma matrícula para consulta.',
            ], 422);
        }

        $row = CoretiGoogleEmail::query()
            ->where('matricula', $matricula)
            ->orderByRaw("CASE WHEN mapeamento_status = 'mapeado' THEN 0 ELSE 1 END")
            ->orderByDesc('updated_at')
            ->first();

        if (! $row) {
            return response()->json([
                'found' => false,
                'message' => 'Matrícula não encontrada na base local de e-mails sincronizados.',
            ], 404);
        }

        $localEmail = DB::table('service_desk_emails')
            ->where('matricula', (string) $row->matricula)
            ->orderByDesc('ativo')
            ->orderBy('id')
            ->first();

        $costCenter = DB::table('service_desk_email_cost_centers')
            ->where('name', (string) ($row->nome_local ?? $row->centro_custo_nome ?? ''))
            ->orderByRaw("FIELD(scope, 'sede', 'unidades', 'cerrado')")
            ->first();

        return response()->json([
            'found' => true,
            'collaborator' => [
                'matricula' => $row->matricula,
                'nome' => $row->nome,
                'email' => $row->email ?: ($localEmail->email ?? null),
                'departamento_unidade' => $row->nome_local ?: ($localEmail->centro_custo ?? null),
                'unicoop_office' => $row->unicoop ?? $costCenter->unicoop ?? ($localEmail->unicoop_sede ?? null),
                'area_office' => $row->area ?? $costCenter->area ?? ($localEmail->area_sede ?? null),
            ],
        ]);
    }

    private function officeLicensesBaseQuery(array $filters): Builder
    {
        $status = (string) ($filters['status'] ?? 'todos');
        $search = trim((string) ($filters['q'] ?? ''));
        $division = (string) ($filters['division'] ?? 'todos');

        $query = OfficeLicense::query();

        if ($status === 'ativo') {
            $query->where('ativo', true);
        } elseif ($status === 'inativo') {
            $query->where('ativo', false);
        } elseif ($status === 'sem_unidade') {
            $query->where('ativo', true)
                ->where(function ($q): void {
                    $q->where(function ($q2): void {
                        $q2->whereNull('departamento_unidade')
                            ->orWhereRaw('TRIM(departamento_unidade) = ""')
                            ->orWhere('departamento_unidade', 'N/D')
                            ->orWhereRaw('TRIM(departamento_unidade) IN ("0", "00")');
                    })->orWhere(function ($q2): void {
                        $q2->whereNull('unicoop_office')
                            ->orWhereRaw('TRIM(unicoop_office) = ""')
                            ->orWhereRaw('TRIM(unicoop_office) IN ("0", "00", "N/D")');
                    })->orWhere(function ($q2): void {
                        $q2->whereNull('area_office')
                            ->orWhereRaw('TRIM(area_office) = ""')
                            ->orWhereRaw('TRIM(area_office) IN ("0", "00", "N/D")');
                    });
                });
        } elseif (in_array($status, ['matched', 'manual_assignment', 'not_found', 'ambiguous', 'invalid_centro_custo'], true)) {
            $query->where('colaborador_match_status', $status);
        }

        $closedUnicoops = ['16', '19', '53', '55', '56', '57', '58', '59', '60', '63', '70', '72', '77', '78', '79'];
        if ($division === 'sede') {
            $query->whereRaw("LPAD(REGEXP_REPLACE(COALESCE(unicoop_office, ''), '[^0-9]', ''), 2, '0') = '01'");
        } elseif ($division === 'cerrado') {
            $query->whereIn(DB::raw("LPAD(REGEXP_REPLACE(COALESCE(unicoop_office, ''), '[^0-9]', ''), 2, '0')"), $closedUnicoops);
        } elseif ($division === 'unidades') {
            $query->whereNotNull('unicoop_office')
                ->whereRaw("TRIM(unicoop_office) <> ''")
                ->whereRaw("LPAD(REGEXP_REPLACE(unicoop_office, '[^0-9]', ''), 2, '0') <> '01'")
                ->whereNotIn(DB::raw("LPAD(REGEXP_REPLACE(unicoop_office, '[^0-9]', ''), 2, '0')"), $closedUnicoops)
                ->where(function ($q): void {
                    $q->whereNull('account_classification')
                        ->orWhere('account_classification', '<>', 'generic_account');
                });
        } elseif ($division === 'genericos') {
            $query->where(function ($q): void {
                $q->where('account_classification', 'generic_account')
                    ->orWhereNull('matricula')
                    ->orWhereRaw("TRIM(matricula) = ''");
            });
        }

        if ($search !== '') {
            $query->where(function ($q) use ($search): void {
                    $q->where('nome', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('matricula', 'like', "%{$search}%")
                        ->orWhere('departamento_unidade', 'like', "%{$search}%")
                        ->orWhere('colaborador_source_view', 'like', "%{$search}%")
                        ->orWhere('centro_custo', 'like', "%{$search}%")
                        ->orWhere('centro_custo_nome', 'like', "%{$search}%")
                        ->orWhere('unidade_nome', 'like', "%{$search}%")
                        ->orWhere('area_nome', 'like', "%{$search}%")
                        ->orWhere('departamento_nome', 'like', "%{$search}%")
                        ->orWhere('setor_nome', 'like', "%{$search}%")
                        ->orWhere('cargo_nome', 'like', "%{$search}%")
                        ->orWhere('empresa_nome', 'like', "%{$search}%")
                        ->orWhere('colaborador_nome', 'like', "%{$search}%")
                        ->orWhere('colaborador_match_status', 'like', "%{$search}%");
            });
        }

        return $query;
    }

    private function lookupOfficeCargoByMatricula(string $matricula): ?string
    {
        return OfficeLicense::query()
            ->where('matricula', trim($matricula))
            ->whereNotNull('cargo_nome')
            ->whereRaw("TRIM(cargo_nome) <> ''")
            ->orderByDesc('ativo')
            ->orderByDesc('updated_at')
            ->value('cargo_nome');
    }

    private function officeDivisionFor(OfficeLicense $license): string
    {
        if ((string) $license->account_classification === 'generic_account' || trim((string) $license->matricula) === '') {
            return 'genericos';
        }

        $code = preg_replace('/\D+/', '', trim((string) $license->unicoop_office)) ?: '';
        $code = str_pad(ltrim($code, '0') ?: '0', 2, '0', STR_PAD_LEFT);

        if ($code === '01') {
            return 'sede';
        }

        return in_array($code, ['16', '19', '53', '55', '56', '57', '58', '59', '60', '63', '70', '72', '77', '78', '79'], true)
            ? 'cerrado'
            : 'unidades';
    }

    private function lookupOfficeCargoForExport(object $license): ?string
    {
        $cargo = trim((string) ($license->cargo_nome ?? ''));

        return $cargo !== '' ? $cargo : null;
    }

    private function normalizeOfficeIdentifier(string $value): string
    {
        return preg_replace('/\D+/', '', $value) ?? '';
    }

    private function lookupOfficeCargoByEmail(string $email): ?string
    {
        $email = mb_strtolower(trim($email));
        if ($email === '') {
            return null;
        }

        return OfficeLicense::query()
            ->whereRaw('LOWER(email) = ?', [$email])
            ->whereNotNull('cargo_nome')
            ->whereRaw("TRIM(cargo_nome) <> ''")
            ->orderByDesc('ativo')
            ->orderByDesc('updated_at')
            ->value('cargo_nome');
    }

    private function findAdUserByEmail(string $email): ?object
    {
        $email = mb_strtolower(trim($email));
        if ($email === '') {
            return null;
        }

        try {
            $username = (string) Str::of($email)->before('@');

            return DB::table('ad_users')
                ->whereRaw('LOWER(email) = ?', [$email])
                ->orWhereRaw('LOWER(nome_usuario) = ?', [$username])
                ->orWhereRaw('LOWER(nome_usuario) = ?', [$email])
                ->orderByDesc('e_ativo')
                ->orderByDesc('id')
                ->first(['id', 'nome_completo', 'nome_usuario', 'email', 'unidade_setor', 'unidade_principal', 'e_ativo']);
        } catch (
Throwable) {
            return null;
        }
    }

    private function findCollaboratorByNameInView(string $name): ?object
    {
        return null;
    }

    public function toggleOfficeStatus(OfficeLicense $officeLicense)
    {
        $officeLicense->update([
            'ativo' => ! $officeLicense->ativo,
        ]);

        return back()->with('success', 'Status da licença atualizado.');
    }

    public function destroyOffice(OfficeLicense $officeLicense)
    {
        $officeLicense->update(['ativo' => false]);

        return back()->with('success', 'Licença Office desativada.');
    }

    public function syncOfficeMicrosoft()
    {
        $exitCode = Artisan::call('office:sync-microsoft', ['--deactivate-missing' => true]);
        $consoleOutput = trim((string) Artisan::output());

        return back()->with($exitCode === 0 ? 'success' : 'error', $consoleOutput !== ''
            ? $consoleOutput
            : ($exitCode === 0 ? 'Sincronização Microsoft 365 concluída.' : 'Falha ao sincronizar o Microsoft 365.'));
    }

    public function importOfficeMatriculasByEmail()
    {
        $exitCode = Artisan::call('office:backfill-organizational-links');
        $consoleOutput = trim((string) Artisan::output());

        return back()->with($exitCode === 0 ? 'success' : 'error', $consoleOutput !== ''
            ? $consoleOutput
            : ($exitCode === 0 ? 'Vínculos internos atualizados.' : 'Falha ao atualizar vínculos internos.'));
    }

    private function csv(string $baseName, array $header, array $rows): StreamedResponse
    {
        $filename = $baseName . '_' . now()->format('Ymd_His') . '.csv';

        return response()->streamDownload(function () use ($header, $rows): void {
            $out = fopen('php://output', 'w');
            fputcsv($out, $header, ';');
            foreach ($rows as $row) {
                fputcsv($out, $row, ';');
            }
            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * @param array{nome?:string|null,tipo?:string|null,unicoop?:string|null,area?:string|null,centro_custo?:string|null} $candidate
     * @return array{unidade:string,unicoop:string,area:string,centro_custo:string,manual:bool,matched:bool,reason:string}
     */
    private function resolveRateioBucket(array $candidate, CoretiRateioLocalService $rateioLocalService): array
    {
        $candidateName = trim((string) ($candidate['nome'] ?? ''));
        $candidateUnicoop = trim((string) ($candidate['unicoop'] ?? ''));
        $candidateArea = trim((string) ($candidate['area'] ?? ''));
        $candidateCentroCusto = trim((string) ($candidate['centro_custo'] ?? ''));

        if ($this->isCristalinaDepartment($candidateName)
            && $candidateUnicoop !== ''
            && $candidateArea !== ''
        ) {
            return [
                'unidade' => $candidateName !== '' ? $candidateName : 'Cristalina II',
                'unicoop' => $candidateUnicoop,
                'area' => $candidateArea,
                'centro_custo' => $candidateCentroCusto !== '' ? $candidateCentroCusto : ($candidateUnicoop . '.' . $candidateArea),
                'manual' => false,
                'matched' => true,
                'reason' => 'Cristalina preservada por únicoop/área informados',
            ];
        }

        $resolved = $rateioLocalService->resolveCandidate($candidate);

        if ($resolved['manual']) {
            return [
                'unidade' => $candidateName !== ''
                    ? $candidateName . ' - validação manual'
                    : 'Cristalina II - validação manual',
                'unicoop' => $candidateUnicoop !== '' ? $candidateUnicoop : '-',
                'area' => $candidateArea !== '' ? $candidateArea : '-',
                'centro_custo' => $candidateCentroCusto !== '' ? $candidateCentroCusto : '-',
                'manual' => true,
                'matched' => false,
                'reason' => $resolved['reason'],
            ];
        }

        if ($resolved['matched'] && $resolved['local'] instanceof CoretiRateioLocal) {
            /** @var CoretiRateioLocal $local */
            $local = $resolved['local'];
            return [
                'unidade' => $local->nome_local,
                'unicoop' => (string) ($local->unicoop ?? '-'),
                'area' => (string) ($local->area ?? '-'),
                'centro_custo' => (string) ($local->centro_custo ?? '-'),
                'manual' => false,
                'matched' => true,
                'reason' => $resolved['reason'],
            ];
        }

        return [
            'unidade' => $candidateName !== ''
                ? $candidateName
                : 'Pendente de mapeamento',
            'unicoop' => $candidateUnicoop !== '' ? $candidateUnicoop : '-',
            'area' => $candidateArea !== '' ? $candidateArea : '-',
            'centro_custo' => $candidateCentroCusto !== '' ? $candidateCentroCusto : '-',
            'manual' => false,
            'matched' => false,
            'reason' => $resolved['reason'],
        ];
    }

    private function emailRateioSpreadsheet($rows, float $unitCost, int $activeEmails, float $extraCostPool): StreamedResponse
    {
        $filename = 'rateio_email_' . now()->format('Ymd_His') . '.xls';

        return response()->streamDownload(function () use ($rows, $unitCost, $activeEmails, $extraCostPool): void {
            echo '<html><head><meta charset="UTF-8"></head><body>';
            echo '<table border="1" cellspacing="0" cellpadding="0" style="border-collapse:collapse;font-family:Arial,sans-serif;font-size:14px;min-width:1180px;">';

            echo '<tr style="background:#0f172a;color:#ffffff;font-weight:700;text-align:center;height:34px;">';
            echo '<th style="padding:8px 10px;">Unidade</th>';
            echo '<th style="padding:8px 10px;">Unicoop</th>';
            echo '<th style="padding:8px 10px;">Área</th>';
            echo '<th style="padding:8px 10px;">Quantidade de E-mails</th>';
            echo '<th style="padding:8px 10px;">Rateio Licenças Não Utilizadas (R$)</th>';
            echo '<th style="padding:8px 10px;">Custo Total (R$)</th>';
            echo '</tr>';

            $index = 0;
            foreach ($rows as $row) {
                $bg = $index % 2 === 0 ? '#f3f4f6' : '#ffffff';
                $qtd = (int) $row->total;
                $baseCost = $unitCost * $qtd;
                $extraShare = $activeEmails > 0 ? ($qtd / $activeEmails) * $extraCostPool : 0.0;
                $custo = $baseCost + $extraShare;

                echo '<tr style="background:' . $bg . ';text-align:center;">';
                echo '<td style="padding:6px 8px;">' . e((string) $row->unidade) . '</td>';
                echo '<td style="padding:6px 8px;">' . e((string) $row->unicoop) . '</td>';
                echo '<td style="padding:6px 8px;">' . e((string) $row->area) . '</td>';
                echo '<td style="padding:6px 8px;">' . $qtd . '</td>';
                echo '<td style="padding:6px 8px;">R$ ' . number_format($extraShare, 2, ',', '.') . '</td>';
                echo '<td style="padding:6px 8px;">R$ ' . number_format($custo, 2, ',', '.') . '</td>';
                echo '</tr>';

                $index++;
            }

            echo '</table></body></html>';
        }, $filename, [
            'Content-Type' => 'application/vnd.ms-excel; charset=UTF-8',
        ]);
    }

    private function fetchUsdBrlRate(): ?float
    {
        try {
            $response = Http::timeout(4)
                ->acceptJson()
                ->get('https://economia.awesomeapi.com.br/last/USD-BRL');

            if (! $response->ok()) {
                return null;
            }

            $bid = (float) data_get($response->json(), 'USDBRL.bid', 0);

            return $bid > 0 ? $bid : null;
        } catch (\Throwable) {
            return null;
        }
    }

    private function jiraRateioXlsx($rows, float $unitCost): StreamedResponse
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Rateio Jira');

        $headers = ['Unidade', 'Centro de Custo', 'Projeto/Grupo', 'Total de Projetos', 'Custo Total (R$)'];
        $sheet->fromArray($headers, null, 'A1');

        $headerStyle = [
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 12],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '2C6E49']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '000000']]],
        ];
        $sheet->getStyle('A1:E1')->applyFromArray($headerStyle);
        $sheet->getRowDimension(1)->setRowHeight(28);
        $sheet->freezePane('A2');

        $rowNum = 2;
        foreach ($rows as $row) {
            $sheet->setCellValue("A{$rowNum}", (string) $row->unidade);
            $sheet->setCellValue("B{$rowNum}", (string) $row->centro_custo);
            $sheet->setCellValue("C{$rowNum}", (string) $row->projeto_grupo);
            $sheet->setCellValue("D{$rowNum}", (int) $row->total);
            $sheet->setCellValue("E{$rowNum}", $unitCost * (int) $row->total);

            $sheet->getStyle("A{$rowNum}:E{$rowNum}")->applyFromArray([
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
                'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '000000']]],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => $rowNum % 2 === 0 ? 'F2F2F2' : 'FFFFFF'],
                ],
            ]);

            $rowNum++;
        }

        $sheet->getStyle('E2:E' . max(2, $rowNum - 1))
            ->getNumberFormat()
            ->setFormatCode('"R$" #,##0.00');

        foreach (['A', 'B', 'C', 'D', 'E'] as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        $writer = new Xlsx($spreadsheet);
        $filename = 'rateio_jira_' . now()->format('Ymd_His') . '.xlsx';

        return response()->streamDownload(function () use ($writer): void {
            $writer->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    private function officeRateioXlsx($rows): StreamedResponse
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Rateio Office');

        $headers = ['Unidade', 'Unicoop', 'Área', 'Office Apps', 'Office Business', 'Power BI Pro', 'Visio Plan', 'Total Licenças', 'Custo Total (R$)'];
        $sheet->fromArray($headers, null, 'A1');

        $headerStyle = [
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 12],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '0F172A']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '000000']]],
        ];
        $sheet->getStyle('A1:I1')->applyFromArray($headerStyle);
        $sheet->getRowDimension(1)->setRowHeight(28);
        $sheet->freezePane('A2');

        $rowNum = 2;
        foreach ($rows as $row) {
            $sheet->setCellValue("A{$rowNum}", (string) $row->unidade);
            $sheet->setCellValue("B{$rowNum}", (string) $row->unicoop);
            $sheet->setCellValue("C{$rowNum}", (string) $row->area);
            $sheet->setCellValue("D{$rowNum}", (int) $row->total_office_apps);
            $sheet->setCellValue("E{$rowNum}", (int) $row->total_office_business);
            $sheet->setCellValue("F{$rowNum}", (int) $row->total_powerbi_pro);
            $sheet->setCellValue("G{$rowNum}", (int) $row->total_visio_plan);
            $sheet->setCellValue("H{$rowNum}", (int) $row->total_licencas);
            $sheet->setCellValue("I{$rowNum}", (float) $row->custo_total);

            $sheet->getStyle("A{$rowNum}:I{$rowNum}")->applyFromArray([
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
                'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '000000']]],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => $rowNum % 2 === 0 ? 'F3F4F6' : 'FFFFFF'],
                ],
            ]);

            $rowNum++;
        }

        $sheet->getStyle('I2:I' . max(2, $rowNum - 1))
            ->getNumberFormat()
            ->setFormatCode('"R$" #,##0.00');

        foreach (range('A', 'I') as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }

        $filename = 'rateio_office_' . now()->format('Ymd_His') . '.xlsx';

        return response()->streamDownload(function () use ($spreadsheet): void {
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    private function parseMoneyBr(string $value): float
    {
        if ($value === '') {
            return 0.0;
        }

        $normalized = str_replace('.', '', $value);
        $normalized = str_replace(',', '.', $normalized);
        $normalized = preg_replace('/[^\d.]/', '', $normalized) ?? '';

        if ($normalized === '' || ! is_numeric($normalized)) {
            return 0.0;
        }

        return max((float) $normalized, 0.0);
    }
}
