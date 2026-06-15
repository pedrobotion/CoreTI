<?php

namespace App\Http\Controllers;

use App\Models\JiraProject;
use App\Models\CoretiRateioLocal;
use App\Models\OfficeLicense;
use App\Models\ServiceDeskEmail;
use App\Models\ServiceDeskEmailCostCenter;
use App\Services\CoretiRateioLocalService;
use App\Services\GoogleWorkspaceDryRunSyncService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;
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
        $rateioLocalService = app(CoretiRateioLocalService::class);
        $cost = (float) $request->query('valor_fatura', 0);
        $availability = $this->resolveWorkspaceEmailAvailability($workspaceSync);
        $extra = (int) $availability['available'];

        $rows = ServiceDeskEmail::query()
            ->where('ativo', true)
            ->get(['centro_custo', 'unicoop_sede', 'area_sede'])
            ->map(function ($row) use ($rateioLocalService): array {
                return $this->resolveRateioBucket([
                    'nome' => (string) ($row->centro_custo ?? ''),
                    'tipo' => null,
                    'unicoop' => $row->unicoop_sede,
                    'area' => $row->area_sede,
                    'centro_custo' => $row->centro_custo,
                ], $rateioLocalService);
            })
            ->groupBy(fn (array $row) => $row['unidade'] . '|' . $row['unicoop'] . '|' . $row['area'])
            ->map(function ($group): object {
                $first = $group->first();
                return (object) [
                    'unidade' => $first['unidade'],
                    'unicoop' => $first['unicoop'],
                    'area' => $first['area'],
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
            ],
        ]);
    }

    private function resolveWorkspaceEmailAvailability(GoogleWorkspaceDryRunSyncService $workspaceSync): array
    {
        $warning = null;
        $preview = $workspaceSync->cachedPreview();
        if (! is_array($preview) || ! isset($preview['summary'])) {
            try {
                $preview = $workspaceSync->buildAndCachePreview();
            } catch (\Throwable $e) {
                $warning = 'Não foi possível atualizar a prévia do Google Workspace agora. Foi mantido o último cálculo disponível.';
            }
        }

        $summary = is_array($preview['summary'] ?? null) ? $preview['summary'] : [];
        // Regra de faturamento: usuário suspenso com licença atribuída continua consumindo custo.
        // Por isso usamos o total de contas do Workspace presentes no snapshot (ativas + suspensas).
        $used = (int) ($summary['google_total'] ?? 0);
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
            'status' => ['nullable', 'string', 'in:todos,ativo,inativo,sem_unidade'],
        ]);

        $status = (string) ($filters['status'] ?? 'todos');
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
        }

        $search = trim((string) ($filters['q'] ?? ''));
        if ($search !== '') {
            $query->where(function ($q) use ($search): void {
                $q->where('nome', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('matricula', 'like', "%{$search}%")
                    ->orWhere('departamento_unidade', 'like', "%{$search}%");
            });
        }

        $perPage = (int) ($filters['per_page'] ?? 10);

        $licensesCount = (int) OfficeLicense::query()->where('ativo', true)->count();

        $licenses = $query->orderBy('nome')->paginate($perPage)->withQueryString();

        $officeEditOptions = collect()
            ->merge(
                DB::table('unidades')
                    ->selectRaw('unidade as name, unicoop, area, "unidade" as source')
                    ->whereNotNull('unidade')
                    ->whereRaw('TRIM(unidade) <> ""')
                    ->get()
            )
            ->merge(
                DB::table('departamentos')
                    ->selectRaw('nome as name, unicoop, area, "departamento" as source')
                    ->whereNotNull('nome')
                    ->whereRaw('TRIM(nome) <> ""')
                    ->get()
            )
            ->filter(fn ($item) => trim((string) $item->name) !== '')
            ->map(function ($item) {
                $item->name = trim((string) $item->name);
                $item->sort_key = mb_strtolower($item->name);

                return $item;
            })
            ->sortBy('sort_key')
            ->unique(fn ($item) => mb_strtolower($item->name) . '|' . trim((string) $item->unicoop) . '|' . trim((string) $item->area))
            ->values();

        $officeUnitOptions = collect()
            ->merge($officeEditOptions)
            ->merge(
                ServiceDeskEmailCostCenter::query()
                    ->selectRaw('name, unicoop, area, "legacy" as source')
                    ->whereNotNull('name')
                    ->whereRaw('TRIM(name) <> ""')
                    ->get()
            )
            ->filter(fn ($item) => trim((string) $item->name) !== '')
            ->map(function ($item) {
                $item->name = trim((string) $item->name);
                $item->sort_key = mb_strtolower($item->name);

                return $item;
            })
            ->sortBy('sort_key')
            ->unique(fn ($item) => mb_strtolower($item->name) . '|' . trim((string) $item->unicoop) . '|' . trim((string) $item->area))
            ->values();

        return view('service-desk.licensing.office', [
            'licenses' => $licenses,
            'officeUnitOptions' => $officeUnitOptions,
            'officeEditOptions' => $officeEditOptions,
            'search' => $search,
            'perPage' => $perPage,
            'status' => $status,
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

    public function officeRateio(Request $request): View|StreamedResponse
    {
        $rateioLocalService = app(CoretiRateioLocalService::class);
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
            ->map(function ($row) use ($rateioLocalService): array {
                $resolved = $this->resolveRateioBucket([
                    'nome' => (string) ($row->departamento_unidade ?? ''),
                    'tipo' => null,
                    'unicoop' => $row->unicoop_office,
                    'area' => $row->area_office,
                    'centro_custo' => trim((string) $row->unicoop_office) !== '' && trim((string) $row->area_office) !== ''
                        ? trim((string) $row->unicoop_office) . '.' . trim((string) $row->area_office)
                        : null,
                ], $rateioLocalService);

                return $resolved + [
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
            ->sortBy('unidade')
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

        OfficeLicense::updateOrCreate(
            ['email' => mb_strtolower(trim($data['email']))],
            [
                'matricula' => trim((string) $data['matricula']),
                'nome' => $data['nome'],
                'email' => mb_strtolower(trim($data['email'])),
                'departamento_unidade' => $data['departamento_unidade'],
                'unicoop_office' => $resolvedUnicoop,
                'area_office' => $resolvedArea,
                'office_apps' => (bool) ($data['office_apps'] ?? false),
                'office_business' => (bool) ($data['office_business'] ?? false),
                'powerbi_pro' => (bool) ($data['powerbi_pro'] ?? false),
                'powerbi_premium' => (bool) ($data['powerbi_premium'] ?? false),
                'visio_plan' => (bool) ($data['visio_plan'] ?? false),
                'ativo' => true,
            ]
        );

        $this->syncOfficeOrgForDepartment($data['departamento_unidade'], $resolvedUnicoop, $resolvedArea);

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

        $officeLicense->update([
            'matricula' => trim((string) ($data['matricula'] ?? '')) ?: null,
            'nome' => $data['nome'],
            'email' => mb_strtolower(trim($data['email'])),
            'departamento_unidade' => $data['departamento_unidade'],
            'unicoop_office' => $resolvedUnicoop,
            'area_office' => $resolvedArea,
            'office_apps' => (bool) ($data['office_apps'] ?? false),
            'office_business' => (bool) ($data['office_business'] ?? false),
            'powerbi_pro' => (bool) ($data['powerbi_pro'] ?? false),
            'powerbi_premium' => (bool) ($data['powerbi_premium'] ?? false),
            'visio_plan' => (bool) ($data['visio_plan'] ?? false),
        ]);

        $this->syncOfficeOrgForDepartment($data['departamento_unidade'], $resolvedUnicoop, $resolvedArea);

        return back()->with('success', 'Licença Office atualizada com sucesso.');
    }

    private function resolveOfficeOrgMeta(string $department, ?string $preferredUnicoop = null, ?string $preferredArea = null): array
    {
        $department = trim($department);
        if ($department === '') {
            return [null, null];
        }

        $preferredUnicoop = trim((string) $preferredUnicoop);
        $preferredArea = trim((string) $preferredArea);

        if ($preferredUnicoop !== '' && $preferredArea !== '') {
            $preferredDepartment = DB::table('departamentos')
                ->where('nome', $department)
                ->where('unicoop', $preferredUnicoop)
                ->where('area', $preferredArea)
                ->select(['unicoop', 'area'])
                ->first();
            if ($preferredDepartment) {
                return [$preferredUnicoop, $preferredArea];
            }

            $preferredUnit = DB::table('unidades')
                ->where('unidade', $department)
                ->where('unicoop', $preferredUnicoop)
                ->where('area', $preferredArea)
                ->select(['unicoop', 'area'])
                ->first();
            if ($preferredUnit) {
                return [$preferredUnicoop, $preferredArea];
            }
        }

        $unit = DB::table('unidades')
            ->where('unidade', $department)
            ->select(['unicoop', 'area'])
            ->first();
        if ($unit) {
            return [
                ($u = trim((string) ($unit->unicoop ?? ''))) !== '' ? $u : null,
                ($a = trim((string) ($unit->area ?? ''))) !== '' ? $a : null,
            ];
        }

        $dep = DB::table('departamentos')
            ->where('nome', $department)
            ->select(['unicoop', 'area'])
            ->first();
        if ($dep) {
            return [
                ($u = trim((string) ($dep->unicoop ?? ''))) !== '' ? $u : null,
                ($a = trim((string) ($dep->area ?? ''))) !== '' ? $a : null,
            ];
        }

        return [null, null];
    }

    private function syncOfficeOrgForDepartment(string $department, ?string $unicoop, ?string $area): void
    {
        $department = trim($department);
        if ($department === '') {
            return;
        }

        if ($this->isCristalinaDepartment($department)) {
            return;
        }

        OfficeLicense::query()
            ->where('departamento_unidade', $department)
            ->update([
                'unicoop_office' => $unicoop,
                'area_office' => $area,
                'updated_at' => now(),
            ]);
    }

    private function isCristalinaDepartment(string $department): bool
    {
        $normalized = mb_strtolower(trim($department));
        return str_starts_with($normalized, 'cristalina');
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

        try {
            $row = DB::connection('intranet_cocari')
                ->table('cadColaborador as c')
                ->leftJoin('cadCentroCustoRh as rh', 'rh.IdCentroCusto', '=', 'c.CentroCusto')
                ->leftJoin('cadCentroCustos as cc', 'cc.IdCCusto', '=', 'c.CentroCusto')
                ->select([
                    DB::raw('c.Matricula as matricula'),
                    DB::raw('c.Nome as nome'),
                    'c.Email as email',
                    DB::raw('COALESCE(rh.Nome, cc.Nome, c.CentroCusto) as centro_custo'),
                ])
                ->where('c.Matricula', $matricula)
                ->first();
        } catch (\Throwable) {
            $row = null;
        }

        if (! $row) {
            return response()->json([
                'found' => false,
                'message' => 'Matrícula não encontrada na base de e-mails.',
            ], 404);
        }

        $localEmail = DB::table('service_desk_emails')
            ->where('matricula', (string) $row->matricula)
            ->orderByDesc('ativo')
            ->orderBy('id')
            ->first();

        $costCenter = DB::table('service_desk_email_cost_centers')
            ->where('name', (string) ($row->centro_custo ?? ''))
            ->orderByRaw("FIELD(scope, 'sede', 'unidades', 'cerrado')")
            ->first();

        return response()->json([
            'found' => true,
            'collaborator' => [
                'matricula' => $row->matricula,
                'nome' => $row->nome,
                'email' => $row->email ?: ($localEmail->email ?? null),
                'departamento_unidade' => $row->centro_custo ?: ($localEmail->centro_custo ?? null),
                'unicoop_office' => $costCenter->unicoop ?? ($localEmail->unicoop_sede ?? null),
                'area_office' => $costCenter->area ?? ($localEmail->area_sede ?? null),
            ],
        ]);
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

    public function importOfficeMatriculasByEmail()
    {
        $officeRows = OfficeLicense::query()
            ->select(['id', 'email', 'matricula'])
            ->get();

        $updated = 0;
        $alreadyFilled = 0;
        $notFound = 0;

        foreach ($officeRows as $office) {
            $email = mb_strtolower(trim((string) $office->email));
            if ($email === '') {
                $notFound++;
                continue;
            }

            $mailRow = ServiceDeskEmail::query()
                ->whereRaw('LOWER(email) = ?', [$email])
                ->orderByDesc('ativo')
                ->orderBy('id')
                ->first(['matricula']);

            $matricula = trim((string) ($mailRow->matricula ?? ''));
            if ($matricula === '') {
                $notFound++;
                continue;
            }

            $current = trim((string) ($office->matricula ?? ''));
            if ($current !== '' && $current === $matricula) {
                $alreadyFilled++;
                continue;
            }

            $office->update(['matricula' => $matricula]);
            $updated++;
        }

        return back()->with(
            'success',
            "Importação concluída. Matrículas atualizadas: {$updated}; já corretas: {$alreadyFilled}; sem correspondência: {$notFound}."
        );
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
        $resolved = $rateioLocalService->resolveCandidate($candidate);

        if ($resolved['manual']) {
            return [
                'unidade' => trim((string) ($candidate['nome'] ?? '')) !== ''
                    ? trim((string) ($candidate['nome'] ?? '')) . ' - validação manual'
                    : 'Cristalina II - validação manual',
                'unicoop' => trim((string) ($candidate['unicoop'] ?? '-')) !== '' ? trim((string) ($candidate['unicoop'] ?? '-')) : '-',
                'area' => trim((string) ($candidate['area'] ?? '-')) !== '' ? trim((string) ($candidate['area'] ?? '-')) : '-',
                'centro_custo' => trim((string) ($candidate['centro_custo'] ?? '-')) !== '' ? trim((string) ($candidate['centro_custo'] ?? '-')) : '-',
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
            'unidade' => trim((string) ($candidate['nome'] ?? '')) !== ''
                ? trim((string) ($candidate['nome'] ?? ''))
                : 'Pendente de mapeamento',
            'unicoop' => trim((string) ($candidate['unicoop'] ?? '-')) !== '' ? trim((string) ($candidate['unicoop'] ?? '-')) : '-',
            'area' => trim((string) ($candidate['area'] ?? '-')) !== '' ? trim((string) ($candidate['area'] ?? '-')) : '-',
            'centro_custo' => trim((string) ($candidate['centro_custo'] ?? '-')) !== '' ? trim((string) ($candidate['centro_custo'] ?? '-')) : '-',
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
