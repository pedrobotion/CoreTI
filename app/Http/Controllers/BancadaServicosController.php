<?php
namespace App\Http\Controllers;
use App\Models\BancadaEquipment;
use App\Models\BancadaEquipmentAttachment;
use App\Models\BancadaEquipmentEvent;
use App\Models\BancadaEquipmentStatusHistory;
use App\Models\BancadaMaloteRoute;
use App\Models\BancadaMaloteRouteUnit;
use App\Models\BancadaThirdPartyCompany;
use App\Models\JiraIssue;
use App\Services\BancadaStatusFlowService;
use App\Services\BancadaAttachmentService;
use App\Services\BancadaDeliveryScheduleService;
use App\Services\JiraAutomationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
class BancadaServicosController extends Controller
{
    private const EQUIPMENT_TYPE_OPTIONS = [
        'Computador',
        'Monitor',
        'Nobreak',
        'Coletor',
        'Notebook',
        'Mi box',
        'Relógio Ponto',
        'Switch',
        'Televisão',
    ];
    private const WEEKDAYS = ['Segunda', 'Terça', 'Quarta', 'Quinta', 'Sexta', 'Sábado', 'Domingo'];
    private const ACTIVE_STATUSES = [
        BancadaStatusFlowService::STATUS_AGUARDANDO_ENTRADA_FISCAL,
        BancadaStatusFlowService::STATUS_EM_BANCADA,
        BancadaStatusFlowService::STATUS_TERCEIROS,
        BancadaStatusFlowService::STATUS_AGUARDANDO_PECA,
        BancadaStatusFlowService::STATUS_EM_MANUTENCAO,
        BancadaStatusFlowService::STATUS_MANUTENCAO_REALIZADA,
        'Manutenção negada',
        BancadaStatusFlowService::STATUS_SEM_CONSERTO,
        BancadaStatusFlowService::STATUS_PRONTO_ENTREGA,
        BancadaStatusFlowService::STATUS_NOTA_FISCAL_EMITIDA,
        BancadaStatusFlowService::STATUS_ENTREGUE,
        BancadaStatusFlowService::STATUS_BACKUP,
        BancadaStatusFlowService::STATUS_DESCARTE,
    ];
    private const BANCADA_PANEL_STATUSES = [
        BancadaStatusFlowService::STATUS_AGUARDANDO_ENTRADA_FISCAL,
        BancadaStatusFlowService::STATUS_EM_BANCADA,
        BancadaStatusFlowService::STATUS_TERCEIROS,
        BancadaStatusFlowService::STATUS_AGUARDANDO_PECA,
        BancadaStatusFlowService::STATUS_EM_MANUTENCAO,
        BancadaStatusFlowService::STATUS_MANUTENCAO_REALIZADA,
        'Manutenção negada',
        BancadaStatusFlowService::STATUS_SEM_CONSERTO,
        BancadaStatusFlowService::STATUS_PRONTO_ENTREGA,
        BancadaStatusFlowService::STATUS_NOTA_FISCAL_EMITIDA,
    ];
    private const ADMIN_PENDING_STATUSES = [
        BancadaStatusFlowService::STATUS_AGUARDANDO_PECA,
        BancadaStatusFlowService::STATUS_TERCEIROS,
        BancadaStatusFlowService::STATUS_PRONTO_ENTREGA,
        BancadaStatusFlowService::STATUS_NOTA_FISCAL_EMITIDA,
    ];
    private const CLOSED_STATUSES = [BancadaStatusFlowService::STATUS_ENTREGUE, BancadaStatusFlowService::STATUS_DESCARTE];
    private const BANCADA_SQUAD = 'Time Bancada de Serviços';
    public function __construct(
        private readonly BancadaStatusFlowService $statusFlow,
        private readonly BancadaAttachmentService $attachmentService,
        private readonly BancadaDeliveryScheduleService $deliveryScheduleService
    )
    {
    }
    public function dashboard(): View
    {
        $activeQuery = BancadaEquipment::query()->whereIn('status', self::BANCADA_PANEL_STATUSES);
        $jiraOpenQuery = JiraIssue::query()
            ->where('squad', self::BANCADA_SQUAD)
            ->whereNull('data_hora_resolucao');
        return view('bancada-servicos.dashboard', [
            'isAdmin' => Auth::user()?->role === 'admin',
            'stats' => [
                'em_bancada' => (clone $activeQuery)->count(),
                'aguardando_peca' => (clone $activeQuery)->where('status', BancadaStatusFlowService::STATUS_AGUARDANDO_PECA)->count(),
                'prontos' => (clone $activeQuery)->where('status', BancadaStatusFlowService::STATUS_PRONTO_ENTREGA)->count(),
                'aguardando_entrada' => BancadaEquipment::query()->whereIn('entrada_status', ['Aguardando Entrada', BancadaStatusFlowService::ENTRY_PENDING])->count(),
                'terceiros_pendentes' => BancadaEquipment::query()
                    ->where('status', BancadaStatusFlowService::STATUS_TERCEIROS)
                    ->whereNull('terceiros_resultado')
                    ->count(),
                'finalizados_hoje' => BancadaEquipment::query()
                    ->whereIn('status', self::CLOSED_STATUSES)
                    ->whereDate('data_saida', today())
                    ->count(),
                'chamados_abertos' => (clone $jiraOpenQuery)->count(),
                'chamados_sla' => (clone $jiraOpenQuery)
                    ->where(function ($query): void {
                        $query->where('tempo_sla_final_remainingTime', '<=', 0)
                            ->orWhere('sla_remainingTime', '<=', 0);
                    })
                    ->count(),
            ],
            'recent' => BancadaEquipment::query()
                ->whereIn('status', self::BANCADA_PANEL_STATUSES)
                ->latest('updated_at')
                ->limit(8)
                ->get(),
            'recentTickets' => JiraIssue::query()
                ->where('squad', self::BANCADA_SQUAD)
                ->orderByDesc(DB::raw('COALESCE(data_hora_atualizacao, data_hora_criacao, updated_at, created_at)'))
                ->limit(8)
                ->get(),
            'jiraTicketBaseUrl' => $this->jiraBancadaTicketBaseUrl(),
        ]);
    }
    public function tickets(Request $request): View
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'string', 'max:255'],
            'per_page' => ['nullable', 'integer', 'in:10,15,25,50'],
        ]);
        $search = trim((string) ($filters['q'] ?? ''));
        $perPage = (int) ($filters['per_page'] ?? 15);
        $query = JiraIssue::query()->where('squad', self::BANCADA_SQUAD);
        if ($search !== '') {
            $query->where(function ($q) use ($search): void {
                $q->where('chave', 'like', "%{$search}%")
                    ->orWhere('resumo', 'like', "%{$search}%")
                    ->orWhere('status', 'like', "%{$search}%")
                    ->orWhere('prioridade', 'like', "%{$search}%")
                    ->orWhere('relator_nome', 'like', "%{$search}%")
                    ->orWhere('responsavel_nome', 'like', "%{$search}%");
            });
        }
        if (! empty($filters['status'])) {
            if ($filters['status'] === 'abertos') {
                $query->whereNull('data_hora_resolucao');
            } elseif ($filters['status'] === 'resolvidos') {
                $query->whereNotNull('data_hora_resolucao');
            } else {
                $query->where('status', $filters['status']);
            }
        }
        $records = $query
            ->orderByDesc(DB::raw('COALESCE(data_hora_atualizacao, data_hora_criacao, updated_at, created_at)'))
            ->paginate($perPage)
            ->withQueryString();
        return view('bancada-servicos.tickets', [
            'records' => $records,
            'jiraTicketBaseUrl' => $this->jiraBancadaTicketBaseUrl(),
            'search' => $search,
            'perPage' => $perPage,
            'status' => $filters['status'] ?? 'abertos',
            'statuses' => JiraIssue::query()
                ->where('squad', self::BANCADA_SQUAD)
                ->whereNotNull('status')
                ->where('status', '<>', '')
                ->distinct()
                ->orderBy('status')
                ->pluck('status'),
        ]);
    }
    public function assets(Request $request): View
    {
        return $this->listAssets($request, self::BANCADA_PANEL_STATUSES, 'equipamentos', 'Equipamentos em Bancada');
    }
    public function deliveredAssets(Request $request): View
    {
        return $this->listAssets($request, ['Entregue'], 'entregues', 'Equipamentos Entregues');
    }
    public function discardedAssets(Request $request): View
    {
        return $this->listAssets($request, [BancadaStatusFlowService::STATUS_DESCARTE], 'descartados', 'Equipamentos Descartados');
    }
    public function backupAssets(Request $request): View
    {
        return $this->listAssets($request, [BancadaStatusFlowService::STATUS_BACKUP], 'backup', 'Equipamentos de Backup', true);
    }
    public function awaitingDelivery(): View
    {
        $filters = request()->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'tipo' => ['nullable', 'string', 'max:100'],
            'unidade' => ['nullable', 'string', 'max:255'],
        ]);
        $search = trim((string) ($filters['q'] ?? ''));
        $tipo = trim((string) ($filters['tipo'] ?? ''));
        $unidade = trim((string) ($filters['unidade'] ?? ''));
        $equipments = BancadaEquipment::query()
            ->where(function ($q): void {
                $q->where(function ($sq): void {
                    $sq->where('status', BancadaStatusFlowService::STATUS_PRONTO_ENTREGA)
                        ->where('origem_tipo', 'sede');
                })->orWhere('status', BancadaStatusFlowService::STATUS_NOTA_FISCAL_EMITIDA);
            })
            ->when($search !== '', function ($q) use ($search): void {
                $q->where(function ($sq) use ($search): void {
                    $sq->where('plaqueta', 'like', "%{$search}%")
                        ->orWhere('tipo_equipamento', 'like', "%{$search}%")
                        ->orWhere('unidade_setor', 'like', "%{$search}%")
                        ->orWhere('tic', 'like', "%{$search}%")
                        ->orWhere('observacao', 'like', "%{$search}%");
                });
            })
            ->when($tipo !== '', fn ($q) => $q->where('tipo_equipamento', 'like', "%{$tipo}%"))
            ->when($unidade !== '', fn ($q) => $q->where('unidade_setor', 'like', "%{$unidade}%"))
            ->orderBy('updated_at')
            ->get();
        $routes = BancadaMaloteRoute::query()
            ->where('ativo', true)
            ->with('units')
            ->orderBy('ordem')
            ->get();
        $grouped = [];
        $sede = [];
        $unassigned = [];
        foreach ($equipments as $equipment) {
            if (($equipment->origem_tipo ?? null) === 'sede' || str_contains(mb_strtolower((string) $equipment->unidade_setor), 'sede')) {
                $sede[] = $equipment;
                continue;
            }
            $matchedRoute = null;
            foreach ($routes as $route) {
                foreach ($route->units as $unit) {
                    if (mb_strtolower(trim($unit->unit_label)) === mb_strtolower(trim((string) $equipment->unidade_setor))) {
                        $matchedRoute = $route;
                        break 2;
                    }
                }
            }
            $schedule = $matchedRoute ? $this->deliveryScheduleService->nextDates($matchedRoute) : null;
            if ($matchedRoute) {
                $grouped[$matchedRoute->id]['route'] = $matchedRoute;
                $grouped[$matchedRoute->id]['items'][] = [
                    'equipment' => $equipment,
                    'schedule' => $schedule,
                ];
            } else {
                $unassigned[] = [
                    'equipment' => $equipment,
                    'schedule' => null,
                ];
            }
        }
        return view('bancada-servicos.awaiting-delivery', [
            'grouped' => $grouped,
            'sedeItems' => $sede,
            'unassignedItems' => $unassigned,
            'pendingCd' => $this->buildDeliveryRouteDashboardData(),
            'search' => $search,
            'tipo' => $tipo,
            'unidade' => $unidade,
        ]);
    }
    public function routesConfig(): View
    {
        return view('bancada-servicos.routes-config', [
            'routes' => BancadaMaloteRoute::query()->with('units')->orderBy('ordem')->get(),
            'routeNameOptions' => [
                'Transcocari',
                'Marcelo',
                'Cantelle',
                'Rodocoop',
            ],
            'weekdayOptions' => self::WEEKDAYS,
            'unitOptions' => $this->maloteUnitOptions(),
        ]);
    }
    public function storeRouteConfig(Request $request)
    {
        $data = $request->validate([
            'nome' => ['required', 'string', 'max:120'],
            'dia_entrega' => ['required', 'string', 'max:20'],
            'dia_carrega' => ['required', 'string', 'max:20'],
            'dia_separa' => ['required', 'string', 'max:20'],
            'observacao' => ['nullable', 'string', 'max:500'],
            'ordem' => ['nullable', 'integer', 'min:0'],
            'units' => ['nullable', 'array'],
            'units.*' => ['string', 'max:255'],
        ]);
        $this->assertNoActiveRouteUnitConflict(($data['units'] ?? []));
        DB::transaction(function () use ($data): void {
            $payload = [
                'nome' => $data['nome'],
                'dia_entrega' => $data['dia_entrega'] ?? null,
                'dia_carrega' => $data['dia_carrega'] ?? null,
                'dia_separa' => $data['dia_separa'] ?? null,
                'ordem' => (int) ($data['ordem'] ?? 0),
                'ativo' => true,
            ];
            if ($this->maloteRoutesHasObservationColumn()) {
                $payload['observacao'] = trim((string) ($data['observacao'] ?? '')) ?: null;
            }
            $route = BancadaMaloteRoute::create($payload);
            $order = 1;
            foreach (($data['units'] ?? []) as $unitLabel) {
                $label = trim((string) $unitLabel);
                if ($label === '') {
                    continue;
                }
                BancadaMaloteRouteUnit::create([
                    'route_id' => $route->id,
                    'unit_label' => $label,
                    'ordem' => $order++,
                ]);
            }
        });
        return back()->with('success', 'Rota cadastrada.');
    }
    public function updateRouteConfig(Request $request, BancadaMaloteRoute $route)
    {
        $data = $request->validate([
            'nome' => ['required', 'string', 'max:120'],
            'dia_entrega' => ['required', 'string', 'max:20'],
            'dia_carrega' => ['required', 'string', 'max:20'],
            'dia_separa' => ['required', 'string', 'max:20'],
            'observacao' => ['nullable', 'string', 'max:500'],
            'ordem' => ['nullable', 'integer', 'min:0'],
            'ativo' => ['nullable', 'boolean'],
            'units' => ['nullable', 'array'],
            'units.*' => ['string', 'max:255'],
        ]);
        $willBeActive = isset($data['ativo']) ? (bool) $data['ativo'] : (bool) $route->ativo;
        if ($willBeActive) {
            $this->assertNoActiveRouteUnitConflict(($data['units'] ?? []), $route->id);
        }
        DB::transaction(function () use ($route, $data): void {
            $payload = [
                'nome' => $data['nome'],
                'dia_entrega' => $data['dia_entrega'] ?? null,
                'dia_carrega' => $data['dia_carrega'] ?? null,
                'dia_separa' => $data['dia_separa'] ?? null,
                'ordem' => (int) ($data['ordem'] ?? 0),
                'ativo' => isset($data['ativo']) ? (bool) $data['ativo'] : (bool) $route->ativo,
            ];
            if ($this->maloteRoutesHasObservationColumn()) {
                $payload['observacao'] = trim((string) ($data['observacao'] ?? '')) ?: null;
            }
            $route->update($payload);
            $route->units()->delete();
            $order = 1;
            foreach (($data['units'] ?? []) as $unitLabel) {
                $label = trim((string) $unitLabel);
                if ($label === '') {
                    continue;
                }
                BancadaMaloteRouteUnit::create([
                    'route_id' => $route->id,
                    'unit_label' => $label,
                    'ordem' => $order++,
                ]);
            }
        });
        return back()->with('success', 'Rota atualizada.');
    }
    public function toggleRouteConfig(BancadaMaloteRoute $route)
    {
        $route->ativo = ! (bool) $route->ativo;
        $route->save();
        return back()->with('success', $route->ativo ? 'Rota ativada.' : 'Rota desativada.');
    }
    public function markSentToCd(BancadaEquipment $equipment)
    {
        $status = $this->statusFlow->normalizeStatus($equipment->status) ?? $equipment->status;
        abort_unless(
            $status === BancadaStatusFlowService::STATUS_PRONTO_ENTREGA || $status === BancadaStatusFlowService::STATUS_NOTA_FISCAL_EMITIDA,
            422
        );
        abort_unless(($equipment->origem_tipo ?? 'unidade') === 'unidade', 422);
        $route = $this->findActiveRouteForUnit($equipment->unidade_setor);
        if (! $route) {
            return back()->with('error', 'Rota não cadastrada para esta unidade/setor.');
        }
        $schedule = $this->deliveryScheduleService->nextDates($route);
        if (! $schedule) {
            return back()->with('error', 'Rota sem dias válidos de separação/carregamento/entrega.');
        }
        $equipment->delivery_route_id = $route->id;
        $equipment->sent_to_cd_at = now();
        $equipment->sent_to_cd_by = Auth::id();
        $equipment->expected_separation_date = $schedule['separation']->toDateString();
        $equipment->expected_loading_date = $schedule['loading']->toDateString();
        $equipment->expected_delivery_date = $schedule['delivery']->toDateString();
        $equipment->save();
        $this->recordEquipmentEvent(
            equipment: $equipment,
            action: 'enviado_ao_cd',
            previousStatus: $equipment->status,
            newStatus: $equipment->status,
            module: 'Bancada',
            observation: 'Equipamento marcado como enviado ao Centro de Distribuição.',
            metadata: [
                'route_name' => $route->nome,
                'unidade_setor' => $equipment->unidade_setor,
                'expected_separation_date' => $equipment->expected_separation_date?->format('Y-m-d'),
                'expected_loading_date' => $equipment->expected_loading_date?->format('Y-m-d'),
                'expected_delivery_date' => $equipment->expected_delivery_date?->format('Y-m-d'),
            ]
        );
        return back()->with('success', 'Equipamento marcado como enviado ao CD.');
    }
    public function createAsset(): View
    {
        return view('bancada-servicos.asset-form', [
            'asset' => new BancadaEquipment(),
            'mode' => 'create',
            'unitOptionsByOrigin' => $this->equipmentUnitOptionsByOrigin(),
            'equipmentTypeOptions' => self::EQUIPMENT_TYPE_OPTIONS,
            'statusOptions' => $this->statusOptionsGrouped(),
        ]);
    }
    public function storeAsset(Request $request)
    {
        $data = $this->validateAsset($request, true);
        DB::transaction(function () use ($data): void {
            $origin = (string) ($data['origem_tipo'] ?? 'unidade');
            $data['status'] = $this->statusFlow->initialStatusForOrigin($origin);
            $data['entrada_status'] = $this->statusFlow->initialEntryStatusForOrigin($origin);
            if ($origin === 'sede') {
                $data['entrada_realizada_em'] = now();
            }
            $asset = BancadaEquipment::create($data);
            BancadaEquipmentStatusHistory::create([
                'bancada_equipment_id' => $asset->id,
                'status' => $asset->status,
                'start_time' => now(),
            ]);
            $this->recordEquipmentEvent(
                equipment: $asset,
                action: 'cadastro_equipamento',
                previousStatus: null,
                newStatus: $asset->status,
                module: 'Bancada',
                observation: 'Equipamento cadastrado.',
                metadata: [
                    'origem_tipo' => $asset->origem_tipo,
                    'entrada_status' => $asset->entrada_status,
                ]
            );
            app(JiraAutomationService::class)->dispatch('equipamento_cadastrado', [
                'equipamento_id' => $asset->id,
                'tipo' => $asset->tipo_equipamento,
                'plaqueta' => $asset->plaqueta,
                'unidade_setor' => $asset->unidade_setor,
                'origem_tipo' => $asset->origem_tipo,
            ]);
        });
        return redirect()->route('bancada-servicos.assets')->with('success', 'Equipamento cadastrado.');
    }
    public function editAsset(BancadaEquipment $equipment): View
    {
        return view('bancada-servicos.asset-form', [
            'asset' => $equipment,
            'mode' => 'edit',
            'unitOptionsByOrigin' => $this->equipmentUnitOptionsByOrigin(),
            'equipmentTypeOptions' => self::EQUIPMENT_TYPE_OPTIONS,
            'statusOptions' => $this->statusOptionsGrouped(),
        ]);
    }
    public function updateAsset(Request $request, BancadaEquipment $equipment)
    {
        $data = $request->validate([
            'tipo_equipamento' => ['required', 'in:' . implode(',', self::EQUIPMENT_TYPE_OPTIONS)],
            'plaqueta' => ['required', 'string', 'max:100'],
            'origem_tipo' => ['required', 'in:unidade,sede'],
            'unidade_setor' => ['required', 'string', 'max:255'],
            'tic' => ['nullable', 'string', 'max:40'],
            'observacao' => ['nullable', 'string', 'max:1000'],
        ]);
        $originOptions = $this->equipmentUnitOptionsByOrigin();
        $allowed = collect($originOptions[$data['origem_tipo']] ?? [])
            ->map(fn ($value) => mb_strtolower(trim((string) $value)))
            ->filter()
            ->values();
        $selectedUnit = mb_strtolower(trim((string) $data['unidade_setor']));
        if ($selectedUnit !== '' && $allowed->isNotEmpty() && ! $allowed->contains($selectedUnit)) {
            abort(422, 'Unidade/Setor inválido para a origem selecionada.');
        }
        $before = [
            'tipo_equipamento' => (string) $equipment->tipo_equipamento,
            'plaqueta' => (string) $equipment->plaqueta,
            'origem_tipo' => (string) $equipment->origem_tipo,
            'unidade_setor' => (string) $equipment->unidade_setor,
            'tic' => (string) ($equipment->tic ?? ''),
            'observacao' => (string) ($equipment->observacao ?? ''),
        ];
        $equipment->update($data);
        $after = [
            'tipo_equipamento' => (string) $equipment->tipo_equipamento,
            'plaqueta' => (string) $equipment->plaqueta,
            'origem_tipo' => (string) $equipment->origem_tipo,
            'unidade_setor' => (string) $equipment->unidade_setor,
            'tic' => (string) ($equipment->tic ?? ''),
            'observacao' => (string) ($equipment->observacao ?? ''),
        ];
        $changes = [];
        foreach ($after as $field => $newValue) {
            $oldValue = $before[$field] ?? '';
            if ($oldValue !== $newValue) {
                $changes[$field] = [
                    'old' => $oldValue !== '' ? $oldValue : null,
                    'new' => $newValue !== '' ? $newValue : null,
                ];
            }
        }
        $this->recordEquipmentEvent(
            equipment: $equipment,
            action: 'equipamento_editado',
            previousStatus: $equipment->status,
            newStatus: $equipment->status,
            module: 'Bancada',
            observation: 'Dados cadastrais do equipamento atualizados.',
            metadata: ['changes' => $changes]
        );
        return back()->with('success', 'Dados do equipamento atualizados.');
    }
    public function updateAssetStatus(Request $request, BancadaEquipment $equipment)
    {
        $data = $request->validate([
            'status' => ['required', 'string', 'max:50'],
            'confirm_peca_recebida' => ['nullable', 'boolean'],
            'peca_nome' => ['nullable', 'string', 'max:255'],
            'peca_quantidade' => ['nullable', 'integer', 'min:1', 'max:999'],
            'peca_origem' => ['nullable', 'in:cd,compra_internet,estoque_ti,dell'],
            'peca_link_compra' => ['nullable', 'url', 'max:1500'],
            'service_tag' => ['nullable', 'string', 'max:100'],
            'terceiros_problema' => ['nullable', 'string', 'max:5000'],
        ]);
        if ($data['status'] === $equipment->status) {
            return back()->with('success', 'Status mantido.');
        }
        $newStatus = $this->statusFlow->normalizeStatus($data['status']) ?? $data['status'];
        $pecaNome = trim((string) ($data['peca_nome'] ?? ''));
        $pecaOrigem = $data['peca_origem'] ?? null;
        $pecaQuantidade = isset($data['peca_quantidade']) ? (int) $data['peca_quantidade'] : null;
        $pecaLinkCompra = trim((string) ($data['peca_link_compra'] ?? ''));
        $serviceTag = trim((string) ($data['service_tag'] ?? ''));
        $terceirosProblema = trim((string) ($data['terceiros_problema'] ?? ''));
        $confirmPecaRecebida = (bool) ($data['confirm_peca_recebida'] ?? false);
        if ($newStatus === BancadaStatusFlowService::STATUS_AGUARDANDO_PECA) {
            if ($pecaNome === '' || $pecaOrigem === null || $pecaQuantidade === null) {
                return back()->with('error', 'Para Aguardando peça, informe peça, quantidade e origem.');
            }
            if ($pecaOrigem === 'compra_internet' && $pecaLinkCompra === '') {
                return back()->with('error', 'Para compra pela internet, informe o link.');
            }
            if ($pecaOrigem === 'dell' && $serviceTag === '') {
                return back()->with('error', 'Para solicitação Dell, informe a ServiceTag.');
            }
        }
        if ($newStatus === BancadaStatusFlowService::STATUS_TERCEIROS && $terceirosProblema === '') {
            return back()->with('error', 'Para enviar para Terceiros, informe a descrição do problema.');
        }
        try {
            $this->statusFlow->assertTransition($equipment, $newStatus);
        } catch (\DomainException $e) {
            return back()->with('error', $e->getMessage());
        }
        try {
            DB::transaction(function () use ($equipment, $newStatus, $pecaNome, $pecaOrigem, $pecaQuantidade, $pecaLinkCompra, $serviceTag, $terceirosProblema, $confirmPecaRecebida): void {
            if ($newStatus === BancadaStatusFlowService::STATUS_AGUARDANDO_PECA) {
                $equipment->peca_nome = $pecaNome;
                $equipment->peca_origem = $pecaOrigem;
                $equipment->peca_quantidade = $pecaQuantidade;
                $equipment->peca_link_compra = $pecaLinkCompra !== '' ? $pecaLinkCompra : null;
                $equipment->service_tag = $serviceTag !== '' ? $serviceTag : null;
                $equipment->peca_fluxo_status = $pecaOrigem === 'estoque_ti' ? 'interno_utilizado' : 'aguardando_adm';
                $equipment->peca_admin_realizado_em = null;
                $equipment->peca_recebida_confirmada_em = null;
                if ($pecaOrigem === 'estoque_ti') {
                    DB::table('bancada_stock_usages')->insert([
                        'bancada_equipment_id' => $equipment->id,
                        'plaqueta' => $equipment->plaqueta,
                        'unidade_setor' => $equipment->unidade_setor,
                        'peca_nome' => $pecaNome,
                        'quantidade' => $pecaQuantidade,
                        'origem' => 'estoque_ti',
                        'used_at' => now(),
                        'status' => 'pendente_debito',
                        'created_at' => now(),
                            'updated_at' => now(),
                    ]);
                    $this->recordEquipmentEvent(
                        equipment: $equipment,
                        action: 'peca_estoque_interno_utilizada',
                        previousStatus: $equipment->status,
                        newStatus: $equipment->status,
                        module: 'Bancada',
                        observation: 'Peça utilizada do estoque interno da TI.',
                        metadata: [
                            'peca' => $pecaNome,
                            'quantidade' => $pecaQuantidade,
                        ]
                    );
                }
            }
            if ($newStatus === BancadaStatusFlowService::STATUS_TERCEIROS) {
                $equipment->terceiros_problema = $terceirosProblema;
                $equipment->terceiros_fluxo_status = 'solicitado';
                $equipment->terceiros_enviado_em = null;
            }
            if ($newStatus === BancadaStatusFlowService::STATUS_BACKUP) {
                $equipment->entrada_status = BancadaStatusFlowService::ENTRY_DONE;
                $equipment->entrada_realizada_em = now();
            }
            if (
                $newStatus === BancadaStatusFlowService::STATUS_MANUTENCAO_REALIZADA
                && $this->statusFlow->normalizeStatus($equipment->status) === BancadaStatusFlowService::STATUS_AGUARDANDO_PECA
                && $confirmPecaRecebida
            ) {
                if ($equipment->peca_origem !== 'estoque_ti' && $equipment->peca_fluxo_status !== 'requisicao_realizada') {
                    throw new \DomainException('A peça ainda não foi marcada como requisitada pelo Administrativo.');
                }
                $equipment->peca_fluxo_status = 'recebida_confirmada';
                $equipment->peca_recebida_confirmada_em = now();
                $this->recordEquipmentEvent(
                    equipment: $equipment,
                    action: match ($equipment->peca_origem) {
                        'cd' => 'peca_cd_recebida',
                        'compra_internet' => 'peca_internet_recebida',
                        'dell' => 'peca_dell_recebida',
                        default => 'peca_recebida_confirmada',
                    },
                    previousStatus: $equipment->status,
                    newStatus: $equipment->status,
                    module: 'Bancada',
                    observation: 'Confirmação de recebimento da peça pela Bancada.',
                    metadata: ['origem_peca' => $equipment->peca_origem]
                );
            }
            $statusAction = match ($newStatus) {
                BancadaStatusFlowService::STATUS_AGUARDANDO_PECA => 'aguardando_peca_solicitado',
                BancadaStatusFlowService::STATUS_TERCEIROS => 'enviado_para_terceiros_solicitado',
                default => 'alteracao_status',
            };
            $statusMetadata = null;
            if ($newStatus === BancadaStatusFlowService::STATUS_AGUARDANDO_PECA) {
                $statusMetadata = [
                    'peca' => $pecaNome,
                    'quantidade' => $pecaQuantidade,
                    'origem' => $pecaOrigem,
                    'link_compra' => $pecaLinkCompra !== '' ? $pecaLinkCompra : null,
                    'service_tag' => $serviceTag !== '' ? $serviceTag : null,
                ];
            }
            if ($newStatus === BancadaStatusFlowService::STATUS_TERCEIROS) {
                $statusMetadata = ['problema' => $terceirosProblema];
            }
                $this->writeStatusChange($equipment, $newStatus, true, $statusAction, 'Bancada', null, $statusMetadata);
            });
        } catch (\DomainException $e) {
            return back()->with('error', $e->getMessage());
        }
        if ($newStatus === BancadaStatusFlowService::STATUS_AGUARDANDO_PECA) {
            app(JiraAutomationService::class)->dispatch('bancada_aguardando_peca', [
                'equipamento_id' => $equipment->id,
                'tipo' => $equipment->tipo_equipamento,
                'plaqueta' => $equipment->plaqueta,
                'unidade_setor' => $equipment->unidade_setor,
                'peca' => $pecaNome,
                'quantidade' => $pecaQuantidade,
                'origem' => $pecaOrigem,
                'link_compra' => $pecaLinkCompra !== '' ? $pecaLinkCompra : null,
                'service_tag' => $serviceTag !== '' ? $serviceTag : null,
            ]);
        } elseif ($newStatus === BancadaStatusFlowService::STATUS_TERCEIROS) {
            app(JiraAutomationService::class)->dispatch('bancada_terceiros_emissao_nota', [
                'equipamento_id' => $equipment->id,
                'tipo' => $equipment->tipo_equipamento,
                'plaqueta' => $equipment->plaqueta,
                'unidade_setor' => $equipment->unidade_setor,
                'problema' => $terceirosProblema,
            ]);
        }
        return back()->with('success', 'Status atualizado.');
    }
    public function markEntryCompleted(BancadaEquipment $equipment)
    {
        if (! $this->isEntryFiscalPending($equipment)) {
            return back()->with('error', 'Entrada fiscal só pode ser concluída para itens em Aguardando Entrada Fiscal.');
        }
        $equipment->update([
            'entrada_status' => BancadaStatusFlowService::ENTRY_DONE,
            'entrada_realizada_em' => now(),
        ]);
        $this->writeStatusChange($equipment, BancadaStatusFlowService::STATUS_EM_BANCADA, true, 'entrada_fiscal_realizada', 'Administrativo');
        app(JiraAutomationService::class)->dispatch('bancada_entrada_realizada', [
            'equipamento_id' => $equipment->id,
            'plaqueta' => $equipment->plaqueta,
            'nota_numero_entrada' => $equipment->nota_numero_entrada,
            'nota_valor_entrada' => $equipment->nota_valor_entrada,
        ]);
        return back()->with('success', 'Entrada fiscal registrada como realizada.');
    }
    public function assetHistory(BancadaEquipment $equipment): View
    {
        $history = $equipment->statusHistory()->orderByDesc('start_time')->get();
        $events = $equipment->events()
            ->with(['performer', 'attachments'])
            ->orderByDesc('created_at')
            ->get();
        return view('bancada-servicos.asset-history', [
            'asset' => $equipment,
            'history' => $history,
            'events' => $events,
        ]);
    }
    public function downloadAttachment(BancadaEquipmentAttachment $attachment)
    {
        abort_unless($this->canAccessBancadaAttachment(), 403);

        $disk = $attachment->storage_disk ?: 'local';
        $path = $attachment->storage_path;
        abort_unless($path && Storage::disk($disk)->exists($path), 404, 'Arquivo de anexo não encontrado.');

        return response()->download(
            Storage::disk($disk)->path($path),
            $attachment->original_name ?: basename($path)
        );
    }

    public function getEquipmentDocuments(BancadaEquipment $equipment): \Illuminate\Http\JsonResponse
    {
        abort_unless($this->canAccessBancadaAttachment(), 403);

        $attachments = $equipment->attachments()
            ->with(['uploader', 'event'])
            ->orderByDesc('uploaded_at')
            ->get();
        $documents = $attachments->map(function (BancadaEquipmentAttachment $attachment) {
            $typeLabels = [
                'nota_entrada' => 'Nota de entrada',
                'nota_remessa' => 'Nota de remessa',
                'orcamento_terceiro' => 'Orçamento terceiro',
                'nota_saida' => 'Nota de saída',
                'outro' => 'Outro',
            ];
            return [
                'id' => $attachment->id,
                'type' => $attachment->attachment_type,
                'type_label' => $typeLabels[$attachment->attachment_type] ?? $attachment->attachment_type,
                'original_name' => $attachment->original_name,
                'uploaded_at_formatted' => optional($attachment->uploaded_at)->format('d/m/Y H:i') ?? '-',
                'uploaded_by_name' => $attachment->uploader?->name ?? 'Sistema',
                'event_action' => $attachment->event?->action ?? null,
                'download_url' => route('bancada-servicos.attachments.download', $attachment),
            ];
        });
        return response()->json([
            'success' => true,
            'equipment_tag' => $equipment->plaqueta,
            'documents' => $documents,
        ]);
    }
    public function updateBackupData(Request $request, BancadaEquipment $equipment)
    {
        abort_unless($equipment->status === 'Backup', 404);
        $data = $request->validate([
            'backup_localizacao' => ['nullable', 'string', 'max:255'],
            'backup_pronto_emprestimo' => ['nullable', 'boolean'],
            'backup_data_formatado' => ['nullable', 'date'],
            'observacao' => ['nullable', 'string', 'max:1000'],
        ]);
        $equipment->update([
            'backup_localizacao' => $data['backup_localizacao'] ?? null,
            'backup_pronto_emprestimo' => (bool) ($data['backup_pronto_emprestimo'] ?? false),
            'backup_data_formatado' => $data['backup_data_formatado'] ?? null,
            'observacao' => $data['observacao'] ?? $equipment->observacao,
        ]);
        return back()->with('success', 'Dados do backup atualizados.');
    }
    public function toggleBackupAvailability(BancadaEquipment $equipment)
    {
        abort_unless($equipment->status === BancadaStatusFlowService::STATUS_BACKUP, 404);
        $before = (bool) $equipment->backup_pronto_emprestimo;
        $after = ! $before;
        $equipment->backup_pronto_emprestimo = $after;
        $equipment->save();
        $this->recordEquipmentEvent(
            equipment: $equipment,
            action: 'backup_disponibilidade_atualizada',
            previousStatus: $equipment->status,
            newStatus: $equipment->status,
            module: 'Bancada',
            observation: 'Disponibilidade de empréstimo do backup atualizada.',
            metadata: [
                'valor_anterior' => $before,
                'valor_novo' => $after,
            ]
        );
        return back()->with('success', 'Disponibilidade de empréstimo atualizada.');
    }
    public function updateDiscardControls(Request $request, BancadaEquipment $equipment)
    {
        abort_unless($equipment->status === BancadaStatusFlowService::STATUS_DESCARTE, 404);
        $data = $request->validate([
            'baixa_realizada' => ['nullable', 'boolean'],
        ]);
        DB::transaction(function () use ($equipment, $data): void {
            $baixa = (bool) ($data['baixa_realizada'] ?? false);
            $beforeBaixa = (bool) $equipment->baixa_realizada;
            // Compatibilidade legada: plaqueta acompanha a baixa.
            $equipment->plaqueta_retirada = $baixa;
            $equipment->plaqueta_retirada_at = $baixa ? now() : null;
            $equipment->plaqueta_retirada_by = $baixa ? Auth::id() : null;
            $equipment->baixa_realizada = $baixa;
            $equipment->baixa_realizada_at = $baixa ? now() : null;
            $equipment->baixa_realizada_by = $baixa ? Auth::id() : null;
            $equipment->save();
            if ($beforeBaixa !== $baixa) {
                $this->recordEquipmentEvent(
                    equipment: $equipment,
                    action: 'descarte_baixa_realizada_atualizada',
                    previousStatus: $equipment->status,
                    newStatus: $equipment->status,
                    module: 'Bancada',
                    observation: 'Controle de baixa realizada atualizado.',
                    metadata: [
                        'baixa_realizada_anterior' => $beforeBaixa,
                        'baixa_realizada_nova' => $baixa,
                    ]
                );
            }
        });
        return back()->with('success', 'Controles de descarte atualizados.');
    }
    public function printLabel(BancadaEquipment $equipment): View
    {
        return view('bancada-servicos.print', ['asset' => $equipment]);
    }
    public function printer(): View
    {
        return view('bancada-servicos.print');
    }
    public function printBackupTemplate(): BinaryFileResponse
    {
        $templatePath = base_path('Etiqueta BKP.nlbl');
        abort_unless(File::exists($templatePath), 404, 'Arquivo de etiqueta de backup não encontrado.');
        return response()->download(
            $templatePath,
            'Etiqueta-BKP.nlbl',
            ['Content-Type' => 'application/octet-stream']
        );
    }
    public function sla(Request $request): View
    {
        $days = (int) $request->query('days', 30);
        if (! in_array($days, [7, 15, 30, 60, 90], true)) {
            $days = 30;
        }
        $from = now()->subDays($days)->startOfDay();
        $openTickets = JiraIssue::query()
            ->where('squad', self::BANCADA_SQUAD)
            ->whereNull('data_hora_resolucao');
        $resolvedInRange = JiraIssue::query()
            ->where('squad', self::BANCADA_SQUAD)
            ->whereNotNull('data_hora_resolucao')
            ->where('data_hora_resolucao', '>=', $from);
        $slaBreached = (clone $openTickets)
            ->where(function ($q): void {
                $q->where('tempo_sla_final_remainingTime', '<=', 0)
                    ->orWhere('sla_remainingTime', '<=', 0);
            });
        $ticketsByStatus = JiraIssue::query()
            ->where('squad', self::BANCADA_SQUAD)
            ->where('data_hora_criacao', '>=', $from)
            ->selectRaw('COALESCE(NULLIF(status, ""), "Sem status") as status_name, COUNT(*) as total')
            ->groupBy('status_name')
            ->orderByDesc('total')
            ->limit(12)
            ->get();
        return view('bancada-servicos.sla', [
            'days' => $days,
            'stats' => [
                'abertos' => (clone $openTickets)->count(),
                'em_alerta' => (clone $slaBreached)->count(),
                'resolvidos_periodo' => (clone $resolvedInRange)->count(),
                'taxa_alerta' => (clone $openTickets)->count() > 0
                    ? round(((clone $slaBreached)->count() / (clone $openTickets)->count()) * 100, 1)
                    : 0.0,
            ],
            'ticketsByStatus' => $ticketsByStatus,
            'recentAlerts' => (clone $openTickets)
                ->where(function ($q): void {
                    $q->where('tempo_sla_final_remainingTime', '<=', 0)
                        ->orWhere('sla_remainingTime', '<=', 0);
                })
                ->orderByDesc(DB::raw('COALESCE(data_hora_atualizacao, data_hora_criacao, updated_at, created_at)'))
                ->limit(20)
                ->get(),
            'jiraTicketBaseUrl' => $this->jiraBancadaTicketBaseUrl(),
        ]);
    }
    public function reports(Request $request): View
    {
        $days = (int) $request->query('days', 30);
        if (! in_array($days, [7, 15, 30, 60, 90], true)) {
            $days = 30;
        }
        $from = now()->subDays($days)->startOfDay();
        $entries = BancadaEquipment::query()
            ->where('data_chegada', '>=', $from->toDateString())
            ->count();
        $exits = BancadaEquipment::query()
            ->whereNotNull('data_saida')
            ->where('data_saida', '>=', $from->toDateString())
            ->count();
        $equipmentByStatus = BancadaEquipment::query()
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->orderByDesc('total')
            ->get();
        $equipmentByType = BancadaEquipment::query()
            ->selectRaw('COALESCE(NULLIF(tipo_equipamento, ""), "Sem tipo") as tipo, COUNT(*) as total')
            ->groupBy('tipo')
            ->orderByDesc('total')
            ->limit(15)
            ->get();
        $equipmentByUnit = BancadaEquipment::query()
            ->selectRaw('COALESCE(NULLIF(unidade_setor, ""), "Sem unidade") as unidade, COUNT(*) as total')
            ->groupBy('unidade')
            ->orderByDesc('total')
            ->limit(20)
            ->get();
        return view('bancada-servicos.reports', [
            'days' => $days,
            'stats' => [
                'ativos' => BancadaEquipment::query()->whereIn('status', self::ACTIVE_STATUSES)->count(),
                'entregues' => BancadaEquipment::query()->where('status', 'Entregue')->count(),
                'descartados' => BancadaEquipment::query()->where('status', BancadaStatusFlowService::STATUS_DESCARTE)->count(),
                'backup' => BancadaEquipment::query()->where('status', BancadaStatusFlowService::STATUS_BACKUP)->count(),
                'entradas_periodo' => $entries,
                'saidas_periodo' => $exits,
            ],
            'equipmentByStatus' => $equipmentByStatus,
            'equipmentByType' => $equipmentByType,
            'equipmentByUnit' => $equipmentByUnit,
        ]);
    }
    public function administrativePanel(): View
    {
        $data = $this->administrativePanelData();
        return view('panels.administrativo.painel', $data);
    }
    public function administrativeOverview(): View
    {
        return view('panels.administrativo.visao-geral', $this->administrativePanelData());
    }
    public function administrativeEntryFiscal(): View
    {
        return view('panels.administrativo.entrada-fiscal', $this->administrativePanelData());
    }
    public function administrativeThirdParties(): View
    {
        return view('panels.administrativo.terceiros', $this->administrativePanelData());
    }
    public function administrativeParts(): View
    {
        return view('panels.administrativo.pecas', $this->administrativePanelData());
    }
    public function administrativeInternalStock(): View
    {
        return view('panels.administrativo.estoque-interno', $this->administrativePanelData());
    }
    public function administrativeOutboundNote(): View
    {
        return view('panels.administrativo.nota-saida', $this->administrativePanelData());
    }
    public function administrativeThirdPartyCompanies(): View
    {
        return view('panels.administrativo.empresas-terceirizadas', $this->administrativePanelData());
    }
    public function administrativeHistory(): View
    {
        return view('panels.administrativo.historico', $this->administrativePanelData());
    }
    public function administrativeProcess(Request $request, BancadaEquipment $equipment)
    {
        $data = $request->validate([
            'action' => ['required', 'in:entrada,peca_requisicao,terceiros_envio,terceiros_retorno_positivo,terceiros_retorno_negativo,terceiros_retorno_info_positivo,terceiros_retorno_info_negativo,terceiros_retorno_fisico,nota_emitida'],
            'nota_documento_entrada' => ['nullable', 'string', 'max:255'],
            'nota_numero_entrada' => ['nullable', 'string', 'max:80'],
            'data_emissao_entrada' => ['nullable', 'date'],
            'nota_valor_entrada' => ['nullable', 'string', 'max:40'],
            'nota_anexo_entrada' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:5120'],
            'nota_documento_saida' => ['nullable', 'string', 'max:255'],
            'nota_numero_saida' => ['nullable', 'string', 'max:80'],
            'nota_anexo_saida' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:5120'],
            'peca_nome' => ['nullable', 'string', 'max:255'],
            'peca_quantidade' => ['nullable', 'integer', 'min:1', 'max:999'],
            'peca_origem' => ['nullable', 'in:cd,compra_internet,estoque_ti,dell'],
            'peca_link_compra' => ['nullable', 'url', 'max:1500'],
            'service_tag' => ['nullable', 'string', 'max:100'],
            'terceiros_problema' => ['nullable', 'string', 'max:5000'],
            'terceiros_empresa' => ['nullable', 'string', 'max:255'],
            'terceiros_cnpj' => ['nullable', 'string', 'max:30'],
            'terceiros_nota_remessa' => ['nullable', 'string', 'max:100'],
            'terceiros_os_numero' => ['nullable', 'string', 'max:100'],
            'terceiros_observacoes' => ['nullable', 'string', 'max:5000'],
            'terceiros_orcamento_anexo' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:5120'],
            'terceiros_nota_orcamento' => ['nullable', 'string', 'max:5000'],
            'terceiros_orcamento_status' => ['nullable', 'in:aprovado,reprovado'],
            'terceiros_valor_reparo' => ['nullable', 'string', 'max:40'],
            'terceiros_retorno_fisico_observacao' => ['nullable', 'string', 'max:5000'],
        ]);
        try {
            DB::transaction(function () use ($request, $equipment, $data): void {
                if ($data['action'] === 'entrada') {
                    if (($equipment->origem_tipo ?? 'unidade') !== 'unidade') {
                        throw new \DomainException('Entrada fiscal administrativa é aplicável somente para origem Unidade.');
                    }
                    if (! $this->isEntryFiscalPending($equipment)) {
                        throw new \DomainException('Entrada fiscal só pode ser processada para itens em Aguardando Entrada Fiscal.');
                    }
                    $equipment->nota_documento_entrada = trim((string) ($data['nota_documento_entrada'] ?? '')) ?: null;
                    $equipment->nota_numero_entrada = trim((string) ($data['nota_numero_entrada'] ?? '')) ?: null;
                    $equipment->data_emissao_entrada = $data['data_emissao_entrada'] ?? null;
                    $equipment->nota_valor_entrada = $this->parseBrazilianMoney($data['nota_valor_entrada'] ?? null);
                    $equipment->entrada_status = BancadaStatusFlowService::ENTRY_DONE;
                    $equipment->entrada_realizada_em = now();
                    $event = $this->writeStatusChange(
                        $equipment,
                        BancadaStatusFlowService::STATUS_EM_BANCADA,
                        true,
                        'entrada_fiscal_realizada',
                        'Administrativo',
                        'Entrada fiscal processada no Administrativo.',
                        [
                            'docto' => $equipment->nota_documento_entrada,
                            'numero_nota' => $equipment->nota_numero_entrada,
                            'data_emissao' => $equipment->data_emissao_entrada?->format('Y-m-d'),
                            'valor' => $equipment->nota_valor_entrada,
                        ]
                    );
                    if ($request->hasFile('nota_anexo_entrada')) {
                        $equipment->nota_anexo_entrada = $this->storeEquipmentAttachment(
                            equipment: $equipment,
                            file: $request->file('nota_anexo_entrada'),
                            attachmentType: 'nota_entrada',
                            eventId: $event->id
                        );
                        $equipment->save();
                    }
                    return;
                }
                if ($data['action'] === 'peca_requisicao') {
                    if (($this->statusFlow->normalizeStatus($equipment->status) !== BancadaStatusFlowService::STATUS_AGUARDANDO_PECA) || $equipment->peca_origem === 'estoque_ti') {
                        throw new \DomainException('Este item não está elegível para requisição administrativa de peça.');
                    }
                    $equipment->peca_admin_realizado_em = now();
                    $equipment->peca_fluxo_status = 'requisicao_realizada';
                    $equipment->save();
                    $action = match ($equipment->peca_origem) {
                        'cd' => 'requisicao_cd_realizada',
                        'compra_internet' => 'pedido_internet_realizado',
                        'dell' => 'pedido_dell_realizado',
                        default => 'requisicao_peca_realizada',
                    };
                    $this->recordEquipmentEvent(
                        equipment: $equipment,
                        action: $action,
                        previousStatus: $equipment->status,
                        newStatus: $equipment->status,
                        module: 'Administrativo',
                        observation: 'Pendência de peça processada pelo Administrativo.',
                        metadata: [
                            'origem_peca' => $equipment->peca_origem,
                            'peca_nome' => $equipment->peca_nome,
                            'peca_quantidade' => $equipment->peca_quantidade,
                        ]
                    );
                    return;
                }
                if ($data['action'] === 'terceiros_envio') {
                    if ($this->statusFlow->normalizeStatus($equipment->status) !== BancadaStatusFlowService::STATUS_TERCEIROS) {
                        throw new \DomainException('Este item não está no status Terceiros.');
                    }
                    // Problema é definido pela bancada no envio para terceiros e não pode ser alterado pelo ADM.
                    $equipment->terceiros_empresa = trim((string) ($data['terceiros_empresa'] ?? '')) ?: null;
                    $equipment->terceiros_cnpj = trim((string) ($data['terceiros_cnpj'] ?? '')) ?: null;
                    $equipment->terceiros_nota_remessa = trim((string) ($data['terceiros_nota_remessa'] ?? '')) ?: null;
                    $equipment->terceiros_os_numero = trim((string) ($data['terceiros_os_numero'] ?? '')) ?: null;
                    $equipment->terceiros_observacoes = trim((string) ($data['terceiros_observacoes'] ?? '')) ?: null;
                    $equipment->terceiros_nota_orcamento = null;
                    $equipment->terceiros_orcamento_status = null;
                    $equipment->terceiros_resultado = null;
                    $equipment->terceiros_valor_reparo = null;
                    $equipment->terceiros_retorno_em = null;
                    $equipment->terceiros_retorno_informado_em = null;
                    $equipment->terceiros_retorno_informado_by = null;
                    $equipment->terceiros_retorno_fisico_em = null;
                    $equipment->terceiros_retorno_fisico_by = null;
                    $equipment->terceiros_retorno_fisico_observacao = null;
                    $equipment->terceiros_orcamento_anexo = null;
                    $equipment->terceiros_fluxo_status = 'enviado_aguardando_informacoes';
                    $equipment->terceiros_enviado_em = now();
                    $equipment->save();
                    $event = $this->recordEquipmentEvent(
                        equipment: $equipment,
                        action: 'terceiro_enviado',
                        previousStatus: $equipment->status,
                        newStatus: $equipment->status,
                        module: 'Administrativo',
                        observation: 'Equipamento enviado para terceiro.',
                        metadata: [
                            'empresa' => $equipment->terceiros_empresa,
                            'cnpj' => $equipment->terceiros_cnpj,
                            'nota_remessa' => $equipment->terceiros_nota_remessa,
                            'observacoes' => $equipment->terceiros_observacoes,
                        ]
                    );
                    if ($request->hasFile('terceiros_orcamento_anexo')) {
                        $equipment->terceiros_orcamento_anexo = $this->storeEquipmentAttachment(
                            equipment: $equipment,
                            file: $request->file('terceiros_orcamento_anexo'),
                            attachmentType: 'nota_remessa',
                            eventId: $event->id
                        );
                        $equipment->save();
                    }
                    return;
                }
                if (in_array($data['action'], ['terceiros_retorno_info_positivo', 'terceiros_retorno_positivo'], true)) {
                    if ($this->statusFlow->normalizeStatus($equipment->status) !== BancadaStatusFlowService::STATUS_TERCEIROS) {
                        throw new \DomainException('Informações de retorno de terceiros só podem ser registradas para itens em Terceiros.');
                    }
                    if (trim((string) ($data['terceiros_os_numero'] ?? '')) === '') {
                        throw new \DomainException('Para registrar as informações do reparo, informe o número da OS da empresa terceira.');
                    }
                    $equipment->terceiros_nota_orcamento = trim((string) ($data['terceiros_nota_orcamento'] ?? '')) ?: null;
                    $equipment->terceiros_valor_reparo = $this->parseBrazilianMoney($data['terceiros_valor_reparo'] ?? null);
                    $equipment->terceiros_orcamento_status = 'aprovado';
                    $equipment->terceiros_resultado = 'aprovada';
                    $equipment->terceiros_os_numero = trim((string) ($data['terceiros_os_numero'] ?? '')) ?: null;
                    $equipment->terceiros_fluxo_status = 'aguardando_retorno_fisico_aprovado';
                    $equipment->terceiros_retorno_em = now();
                    $equipment->terceiros_retorno_informado_em = now();
                    $equipment->terceiros_retorno_informado_by = Auth::id();
                    $equipment->terceiros_retorno_fisico_em = null;
                    $equipment->terceiros_retorno_fisico_by = null;
                    $equipment->terceiros_retorno_fisico_observacao = null;
                    $equipment->save();
                    $event = $this->recordEquipmentEvent(
                        equipment: $equipment,
                        action: 'terceiro_informacoes_reparo_aprovado',
                        previousStatus: $equipment->status,
                        newStatus: $equipment->status,
                        module: 'Administrativo',
                        observation: 'Informações do reparo aprovadas registradas no Administrativo.',
                        metadata: [
                            'os_terceiro' => $equipment->terceiros_os_numero,
                            'resultado' => 'aprovada',
                            'status_previsto' => BancadaStatusFlowService::STATUS_MANUTENCAO_REALIZADA,
                            'orcamento' => $equipment->terceiros_nota_orcamento,
                        ]
                    );
                    if ($request->hasFile('terceiros_orcamento_anexo')) {
                        $equipment->terceiros_orcamento_anexo = $this->storeEquipmentAttachment(
                            equipment: $equipment,
                            file: $request->file('terceiros_orcamento_anexo'),
                            attachmentType: 'orcamento_terceiro',
                            eventId: $event->id
                        );
                        $equipment->save();
                    }
                    return;
                }
                if (in_array($data['action'], ['terceiros_retorno_info_negativo', 'terceiros_retorno_negativo'], true)) {
                    if ($this->statusFlow->normalizeStatus($equipment->status) !== BancadaStatusFlowService::STATUS_TERCEIROS) {
                        throw new \DomainException('Informações de retorno de terceiros só podem ser registradas para itens em Terceiros.');
                    }
                    if (trim((string) ($data['terceiros_os_numero'] ?? '')) === '') {
                        throw new \DomainException('Para registrar as informações do reparo, informe o número da OS da empresa terceira.');
                    }
                    $valorReparo = $this->parseBrazilianMoney($data['terceiros_valor_reparo'] ?? null);
                    $equipment->terceiros_nota_orcamento = trim((string) ($data['terceiros_nota_orcamento'] ?? '')) ?: null;
                    $equipment->terceiros_orcamento_status = 'reprovado';
                    $equipment->terceiros_resultado = 'sem_conserto';
                    $equipment->terceiros_os_numero = trim((string) ($data['terceiros_os_numero'] ?? '')) ?: null;
                    $equipment->terceiros_valor_reparo = $valorReparo;
                    $equipment->terceiros_fluxo_status = 'aguardando_retorno_fisico_reprovado';
                    $equipment->terceiros_retorno_em = now();
                    $equipment->terceiros_retorno_informado_em = now();
                    $equipment->terceiros_retorno_informado_by = Auth::id();
                    $equipment->terceiros_retorno_fisico_em = null;
                    $equipment->terceiros_retorno_fisico_by = null;
                    $equipment->terceiros_retorno_fisico_observacao = null;
                    $equipment->save();
                    $event = $this->recordEquipmentEvent(
                        equipment: $equipment,
                        action: 'terceiro_informacoes_reparo_reprovado',
                        previousStatus: $equipment->status,
                        newStatus: $equipment->status,
                        module: 'Administrativo',
                        observation: 'Informações do reparo reprovadas registradas no Administrativo.',
                        metadata: [
                            'os_terceiro' => $equipment->terceiros_os_numero,
                            'resultado' => 'sem_conserto',
                            'valor_reparo' => $equipment->terceiros_valor_reparo,
                            'status_previsto' => BancadaStatusFlowService::STATUS_SEM_CONSERTO,
                            'orcamento' => $equipment->terceiros_nota_orcamento,
                        ]
                    );
                    if ($request->hasFile('terceiros_orcamento_anexo')) {
                        $equipment->terceiros_orcamento_anexo = $this->storeEquipmentAttachment(
                            equipment: $equipment,
                            file: $request->file('terceiros_orcamento_anexo'),
                            attachmentType: 'orcamento_terceiro',
                            eventId: $event->id
                        );
                        $equipment->save();
                    }
                    return;
                }
                if ($data['action'] === 'terceiros_retorno_fisico') {
                    if ($this->statusFlow->normalizeStatus($equipment->status) !== BancadaStatusFlowService::STATUS_TERCEIROS) {
                        throw new \DomainException('Retorno físico só pode ser confirmado para itens em Terceiros.');
                    }
                    if (! $equipment->terceiros_resultado) {
                        throw new \DomainException('Registre primeiro as informações do reparo antes de confirmar o retorno físico.');
                    }
                    $equipment->terceiros_retorno_fisico_em = now();
                    $equipment->terceiros_retorno_fisico_by = Auth::id();
                    $equipment->terceiros_retorno_fisico_observacao = trim((string) ($data['terceiros_retorno_fisico_observacao'] ?? '')) ?: null;
                    $equipment->terceiros_fluxo_status = 'retorno_fisico_registrado';
                    $equipment->save();

                    $finalStatus = in_array((string) $equipment->terceiros_resultado, ['aprovada', 'aprovado'], true)
                        || (string) ($equipment->terceiros_orcamento_status ?? '') === 'aprovado'
                        ? BancadaStatusFlowService::STATUS_MANUTENCAO_REALIZADA
                        : BancadaStatusFlowService::STATUS_SEM_CONSERTO;

                    $this->writeStatusChange(
                        $equipment,
                        $finalStatus,
                        true,
                        'terceiro_retorno_fisico_registrado',
                        'Administrativo',
                        'Retorno físico do equipamento registrado no Administrativo.',
                        [
                            'os_terceiro' => $equipment->terceiros_os_numero,
                            'resultado' => $equipment->terceiros_resultado,
                            'status_final' => $finalStatus,
                            'observacao' => $equipment->terceiros_retorno_fisico_observacao,
                        ]
                    );
                    return;
                }
                if ($data['action'] === 'nota_emitida') {
                    if (($equipment->origem_tipo ?? 'unidade') !== 'unidade') {
                        throw new \DomainException('Nota de saída é obrigatória apenas para origem Unidade.');
                    }
                    $equipment->nota_documento_saida = trim((string) ($data['nota_documento_saida'] ?? '')) ?: null;
                    $equipment->nota_numero_saida = trim((string) ($data['nota_numero_saida'] ?? '')) ?: null;
                    if ($equipment->nota_numero_saida === null) {
                        throw new \DomainException('Informe o Número da nota de saída.');
                    }
                    $equipment->nota_saida_emitida_em = now();
                    $event = $this->writeStatusChange(
                        $equipment,
                        BancadaStatusFlowService::STATUS_NOTA_FISCAL_EMITIDA,
                        true,
                        'nota_saida_emitida',
                        'Administrativo',
                        'Nota de saída emitida para unidade.',
                        [
                            'numero_nota_saida' => $equipment->nota_numero_saida,
                            'docto_entrada' => $equipment->nota_documento_entrada,
                            'numero_nota_entrada' => $equipment->nota_numero_entrada,
                            'valor_nota_entrada' => $equipment->nota_valor_entrada,
                        ]
                    );
                    if ($request->hasFile('nota_anexo_saida')) {
                        $equipment->nota_anexo_saida = $this->storeEquipmentAttachment(
                            equipment: $equipment,
                            file: $request->file('nota_anexo_saida'),
                            attachmentType: 'nota_saida',
                            eventId: $event->id
                        );
                        $equipment->save();
                    }
                }
            });
        } catch (\DomainException $e) {
            return back()->with('error', $e->getMessage());
        }
        return back()->with('success', 'Processo administrativo concluído e equipamento devolvido para a Bancada.');
    }
    public function storeThirdPartyCompany(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'cnpj' => ['nullable', 'string', 'max:20'],
            'contact' => ['nullable', 'string', 'max:255'],
        ]);
        BancadaThirdPartyCompany::updateOrCreate(
            ['name' => trim($data['name']), 'cnpj' => trim((string) ($data['cnpj'] ?? '')) ?: null],
            ['contact' => trim((string) ($data['contact'] ?? '')) ?: null, 'is_active' => true]
        );
        return back()->with('success', 'Empresa terceirizada cadastrada.');
    }
    public function updateThirdPartyCompany(Request $request, BancadaThirdPartyCompany $company)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'cnpj' => ['nullable', 'string', 'max:20'],
            'contact' => ['nullable', 'string', 'max:255'],
            'is_active' => ['nullable', 'boolean'],
        ]);
        $name = trim((string) $data['name']);
        $cnpj = trim((string) ($data['cnpj'] ?? '')) ?: null;
        $exists = BancadaThirdPartyCompany::query()
            ->where('id', '!=', $company->id)
            ->where('name', $name)
            ->where('cnpj', $cnpj)
            ->exists();
        if ($exists) {
            return back()->with('error', 'Já existe uma empresa com o mesmo Nome e CNPJ.');
        }
        $company->update([
            'name' => $name,
            'cnpj' => $cnpj,
            'contact' => trim((string) ($data['contact'] ?? '')) ?: null,
            'is_active' => isset($data['is_active']) ? (bool) $data['is_active'] : (bool) $company->is_active,
        ]);
        return back()->with('success', 'Empresa terceirizada atualizada.');
    }
    public function toggleThirdPartyCompany(BancadaThirdPartyCompany $company)
    {
        $company->is_active = ! (bool) $company->is_active;
        $company->save();
        return back()->with('success', $company->is_active ? 'Empresa terceirizada ativada.' : 'Empresa terceirizada desativada.');
    }
    private function parseBrazilianMoney(mixed $value): ?float
    {
        if ($value === null) {
            return null;
        }
        $raw = trim((string) $value);
        if ($raw === '') {
            return null;
        }
        $normalized = str_replace('.', '', $raw);
        $normalized = str_replace(',', '.', $normalized);
        if (! is_numeric($normalized)) {
            return null;
        }
        return (float) $normalized;
    }
    private function listAssets(Request $request, array $statuses, string $scope, string $title, bool $isBackup = false): View
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'plaqueta' => ['nullable', 'string', 'max:100'],
            'tipo' => ['nullable', 'string', 'max:100'],
            'unidade_setor' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'string', 'max:60'],
            'data_chegada' => ['nullable', 'date'],
            'origem_tipo' => ['nullable', 'in:unidade,sede'],
            'per_page' => ['nullable', 'integer', 'in:10,25,50'],
            'sort_order' => ['nullable', 'in:newest,oldest'],
        ]);
        $search = trim((string) ($filters['q'] ?? ''));
        $plaqueta = trim((string) ($filters['plaqueta'] ?? ''));
        $tipo = trim((string) ($filters['tipo'] ?? ''));
        $unidadeSetor = trim((string) ($filters['unidade_setor'] ?? ''));
        $statusFilter = trim((string) ($filters['status'] ?? ''));
        $dataChegada = $filters['data_chegada'] ?? null;
        $origemTipo = $filters['origem_tipo'] ?? '';
        $perPage = (int) ($filters['per_page'] ?? 10);
        $sortOrder = $filters['sort_order'] ?? 'newest';
        $query = BancadaEquipment::query()->where(function ($q) use ($statuses): void {
            foreach ($statuses as $status) {
                $normalizedStatus = mb_strtolower(trim((string) $status));
                $q->orWhereRaw('LOWER(TRIM(status)) = ?', [$normalizedStatus]);
            }
        });
        if ($search !== '') {
            $query->where(function ($q) use ($search): void {
                $q->where('tipo_equipamento', 'like', "%{$search}%")
                    ->orWhere('plaqueta', 'like', "%{$search}%")
                    ->orWhere('unidade_setor', 'like', "%{$search}%")
                    ->orWhere('observacao', 'like', "%{$search}%")
                    ->orWhere('tic', 'like', "%{$search}%")
                    ->orWhere('backup_localizacao', 'like', "%{$search}%");
            });
        }
        if ($plaqueta !== '') {
            $query->where('plaqueta', 'like', "%{$plaqueta}%");
        }
        if ($tipo !== '') {
            $query->where('tipo_equipamento', 'like', "%{$tipo}%");
        }
        if ($unidadeSetor !== '') {
            $query->where('unidade_setor', 'like', "%{$unidadeSetor}%");
        }
        if ($statusFilter !== '') {
            $query->where('status', $statusFilter);
        }
        if (! empty($dataChegada)) {
            $query->whereDate('data_chegada', $dataChegada);
        }
        if (in_array($origemTipo, ['unidade', 'sede'], true)) {
            $query->where('origem_tipo', $origemTipo);
        }
        if ($scope === 'equipamentos') {
            // Ativos de bancada: mantém fluxo técnico e remove listas finais.
            $query->where(function ($q): void {
                $q->where('status', '!=', BancadaStatusFlowService::STATUS_NOTA_FISCAL_EMITIDA)
                    ->where(function ($sq): void {
                        $sq->where('status', '!=', BancadaStatusFlowService::STATUS_PRONTO_ENTREGA)
                            ->orWhere('origem_tipo', 'unidade');
                    });
            });
        }
        $assets = $query
            ->when($sortOrder === 'oldest',
                fn ($q) => $q->orderBy('data_chegada', 'asc'),
                fn ($q) => $q->orderBy('data_chegada', 'desc')
            )
            ->paginate($perPage)
            ->withQueryString();
        $availableTransitions = [];
        foreach ($assets->items() as $equipment) {
            $availableTransitions[$equipment->id] = $this->statusFlow->availableTransitions($equipment);
        }
        return view('bancada-servicos.assets-list', [
            'assets' => $assets,
            'jiraTicketBaseUrl' => $this->jiraBancadaTicketBaseUrl(),
            'search' => $search,
            'plaqueta' => $plaqueta,
            'tipo' => $tipo,
            'unidadeSetor' => $unidadeSetor,
            'statusFilter' => $statusFilter,
            'dataChegada' => $dataChegada,
            'origemTipo' => $origemTipo,
            'sortOrder' => $sortOrder,
            'perPage' => $perPage,
            'scope' => $scope,
            'title' => $title,
            'isBackup' => $isBackup,
            'equipmentTypeOptions' => self::EQUIPMENT_TYPE_OPTIONS,
            'statusOptions' => $this->statusOptionsGrouped(),
            'unitOptionsByOrigin' => $this->equipmentUnitOptionsByOrigin(),
            'availableTransitions' => $availableTransitions,
        ]);
    }
    private function administrativePanelData(): array
    {
        $notaSaidaSearch = trim((string) request()->query('nota_plaqueta', ''));
        $equipmentSearchPlaqueta = trim((string) request()->query('equipment_plaqueta', ''));
        $equipmentSearchNormalized = $this->normalizePlaquetaForSearch($equipmentSearchPlaqueta);

        $pendingEntry = BancadaEquipment::query()
            ->whereIn('entrada_status', ['Aguardando Entrada', BancadaStatusFlowService::ENTRY_PENDING])
            ->where('origem_tipo', 'unidade')
            ->latest('updated_at')
            ->get();
        $pendingThirdParty = BancadaEquipment::query()
            ->where('status', BancadaStatusFlowService::STATUS_TERCEIROS)
            ->with(['attachments' => function ($query): void {
                $query->orderByDesc('uploaded_at');
            }])
            ->latest('updated_at')
            ->get();
        $pendingParts = BancadaEquipment::query()
            ->where('status', BancadaStatusFlowService::STATUS_AGUARDANDO_PECA)
            ->whereIn('peca_origem', ['cd', 'compra_internet', 'dell'])
            ->latest('updated_at')
            ->get();
        $internalStockReplenishments = BancadaEquipment::query()
            ->where('status', BancadaStatusFlowService::STATUS_AGUARDANDO_PECA)
            ->where('peca_origem', 'estoque_ti')
            ->latest('updated_at')
            ->get();
        $pendingOutboundNote = BancadaEquipment::query()
            ->where('status', BancadaStatusFlowService::STATUS_PRONTO_ENTREGA)
            ->where('origem_tipo', 'unidade')
            ->when($notaSaidaSearch !== '', function ($query) use ($notaSaidaSearch): void {
                $query->where(function ($sq) use ($notaSaidaSearch): void {
                    $sq->where('plaqueta', 'like', "%{$notaSaidaSearch}%")
                        ->orWhere('tipo_equipamento', 'like', "%{$notaSaidaSearch}%")
                        ->orWhere('unidade_setor', 'like', "%{$notaSaidaSearch}%");
                });
            })
            ->latest('updated_at')
            ->get();
        $equipmentSearchRecord = null;
        $equipmentSearchExactMatch = false;
        $equipmentSearchResults = collect();
        if ($equipmentSearchPlaqueta !== '') {
            $equipmentSearchQuery = BancadaEquipment::query()
                ->with([
                    'attachments' => fn ($query) => $query->with(['uploader', 'event'])->orderByDesc('uploaded_at'),
                    'events' => fn ($query) => $query->with(['performer', 'attachments'])->orderByDesc('created_at'),
                ]);

            $equipmentSearchExactMatch = (bool) (clone $equipmentSearchQuery)
                ->whereRaw('LOWER(TRIM(REPLACE(REPLACE(REPLACE(plaqueta, ".", ""), "-", ""), "/", ""))) = ?', [$equipmentSearchNormalized])
                ->exists();

            $equipmentSearchResults = (clone $equipmentSearchQuery)
                ->where(function ($query) use ($equipmentSearchPlaqueta, $equipmentSearchNormalized): void {
                    $query->whereRaw('LOWER(TRIM(plaqueta)) = ?', [mb_strtolower($equipmentSearchPlaqueta)])
                        ->orWhereRaw('LOWER(TRIM(REPLACE(REPLACE(REPLACE(plaqueta, ".", ""), "-", ""), "/", ""))) = ?', [$equipmentSearchNormalized])
                        ->orWhereRaw('LOWER(plaqueta) like ?', ['%' . mb_strtolower($equipmentSearchPlaqueta) . '%']);

                    if ($equipmentSearchNormalized !== '') {
                        $query->orWhereRaw('LOWER(REPLACE(REPLACE(REPLACE(plaqueta, ".", ""), "-", ""), "/", "")) like ?', ['%' . $equipmentSearchNormalized . '%']);
                    }
                })
                ->orderByRaw('CASE WHEN LOWER(TRIM(REPLACE(REPLACE(REPLACE(plaqueta, ".", ""), "-", ""), "/", ""))) = ? THEN 0 ELSE 1 END', [$equipmentSearchNormalized])
                ->orderByDesc('updated_at')
                ->limit(10)
                ->get();

            $equipmentSearchRecord = $equipmentSearchResults->first();
        }
        $administrativeHistory = BancadaEquipmentEvent::query()
            ->with(['equipment', 'performer'])
            ->where('module', 'Administrativo')
            ->whereIn('action', [
                'entrada_fiscal_realizada',
                'terceiro_enviado',
                'terceiro_informacoes_reparo_aprovado',
                'terceiro_informacoes_reparo_reprovado',
                'terceiro_retorno_fisico_registrado',
                'terceiro_retorno_positivo',
                'terceiro_retorno_negativo',
                'requisicao_cd_realizada',
                'pedido_internet_realizado',
                'pedido_dell_realizado',
                'nota_saida_emitida',
            ])
            ->latest('created_at')
            ->limit(200)
            ->get();
        $activeThirdPartyCompaniesCount = BancadaThirdPartyCompany::query()
            ->where('is_active', true)
            ->count();
        $thirdPartyCompanies = BancadaThirdPartyCompany::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get();
        $allThirdPartyCompanies = BancadaThirdPartyCompany::query()
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->get();
        return [
            'pendingEntry' => $pendingEntry,
            'pendingThirdParty' => $pendingThirdParty,
            'pendingParts' => $pendingParts,
            'internalStockReplenishments' => $internalStockReplenishments,
            'pendingOutboundNote' => $pendingOutboundNote,
            'notaSaidaSearch' => $notaSaidaSearch,
            'equipmentSearchPlaqueta' => $equipmentSearchPlaqueta,
            'equipmentSearchRecord' => $equipmentSearchRecord,
            'equipmentSearchResults' => $equipmentSearchResults,
            'equipmentSearchExactMatch' => $equipmentSearchExactMatch,
            'activeThirdPartyCompaniesCount' => $activeThirdPartyCompaniesCount,
            'thirdPartyCompanies' => $thirdPartyCompanies,
            'allThirdPartyCompanies' => $allThirdPartyCompanies,
            'administrativeHistory' => $administrativeHistory,
            'adminSummary' => [
                'entrada_fiscal' => $pendingEntry->count(),
                'terceiros' => $pendingThirdParty->count(),
                'pecas' => $pendingParts->count(),
                'estoque_interno' => $internalStockReplenishments->count(),
                'nota_saida' => $pendingOutboundNote->count(),
                'empresas_ativas' => $activeThirdPartyCompaniesCount,
                'historico' => $administrativeHistory->count(),
            ],
        ];
    }

    private function normalizePlaquetaForSearch(string $value): string
    {
        $value = mb_strtolower(trim($value));
        if ($value === '') {
            return '';
        }

        return preg_replace('/[\s\.\-\/_]+/u', '', $value) ?: '';
    }
    private function validateAsset(Request $request, bool $isCreate, ?int $id = null): array
    {
        $rules = [
            'tipo_equipamento' => ['required', 'in:' . implode(',', self::EQUIPMENT_TYPE_OPTIONS)],
            'plaqueta' => ['required', 'string', 'max:100'],
            'unidade_setor' => ['required', 'string', 'max:255'],
            'origem_tipo' => ['required', 'in:unidade,sede'],
            'data_chegada' => ['required', 'date'],
            'status' => ['nullable', 'string', 'max:50'],
            'entrada_status' => ['nullable', 'in:Aguardando Entrada,Aguardando Entrada Fiscal,Entrada Realizada'],
            'nota_documento_entrada' => ['nullable', 'string', 'max:255'],
            'nota_numero_entrada' => ['nullable', 'string', 'max:80'],
            'nota_valor_entrada' => ['nullable', 'numeric', 'min:0'],
            'nota_anexo_entrada' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:5120'],
            'observacao' => ['nullable', 'string', 'max:1000'],
            'tic' => ['nullable', 'string', 'max:40'],
            'peca_nome' => ['nullable', 'string', 'max:255'],
            'peca_quantidade' => ['nullable', 'integer', 'min:1', 'max:999'],
            'peca_origem' => ['nullable', 'in:cd,compra_internet,estoque_ti,dell'],
            'peca_link_compra' => ['nullable', 'url', 'max:1500'],
            'service_tag' => ['nullable', 'string', 'max:100'],
            'backup_localizacao' => ['nullable', 'string', 'max:255'],
            'backup_pronto_emprestimo' => ['nullable', 'boolean'],
            'backup_data_formatado' => ['nullable', 'date'],
            'terceiros_nota_orcamento' => ['nullable', 'string', 'max:5000'],
            'terceiros_problema' => ['nullable', 'string', 'max:5000'],
            'terceiros_empresa' => ['nullable', 'string', 'max:255'],
            'terceiros_nota_remessa' => ['nullable', 'string', 'max:100'],
            'terceiros_os_numero' => ['nullable', 'string', 'max:100'],
            'terceiros_orcamento_anexo' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:5120'],
            'terceiros_observacoes' => ['nullable', 'string', 'max:5000'],
            'terceiros_resultado' => ['nullable', 'in:aprovada,negada,sem_conserto'],
            'terceiros_orcamento_status' => ['nullable', 'in:aprovado,reprovado'],
        ];
        $data = $request->validate($rules);
        $data['backup_pronto_emprestimo'] = (bool) ($data['backup_pronto_emprestimo'] ?? false);
        $origin = $data['origem_tipo'] ?? 'unidade';
        $data['status'] = $this->statusFlow->normalizeStatus(
            trim((string) ($data['status'] ?? '')) ?: $this->statusFlow->initialStatusForOrigin($origin)
        ) ?? $this->statusFlow->initialStatusForOrigin($origin);
        $data['entrada_status'] = $this->statusFlow->normalizeEntryStatus(
            trim((string) ($data['entrada_status'] ?? '')) ?: $this->statusFlow->initialEntryStatusForOrigin($origin)
        );
        $originOptions = $this->equipmentUnitOptionsByOrigin();
        $allowed = collect($originOptions[$origin] ?? [])
            ->map(fn ($value) => mb_strtolower(trim((string) $value)))
            ->filter()
            ->values();
        $selectedUnit = mb_strtolower(trim((string) ($data['unidade_setor'] ?? '')));
        if ($selectedUnit !== '' && $allowed->isNotEmpty() && ! $allowed->contains($selectedUnit)) {
            abort(422, 'Unidade/Setor inválido para a origem selecionada.');
        }
        if (($data['status'] ?? '') === BancadaStatusFlowService::STATUS_AGUARDANDO_PECA) {
            $request->validate([
                'peca_nome' => ['required', 'string', 'max:255'],
                'peca_quantidade' => ['required', 'integer', 'min:1', 'max:999'],
                'peca_origem' => ['required', 'in:cd,compra_internet,estoque_ti,dell'],
            ]);
            if (($data['peca_origem'] ?? null) === 'compra_internet') {
                $request->validate(['peca_link_compra' => ['required', 'url', 'max:1500']]);
            }
            if (($data['peca_origem'] ?? null) === 'dell') {
                $request->validate(['service_tag' => ['required', 'string', 'max:100']]);
            }
        }
        if (($data['status'] ?? '') === BancadaStatusFlowService::STATUS_TERCEIROS) {
            $request->validate([
                'terceiros_problema' => ['required', 'string', 'max:5000'],
            ]);
        }
        if ($request->hasFile('nota_anexo_entrada')) {
            $data['nota_anexo_entrada'] = $request->file('nota_anexo_entrada')->store('bancada/notas-entrada', 'public');
        } else {
            unset($data['nota_anexo_entrada']);
        }
        if ($request->hasFile('terceiros_orcamento_anexo')) {
            $data['terceiros_orcamento_anexo'] = $request->file('terceiros_orcamento_anexo')->store('bancada/terceiros-orcamentos', 'public');
        } else {
            unset($data['terceiros_orcamento_anexo']);
        }
        return $data;
    }
    private function writeStatusChange(
        BancadaEquipment $equipment,
        string $newStatus,
        bool $persistEquipment,
        string $action = 'alteracao_status',
        string $module = 'Bancada',
        ?string $observation = null,
        ?array $metadata = null
    ): BancadaEquipmentEvent
    {
        $currentStatus = $this->statusFlow->normalizeStatus($equipment->status) ?? $equipment->status;
        $targetStatus = $this->statusFlow->normalizeStatus($newStatus) ?? $newStatus;
        $this->statusFlow->assertTransition($equipment, $targetStatus);
        BancadaEquipmentStatusHistory::query()
            ->where('bancada_equipment_id', $equipment->id)
            ->whereNull('end_time')
            ->update(['end_time' => now()]);
        $equipment->status = $targetStatus;
        if (in_array($targetStatus, self::CLOSED_STATUSES, true) && ! $equipment->data_saida) {
            $equipment->data_saida = now();
        } elseif (! in_array($targetStatus, self::CLOSED_STATUSES, true)) {
            $equipment->data_saida = null;
        }
        if ($persistEquipment) {
            $equipment->save();
        }
        BancadaEquipmentStatusHistory::create([
            'bancada_equipment_id' => $equipment->id,
            'status' => $targetStatus,
            'start_time' => now(),
        ]);
        return $this->recordEquipmentEvent(
            equipment: $equipment,
            action: $action,
            previousStatus: $currentStatus,
            newStatus: $targetStatus,
            module: $module,
            observation: $observation,
            metadata: $metadata
        );
    }
    private function recordEquipmentEvent(
        BancadaEquipment $equipment,
        string $action,
        ?string $previousStatus,
        ?string $newStatus,
        string $module,
        ?string $observation = null,
        ?array $metadata = null
    ): BancadaEquipmentEvent {
        return BancadaEquipmentEvent::create([
            'bancada_equipment_id' => $equipment->id,
            'previous_status' => $previousStatus,
            'new_status' => $newStatus,
            'action' => $action,
            'module' => $module,
            'performed_by' => Auth::id(),
            'observation' => $observation,
            'metadata' => $metadata,
        ]);
    }
    private function storeEquipmentAttachment(BancadaEquipment $equipment, \Illuminate\Http\UploadedFile $file, string $attachmentType, ?int $eventId): string
    {
        $attachment = $this->attachmentService->store(
            equipment: $equipment,
            file: $file,
            attachmentType: $attachmentType,
            eventId: $eventId,
            disk: 'local'
        );
        return $attachment->storage_path;
    }
    private function equipmentUnitOptionsByOrigin(): array
    {
        $units = DB::table('unidades')
            ->whereNotNull('unidade')
            ->whereRaw('TRIM(unidade) <> ""')
            ->orderBy('unidade')
            ->pluck('unidade')
            ->unique()
            ->values();
        $departments = DB::table('departamentos')
            ->whereNotNull('nome')
            ->whereRaw('TRIM(nome) <> ""')
            ->orderBy('nome')
            ->pluck('nome')
            ->unique()
            ->values();
        return [
            'unidade' => $units,
            'sede' => $departments,
        ];
    }
    private function maloteUnitOptions()
    {
        try {
            $conn = DB::connection('intranet_cocari');
            if (! Schema::connection('intranet_cocari')->hasTable('cadUnicoop')) {
                return collect($this->equipmentUnitOptionsByOrigin()['unidade']);
            }
            $query = $conn->table('cadUnicoop')
                ->select('Nome')
                ->whereNotNull('Nome')
                ->where('Nome', '<>', '')
                ->orderBy('Nome');
            if (Schema::connection('intranet_cocari')->hasColumn('cadUnicoop', 'Ativo')) {
                $query->where('Ativo', 1);
            }
            $units = $query->pluck('Nome')->filter(fn ($v) => trim((string) $v) !== '')->unique()->values();
            if ($units->isEmpty()) {
                return collect($this->equipmentUnitOptionsByOrigin()['unidade']);
            }
            return $units;
        } catch (\Throwable $e) {
            return collect($this->equipmentUnitOptionsByOrigin()['unidade']);
        }
    }
    private function findActiveRouteForUnit(?string $unitLabel): ?BancadaMaloteRoute
    {
        $needle = mb_strtolower(trim((string) $unitLabel));
        if ($needle === '') {
            return null;
        }
        return BancadaMaloteRoute::query()
            ->where('ativo', true)
            ->whereHas('units', function ($query) use ($needle): void {
                $query->whereRaw('LOWER(TRIM(unit_label)) = ?', [$needle]);
            })
            ->with('units')
            ->orderBy('ordem')
            ->first();
    }
    private function assertNoActiveRouteUnitConflict(array $units, ?int $ignoreRouteId = null): void
    {
        $normalizedUnits = collect($units)
            ->map(fn ($unit) => mb_strtolower(trim((string) $unit)))
            ->filter()
            ->unique()
            ->values();
        if ($normalizedUnits->isEmpty()) {
            return;
        }
        $conflicts = BancadaMaloteRouteUnit::query()
            ->select('unit_label')
            ->whereHas('route', function ($query) use ($ignoreRouteId): void {
                $query->where('ativo', true);
                if ($ignoreRouteId !== null) {
                    $query->where('id', '<>', $ignoreRouteId);
                }
            })
            ->get()
            ->filter(function ($unit) use ($normalizedUnits): bool {
                return $normalizedUnits->contains(mb_strtolower(trim((string) $unit->unit_label)));
            })
            ->pluck('unit_label')
            ->unique()
            ->values();
        if ($conflicts->isNotEmpty()) {
            abort(422, 'As seguintes unidades já possuem rota ativa: ' . $conflicts->implode(', '));
        }
    }
    private function statusOptionsGrouped(): array
    {
        return [
            'operacionais' => [
                BancadaStatusFlowService::STATUS_AGUARDANDO_ENTRADA_FISCAL,
                BancadaStatusFlowService::STATUS_EM_BANCADA,
                BancadaStatusFlowService::STATUS_TERCEIROS,
                BancadaStatusFlowService::STATUS_AGUARDANDO_PECA,
                BancadaStatusFlowService::STATUS_EM_MANUTENCAO,
                BancadaStatusFlowService::STATUS_MANUTENCAO_REALIZADA,
                'Manutenção negada',
                BancadaStatusFlowService::STATUS_SEM_CONSERTO,
                BancadaStatusFlowService::STATUS_PRONTO_ENTREGA,
                BancadaStatusFlowService::STATUS_NOTA_FISCAL_EMITIDA,
                BancadaStatusFlowService::STATUS_ENTREGUE,
            ],
            'arquivamento' => [
                BancadaStatusFlowService::STATUS_BACKUP,
                BancadaStatusFlowService::STATUS_DESCARTE,
            ],
        ];
    }
    private function jiraBancadaTicketBaseUrl(): string
    {
        return rtrim(
            (string) config(
                'services.jira.bancada_ticket_base_url',
                'https://cocari.atlassian.net/jira/servicedesk/projects/TIC/queues/custom/68'
            ),
            '/'
        );
    }
    private function maloteRoutesHasObservationColumn(): bool
    {
        static $hasColumn = null;
        if ($hasColumn !== null) {
            return $hasColumn;
        }
        try {
            $hasColumn = Schema::hasColumn('bancada_malote_routes', 'observacao');
        } catch (\Throwable $e) {
            $hasColumn = false;
        }
        return $hasColumn;
    }
    private function isEntryFiscalPending(BancadaEquipment $equipment): bool
    {
        $normalizedStatus = $this->statusFlow->normalizeStatus((string) $equipment->status) ?? (string) $equipment->status;
        if ($normalizedStatus === BancadaStatusFlowService::STATUS_AGUARDANDO_ENTRADA_FISCAL) {
            return true;
        }
        $entryStatus = $this->statusFlow->normalizeEntryStatus((string) ($equipment->entrada_status ?? ''));
        return $entryStatus === BancadaStatusFlowService::ENTRY_PENDING;
    }
    private function buildDeliveryRouteDashboardData(): array
    {
        $pendingCd = [
            'today' => [],
            'upcoming' => [],
            'unmapped' => [],
        ];
        $items = BancadaEquipment::query()
            ->where('origem_tipo', 'unidade')
            ->whereIn('status', [BancadaStatusFlowService::STATUS_NOTA_FISCAL_EMITIDA, BancadaStatusFlowService::STATUS_PRONTO_ENTREGA])
            ->orderBy('updated_at')
            ->get();
        $today = today();
        foreach ($items as $equipment) {
            $route = $this->findActiveRouteForUnit($equipment->unidade_setor);
            $schedule = $route ? $this->deliveryScheduleService->nextDates($route, $today->toImmutable()) : null;
            $row = [
                'equipment' => $equipment,
                'route' => $route,
                'schedule' => $schedule,
            ];
            if (! $route || ! $schedule) {
                $pendingCd['unmapped'][] = $row;
                continue;
            }
            $separation = $schedule['separation']->toDateString();
            if ($separation === $today->toDateString()) {
                $pendingCd['today'][] = $row;
            } else {
                $pendingCd['upcoming'][] = $row;
            }
        }
        return $pendingCd;
    }

    private function canAccessBancadaAttachment(): bool
    {
        $user = Auth::user();

        if (! $user) {
            return false;
        }

        if ((int) $user->id === 2) {
            return true;
        }

        return method_exists($user, 'hasModuleAccess')
            && (
                $user->hasModuleAccess('bancada')
                || $user->hasModuleAccess('administrativo')
            );
    }
}
