<?php

namespace App\Http\Controllers;

use App\Models\CmdbAsset;
use App\Models\CmdbConnector;
use App\Models\CmdbDiscoveredAsset;
use App\Services\CmdbDiscoveredAssetService;
use App\Services\CmdbDiscoveryQueryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CmdbDiscoveredAssetController extends Controller
{
    private const STATUS_LABELS = [
        'pending' => 'Pendente',
        'linked' => 'Vinculada',
        'created' => 'Criada',
        'ignored' => 'Ignorada',
        'duplicate' => 'Duplicada',
        'error' => 'Erro',
    ];

    private const ACTION_LABELS = [
        'link' => 'Vincular',
        'create' => 'Criar',
        'ignore' => 'Ignorar',
        'reopen' => 'Reabrir',
    ];

    public function __construct(
        private readonly CmdbDiscoveredAssetService $discoveredAssetService,
        private readonly CmdbDiscoveryQueryService $discoveryQuery,
    ) {
    }

    public function index(Request $request): View
    {
        if (! Schema::hasTable('cmdb_discovered_assets')) {
            return view('cmdb.discovered-assets.index', [
                'setupPending' => true,
                'filters' => [],
                'items' => collect(),
                'summary' => ['total' => 0, 'pending' => 0, 'linked' => 0, 'created' => 0, 'ignored' => 0, 'errors' => 0],
                'statuses' => self::STATUS_LABELS,
                'connectors' => collect(),
                'sourceTypes' => [],
            ]);
        }

        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
            'status' => ['nullable', Rule::in(array_keys(self::STATUS_LABELS))],
            'type' => ['nullable', Rule::in(['vcenter_virtual_machine', 'vcenter_host', 'vcenter_network', 'vcenter_datastore', 'vcenter_cluster', 'vcenter_datacenter', 'snmp'])],
            'connector_id' => ['nullable', 'integer', 'exists:cmdb_connectors,id'],
            'hostname' => ['nullable', 'string', 'max:120'],
            'ip_address' => ['nullable', 'string', 'max:45'],
            'manufacturer' => ['nullable', 'string', 'max:120'],
            'model' => ['nullable', 'string', 'max:120'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'per_page' => ['nullable', 'integer', 'in:10,15,25,50'],
        ]);

        $query = $this->discoveryQuery->query()
            ->with(['connector', 'asset', 'reviewedBy'])
            ->orderByDesc('discovered_at')
            ->orderByDesc('created_at');

        if ($search = trim((string) ($filters['q'] ?? ''))) {
            $query->where(function ($builder) use ($search): void {
                $builder->where('hostname', 'like', '%' . $search . '%')
                    ->orWhere('ip_address', 'like', '%' . $search . '%')
                    ->orWhere('manufacturer', 'like', '%' . $search . '%')
                    ->orWhere('model', 'like', '%' . $search . '%')
                    ->orWhere('serial_number', 'like', '%' . $search . '%')
                    ->orWhere('source_external_id', 'like', '%' . $search . '%')
                    ->orWhere('description', 'like', '%' . $search . '%');
            });
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['type'])) {
            $query->where('source_type', $filters['type']);
        }

        if (! empty($filters['connector_id'])) {
            $query->where('connector_id', $filters['connector_id']);
        }

        if ($hostname = trim((string) ($filters['hostname'] ?? ''))) {
            $query->where('hostname', 'like', '%' . $hostname . '%');
        }

        if ($ipAddress = trim((string) ($filters['ip_address'] ?? ''))) {
            $query->where('ip_address', 'like', '%' . $ipAddress . '%');
        }

        if ($manufacturer = trim((string) ($filters['manufacturer'] ?? ''))) {
            $query->where('manufacturer', 'like', '%' . $manufacturer . '%');
        }

        if ($model = trim((string) ($filters['model'] ?? ''))) {
            $query->where('model', 'like', '%' . $model . '%');
        }

        if (! empty($filters['from'])) {
            $query->whereDate('discovered_at', '>=', $filters['from']);
        }

        if (! empty($filters['to'])) {
            $query->whereDate('discovered_at', '<=', $filters['to']);
        }

        $items = $query->paginate((int) ($filters['per_page'] ?? 15))->withQueryString();

        return view('cmdb.discovered-assets.index', [
            'setupPending' => false,
            'filters' => $filters,
            'items' => $items,
            'summary' => $this->discoveryQuery->counts(),
            'statuses' => self::STATUS_LABELS,
            'connectors' => CmdbConnector::query()->orderBy('name')->get(['id', 'name', 'connector_type']),
            'sourceTypes' => [
                'vcenter_virtual_machine' => 'Máquina virtual', 'vcenter_host' => 'Host ESXi', 'vcenter_network' => 'Rede / VLAN',
                'vcenter_datastore' => 'Datastore', 'vcenter_cluster' => 'Cluster', 'vcenter_datacenter' => 'Datacenter', 'snmp' => 'Dispositivo SNMP',
            ],
        ]);
    }

    public function show(CmdbDiscoveredAsset $discoveredAsset): View
    {
        $discoveredAsset->load(['connector', 'syncLog', 'asset', 'reviewedBy']);

        return view('cmdb.discovered-assets.show', [
            'setupPending' => false,
            'item' => $discoveredAsset,
            'statuses' => self::STATUS_LABELS,
            'actions' => self::ACTION_LABELS,
            'suggestions' => $this->discoveredAssetService->suggestAssets($discoveredAsset),
        ]);
    }

    public function link(Request $request, CmdbDiscoveredAsset $discoveredAsset): RedirectResponse
    {
        $data = $request->validate([
            'asset_id' => ['required', 'integer', 'exists:cmdb_assets,id'],
            'review_notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $asset = CmdbAsset::query()->findOrFail($data['asset_id']);
        $this->discoveredAssetService->link($discoveredAsset, $asset, $request->user(), $data['review_notes'] ?? null);

        return redirect()
            ->route('cmdb.discovered-assets.show', $discoveredAsset)
            ->with('success', 'Descoberta vinculada ao ativo selecionado.');
    }

    public function createAsset(Request $request, CmdbDiscoveredAsset $discoveredAsset): RedirectResponse
    {
        return redirect()->route('cmdb.quick-create.create', [
            'source_log_id' => $discoveredAsset->sync_log_id,
            'source_type' => $discoveredAsset->source_type ?: 'snmp',
            'source_connector_id' => $discoveredAsset->connector_id,
            'source_external_id' => $discoveredAsset->source_external_id ?: $discoveredAsset->hostname,
            'name' => $discoveredAsset->hostname ?: $discoveredAsset->ip_address ?: 'Novo ativo',
            'hostname' => $discoveredAsset->hostname,
            'ip' => $discoveredAsset->ip_address,
            'description' => $discoveredAsset->description,
        ]);
    }

    public function ignore(Request $request, CmdbDiscoveredAsset $discoveredAsset): RedirectResponse
    {
        $this->discoveredAssetService->ignore($discoveredAsset, $request->user());

        return redirect()
            ->route('cmdb.discovered-assets.show', $discoveredAsset)
            ->with('success', 'Descoberta ignorada.');
    }

    public function reopen(Request $request, CmdbDiscoveredAsset $discoveredAsset): RedirectResponse
    {
        $this->discoveredAssetService->reopen($discoveredAsset, $request->user());

        return redirect()
            ->route('cmdb.discovered-assets.show', $discoveredAsset)
            ->with('success', 'Descoberta reaberta para revisão.');
    }

    public function bulkIgnore(Request $request): RedirectResponse
    {
        $data = $request->validate(['discovery_ids' => ['required', 'array', 'min:1'], 'discovery_ids.*' => ['integer', 'exists:cmdb_discovered_assets,id']]);
        foreach (CmdbDiscoveredAsset::query()->whereIn('id', $data['discovery_ids'])->whereIn('status', ['pending', 'suggested', 'reopened'])->get() as $item) {
            $this->discoveredAssetService->ignore($item, $request->user(), 'Ignorada em lote pela revisão de descobertas.');
        }
        return back()->with('success', 'Descobertas selecionadas ignoradas.');
    }

    public function bulkReopen(Request $request): RedirectResponse
    {
        $data = $request->validate(['discovery_ids' => ['required', 'array', 'min:1'], 'discovery_ids.*' => ['integer', 'exists:cmdb_discovered_assets,id']]);
        foreach (CmdbDiscoveredAsset::query()->whereIn('id', $data['discovery_ids'])->whereIn('status', ['linked', 'created', 'ignored', 'error'])->get() as $item) {
            $this->discoveredAssetService->reopen($item, $request->user(), 'Reaberta em lote pela revisão de descobertas.');
        }
        return back()->with('success', 'Descobertas selecionadas reabertas.');
    }

}
