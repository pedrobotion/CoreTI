<?php

namespace App\Http\Controllers;

use App\Models\CmdbAsset;
use App\Models\CmdbAssetDocument;
use App\Models\CmdbAssetHistory;
use App\Models\CmdbConnector;
use App\Models\CmdbManufacturer;
use App\Models\CmdbModel;
use App\Models\CmdbRack;
use App\Models\CmdbService;
use App\Models\CmdbServer;
use App\Models\Departamento;
use App\Models\Unidade;
use App\Models\User;
use App\Services\CmdbAssetDetailService;
use App\Services\CmdbAssetHistoryService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Illuminate\Support\Arr;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

class CmdbAssetController extends Controller
{
    private const TYPES = [
        'server' => 'Servidor físico',
        'virtual_machine' => 'Máquina virtual',
        'database' => 'Banco de dados',
        'switch' => 'Switch',
        'firewall' => 'Firewall',
        'sonicwall' => 'Sonicwall',
        'storage' => 'Storage',
        'ups' => 'Nobreak / UPS',
        'link' => 'Link de internet',
        'circuit' => 'Circuito',
        'rack' => 'Rack',
        'certificate' => 'Certificado SSL',
        'backup' => 'Backup',
        'other' => 'Outro',
    ];

    private const STATUSES = ['active', 'inactive', 'maintenance', 'decommissioned', 'planned'];
    private const STATUS_LABELS = [
        'active' => 'Ativo',
        'inactive' => 'Inativo',
        'maintenance' => 'Em manutenção',
        'decommissioned' => 'Descomissionado',
        'planned' => 'Planejado',
    ];

    private const CRITICALITIES = ['low', 'medium', 'high', 'critical'];
    private const CRITICALITY_LABELS = [
        'low' => 'Baixa',
        'medium' => 'Média',
        'high' => 'Alta',
        'critical' => 'Crítica',
    ];

    private const ENVIRONMENTS = ['production', 'homologation', 'development', 'test'];
    private const ENVIRONMENT_LABELS = [
        'production' => 'Produção',
        'homologation' => 'Homologação',
        'development' => 'Desenvolvimento',
        'test' => 'Teste',
    ];
    private const HOSTNAME_DRIVEN_TYPES = [
        'server',
        'virtual_machine',
        'database',
        'switch',
        'firewall',
        'sonicwall',
        'storage',
        'ups',
    ];
    private const VIRTUAL_MACHINE_UNIT_ID = 1;
    private const VIRTUAL_MACHINE_LOCATION = 'Rua Lord Lovat, 420, Jardim Esplanada';

    private const DATABASE_TYPES = ['PostgreSQL', 'SQL Server', 'MySQL', 'MariaDB', 'Oracle', 'MongoDB', 'Redis', 'Outro'];
    private const NETWORK_DEVICE_TYPES = ['Switch', 'Access Point', 'Roteador', 'Controller Wi-Fi', 'Outro'];
    private const FIREWALL_TYPES = ['Firewall', 'Sonicwall', 'Outro'];
    private const STORAGE_TYPES = ['SAN', 'NAS', 'DAS', 'HCI', 'Outro'];

    public function __construct(
        private readonly CmdbAssetDetailService $detailService,
        private readonly CmdbAssetHistoryService $historyService,
    )
    {
    }

