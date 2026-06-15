<?php

namespace App\Http\Controllers;

use App\Models\JiraIssue;
use App\Models\CoretiGoogleEmail;
use App\Models\OfficeLicense;
use App\Models\ServiceDeskQueueEmail;
use App\Models\ServiceDeskEmail;
use App\Models\ServiceDeskEmailCostCenter;
use App\Services\GoogleWorkspaceDryRunSyncService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class ServiceDeskController extends Controller
{
    private const SQUAD_CERRADO = 'Time Cerrado';

    public function dashboard(GoogleWorkspaceDryRunSyncService $workspaceSync): View
    {
        $isAdmin = Auth::user()?->role === 'admin';
        $baseQuery = JiraIssue::query()
            ->where(function ($query): void {
                $query->whereNull('squad')
                    ->orWhere('squad', '<>', self::SQUAD_CERRADO);
            });

        $openQuery = (clone $baseQuery)->whereNull('data_hora_resolucao');

        $stats = [
            'open_tickets' => (clone $openQuery)->count(),
            'waiting_user' => (clone $openQuery)
                ->where(function ($query): void {
                    $query->where('status', 'like', '%Aguardando%')
                        ->orWhere('currentstatus_status', 'like', '%Aguardando%');
                })
                ->count(),
            'resolved_today' => (clone $baseQuery)
                ->whereDate('data_hora_resolucao', today())
                ->count(),
            'sla_attention' => (clone $openQuery)
                ->where(function ($query): void {
                    $query->where('tempo_sla_final_remainingTime', '<=', 0)
                        ->orWhere('sla_remainingTime', '<=', 0);
                })
                ->count(),
            'agents' => (clone $baseQuery)
                ->whereNotNull('responsavel_nome')
                ->where('responsavel_nome', '<>', '')
                ->distinct()
                ->count('responsavel_nome'),
        ];

        $queues = (clone $baseQuery)
            ->selectRaw("COALESCE(NULLIF(catalogo, ''), NULLIF(tipo_requisicao, ''), 'Sem catalogo') as name")
            ->selectRaw('COUNT(*) as total')
            ->selectRaw('SUM(CASE WHEN tempo_sla_final_remainingTime <= 0 OR sla_remainingTime <= 0 THEN 1 ELSE 0 END) as sla_attention')
            ->whereNull('data_hora_resolucao')
            ->groupBy('name')
            ->orderByDesc('total')
            ->limit(6)
            ->get()
            ->map(fn ($queue) => [
                'name' => $queue->name,
                'total' => (int) $queue->total,
                'status' => ((int) $queue->sla_attention) > 0 ? 'SLA em atenção' : 'Dentro do SLA',
            ]);

        $recentTickets = (clone $baseQuery)
            ->orderByDesc(DB::raw('COALESCE(data_hora_atualizacao, data_hora_criacao, updated_at, created_at)'))
            ->limit(8)
            ->get();

        $emailStats = [
            'total_ativos' => (int) ServiceDeskEmail::query()->where('ativo', true)->count(),
            'sede' => (int) ServiceDeskEmail::query()->where('ativo', true)->where('scope', 'sede')->count(),
            'unidades' => (int) ServiceDeskEmail::query()->where('ativo', true)->where('scope', 'unidades')->count(),
            'cerrado' => (int) ServiceDeskEmail::query()->where('ativo', true)->where('scope', 'cerrado')->count(),
            'genericos' => (int) ServiceDeskEmail::query()->where('ativo', true)->where('scope', 'genericos')->count(),
        ];

        $officeStats = [
            'total_ativos' => (int) OfficeLicense::query()->where('ativo', true)->count(),
            'apps' => (int) OfficeLicense::query()->where('ativo', true)->where('office_apps', true)->count(),
            'business' => (int) OfficeLicense::query()->where('ativo', true)->where('office_business', true)->count(),
            'powerbi' => (int) OfficeLicense::query()->where('ativo', true)->where(function ($query): void {
                $query->where('powerbi_pro', true)->orWhere('powerbi_premium', true);
            })->count(),
            'visio' => (int) OfficeLicense::query()->where('ativo', true)->where('visio_plan', true)->count(),
        ];

        $jiraGeneralStats = [
            'chamados_total' => (int) (clone $baseQuery)->count(),
            'chamados_abertos' => (int) (clone $openQuery)->count(),
            'resolvidos_hoje' => (int) (clone $baseQuery)->whereDate('data_hora_resolucao', today())->count(),
            'sla_atencao' => (int) (clone $openQuery)->where(function ($query): void {
                $query->where('tempo_sla_final_remainingTime', '<=', 0)
                    ->orWhere('sla_remainingTime', '<=', 0);
            })->count(),
        ];

        $workspacePreview = null;
        if ($isAdmin) {
            $workspacePreview = $workspaceSync->cachedPreview();
        }

        return view('service-desk.dashboard', [
            'isAdmin' => $isAdmin,
            'stats' => $stats,
            'queues' => $queues,
            'recentTickets' => $recentTickets,
            'emailStats' => $emailStats,
            'officeStats' => $officeStats,
            'jiraGeneralStats' => $jiraGeneralStats,
            'jiraTicketBaseUrl' => $this->jiraTicketBaseUrl(),
            'workspacePreview' => $workspacePreview,
        ]);
    }

    public function refreshWorkspacePreview(GoogleWorkspaceDryRunSyncService $workspaceSync): RedirectResponse
    {
        abort_unless(Auth::user()?->role === 'admin', 403);

        $workspaceSync->buildAndCachePreview();

        return back()->with('success', 'Prévia de sincronização atualizada.');
    }

    public function syncWorkspaceNow(GoogleWorkspaceDryRunSyncService $workspaceSync): RedirectResponse
    {
        abort_unless(Auth::user()?->role === 'admin', 403);

        try {
            $workspaceSync->buildAndCachePreview();
            $result = $workspaceSync->applyFromCachedPreview();
        } catch (Throwable $e) {
            return back()->with('error', 'Falha ao sincronizar Google Workspace: ' . $e->getMessage());
        }

        return back()->with(
            'success',
            'Sincronização concluída. '
            . 'Total Google: ' . ($result['summary']['google_total'] ?? 0)
            . ', novos e-mails: ' . ($result['summary']['new_emails'] ?? 0)
            . ', atualizados: ' . ($result['summary']['updated_emails'] ?? 0)
            . ', mapeados: ' . ($result['summary']['mapped'] ?? 0)
            . ', pendentes: ' . ($result['summary']['pending'] ?? 0)
            . ', sem centro de custo real: ' . ($result['summary']['sem_centro_custo'] ?? 0)
            . ', trocas de centro de custo: ' . ($result['summary']['center_cost_changes'] ?? 0) . '.'
        );
    }

    public function applyWorkspaceSync(GoogleWorkspaceDryRunSyncService $workspaceSync): RedirectResponse
    {
        abort_unless(Auth::user()?->role === 'admin', 403);

        try {
            $result = $workspaceSync->applyFromCachedPreview();
        } catch (Throwable $e) {
            return back()->with('error', 'Falha ao aplicar sync do Google Workspace: ' . $e->getMessage());
        }

        return back()->with(
            'success',
            'Sync aplicado. '
            . 'Total Google: ' . ($result['summary']['google_total'] ?? 0)
            . ', novos e-mails: ' . ($result['summary']['new_emails'] ?? 0)
            . ', atualizados: ' . ($result['summary']['updated_emails'] ?? 0)
            . ', mapeados: ' . ($result['summary']['mapped'] ?? 0)
            . ', pendentes: ' . ($result['summary']['pending'] ?? 0)
            . ', sem centro de custo real: ' . ($result['summary']['sem_centro_custo'] ?? 0)
            . ', trocas de centro de custo: ' . ($result['summary']['center_cost_changes'] ?? 0) . '.'
        );
    }

    public function tickets(Request $request): View
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'string', 'max:255'],
            'prioridade' => ['nullable', 'string', 'max:255'],
            'per_page' => ['nullable', 'integer', 'in:10,15,25,50'],
        ]);

        $query = JiraIssue::query();
        $search = trim((string) ($filters['q'] ?? ''));
        $perPage = (int) ($filters['per_page'] ?? 15);

        if ($search !== '') {
            $query->where(function ($q) use ($search): void {
                $q->where('chave', 'like', "%{$search}%")
                    ->orWhere('resumo', 'like', "%{$search}%")
                    ->orWhere('relator_nome', 'like', "%{$search}%")
                    ->orWhere('relator_email', 'like', "%{$search}%")
                    ->orWhere('responsavel_nome', 'like', "%{$search}%")
                    ->orWhere('unidade', 'like', "%{$search}%")
                    ->orWhere('departamento', 'like', "%{$search}%");
            });
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['prioridade'])) {
            $query->where('prioridade', $filters['prioridade']);
        }

        $tickets = $query
            ->orderByDesc(DB::raw('COALESCE(data_hora_atualizacao, data_hora_criacao, updated_at, created_at)'))
            ->paginate($perPage)
            ->withQueryString();

        $statuses = JiraIssue::query()
            ->whereNotNull('status')
            ->where('status', '<>', '')
            ->distinct()
            ->orderBy('status')
            ->pluck('status');

        $priorities = JiraIssue::query()
            ->whereNotNull('prioridade')
            ->where('prioridade', '<>', '')
            ->distinct()
            ->orderBy('prioridade')
            ->pluck('prioridade');

        return view('service-desk.tickets', [
            'tickets' => $tickets,
            'filters' => $filters,
            'search' => $search,
            'perPage' => $perPage,
            'statuses' => $statuses,
            'priorities' => $priorities,
            'jiraTicketBaseUrl' => $this->jiraTicketBaseUrl(),
        ]);
    }

    public function myQueue(Request $request): View
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'string', 'max:255'],
            'prioridade' => ['nullable', 'string', 'max:255'],
            'per_page' => ['nullable', 'integer', 'in:10,15,25,50'],
        ]);

        $user = Auth::user();
        $search = trim((string) ($filters['q'] ?? ''));
        $perPage = (int) ($filters['per_page'] ?? 15);

        $queueEmails = ServiceDeskQueueEmail::query()
            ->where('user_id', $user->id)
            ->orderBy('email')
            ->get();

        $emails = $queueEmails->pluck('email')
            ->map(fn (string $email) => mb_strtolower(trim($email)))
            ->filter()
            ->values();

        $nameTokens = collect([
            $user->name,
            ...$queueEmails->pluck('email')->map(fn (string $email) => str_replace('.', ' ', explode('@', $email)[0] ?? ''))->all(),
        ])->map(fn (?string $value) => trim((string) $value))
            ->filter()
            ->values();

        $query = JiraIssue::query();

        $query->where(function ($scope) use ($emails, $nameTokens): void {
            if ($emails->isNotEmpty()) {
                $scope->whereIn(DB::raw('LOWER(relator_email)'), $emails->all());
            }

            foreach ($nameTokens as $token) {
                $scope->orWhere('responsavel_nome', 'like', '%' . $token . '%')
                    ->orWhere('relator_nome', 'like', '%' . $token . '%');
            }
        });

        if ($search !== '') {
            $query->where(function ($q) use ($search): void {
                $q->where('chave', 'like', "%{$search}%")
                    ->orWhere('resumo', 'like', "%{$search}%")
                    ->orWhere('relator_nome', 'like', "%{$search}%")
                    ->orWhere('relator_email', 'like', "%{$search}%")
                    ->orWhere('responsavel_nome', 'like', "%{$search}%")
                    ->orWhere('unidade', 'like', "%{$search}%")
                    ->orWhere('departamento', 'like', "%{$search}%");
            });
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['prioridade'])) {
            $query->where('prioridade', $filters['prioridade']);
        }

        $tickets = $query
            ->orderByDesc(DB::raw('COALESCE(data_hora_atualizacao, data_hora_criacao, updated_at, created_at)'))
            ->paginate($perPage)
            ->withQueryString();

        $statuses = JiraIssue::query()
            ->whereNotNull('status')
            ->where('status', '<>', '')
            ->distinct()
            ->orderBy('status')
            ->pluck('status');

        $priorities = JiraIssue::query()
            ->whereNotNull('prioridade')
            ->where('prioridade', '<>', '')
            ->distinct()
            ->orderBy('prioridade')
            ->pluck('prioridade');

        return view('service-desk.my-queue', [
            'tickets' => $tickets,
            'filters' => $filters,
            'search' => $search,
            'perPage' => $perPage,
            'statuses' => $statuses,
            'priorities' => $priorities,
            'jiraTicketBaseUrl' => $this->jiraTicketBaseUrl(),
            'queueEmails' => $queueEmails,
        ]);
    }

    public function storeMyQueueEmail(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email', 'max:255'],
        ]);

        ServiceDeskQueueEmail::query()->firstOrCreate([
            'user_id' => Auth::id(),
            'email' => mb_strtolower(trim($data['email'])),
        ]);

        return back()->with('success', 'E-mail adicionado na Minha fila.');
    }

    public function destroyMyQueueEmail(ServiceDeskQueueEmail $queueEmail): RedirectResponse
    {
        abort_if($queueEmail->user_id !== Auth::id(), 403);

        $queueEmail->delete();

        return back()->with('success', 'E-mail removido da Minha fila.');
    }

    public function emails(Request $request, string $scope): View
    {
        $scope = $this->normalizeEmailScope($scope);
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'string', Rule::in(['todos', 'ativos', 'inativos'])],
            'per_page' => ['nullable', 'integer', 'in:10,25,50'],
            'colaborador_q' => ['nullable', 'string', 'max:100'],
            'selected_matricula' => ['nullable', 'string', 'max:20'],
            'selected_nome' => ['nullable', 'string', 'max:255'],
            'selected_id_pessoa' => ['nullable', 'integer'],
        ]);

        $query = ServiceDeskEmail::query()->where('scope', $scope);
        $search = trim((string) ($filters['q'] ?? ''));
        $status = $filters['status'] ?? 'todos';
        $perPage = (int) ($filters['per_page'] ?? 10);

        if ($search !== '') {
            $query->where(function ($q) use ($search): void {
                $q->where('email', 'like', "%{$search}%")
                    ->orWhere('matricula', 'like', "%{$search}%")
                    ->orWhere('colaborador_nome', 'like', "%{$search}%")
                    ->orWhere('centro_custo', 'like', "%{$search}%")
                    ->orWhere('unicoop_sede', 'like', "%{$search}%")
                    ->orWhere('area_sede', 'like', "%{$search}%");
            });
        }

        if ($status === 'ativos') {
            $query->where('ativo', true);
        } elseif ($status === 'inativos') {
            $query->where('ativo', false);
        }

        $emails = $query->orderBy('email')
            ->paginate($perPage)
            ->withQueryString();

        $manualCostCenterEmailsQuery = CoretiGoogleEmail::query()
            ->where(function ($query): void {
                $query->whereNull('centro_custo')
                    ->orWhereRaw("TRIM(COALESCE(centro_custo, '')) = ''")
                    ->orWhereNull('unicoop')
                    ->orWhereRaw("TRIM(COALESCE(unicoop, '')) = ''")
                    ->orWhereNull('area')
                    ->orWhereRaw("TRIM(COALESCE(area, '')) = ''");
            });

        $manualCostCenterEmailsCount = (clone $manualCostCenterEmailsQuery)->count();
        $manualCostCenterEmails = $manualCostCenterEmailsQuery
            ->orderBy('email')
            ->get([
                'id',
                'email',
                'nome',
                'nome_usuario',
                'centro_custo',
                'unicoop',
                'area',
                'mapeamento_status',
                'mapeamento_motivo',
            ]);

        $collaboratorSearch = trim((string) ($filters['colaborador_q'] ?? ''));
        $collaborators = $collaboratorSearch !== ''
            ? $this->searchCollaborators($collaboratorSearch)
            : collect();

        return view('service-desk.emails.index', [
            'scope' => $scope,
            'scopeLabel' => $this->emailScopeLabel($scope),
            'emails' => $emails,
            'filters' => $filters,
            'search' => $search,
            'status' => $status,
            'perPage' => $perPage,
            'collaboratorSearch' => $collaboratorSearch,
            'collaborators' => $collaborators,
            'costCenterRecords' => $this->intranetCostCenterRecords(),
            'cristalinaIiAreaOptions' => $this->cristalinaIiAreaOptions(),
            'manualCostCenterEmailsCount' => $manualCostCenterEmailsCount,
            'manualCostCenterEmails' => $manualCostCenterEmails,
            'manualCostCenterSourceLabel' => 'Google Workspace Admin',
        ]);
    }

    public function exportEmails(): StreamedResponse
    {
        $emails = ServiceDeskEmail::query()
            ->orderBy('scope')
            ->orderBy('email')
            ->get([
                'scope',
                'email',
                'matricula',
                'colaborador_nome',
                'centro_custo',
                'unicoop_sede',
                'area_sede',
                'data_inclusao',
                'data_desativacao',
                'ativo',
                'observacao',
            ]);

        $filename = 'service-desk-e-mails-' . now()->format('Ymd_His') . '.csv';

        return response()->streamDownload(function () use ($emails): void {
            $out = fopen('php://output', 'w');
            fprintf($out, "\xEF\xBB\xBF");

            fputcsv($out, [
                'Scope',
                'Email',
                'Matricula',
                'Colaborador',
                'Centro Custo',
                'Unicoop',
                'Area',
                'Data Inclusao',
                'Data Desativacao',
                'Status',
                'Observacao',
            ], ';');

            foreach ($emails as $email) {
                fputcsv($out, [
                    $this->emailScopeLabel((string) $email->scope),
                    $email->email,
                    $email->matricula,
                    $email->colaborador_nome,
                    $email->centro_custo,
                    $email->unicoop_sede,
                    $email->area_sede,
                    optional($email->data_inclusao)->format('d/m/Y'),
                    optional($email->data_desativacao)->format('d/m/Y'),
                    $email->ativo ? 'Ativo' : 'Inativo',
                    $email->observacao,
                ], ';');
            }

            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function lookupCollaborator(Request $request): JsonResponse
    {
        $data = $request->validate([
            'matricula' => ['required', 'string', 'max:20'],
        ]);

        $collaborator = $this->findCollaboratorByMatricula(trim($data['matricula']));

        if (! $collaborator) {
            return response()->json([
                'found' => false,
                'message' => 'Matrícula não encontrada.',
            ], 404);
        }

        return response()->json([
            'found' => true,
            'collaborator' => [
                'matricula' => (string) $collaborator->matricula,
                'nome' => $collaborator->nome,
                'email' => $collaborator->email,
                'id_pessoa' => $collaborator->id_pessoa,
                'usuario_ad' => $collaborator->usuario_ad,
                'centro_custo' => $collaborator->centro_custo,
                'unicoop_sede' => $collaborator->unicoop_sede,
                'area_sede' => $collaborator->area_sede,
            ],
        ]);
    }

    public function lookupCostCenter(Request $request, string $scope): JsonResponse
    {
        $scope = $this->normalizeEmailScope($scope);
        $data = $request->validate([
            'centro_custo' => ['required', 'string', 'max:255'],
            'area_sede' => ['nullable', 'string', 'max:10'],
        ]);

        $costCenter = $this->findCostCenter(
            $scope,
            trim($data['centro_custo']),
            trim((string) ($data['area_sede'] ?? '')) ?: null
        );

        if (! $costCenter) {
            return response()->json([
                'found' => false,
                'message' => 'Centro de custo ou unidade não encontrado.',
            ], 404);
        }

        return response()->json([
            'found' => true,
            'cost_center' => $costCenter,
        ]);
    }

    public function storeEmail(Request $request, string $scope)
    {
        $scope = $this->normalizeEmailScope($scope);

        $data = $request->validate([
            'email' => ['required', 'email', 'max:255', Rule::unique('service_desk_emails', 'email')->where('scope', $scope)],
            'matricula' => ['nullable', 'string', 'max:20'],
            'colaborador_nome' => ['required', 'string', 'max:255'],
            'id_pessoa' => ['nullable', 'integer'],
            'centro_custo' => ['nullable', 'string', 'max:255'],
            'unicoop_sede' => ['nullable', 'string', 'max:50'],
            'area_sede' => ['nullable', 'string', 'max:50'],
            'centro_custo_manual' => ['nullable', 'boolean'],
            'data_inclusao' => ['nullable', 'date'],
            'data_desativacao' => ['nullable', 'date'],
            'observacao' => ['nullable', 'string', 'max:2000'],
        ]);

        $data['scope'] = $scope;
        $data['matricula'] = filled($data['matricula'] ?? null) ? $data['matricula'] : null;
        $data['ativo'] = empty($data['data_desativacao']);
        $data['data_inclusao'] = $data['data_inclusao'] ?? today()->toDateString();
        $data['centro_custo_manual'] = (bool) ($data['centro_custo_manual'] ?? false);
        $data['centro_custo_manual_at'] = $data['centro_custo_manual'] ? now() : null;
        $data = $this->syncEmailCostCenterData($scope, $data, ! $data['centro_custo_manual']);

        ServiceDeskEmail::create($data);

        return redirect()->route("service-desk.emails.{$scope}")
            ->with('success', 'E-mail cadastrado com sucesso.');
    }

    public function editEmail(string $scope, ServiceDeskEmail $email): View
    {
        $scope = $this->normalizeEmailScope($scope);
        abort_unless($email->scope === $scope, 404);

        return view('service-desk.emails.edit', [
            'scope' => $scope,
            'scopeLabel' => $this->emailScopeLabel($scope),
            'emailRecord' => $email,
            'costCenterRecords' => $this->intranetCostCenterRecords(),
            'cristalinaIiAreaOptions' => $this->cristalinaIiAreaOptions(),
        ]);
    }

    public function updateEmail(Request $request, string $scope, ServiceDeskEmail $email)
    {
        $scope = $this->normalizeEmailScope($scope);
        abort_unless($email->scope === $scope, 404);

        $data = $request->validate([
            'email' => ['required', 'email', 'max:255', Rule::unique('service_desk_emails', 'email')->where('scope', $scope)->ignore($email->id)],
            'matricula' => ['nullable', 'string', 'max:20'],
            'colaborador_nome' => ['required', 'string', 'max:255'],
            'id_pessoa' => ['nullable', 'integer'],
            'centro_custo' => ['nullable', 'string', 'max:255'],
            'unicoop_sede' => ['nullable', 'string', 'max:50'],
            'area_sede' => ['nullable', 'string', 'max:50'],
            'centro_custo_manual' => ['nullable', 'boolean'],
            'data_inclusao' => ['nullable', 'date'],
            'data_desativacao' => ['nullable', 'date'],
            'observacao' => ['nullable', 'string', 'max:2000'],
        ]);

        $data['matricula'] = filled($data['matricula'] ?? null) ? $data['matricula'] : null;
        $data['ativo'] = empty($data['data_desativacao']);
        $submittedManualCostCenter = (bool) ($data['centro_custo_manual'] ?? false);
        $changedCostCenter = filled($data['centro_custo'] ?? null)
            && (string) ($data['centro_custo'] ?? '') !== (string) ($email->centro_custo ?? '');
        $manualCostCenter = $changedCostCenter
            ? $submittedManualCostCenter
            : (bool) $email->centro_custo_manual;
        $data['centro_custo_manual'] = $manualCostCenter;
        $data['centro_custo_manual_at'] = $manualCostCenter
            ? ($changedCostCenter ? now() : $email->centro_custo_manual_at)
            : null;
        $data = $this->syncEmailCostCenterData($scope, $data, ! $manualCostCenter);
        $email->update($data);

        return redirect()->route("service-desk.emails.{$scope}")
            ->with('success', 'E-mail atualizado com sucesso.');
    }

    public function toggleEmail(string $scope, ServiceDeskEmail $email)
    {
        $scope = $this->normalizeEmailScope($scope);
        abort_unless($email->scope === $scope, 404);

        $email->update([
            'ativo' => ! $email->ativo,
            'data_desativacao' => $email->ativo ? today() : null,
        ]);

        return back()->with('success', 'Status atualizado com sucesso.');
    }

    public function destroyEmail(string $scope, ServiceDeskEmail $email)
    {
        $scope = $this->normalizeEmailScope($scope);
        abort_unless($email->scope === $scope, 404);

        $email->delete();

        return back()->with('success', 'E-mail removido com sucesso.');
    }

    private function searchCollaborators(string $search)
    {
        return DB::connection('intranet_cocari')
            ->table('cadColaborador as c')
            ->leftJoin('gasUsuario as u', 'u.IdPessoa', '=', 'c.IdPessoa')
            ->leftJoin('cadCentroCustoRh as rh', 'rh.IdCentroCusto', '=', 'c.CentroCusto')
            ->leftJoin('cadCentroCustos as cc', 'cc.IdCCusto', '=', 'c.CentroCusto')
            ->select([
                DB::raw('c.IdPessoa as matricula'),
                DB::raw('c.Nome as nome'),
                'c.Email as email',
                'c.IdPessoa as id_pessoa',
                'u.UsuarioAd as usuario_ad',
                DB::raw('COALESCE(rh.Nome, cc.Nome, c.CentroCusto) as centro_custo'),
            ])
            ->where(function ($query) use ($search): void {
                $query->where('c.Nome', 'like', "%{$search}%")
                    ->orWhere('c.Matricula', 'like', "%{$search}%")
                    ->orWhere('c.IdPessoa', 'like', "%{$search}%")
                    ->orWhere('u.Nome', 'like', "%{$search}%")
                    ->orWhere('u.IdUsuario', 'like', "%{$search}%")
                    ->orWhere('u.UsuarioAd', 'like', "%{$search}%");
            })
            ->orderBy('nome')
            ->limit(12)
            ->get();
    }

    private function findCollaboratorByMatricula(string $matricula): ?object
    {
        try {
            $collaborator = DB::connection('intranet_cocari')
                ->table('cadColaborador as c')
                ->leftJoin('gasUsuario as u', 'u.IdPessoa', '=', 'c.IdPessoa')
                ->leftJoin('cadCentroCustoRh as rh', 'rh.IdCentroCusto', '=', 'c.CentroCusto')
                ->leftJoin('cadCentroCustos as cc', 'cc.IdCCusto', '=', 'c.CentroCusto')
                ->select([
                    DB::raw('c.IdPessoa as matricula'),
                    DB::raw('c.Nome as nome'),
                    'c.Email as email',
                    'c.IdPessoa as id_pessoa',
                    'u.UsuarioAd as usuario_ad',
                    DB::raw('COALESCE(rh.Nome, cc.Nome, c.CentroCusto) as centro_custo'),
                ])
                ->where(function ($query) use ($matricula): void {
                    $query->where('c.IdPessoa', $matricula);
                })
                ->first();

            if (! $collaborator) {
                return null;
            }

            $org = app(GoogleWorkspaceDryRunSyncService::class)
                ->resolveServiceDeskIntranetDataByMatricula($matricula);

            if ($org !== null) {
                $collaborator->matricula = $org['matricula'] ?? $collaborator->matricula;
                $collaborator->id_pessoa = $org['id_pessoa'] ?? $collaborator->id_pessoa;
                $collaborator->nome = $org['colaborador_nome'] ?? $collaborator->nome;
                $collaborator->centro_custo = $org['centro_custo'] ?? $collaborator->centro_custo;
                $collaborator->unicoop_sede = $org['unicoop_sede'] ?? null;
                $collaborator->area_sede = $org['area_sede'] ?? null;
            } else {
                $collaborator->unicoop_sede = null;
                $collaborator->area_sede = null;
            }

            return $collaborator;
        } catch (Throwable) {
            return null;
        }
    }

    private function costCenterOptions(string $scope)
    {
        $scope = $this->normalizeEmailScope($scope);

        return ServiceDeskEmailCostCenter::query()
            ->when($scope !== 'genericos', fn ($query) => $query->where('scope', $scope))
            ->orderBy('name')
            ->limit(400)
            ->pluck('name');
    }

    private function costCenterRecords(string $scope)
    {
        $scope = $this->normalizeEmailScope($scope);

        return ServiceDeskEmailCostCenter::query()
            ->when($scope !== 'genericos', fn ($query) => $query->where('scope', $scope))
            ->orderBy('name')
            ->limit(400)
            ->get(['name', 'unicoop', 'area', 'source_table']);
    }

    private function intranetCostCenterRecords()
    {
        $units = DB::table('unidades')
            ->select([
                DB::raw('unidade as name'),
                'unicoop',
                'area',
            ])
            ->whereNotNull('unidade');

        $departments = DB::table('departamentos')
            ->select([
                DB::raw('nome as name'),
                'unicoop',
                'area',
            ])
            ->where('ativo', 1);

        $records = DB::query()
            ->fromSub($units->unionAll($departments), 'intranet_cost_centers')
            ->select(['name', 'unicoop', 'area'])
            ->orderBy('name')
            ->get();

        return $records
            ->groupBy('name')
            ->map(function ($group, $name) {
                $first = $group->first();

                if ($name === 'Cristalina II') {
                    return (object) [
                        'name' => $name,
                        'unicoop' => $first->unicoop,
                        'area' => null,
                    ];
                }

                return $first;
            })
            ->values();
    }

    private function findCostCenter(string $scope, string $value, ?string $area = null): ?array
    {
        $scope = $this->normalizeEmailScope($scope);

        $localRecord = $this->findIntranetCostCenter($value, $area);
        if ($localRecord !== null) {
            return $localRecord;
        }

        return null;
    }

    private function findIntranetCostCenter(string $value, ?string $area = null): ?array
    {
        $normalized = trim((string) $value);
        if ($normalized === '') {
            return null;
        }

        $areaNormalized = $this->normalizeAreaValue($area);

        $unidadeQuery = DB::table('unidades')
            ->select([
                DB::raw('unidade as name'),
                'unicoop',
                'area',
            ])
            ->where('unidade', $normalized);

        if ($areaNormalized !== null) {
            $unidadeQuery->whereRaw('TRIM(area) = ?', [$areaNormalized]);
        }

        $unidade = $unidadeQuery->first();

        if ($unidade) {
            return [
                'id' => null,
                'centro_custo' => $unidade->name,
                'unicoop_sede' => $unidade->unicoop,
                'area_sede' => $unidade->area,
            ];
        }

        $departamentoQuery = DB::table('departamentos')
            ->select([
                DB::raw('nome as name'),
                'unicoop',
                'area',
            ])
            ->where('ativo', 1)
            ->where('nome', $normalized);

        if ($areaNormalized !== null) {
            $departamentoQuery->whereRaw('TRIM(area) = ?', [$areaNormalized]);
        }

        $departamento = $departamentoQuery->first();

        if ($departamento) {
            return [
                'id' => null,
                'centro_custo' => $departamento->name,
                'unicoop_sede' => $departamento->unicoop,
                'area_sede' => $departamento->area,
            ];
        }

        return null;
    }

    private function cristalinaIiAreaOptions(): array
    {
        return ['144', '191', '150', '177', '149', '200', '154', '158', '152', '180', '188', '291', '156', '141'];
    }

    private function normalizeAreaValue(?string $value): ?string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $value) ?: '';

        return $digits !== '' ? $digits : null;
    }

    private function syncEmailCostCenterData(string $scope, array $data, bool $allowMatriculaAutoFill = true): array
    {
        $matricula = trim((string) ($data['matricula'] ?? ''));
        if ($allowMatriculaAutoFill && $matricula !== '') {
            $org = app(GoogleWorkspaceDryRunSyncService::class)
                ->resolveServiceDeskIntranetDataByMatricula($matricula);

            if ($org !== null) {
                $data['centro_custo'] = $org['centro_custo'] ?? $data['centro_custo'] ?? null;
                $data['unicoop_sede'] = $org['unicoop_sede'] ?? $data['unicoop_sede'] ?? null;
                $data['area_sede'] = $org['area_sede'] ?? $data['area_sede'] ?? null;
            }
        }

        $name = trim((string) ($data['centro_custo'] ?? ''));

        if ($name === '') {
            $data['service_desk_email_cost_center_id'] = null;

            return $data;
        }

        $costCenter = ServiceDeskEmailCostCenter::query()
            ->where('scope', $this->normalizeEmailScope($scope))
            ->where('name', $name)
            ->first();

        if (! $costCenter) {
            $costCenter = ServiceDeskEmailCostCenter::create([
                'scope' => $scope,
                'name' => $name,
                'unicoop' => $data['unicoop_sede'] ?? null,
                'area' => $data['area_sede'] ?? null,
                'source_table' => 'service_desk_emails',
            ]);
        } elseif (filled($data['unicoop_sede'] ?? null) || filled($data['area_sede'] ?? null)) {
            $costCenter->update([
                'unicoop' => $data['unicoop_sede'] ?? $costCenter->unicoop,
                'area' => $data['area_sede'] ?? $costCenter->area,
                'source_table' => 'intranet_cocari',
            ]);
        }

        $data['service_desk_email_cost_center_id'] = $costCenter->id;
        $data['centro_custo'] = $costCenter->name;
        $data['unicoop_sede'] = $data['unicoop_sede'] ?? $costCenter->unicoop;
        $data['area_sede'] = $data['area_sede'] ?? $costCenter->area;

        $this->syncCoretiGoogleEmailFromLegacy([
            'email' => $data['email'] ?? null,
            'nome' => $data['colaborador_nome'] ?? null,
            'matricula' => $data['matricula'] ?? null,
            'centro_custo_nome' => $data['centro_custo'] ?? null,
            'unicoop' => $data['unicoop_sede'] ?? null,
            'area' => $data['area_sede'] ?? null,
            'mapeamento_status' => 'mapeado',
            'mapeamento_motivo' => null,
        ]);

        return $data;
    }

    private function syncCoretiGoogleEmailFromLegacy(array $data): void
    {
        $email = mb_strtolower(trim((string) ($data['email'] ?? '')));
        if ($email === '') {
            return;
        }

        $googleEmail = CoretiGoogleEmail::query()->whereRaw('LOWER(TRIM(email)) = ?', [$email])->first();
        if (! $googleEmail) {
            return;
        }

        $unicoop = trim((string) ($data['unicoop'] ?? ''));
        $area = trim((string) ($data['area'] ?? ''));
        $centroNome = trim((string) ($data['centro_custo_nome'] ?? '')) ?: null;
        $centroCodigo = ($unicoop !== '' && $area !== '') ? ltrim($unicoop, '0') . '.' . ltrim($area, '0') : null;

        if ($unicoop !== '') {
            $unicoop = str_pad(ltrim(preg_replace('/\D+/', '', $unicoop) ?: '', '0') ?: '0', 2, '0', STR_PAD_LEFT);
        }
        if ($area !== '') {
            $area = str_pad(ltrim(preg_replace('/\D+/', '', $area) ?: '', '0') ?: '0', 3, '0', STR_PAD_LEFT);
        }

        $googleEmail->update([
            'nome' => $data['nome'] ?? $googleEmail->nome,
            'nome_usuario' => $googleEmail->nome_usuario ?: Str::before($email, '@'),
            'ad_unidade_setor_original' => $googleEmail->ad_unidade_setor_original,
            'nome_local' => $centroNome,
            'nome_local_normalizado' => $centroNome ? app(\App\Services\CoretiRateioLocalService::class)->normalizeLocalName($centroNome) : null,
            'tipo_local' => $googleEmail->tipo_local ?: null,
            'unicoop' => $unicoop !== '' ? $unicoop : null,
            'area' => $area !== '' ? $area : null,
            'centro_custo' => $centroCodigo,
            'centro_custo_nome' => $centroNome,
            'mapeamento_status' => 'mapeado',
            'mapeamento_motivo' => null,
            'atualizado_rateio_em' => now(),
            'importado_em' => now(),
        ]);
    }

    private function normalizeEmailScope(string $scope): string
    {
        abort_unless(in_array($scope, ['sede', 'unidades', 'cerrado', 'genericos'], true), 404);

        return $scope;
    }

    private function emailScopeLabel(string $scope): string
    {
        return [
            'sede' => 'Sede',
            'unidades' => 'Unidades',
            'cerrado' => 'Cerrado',
            'genericos' => 'Genéricos',
        ][$scope];
    }

    private function jiraTicketBaseUrl(): ?string
    {
        $baseUrl = trim((string) config('services.jira.ticket_base_url', ''));

        return $baseUrl !== '' ? rtrim($baseUrl, '/') : null;
    }
}
