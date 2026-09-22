<?php

namespace App\Http\Controllers;

use App\Models\CmdbAsset;
use App\Models\CmdbIpAddress;
use App\Services\CmdbAssetHistoryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CmdbIpAddressController extends Controller
{
    public function index(Request $request): View
    {
        if (! Schema::hasTable('cmdb_ip_addresses')) {
            return view('cmdb.ip-addresses.index', [
                'items' => collect(),
                'assets' => collect(),
                'setupPending' => true,
            ]);
        }

        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
            'asset_id' => ['nullable', 'integer', 'exists:cmdb_assets,id'],
            'vlan' => ['nullable', 'string', 'max:50'],
            'is_primary' => ['nullable', Rule::in(['0', '1'])],
        ]);

        $query = CmdbIpAddress::query()->with('asset');
        if ($search = trim((string) ($filters['q'] ?? ''))) {
            $query->where(function ($q) use ($search): void {
                $q->where('ip_address', 'like', "%{$search}%")
                    ->orWhere('subnet_mask', 'like', "%{$search}%")
                    ->orWhere('gateway', 'like', "%{$search}%")
                    ->orWhere('dns', 'like', "%{$search}%")
                    ->orWhere('mac_address', 'like', "%{$search}%")
                    ->orWhere('vlan', 'like', "%{$search}%");
            });
        }

        if (! empty($filters['asset_id'])) {
            $query->where('asset_id', $filters['asset_id']);
        }

        if (($filters['vlan'] ?? '') !== '') {
            $query->where('vlan', 'like', '%' . trim((string) $filters['vlan']) . '%');
        }

        if (($filters['is_primary'] ?? '') !== '') {
            $query->where('is_primary', (bool) $filters['is_primary']);
        }

        return view('cmdb.ip-addresses.index', [
            'items' => $query->orderByDesc('is_primary')->orderBy('ip_address')->paginate(20)->withQueryString(),
            'assets' => CmdbAsset::query()->orderBy('name')->get(['id', 'name', 'hostname']),
            'setupPending' => false,
            'filters' => $filters,
            'asset' => null,
            'ipAddress' => null,
        ]);
    }

    public function create(Request $request): View
    {
        return view('cmdb.ip-addresses.create', [
            'assets' => CmdbAsset::query()->orderBy('name')->get(['id', 'name', 'hostname']),
            'asset' => $request->filled('asset_id') ? CmdbAsset::find($request->integer('asset_id')) : null,
            'ipAddress' => null,
        ]);
    }

    public function store(Request $request, CmdbAssetHistoryService $history): RedirectResponse
    {
        $data = $this->validated($request);
        $ipAddress = CmdbIpAddress::create($data);
        $this->syncPrimaryIp($ipAddress->asset_id, $ipAddress, (bool) ($data['is_primary'] ?? false));
        $asset = $ipAddress->asset;
        if ($asset) {
            $history->record($asset, $request->user(), 'ip_added', 'IP adicionado', null, [], $ipAddress->toArray());
        }

        return redirect()->route('cmdb.assets.show', $ipAddress->asset_id)->with('success', 'Endereço IP cadastrado com sucesso.');
    }

    public function edit(CmdbIpAddress $ipAddress): View
    {
        return view('cmdb.ip-addresses.edit', [
            'assets' => CmdbAsset::query()->orderBy('name')->get(['id', 'name', 'hostname']),
            'asset' => $ipAddress->asset,
            'ipAddress' => $ipAddress,
        ]);
    }

    public function update(Request $request, CmdbIpAddress $ipAddress, CmdbAssetHistoryService $history): RedirectResponse
    {
        $data = $this->validated($request, $ipAddress);
        $oldValues = $ipAddress->toArray();
        $ipAddress->update($data);
        $this->syncPrimaryIp($ipAddress->asset_id, $ipAddress, (bool) ($data['is_primary'] ?? false));
        if ($ipAddress->asset) {
            $history->record($ipAddress->asset, $request->user(), 'ip_updated', 'IP alterado', null, $oldValues, $ipAddress->fresh()->toArray());
        }

        return redirect()->route('cmdb.assets.show', $ipAddress->asset_id)->with('success', 'Endereço IP atualizado com sucesso.');
    }

    public function destroy(Request $request, CmdbIpAddress $ipAddress, CmdbAssetHistoryService $history): RedirectResponse
    {
        $assetId = $ipAddress->asset_id;
        $wasPrimary = (bool) $ipAddress->is_primary;
        $asset = $ipAddress->asset;
        $oldValues = $ipAddress->toArray();
        $ipAddress->delete();
        $this->refreshAssetMainIp($assetId, $wasPrimary ? $ipAddress->ip_address : null);
        if ($asset) {
            $history->record($asset, $request->user(), 'ip_deleted', 'IP removido', null, $oldValues, []);
        }

        return redirect()->route('cmdb.assets.show', $assetId)->with('success', 'Endereço IP removido com sucesso.');
    }

    private function validated(Request $request, ?CmdbIpAddress $ipAddress = null): array
    {
        return $request->validate([
            'asset_id' => ['required', 'integer', 'exists:cmdb_assets,id'],
            'ip_address' => ['required', 'ip', 'max:255', Rule::unique('cmdb_ip_addresses', 'ip_address')->ignore($ipAddress?->id)],
            'subnet_mask' => ['nullable', 'ip', 'max:255'],
            'gateway' => ['nullable', 'ip', 'max:255'],
            'dns' => ['nullable', 'string', 'max:255'],
            'vlan' => ['nullable', 'string', 'max:50'],
            'mac_address' => ['nullable', 'regex:/^([0-9A-Fa-f]{2}([-:])?){5}[0-9A-Fa-f]{2}$/'],
            'is_primary' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string'],
        ]);
    }

    private function syncPrimaryIp(int $assetId, CmdbIpAddress $ipAddress, bool $isPrimary): void
    {
        if (! $isPrimary) {
            $this->refreshAssetMainIp($assetId);
            return;
        }

        CmdbIpAddress::query()
            ->where('asset_id', $assetId)
            ->where('id', '!=', $ipAddress->id)
            ->update(['is_primary' => false]);

        $this->refreshAssetMainIp($assetId, $ipAddress->ip_address);
    }

    private function refreshAssetMainIp(int $assetId, ?string $preferredIp = null): void
    {
        $asset = CmdbAsset::find($assetId);
        if (! $asset) {
            return;
        }

        if ($preferredIp) {
            $asset->update(['main_ip' => $preferredIp]);
            return;
        }

        $primaryIp = CmdbIpAddress::query()
            ->where('asset_id', $assetId)
            ->where('is_primary', true)
            ->orderByDesc('id')
            ->value('ip_address');

        if ($primaryIp) {
            $asset->update(['main_ip' => $primaryIp]);
            return;
        }

        $firstIp = CmdbIpAddress::query()->where('asset_id', $assetId)->orderBy('id')->value('ip_address');
        $asset->update(['main_ip' => $firstIp]);
    }
}