    public function index(Request $request): View
    {
        if (! Schema::hasTable('cmdb_assets')) {
            return view('cmdb.assets.index', [
                'assets' => collect(),
                'types' => self::TYPES,
                'filters' => [],
                'setupPending' => true,
                'title' => 'Ativos',
                'statusLabels' => self::STATUS_LABELS,
                'criticalityLabels' => self::CRITICALITY_LABELS,
            ]);
        }

        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
            'type' => ['nullable', Rule::in(array_keys(self::TYPES))],
            'asset_type' => ['nullable', Rule::in(array_keys(self::TYPES))],
            'view' => ['nullable', Rule::in(['vms', 'hosts', 'physical', 'other'])],
            'status' => ['nullable', Rule::in(self::STATUSES)],
            'criticality' => ['nullable', Rule::in(self::CRITICALITIES)],
            'environment' => ['nullable', Rule::in(self::ENVIRONMENTS)],
            'rack_id' => ['nullable', 'integer', 'exists:cmdb_racks,id'],
            'service' => ['nullable', 'string', 'max:120'],
            'service_id' => ['nullable', 'integer', 'exists:cmdb_services,id'],
            'responsible_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'source_type' => ['nullable', 'string', 'max:40'],
            'source_connector_id' => ['nullable', 'integer', 'exists:cmdb_connectors,id'],
            'backup_status' => ['nullable', Rule::in(['protected', 'overdue', 'failed', 'unprotected', 'unknown'])],
            'without_ip' => ['nullable', 'boolean'],
            'without_service' => ['nullable', 'boolean'],
            'without_responsible' => ['nullable', 'boolean'],
            'per_page' => ['nullable', 'integer', 'in:10,15,25,50'],
        ]);

        $assets = $this->filteredQuery($filters)->orderByDesc('created_at')->paginate((int) ($filters['per_page'] ?? 15))->withQueryString();

        return view('cmdb.assets.index', [
            'assets' => $assets,
            'types' => self::TYPES,
            'racks' => Schema::hasTable('cmdb_racks') ? CmdbRack::query()->orderBy('name')->get(['id', 'name']) : collect(),
            'filters' => $filters,
            'setupPending' => false,
            'title' => 'Ativos',
            'statusLabels' => self::STATUS_LABELS,
            'criticalityLabels' => self::CRITICALITY_LABELS,
            'services' => CmdbService::query()->where('active', true)->orderBy('name')->get(),
            'users' => User::query()->orderBy('name')->get(['id', 'name']),
            'connectors' => CmdbConnector::query()->orderBy('name')->get(['id', 'name', 'connector_type']),
            'backupStatuses' => [
                'protected' => 'Protegidas',
                'overdue' => 'Atrasadas',
                'failed' => 'Com falha',
                'unprotected' => 'Não protegidas',
                'unknown' => 'Desconhecidas',
            ],
            'hasBackupTable' => Schema::hasTable('cmdb_asset_backups'),
        ]);
    }

    public function export(Request $request)
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:120'], 'type' => ['nullable', Rule::in(array_keys(self::TYPES))],
            'asset_type' => ['nullable', Rule::in(array_keys(self::TYPES))], 'view' => ['nullable', Rule::in(['vms', 'hosts', 'physical', 'other'])],
            'status' => ['nullable', Rule::in(self::STATUSES)], 'criticality' => ['nullable', Rule::in(self::CRITICALITIES)],
            'environment' => ['nullable', Rule::in(self::ENVIRONMENTS)], 'rack_id' => ['nullable', 'integer'],
            'service' => ['nullable', 'string', 'max:120'], 'service_id' => ['nullable', 'integer'], 'responsible_user_id' => ['nullable', 'integer'],
            'source_type' => ['nullable', 'string', 'max:40'], 'source_connector_id' => ['nullable', 'integer'], 'backup_status' => ['nullable', Rule::in(['protected', 'overdue', 'failed', 'unprotected', 'unknown'])],
            'without_ip' => ['nullable', 'boolean'], 'without_service' => ['nullable', 'boolean'], 'without_responsible' => ['nullable', 'boolean'],
        ]);
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $hasBackupTable = Schema::hasTable('cmdb_asset_backups');
        $sheet->fromArray([
            'ID',
            'Ativo',
            'Hostname',
            'Tipo',
            'Status',
            'Criticidade',
            'Ambiente',
            'Fabricante',
            'Modelo',
            'Número de série',
            'Patrimônio',
            'IP principal',
            'Sistema operacional',
            'Localização',
            'Serviço cadastrado',
            'Tipo de serviço',
            'Serviços / descrição',
            'Estado de energia',
            'Status operacional',
            'Espaço usado (GB)',
            'Empresa',
            'Política de backup',
            'Retenção de backup',
            'Publicação web',
            'Status do backup',
            'Job de backup',
            'Repositório',
            'Último backup realizado',
            'Última sincronização do backup',
            'Responsável',
            'Origem',
            'Última sincronização',
            'Observações',
        ], null, 'A1');
        $row = 2;
        $this->filteredQuery($filters)->orderBy('id')->chunk(500, function ($assets) use (&$row, $sheet, $hasBackupTable): void {
            foreach ($assets as $asset) {
                $details = $asset->serverDetails;
                $backup = $hasBackupTable
                    ? $asset->backupRecords->sortByDesc('last_synced_at')->first()
                    : null;

                $sheet->fromArray([
                    $asset->id,
                    $asset->hostname ?: $asset->name,
                    $asset->hostname,
                    self::TYPES[$asset->asset_type] ?? $asset->asset_type,
                    self::STATUS_LABELS[$asset->status] ?? $asset->status,
                    self::CRITICALITY_LABELS[$asset->criticality] ?? $asset->criticality,
                    self::ENVIRONMENT_LABELS[$asset->environment] ?? $asset->environment,
                    $asset->manufacturer,
                    $asset->model,
                    $asset->serial_number,
                    $asset->patrimony_number,
                    $asset->main_ip,
                    $asset->operating_system,
                    $asset->location,
                    $details?->service?->name,
                    $details?->service_type,
                    $details?->service_description,
                    $details?->power_state,
                    $details?->operational_status,
                    $details?->used_space_gb,
                    $details?->company_name,
                    $details?->backup_policy,
                    $details?->backup_retention,
                    $details?->web_publication === null ? null : ($details->web_publication ? 'Sim' : 'Não'),
                    $backup ? ($backup->protection_status ?: 'Desconhecido') : 'Sem sincronização',
                    $backup?->job_name,
                    $backup?->repository_name,
                    optional($backup?->last_successful_backup_at)->format('d/m/Y H:i'),
                    optional($backup?->last_synced_at)->format('d/m/Y H:i'),
                    $asset->responsibleUser?->name,
                    $asset->source_type,
                    optional($asset->last_synced_at)->format('d/m/Y H:i'),
                    $asset->notes,
                ], null, 'A' . $row++);
            }
        });
        $sheet->freezePane('A2');
        $sheet->setAutoFilter('A1:AG1');
        $sheet->getStyle('A1:AG1')->getFont()->setBold(true);
        $sheet->getStyle('A1:AG1')->getFill()->setFillType('solid')->getStartColor()->setARGB('FF033151');
        $sheet->getStyle('A1:AG1')->getFont()->getColor()->setARGB('FFFFFFFF');
        for ($columnIndex = 1; $columnIndex <= 33; $columnIndex++) {
            $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($columnIndex))->setAutoSize(true);
        }
        $filename = (($filters['asset_type'] ?? $filters['type'] ?? '') === 'virtual_machine' || ($filters['view'] ?? '') === 'vms')
            ? 'cmdb_maquinas_virtuais_' . now()->format('Y_m_d') . '.xlsx' : 'cmdb_ativos_' . now()->format('Y_m_d') . '.xlsx';
        return response()->streamDownload(static function () use ($spreadsheet): void { (new Xlsx($spreadsheet))->save('php://output'); }, $filename, ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
    }

    public function bulkUpdate(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'asset_ids' => ['required', 'array', 'min:1'],
            'asset_ids.*' => ['integer', 'exists:cmdb_assets,id'],
            'service_id' => ['nullable', 'integer', 'exists:cmdb_services,id'],
            'responsible_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'criticality' => ['nullable', Rule::in(self::CRITICALITIES)],
            'environment' => ['nullable', Rule::in(self::ENVIRONMENTS)],
            'status' => ['nullable', Rule::in(self::STATUSES)],
            'location' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
        ]);

        $changes = array_filter(Arr::only($data, ['responsible_user_id', 'criticality', 'environment', 'status', 'location', 'notes']), static fn ($value): bool => $value !== null && $value !== '');
        $service = ! empty($data['service_id']) ? CmdbService::findOrFail($data['service_id']) : null;
        if (! $service && $changes === []) {
            throw ValidationException::withMessages(['asset_ids' => 'Informe pelo menos uma alteração para aplicar.']);
        }

        DB::transaction(function () use ($data, $changes, $service, $request): void {
            CmdbAsset::query()->whereIn('id', $data['asset_ids'])->with('serverDetails')->get()->each(function (CmdbAsset $asset) use ($changes, $service, $request): void {
                $before = $asset->only(array_keys($changes));
                if ($changes !== []) {
                    $asset->update($changes);
                }
                if ($service && in_array($asset->asset_type, ['server', 'virtual_machine'], true)) {
                    $details = CmdbServer::query()->firstOrCreate(['asset_id' => $asset->id]);
                    $before['service_id'] = $details->service_id;
                    $details->update(['service_id' => $service->id, 'service_type' => $service->name]);
                }
                $this->historyService->record($asset, $request->user(), 'bulk_updated', 'Dados operacionais atualizados em lote', null, $before, $asset->fresh()->toArray());
            });
        });

        return back()->with('success', 'Alterações aplicadas aos ativos selecionados.');
    }

    private function filteredQuery(array $filters): Builder
    {
        $relations = ['rack', 'responsibleUser', 'serverDetails.service'];
        if (Schema::hasTable('cmdb_asset_backups')) {
            $relations[] = 'backupRecords.connector';
        }
        $query = CmdbAsset::query()->with($relations);
        if ($search = trim((string) ($filters['q'] ?? ''))) {
            $query->where(function ($q) use ($search): void {
                $q->where('name', 'like', "%{$search}%")->orWhere('hostname', 'like', "%{$search}%")->orWhere('serial_number', 'like', "%{$search}%")->orWhere('patrimony_number', 'like', "%{$search}%")->orWhere('main_ip', 'like', "%{$search}%")->orWhere('manufacturer', 'like', "%{$search}%")->orWhere('model', 'like', "%{$search}%");
            });
        }
        $assetType = $filters['asset_type'] ?? $filters['type'] ?? null;
        if ($assetType) $query->where('asset_type', $assetType);
        if (($filters['view'] ?? '') === 'vms') $query->where('asset_type', 'virtual_machine');
        if (($filters['view'] ?? '') === 'hosts') $query->where('asset_type', 'server')->where(function ($q): void {
            $q->where('source_type', 'vcenter')->orWhereHas('serverDetails', fn ($details) => $details->where('hypervisor', 'like', '%ESXi%'));
        });
        if (($filters['view'] ?? '') === 'physical') $query->where('asset_type', 'server')->where(function ($q): void {
            $q->whereDoesntHave('serverDetails')->orWhereHas('serverDetails', fn ($details) => $details->where('is_virtualized', false)->where(function ($source) {
                $source->whereNull('hypervisor')->orWhere('hypervisor', '');
            }))->where(function ($source): void {
                $source->whereNull('source_type')->orWhere('source_type', '<>', 'vcenter');
            });
        });
        if (($filters['view'] ?? '') === 'other') $query->whereNotIn('asset_type', ['server', 'virtual_machine']);
        foreach (['status', 'criticality', 'environment', 'rack_id', 'responsible_user_id', 'source_type', 'source_connector_id'] as $field) if (! empty($filters[$field])) $query->where($field, $filters[$field]);
        if (! empty($filters['without_ip'])) $query->whereDoesntHave('ipAddresses');
        if (! empty($filters['without_responsible'])) $query->whereNull('responsible_user_id');
        if (! empty($filters['without_service'])) $query->whereIn('asset_type', ['server', 'virtual_machine'])->where(function ($q): void {
            $q->whereDoesntHave('serverDetails')->orWhereHas('serverDetails', fn ($s) => $s->whereNull('service_id')->where(function ($x): void {
                $x->whereNull('service_type')->orWhere('service_type', '')->orWhereNull('service_description')->orWhere('service_description', '');
            }));
        });
        if (! empty($filters['service_id'])) $query->whereHas('serverDetails', fn ($q) => $q->where('service_id', $filters['service_id']));
        if ($service = trim((string) ($filters['service'] ?? ''))) $query->whereHas('serverDetails', fn ($q) => $q->where('service_type', 'like', "%{$service}%")->orWhere('service_description', 'like', "%{$service}%")->orWhereHas('service', fn ($s) => $s->where('name', 'like', "%{$service}%")));
        if (! empty($filters['backup_status']) && Schema::hasTable('cmdb_asset_backups')) {
            $query->whereHas('backupRecords', fn ($q) => $q->where('protection_status', $filters['backup_status']));
        }
        return $query;
    }

    public function create(Request $request): View
    {
        return view('cmdb.assets.create', $this->formData($request->query('type')));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        $asset = DB::transaction(function () use ($data): CmdbAsset {
            $asset = CmdbAsset::create($data);
            $this->detailService->sync($asset, $data);

            return $asset;
        });

        $this->historyService->record(
            $asset,
            $request->user(),
            'created',
            'Ativo criado',
            null,
            [],
            $asset->toArray()
        );

        return redirect()->route('cmdb.assets.show', $asset)->with('success', 'Ativo cadastrado com sucesso.');
    }

    public function show(CmdbAsset $asset): View
    {
        $relations = [
            'rack',
            'responsibleUser',
            'manufacturerCatalog',
            'modelCatalog',
            'serverDetails.service',
            'virtualMachineDetails.hostAsset',
            'databaseDetails.serverAsset',
            'networkDeviceDetails',
            'firewallDetails',
            'storageDetails',
            'upsDetails.rack',
            'ipAddresses',
            'expirationItems',
            'outgoingRelationships.targetAsset',
            'incomingRelationships.sourceAsset',
        ];

        if (Schema::hasTable('cmdb_connectors')) {
            $relations[] = 'sourceConnector';
        }

        if (Schema::hasTable('cmdb_manufacturers')) {
            $relations[] = 'manufacturerCatalog';
        }

        if (Schema::hasTable('cmdb_models')) {
            $relations[] = 'modelCatalog';
        }

        $asset->load($relations);

        if (Schema::hasTable('cmdb_asset_histories')) {
            $asset->load(['histories.user']);
        }

        if (Schema::hasTable('cmdb_asset_documents')) {
            $asset->load(['documents.user']);
        }

        if (Schema::hasTable('cmdb_asset_backups')) {
            $asset->load(['backupRecords.connector']);
        }

        return view('cmdb.assets.show', [
            'asset' => $asset,
            'types' => self::TYPES,
            'statusLabels' => self::STATUS_LABELS,
            'criticalityLabels' => self::CRITICALITY_LABELS,
            'environmentLabels' => self::ENVIRONMENT_LABELS,
            'details' => $this->detailService->formDetails($asset),
            'assetsForRelations' => CmdbAsset::query()->orderBy('name')->get(['id', 'name']),
            'connectors' => Schema::hasTable('cmdb_connectors') ? CmdbConnector::query()->orderBy('name')->get(['id', 'name', 'connector_type']) : collect(),
            'manufacturers' => Schema::hasTable('cmdb_manufacturers') ? CmdbManufacturer::query()->with(['models' => fn ($query) => $query->orderBy('name')])->orderBy('name')->get(['id', 'name', 'slug', 'is_active']) : collect(),
            'models' => Schema::hasTable('cmdb_models') ? CmdbModel::query()->with('manufacturer')->orderBy('name')->get(['id', 'manufacturer_id', 'name', 'slug', 'asset_type', 'is_active']) : collect(),
            'hasManufacturers' => Schema::hasTable('cmdb_manufacturers'),
            'hasModels' => Schema::hasTable('cmdb_models'),
            'hasHistories' => Schema::hasTable('cmdb_asset_histories'),
            'hasDocuments' => Schema::hasTable('cmdb_asset_documents'),
            'hasBackupTable' => Schema::hasTable('cmdb_asset_backups'),
        ]);
    }

    public function dependencies(CmdbAsset $asset): View
    {
        $asset->load([
            'rack',
            'serverDetails',
            'virtualMachineDetails.hostAsset',
            'databaseDetails.serverAsset',
            'networkDeviceDetails',
            'firewallDetails',
            'storageDetails',
            'upsDetails.rack',
            'ipAddresses',
            'expirationItems',
            'outgoingRelationships.targetAsset',
            'incomingRelationships.sourceAsset',
        ]);

        if (Schema::hasTable('cmdb_manufacturers')) {
            $asset->load(['manufacturerCatalog']);
        }

        if (Schema::hasTable('cmdb_models')) {
            $asset->load(['modelCatalog']);
        }

        if (Schema::hasTable('cmdb_asset_histories')) {
            $asset->load(['histories.user']);
        }

        if (Schema::hasTable('cmdb_asset_documents')) {
            $asset->load(['documents.user']);
        }

        return view('cmdb.assets.dependencies', [
            'asset' => $asset,
            'types' => self::TYPES,
            'details' => $this->detailService->formDetails($asset),
            'assetsForRelations' => CmdbAsset::query()->orderBy('name')->get(['id', 'name']),
            'connectors' => Schema::hasTable('cmdb_connectors') ? CmdbConnector::query()->orderBy('name')->get(['id', 'name', 'connector_type']) : collect(),
            'manufacturers' => Schema::hasTable('cmdb_manufacturers') ? CmdbManufacturer::query()->with(['models' => fn ($query) => $query->orderBy('name')])->orderBy('name')->get(['id', 'name', 'slug', 'is_active']) : collect(),
            'models' => Schema::hasTable('cmdb_models') ? CmdbModel::query()->with('manufacturer')->orderBy('name')->get(['id', 'manufacturer_id', 'name', 'slug', 'asset_type', 'is_active']) : collect(),
            'hasManufacturers' => Schema::hasTable('cmdb_manufacturers'),
            'hasModels' => Schema::hasTable('cmdb_models'),
            'hasHistories' => Schema::hasTable('cmdb_asset_histories'),
            'hasDocuments' => Schema::hasTable('cmdb_asset_documents'),
        ]);
    }

    public function diagram(CmdbAsset $asset): View
    {
        $asset->load([
            'rack',
            'responsibleUser',
            'outgoingRelationships.targetAsset.rack',
            'incomingRelationships.sourceAsset.rack',
        ]);

        if (Schema::hasTable('cmdb_asset_histories')) {
            $asset->load(['histories.user']);
        }

        if (Schema::hasTable('cmdb_asset_documents')) {
            $asset->load(['documents.user']);
        }

        return view('cmdb.assets.diagram', [
            'asset' => $asset,
            'types' => self::TYPES,
            'relationshipLabels' => [
                'hosts' => 'Hospeda',
                'depends_on' => 'Depende de',
                'connects_to' => 'Conecta-se a',
                'protects' => 'Protege',
                'backs_up_to' => 'Faz backup para',
                'replicates_to' => 'Replica para',
                'uses' => 'Utiliza',
                'located_in' => 'Está localizado em',
                'powered_by' => 'Alimentado por',
                'monitored_by' => 'Monitorado por',
            ],
            'hasHistories' => Schema::hasTable('cmdb_asset_histories'),
            'hasDocuments' => Schema::hasTable('cmdb_asset_documents'),
        ]);
    }

    public function edit(Request $request, CmdbAsset $asset): View
    {
        return view('cmdb.assets.edit', array_merge($this->formData($asset->asset_type, $asset), [
            'asset' => $asset,
            'technicalMode' => $request->query('mode') === 'technical' && $request->user()?->role === 'admin',
            'canTechnicalMode' => $request->user()?->role === 'admin',
        ]));
    }

    public function update(Request $request, CmdbAsset $asset): RedirectResponse
    {
        $originalType = $asset->asset_type;
        $data = $this->validated($request, $asset);

        // $request precisa ser capturado: o histórico registra quem alterou.
        DB::transaction(function () use ($request, $asset, $data): void {
            $oldValues = $asset->only([
                'asset_type',
                'name',
                'hostname',
                'description',
                'manufacturer_id',
                'manufacturer',
                'model_id',
                'model',
                'serial_number',
                'patrimony_number',
                'status',
                'criticality',
                'environment',
                'company_id',
                'unit_id',
                'location',
                'rack_id',
                'main_ip',
                'operating_system',
                'responsible_user_id',
                'notes',
                'source_type',
                'source_connector_id',
                'source_external_id',
                'last_synced_at',
                'sync_status',
                'sync_error',
                'sync_hash',
            ]);
            $asset->update($data);
            $this->detailService->sync($asset, $data);

            $this->historyService->record(
                $asset,
                $request->user(),
                'updated',
                'Ativo atualizado',
                null,
                $oldValues,
                $asset->fresh()->toArray()
            );
        });

        $flash = ['success' => 'Ativo atualizado com sucesso.'];
        if ($originalType !== $asset->asset_type) {
            $flash['warning'] = 'O tipo do ativo foi alterado. Os dados específicos anteriores foram preservados.';
        }

        return redirect()->route('cmdb.assets.show', $asset)->with($flash);
    }

    public function destroy(Request $request, CmdbAsset $asset): RedirectResponse
    {
        $oldValues = $asset->toArray();
        $this->historyService->record($asset, $request->user(), 'deleted', 'Ativo removido', null, $oldValues, []);
        $asset->delete();

        return redirect()->route('cmdb.assets.index')->with('success', 'Ativo removido com sucesso.');
    }

    private function formData(?string $type = null, ?CmdbAsset $asset = null): array
    {
        $serverAssets = Schema::hasTable('cmdb_assets')
            ? CmdbAsset::query()->where('asset_type', 'server')->orderBy('name')->get(['id', 'name', 'hostname'])
            : collect();

        $databaseHostAssets = Schema::hasTable('cmdb_assets')
            ? CmdbAsset::query()->whereIn('asset_type', ['server', 'virtual_machine'])->orderBy('name')->get(['id', 'name', 'hostname', 'asset_type'])
            : collect();

        $detailDefaults = $this->detailService->formDetails($asset);

        return [
            'asset' => $asset,
            'types' => self::TYPES,
            'hostnameDrivenTypes' => self::HOSTNAME_DRIVEN_TYPES,
            'statuses' => self::STATUSES,
            'statusLabels' => self::STATUS_LABELS,
            'criticalities' => self::CRITICALITIES,
            'criticalityLabels' => self::CRITICALITY_LABELS,
            'environments' => self::ENVIRONMENTS,
            'environmentLabels' => self::ENVIRONMENT_LABELS,
            'databaseTypes' => self::DATABASE_TYPES,
            'networkDeviceTypes' => self::NETWORK_DEVICE_TYPES,
            'firewallTypes' => self::FIREWALL_TYPES,
            'storageTypes' => self::STORAGE_TYPES,
            'racks' => Schema::hasTable('cmdb_racks') ? CmdbRack::query()->orderBy('name')->get() : collect(),
            'services' => Schema::hasTable('cmdb_services') ? CmdbService::query()->where('active', true)->orderBy('name')->get(['id', 'name']) : collect(),
            'users' => User::query()->orderBy('name')->get(['id', 'name']),
            'serverAssets' => $serverAssets,
            'databaseHostAssets' => $databaseHostAssets,
            'manufacturers' => Schema::hasTable('cmdb_manufacturers') ? CmdbManufacturer::query()->with(['models' => fn ($query) => $query->orderBy('name')])->where('is_active', true)->orderBy('name')->get(['id', 'name', 'slug', 'is_active']) : collect(),
            'models' => Schema::hasTable('cmdb_models') ? CmdbModel::query()->with('manufacturer')->where('is_active', true)->orderBy('name')->get(['id', 'manufacturer_id', 'name', 'slug', 'asset_type', 'is_active']) : collect(),
            'selectedType' => array_key_exists((string) $type, self::TYPES) ? $type : null,
            'details' => $detailDefaults,
            'unitOptions' => $this->unitOptions(),
        ];
    }

    private function validated(Request $request, ?CmdbAsset $asset = null): array
    {
        $data = $request->validate([
            'asset_type' => ['required', Rule::in(array_keys(self::TYPES))],
            'name' => ['nullable', 'string', 'max:255'],
            'hostname' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'manufacturer_id' => Schema::hasTable('cmdb_manufacturers') ? ['nullable', 'integer', 'exists:cmdb_manufacturers,id'] : ['nullable', 'integer'],
            'manufacturer' => ['nullable', 'string', 'max:255'],
            'model_id' => Schema::hasTable('cmdb_models') ? ['nullable', 'integer', 'exists:cmdb_models,id'] : ['nullable', 'integer'],
            'model' => ['nullable', 'string', 'max:255'],
            'serial_number' => ['nullable', 'string', 'max:255'],
            'patrimony_number' => ['nullable', 'string', 'max:255'],
            'status' => ['required', Rule::in(self::STATUSES)],
            'criticality' => ['required', Rule::in(self::CRITICALITIES)],
            'environment' => ['required', Rule::in(self::ENVIRONMENTS)],
            'company_id' => ['nullable', 'integer', 'min:1'],
            'unit_id' => ['nullable', 'integer', 'min:1'],
            'organization_unit_id' => ['nullable', 'integer', 'exists:organization_units,id'],
            'organization_department_id' => ['nullable', 'integer', 'exists:organization_departments,id'],
            'location' => ['nullable', 'string', 'max:255'],
            'rack_id' => ['nullable', 'integer', 'exists:cmdb_racks,id'],
            'main_ip' => ['nullable', 'string', 'max:255'],
            'operating_system' => ['nullable', 'string', 'max:255'],
            'responsible_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'notes' => ['nullable', 'string'],

            'server_power_state' => ['nullable', 'string', 'max:255'],
            'server_service_id' => ['nullable', 'integer', 'exists:cmdb_services,id'],
            'server_operational_status' => ['nullable', 'string', 'max:255'],
            'server_cpu' => ['nullable', 'string', 'max:255'],
            'server_memory_gb' => ['nullable', 'integer', 'min:0'],
            'server_storage_total_gb' => ['nullable', 'integer', 'min:0'],
            'server_used_space_gb' => ['nullable', 'numeric', 'min:0'],
            'server_service_description' => ['nullable', 'string'],
            'server_company_name' => ['nullable', 'string', 'max:255'],
            'server_service_type' => ['nullable', 'string', 'max:255'],
            'server_backup_policy' => ['nullable', 'string', 'max:255'],
            'server_backup_retention' => ['nullable', 'string', 'max:255'],
            'server_web_publication' => ['nullable', 'boolean'],
            'server_raid' => ['nullable', 'string', 'max:255'],
            'server_hypervisor' => ['nullable', 'string', 'max:255'],
            'server_is_virtualized' => ['nullable', 'boolean'],
            'server_warranty_start_date' => ['nullable', 'date'],
            'server_warranty_end_date' => ['nullable', 'date'],

            'vm_host_asset_id' => ['nullable', 'integer', 'exists:cmdb_assets,id'],
            'vm_cpu_cores' => ['nullable', 'integer', 'min:0'],
            'vm_memory_gb' => ['nullable', 'integer', 'min:0'],
            'vm_storage_gb' => ['nullable', 'integer', 'min:0'],
            'vm_hypervisor' => ['nullable', 'string', 'max:255'],
            'vm_datastore' => ['nullable', 'string', 'max:255'],

            'db_server_asset_id' => ['nullable', 'integer', 'exists:cmdb_assets,id'],
            'db_database_type' => ['nullable', Rule::in(self::DATABASE_TYPES)],
            'db_version' => ['nullable', 'string', 'max:255'],
            'db_port' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'db_instance_name' => ['nullable', 'string', 'max:255'],
            'db_backup_enabled' => ['nullable', 'boolean'],
            'db_replication_enabled' => ['nullable', 'boolean'],
            'db_notes' => ['nullable', 'string'],

            'network_device_type' => ['nullable', Rule::in(self::NETWORK_DEVICE_TYPES)],
            'network_firmware_version' => ['nullable', 'string', 'max:255'],
            'network_ports_count' => ['nullable', 'integer', 'min:0'],
            'network_poe_enabled' => ['nullable', 'boolean'],
            'network_stack_enabled' => ['nullable', 'boolean'],
            'network_management_ip' => ['nullable', 'string', 'max:255'],

            'firewall_firewall_type' => ['nullable', Rule::in(self::FIREWALL_TYPES)],
            'firewall_firmware_version' => ['nullable', 'string', 'max:255'],
            'firewall_license_key' => ['nullable', 'string', 'max:255'],
            'firewall_license_expiration_date' => ['nullable', 'date'],
            'firewall_ha_enabled' => ['nullable', 'boolean'],
            'firewall_vpn_enabled' => ['nullable', 'boolean'],
            'firewall_vpn_count' => ['nullable', 'integer', 'min:0'],

            'storage_storage_type' => ['nullable', Rule::in(self::STORAGE_TYPES)],
            'storage_total_capacity_tb' => ['nullable', 'numeric', 'min:0'],
            'storage_used_capacity_tb' => ['nullable', 'numeric', 'min:0'],
            'storage_raid' => ['nullable', 'string', 'max:255'],
            'storage_iscsi_enabled' => ['nullable', 'boolean'],
            'storage_nfs_enabled' => ['nullable', 'boolean'],
            'storage_smb_enabled' => ['nullable', 'boolean'],

            'ups_power_va' => ['nullable', 'integer', 'min:0'],
            'ups_power_watts' => ['nullable', 'integer', 'min:0'],
            'ups_battery_quantity' => ['nullable', 'integer', 'min:0'],
            'ups_battery_type' => ['nullable', 'string', 'max:255'],
            'ups_installation_date' => ['nullable', 'date'],
            'ups_last_battery_replacement_date' => ['nullable', 'date'],
            'ups_next_battery_replacement_date' => ['nullable', 'date'],
            'ups_estimated_runtime_minutes' => ['nullable', 'integer', 'min:0'],
            'ups_warranty_start_date' => ['nullable', 'date'],
            'ups_warranty_end_date' => ['nullable', 'date'],
            'ups_rack_id' => ['nullable', 'integer', 'exists:cmdb_racks,id'],

            'source_type' => ['nullable', Rule::in(['manual', 'vmware', 'proxmox', 'hyperv', 'snmp', 'ssh', 'winrm', 'api', 'other'])],
            'source_connector_id' => Schema::hasTable('cmdb_connectors') ? ['nullable', 'integer', 'exists:cmdb_connectors,id'] : ['nullable', 'integer'],
            'source_external_id' => ['nullable', 'string', 'max:255'],
            'last_synced_at' => ['nullable', 'date'],
            'sync_status' => ['nullable', Rule::in(['never_synced', 'synced', 'pending', 'error', 'ignored'])],
            'sync_error' => ['nullable', 'string'],
            'sync_hash' => ['nullable', 'string', 'max:64'],
        ]);

        $data['name'] = trim((string) ($data['name'] ?? ''));
        $data['hostname'] = trim((string) ($data['hostname'] ?? ''));

        if (in_array($data['asset_type'], self::HOSTNAME_DRIVEN_TYPES, true)) {
            if ($data['hostname'] === '') {
                throw ValidationException::withMessages([
                    'hostname' => 'Informe o hostname do ativo.',
                ]);
            }

            $data['name'] = $data['hostname'];
        } else {
            if ($data['name'] === '' && $data['hostname'] === '') {
                throw ValidationException::withMessages([
                    'name' => 'Informe o nome do ativo.',
                    'hostname' => 'Informe ao menos o nome ou o hostname do ativo.',
                ]);
            }

            if ($data['name'] === '' && $data['hostname'] !== '') {
                $data['name'] = $data['hostname'];
            }
        }

        if ($data['hostname'] === '') {
            $data['hostname'] = null;
        }

        if ($data['name'] === '') {
            $data['name'] = null;
        }

        if ($data['asset_type'] === 'virtual_machine') {
            $data['organization_unit_id'] = app(\App\Services\OrganizationLookupService::class)
                ->findUnitBySourceId(self::VIRTUAL_MACHINE_UNIT_ID)?->id
                ?? ($data['organization_unit_id'] ?? null);
            $data['location'] = self::VIRTUAL_MACHINE_LOCATION;
            $data['rack_id'] = null;
        }

        if ($asset === null) {
            $data['source_type'] = $data['source_type'] ?? 'manual';
            $data['sync_status'] = $data['sync_status'] ?? 'never_synced';
        } else {
            $data['source_type'] = $data['source_type'] ?? $asset->source_type;
            unset($data['sync_status'], $data['last_synced_at'], $data['sync_error'], $data['sync_hash']);
        }

        if (Schema::hasTable('cmdb_manufacturers') && filled($data['manufacturer_id'] ?? null)) {
            $manufacturer = CmdbManufacturer::query()->find($data['manufacturer_id']);
            if ($manufacturer && blank($data['manufacturer'] ?? null)) {
                $data['manufacturer'] = $manufacturer->name;
            }
        }

        if (Schema::hasTable('cmdb_models') && filled($data['model_id'] ?? null)) {
            $catalogModel = CmdbModel::query()->find($data['model_id']);
            if ($catalogModel) {
                if (blank($data['model'] ?? null)) {
                    $data['model'] = $catalogModel->name;
                }

                if (blank($data['manufacturer_id'] ?? null) && filled($catalogModel->manufacturer_id)) {
                    $data['manufacturer_id'] = $catalogModel->manufacturer_id;
                }
            }
        }

        if (
            Schema::hasTable('cmdb_models')
            && filled($data['manufacturer_id'] ?? null)
            && filled($data['model_id'] ?? null)
        ) {
            $catalogModel = CmdbModel::query()->find($data['model_id']);
            if ($catalogModel && (int) $catalogModel->manufacturer_id !== (int) $data['manufacturer_id']) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'model_id' => 'O modelo selecionado não pertence ao fabricante informado.',
                ]);
            }
        }

        return array_map(static fn ($value) => is_string($value) ? trim($value) : $value, $data);
    }

    private function unitOptions(): array
    {
        return app(\App\Services\OrganizationLookupService::class)
            ->unitOptions()
            ->map(fn ($item) => [
                'id' => (string) ($item->organization_unit_id ?? ''),
                'label' => $item->name,
                'scope' => 'Unidade',
            ])
            ->filter(fn (array $item) => $item['id'] !== '')
            ->values()
            ->all();
    }
}
