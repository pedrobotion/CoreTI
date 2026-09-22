<?php

namespace App\Http\Controllers;

use App\Models\JiraIssue;
use App\Models\CoretiGoogleEmail;
use App\Models\CoretiCostCenter;
use App\Models\OfficeLicense;
use App\Models\ServiceDeskQueueEmail;
use App\Models\ServiceDeskEmail;
use App\Models\ServiceDeskEmailCostCenter;
use App\Services\GoogleWorkspaceDryRunSyncService;
use App\Services\ServiceDeskAdDirectoryService;
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

    public function dashboard(GoogleWorkspaceDryRunSyncService $workspaceSync, ServiceDeskAdDirectoryService $adDirectory): View
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
            'total_ativos' => (int) ServiceDeskEmail::query()->count(),
            'sede' => (int) ServiceDeskEmail::query()->where('scope', 'sede')->count(),
            'unidades' => (int) ServiceDeskEmail::query()->where('scope', 'unidades')->count(),
            'cerrado' => (int) ServiceDeskEmail::query()->where('scope', 'cerrado')->count(),
            'genericos' => (int) ServiceDeskEmail::query()->where('scope', 'genericos')->count(),
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

        $adStats = $adDirectory->summary($adDirectory->users());

        $jiraGeneralStats = [
            'chamados_total' => (int) (clone $baseQuery)->count(),
            'chamados_abertos' => (int) (clone $openQuery)->count(),
            'resolvidos_hoje' => (int) (clone $baseQuery)->whereDate('data_hora_resolucao', today())->count(),
            'sla_atencao' => (int) (clone $openQuery)->where(function ($query): void {
                $query->where('tempo_sla_final_remainingTime', '<=', 0)
                    ->orWhere('sla_remainingTime', '<=', 0);
            })->count(),
        ];

        $workspacePreviewOpen = (bool) session()->pull('workspace_preview_open', false);
        $workspacePreview = $isAdmin && $workspacePreviewOpen
            ? $workspaceSync->cachedPreview()
            : null;

        return view('service-desk.dashboard', [
            'isAdmin' => $isAdmin,
            'stats' => $stats,
            'queues' => $queues,
            'recentTickets' => $recentTickets,
            'emailStats' => $emailStats,
            'officeStats' => $officeStats,
            'adStats' => $adStats,
            'jiraGeneralStats' => $jiraGeneralStats,
            'jiraTicketBaseUrl' => $this->jiraTicketBaseUrl(),
            'workspacePreview' => $workspacePreview,
            'workspacePreviewOpen' => $workspacePreviewOpen,
        ]);
    }

    public function refreshWorkspacePreview(GoogleWorkspaceDryRunSyncService $workspaceSync): RedirectResponse
    {
        abort_unless(Auth::user()?->role === 'admin', 403);

        $preview = $workspaceSync->buildAndCachePreview();
        $summary = is_array($preview['summary'] ?? null) ? $preview['summary'] : [];
        $hasChanges =
            (int) ($summary['new_emails'] ?? 0) > 0
            || (int) ($summary['updated_emails'] ?? 0) > 0
            || (int) ($summary['center_cost_changes'] ?? 0) > 0;

        return back()
            ->with('success', $hasChanges
                ? 'Prévia de sincronização atualizada.'
                : 'Prévia de sincronização atualizada. Nenhuma alteração pendente.')
            ->with('workspace_preview_open', $hasChanges);
    }

    public function syncWorkspaceNow(GoogleWorkspaceDryRunSyncService $workspaceSync): RedirectResponse
    {
        abort_unless(Auth::user()?->role === 'admin', 403);

        try {
            $workspaceSync->buildAndCachePreview();
            $result = $workspaceSync->applyFromCachedPreview();
            $legacyRefresh = $workspaceSync->refreshIntranetDataForExistingEmails();
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
            . ', trocas de centro de custo: ' . ($result['summary']['center_cost_changes'] ?? 0)
            . ', e-mails atualizados pelo AD: ' . ($legacyRefresh['ad_rateio_updates'] ?? 0)
            . ', e-mails legados atualizados: ' . ($legacyRefresh['updated'] ?? 0) . '.'
        );
    }

    public function applyWorkspaceSync(GoogleWorkspaceDryRunSyncService $workspaceSync): RedirectResponse
    {
        abort_unless(Auth::user()?->role === 'admin', 403);

        try {
            $result = $workspaceSync->applyFromCachedPreview();
            $legacyRefresh = $workspaceSync->refreshIntranetDataForExistingEmails();
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
            . ', trocas de centro de custo: ' . ($result['summary']['center_cost_changes'] ?? 0)
            . ', e-mails atualizados pelo AD: ' . ($legacyRefresh['ad_rateio_updates'] ?? 0)
            . ', e-mails legados atualizados: ' . ($legacyRefresh['updated'] ?? 0) . '.'
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

        /*
         | Opções para o seletor de e-mail.
         |
         | A fonte é o próprio histórico de chamados: só faz sentido oferecer
         | e-mails que realmente aparecem como relator, senão o usuário cadastra
         | um endereço que nunca vai filtrar nada. São poucas dezenas.
         |
         | Não exibimos relator_nome junto: o mesmo e-mail registra chamados de
         | centenas de pessoas diferentes (é quem abre, não quem solicita), então
         | qualquer nome escolhido seria enganoso.
         */
        $emailsDisponiveis = JiraIssue::query()
            ->selectRaw('LOWER(relator_email) as email, COUNT(*) as total')
            ->whereNotNull('relator_email')
            ->where('relator_email', '!=', '')
            ->groupBy('email')
            ->orderByDesc('total')
            ->get()
            ->reject(fn ($linha) => $emails->contains($linha->email))
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
            'emailsDisponiveis' => $emailsDisponiveis,
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
            'units' => ['nullable', 'array'],
            'units.*' => ['string', 'max:255'],
        ]);

        $query = ServiceDeskEmail::query()->where('scope', $scope);
        $search = trim((string) ($filters['q'] ?? ''));
        $status = $filters['status'] ?? 'todos';
        $perPage = (int) ($filters['per_page'] ?? 10);
        $units = collect($filters['units'] ?? [])
            ->map(fn ($unit): string => trim((string) $unit))
            ->filter()
            ->unique()
            ->values()
            ->all();

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

        if ($units !== []) {
            $query->whereIn('centro_custo', $units);
        }

        $emails = $query->orderBy('email')
            ->paginate($perPage)
            ->withQueryString();

        $emails->setCollection($emails->getCollection()->map(function (ServiceDeskEmail $email): ServiceDeskEmail {
            $catalogName = $this->resolveCatalogCostCenterName($email->unicoop_sede, $email->area_sede);
            if ($catalogName !== null) {
                $email->setAttribute('centro_custo', $catalogName);
            }

            return $email;
        }));

        $manualCostCenterEmailsQuery = CoretiGoogleEmail::query()
            ->where('ativo', true)
            ->where(function ($query): void {
                $query->whereNull('centro_custo')
                    ->orWhereRaw("TRIM(COALESCE(centro_custo, '')) = ''");
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
                'centro_custo_nome',
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
            'units' => $units,
            'unitOptions' => ServiceDeskEmail::query()
                ->where('scope', $scope)
                ->whereNotNull('centro_custo')
                ->whereRaw("TRIM(centro_custo) <> ''")
                ->distinct()
                ->orderBy('centro_custo')
                ->pluck('centro_custo'),
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

    public function exportEmails(Request $request): StreamedResponse
    {
        $scope = trim((string) $request->query('scope', ''));
        if ($scope !== '') {
            $scope = $this->normalizeEmailScope($scope);
        }

        $status = (string) $request->query('status', 'todos');
        if (! in_array($status, ['todos', 'ativos', 'inativos'], true)) {
            $status = 'todos';
        }

        $search = trim((string) $request->query('q', ''));
        $rawUnits = $request->query('units', []);
        $units = collect(is_array($rawUnits) ? $rawUnits : [$rawUnits])
            ->map(fn ($unit): string => trim((string) $unit))
            ->filter()
            ->unique()
            ->values()
            ->all();

        $emails = ServiceDeskEmail::query()
            ->when($scope !== '', fn ($query) => $query->where('scope', $scope))
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($q) use ($search): void {
                    $q->where('email', 'like', "%{$search}%")
                        ->orWhere('matricula', 'like', "%{$search}%")
                        ->orWhere('colaborador_nome', 'like', "%{$search}%")
                        ->orWhere('centro_custo', 'like', "%{$search}%")
                        ->orWhere('unicoop_sede', 'like', "%{$search}%")
                        ->orWhere('area_sede', 'like', "%{$search}%");
                });
            })
            ->when($status === 'ativos', fn ($query) => $query->where('ativo', true))
            ->when($status === 'inativos', fn ($query) => $query->where('ativo', false))
            ->when($units !== [], fn ($query) => $query->whereIn('centro_custo', $units))
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
                $catalogName = $this->resolveCatalogCostCenterName($email->unicoop_sede, $email->area_sede);

                fputcsv($out, [
                    $this->emailScopeLabel((string) $email->scope),
                    $email->email,
                    $email->matricula,
                    $email->colaborador_nome,
                    $catalogName ?? $email->centro_custo,
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
            'currentCostCenterName' => $this->resolveCatalogCostCenterName($email->unicoop_sede, $email->area_sede),
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

    public function updateCoretiGoogleEmail(Request $request, CoretiGoogleEmail $email): RedirectResponse
    {
        $data = $request->validate([
            'centro_custo' => ['nullable', 'string', 'max:50'],
            'centro_custo_nome' => ['nullable', 'string', 'max:255'],
            'unicoop' => ['nullable', 'string', 'max:10'],
            'area' => ['nullable', 'string', 'max:10'],
        ]);

        $centroCusto = trim((string) ($data['centro_custo'] ?? '')) ?: null;
        $centroCustoNome = trim((string) ($data['centro_custo_nome'] ?? '')) ?: null;
        $unicoop = trim((string) ($data['unicoop'] ?? '')) ?: null;
        $area = trim((string) ($data['area'] ?? '')) ?: null;

        if ($unicoop !== null && $area !== null) {
            $unicoopDigits = preg_replace('/\D+/', '', $unicoop) ?: '';
            $areaDigits = preg_replace('/\D+/', '', $area) ?: '';
            $unicoop = $unicoopDigits !== '' ? str_pad(ltrim($unicoopDigits, '0') ?: '0', 2, '0', STR_PAD_LEFT) : null;
            $area = $areaDigits !== '' ? str_pad(ltrim($areaDigits, '0') ?: '0', 3, '0', STR_PAD_LEFT) : null;
            $centroCusto = ($unicoop !== null && $area !== null) ? $unicoop . '.' . $area : $centroCusto;

            $catalogName = $this->resolveCatalogCostCenterName($unicoop, $area);
            if ($catalogName === null) {
                return back()->withErrors(['centro_custo' => 'O centro de custo informado não existe no catálogo coreti_cost_centers.']);
            }

            $centroCustoNome = $catalogName;
        }

        $complete = $centroCusto !== null && $unicoop !== null && $area !== null;

        $email->update([
            'centro_custo' => $centroCusto,
            'centro_custo_nome' => $centroCustoNome,
            'unicoop' => $unicoop,
            'area' => $area,
            'centro_custo_manual' => true,
            'centro_custo_manual_at' => now(),
            'mapeamento_status' => $complete ? 'mapeado' : 'pendente',
            'mapeamento_motivo' => $complete ? null : 'Rateio manual incompleto.',
            'atualizado_rateio_em' => now(),
        ]);

        return back()->with('success', 'Rateio do e-mail atualizado com sucesso.');
    }

    private function searchCollaborators(string $search)
    {
        $search = trim($search);

        return CoretiGoogleEmail::query()
            ->where(function ($query) use ($search): void {
                $query->where('nome', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('matricula', 'like', "%{$search}%")
                    ->orWhere('nome_usuario', 'like', "%{$search}%");
            })
            ->orderBy('nome')
            ->limit(12)
            ->get()
            ->map(function (CoretiGoogleEmail $email): object {
                return (object) [
                    'matricula' => $email->matricula,
                    'nome' => $email->nome,
                    'email' => $email->email,
                    'id_pessoa' => $email->matricula,
                    'usuario_ad' => $email->nome_usuario,
                    'centro_custo' => $this->resolveCatalogCostCenterName($email->unicoop, $email->area)
                        ?? $email->nome_local
                        ?? $email->centro_custo_nome
                        ?? $email->centro_custo,
                    'unicoop_sede' => $email->unicoop,
                    'area_sede' => $email->area,
                ];
            });
    }

    private function findCollaboratorByMatricula(string $matricula): ?object
    {
        try {
            $email = CoretiGoogleEmail::query()
                ->where('matricula', $matricula)
                ->orderByRaw("CASE WHEN mapeamento_status = 'mapeado' THEN 0 ELSE 1 END")
                ->orderByDesc('updated_at')
                ->first();

            if (! $email) {
                return null;
            }

            return (object) [
                'matricula' => $email->matricula,
                'nome' => $email->nome,
                'email' => $email->email,
                'id_pessoa' => $email->matricula,
                'usuario_ad' => $email->nome_usuario,
                'centro_custo' => $this->resolveCatalogCostCenterName($email->unicoop, $email->area)
                    ?? $email->nome_local
                    ?? $email->centro_custo_nome
                    ?? $email->centro_custo,
                'unicoop_sede' => $email->unicoop,
                'area_sede' => $email->area,
            ];
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
        $records = CoretiCostCenter::query()
            ->active()
            ->orderBy('name')
            ->orderBy('code')
            ->get(['code', 'name'])
            ->map(function (CoretiCostCenter $center): object {
                [$unicoop, $area] = $this->splitCoretiCostCenterCode($center->code);

                return (object) [
                    'name' => trim((string) $center->name),
                    'display_name' => trim((string) $center->name) . ' - ' . $center->code,
                    'unicoop' => $unicoop,
                    'area' => $area,
                    'source' => 'coreti_cost_centers',
                    'code' => trim((string) $center->code),
                    'office_key' => mb_strtolower(trim((string) $center->name)) . '|' . $unicoop . '|' . $area,
                ];
            })
            ->filter(fn (object $item): bool => $item->name !== '' && $item->area !== null && $item->area !== '000')
            ->values();

        return $records;
    }

    /** @return array{0:?string,1:?string} */
    private function splitCoretiCostCenterCode(?string $code): array
    {
        if (! preg_match('/^\s*(\d{1,2})\s*[.\/-]\s*(\d{1,3})\s*$/', (string) $code, $matches)) {
            return [null, null];
        }

        $unicoop = str_pad((string) ((int) $matches[1]), 2, '0', STR_PAD_LEFT);
        $area = str_pad((string) ((int) $matches[2]), 3, '0', STR_PAD_LEFT);

        return [$unicoop, $area];
    }

    private function resolveCatalogCostCenterName(?string $unicoop, ?string $area): ?string
    {
        $unicoop = preg_replace('/\D+/', '', trim((string) $unicoop)) ?: '';
        $area = preg_replace('/\D+/', '', trim((string) $area)) ?: '';

        if ($unicoop === '' || $area === '') {
            return null;
        }

        $code = str_pad((string) ((int) $unicoop), 2, '0', STR_PAD_LEFT)
            . '.'
            . str_pad((string) ((int) $area), 3, '0', STR_PAD_LEFT);

        return CoretiCostCenter::query()
            ->active()
            ->where('code', $code)
            ->value('name');
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

        $record = CoretiCostCenter::query()
            ->active()
            ->where(function ($query) use ($normalized): void {
                $query->where('name', $normalized)->orWhere('code', $normalized);
            })
            ->first(['code', 'name']);

        if ($record) {
            [$unicoop, $recordArea] = $this->splitCoretiCostCenterCode($record->code);
            if ($recordArea === null || $recordArea === '000' || ($area !== null && trim($area) !== $recordArea)) {
                return null;
            }

            return [
                'id' => null,
                'centro_custo' => $record->name,
                'unicoop_sede' => $unicoop,
                'area_sede' => $recordArea,
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
            'centro_custo_manual' => $data['centro_custo_manual'] ?? false,
            'centro_custo_manual_at' => $data['centro_custo_manual_at'] ?? null,
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

        $catalogName = $this->resolveCatalogCostCenterName($unicoop, $area);
        if ($catalogName !== null) {
            $centroNome = $catalogName;
        }

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
            'centro_custo_manual' => (bool) ($data['centro_custo_manual'] ?? false),
            'centro_custo_manual_at' => ! empty($data['centro_custo_manual']) ? ($data['centro_custo_manual_at'] ?? now()) : null,
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
