<?php

namespace App\Http\Controllers;

use App\Models\CmdbAsset;
use App\Models\CmdbConnector;
use App\Models\CmdbDiscoveredAsset;
use App\Models\CmdbSyncJob;
use App\Models\CmdbSyncLog;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class CmdbDiscoveryController extends Controller
{
    public function index(): View
    {
        if (! Schema::hasTable('cmdb_connectors')) {
            return view('cmdb.discovery.index', [
                'setupPending' => true,
                'cards' => [],
                'activeConnectors' => collect(),
                'errorConnectors' => collect(),
                'activeJobs' => collect(),
                'recentLogs' => collect(),
                'failureLogs' => collect(),
                'recentDiscoveries' => collect(),
                'pendingDiscoveries' => collect(),
                'lastSyncAt' => null,
                'lastSnmpDiscoveryAt' => null,
            ]);
        }

        $hasSyncAssetColumns = Schema::hasColumn('cmdb_assets', 'last_synced_at') && Schema::hasColumn('cmdb_assets', 'sync_status');
        $hasSyncLogsTable = Schema::hasTable('cmdb_sync_logs');
        $hasDiscoveryTable = Schema::hasTable('cmdb_discovered_assets');

        $recentLogs = $hasSyncLogsTable
            ? CmdbSyncLog::query()->with(['connector', 'syncJob'])->orderByDesc('started_at')->limit(10)->get()
            : collect();

        $failureLogs = $hasSyncLogsTable
            ? CmdbSyncLog::query()->with(['connector', 'syncJob'])->where('status', 'failed')->orderByDesc('started_at')->limit(8)->get()
            : collect();

        $recentDiscoveries = collect();
        if ($hasSyncLogsTable) {
            $recentDiscoveries = CmdbSyncLog::query()
                ->with(['connector', 'syncJob'])
                ->whereHas('connector', static function ($query): void {
                    $query->whereIn('connector_type', ['snmp', 'vmware']);
                })
                ->orderByDesc('started_at')
                ->limit(20)
                ->get()
                ->map(static function (CmdbSyncLog $log): array {
                    $discovered = Arr::get($log->details ?? [], 'discovery.items.0')
                        ?? Arr::get($log->details ?? [], 'items.0.discovered')
                        ?? [];
                    $discoveredAssetId = Arr::get($log->details ?? [], 'items.0.discovered_asset_id');
                    $discoveredAsset = $discoveredAssetId ? CmdbDiscoveredAsset::query()->find($discoveredAssetId) : null;

                    return [
                        'log' => $log,
                        'connector' => $log->connector,
                        'hostname' => Arr::get($discovered, 'hostname') ?? Arr::get($discovered, 'external_id') ?? '-',
                        'ip' => Arr::get($discovered, 'ip') ?? '-',
                        'description' => Arr::get($discovered, 'description') ?? '-',
                        'status' => $discoveredAsset?->status ?? $log->status,
                        'recommendation' => $discoveredAsset
                            ? match ($discoveredAsset->status) {
                                'linked' => 'linked_to_existing_asset',
                                'created' => 'created',
                                'ignored' => 'ignored',
                                default => 'review_required',
                            }
                            : Arr::get($discovered, 'recommendation', 'review_required'),
                        'discovered_asset_id' => $discoveredAssetId,
                    ];
                });
        }

        $pendingDiscoveries = collect();
        if ($hasDiscoveryTable) {
            $pendingDiscoveries = CmdbDiscoveredAsset::query()
                ->where(function ($query): void {
                    $query->where('source_type', 'snmp')
                        ->orWhere('source_type', 'like', 'vcenter_%');
                })
                ->with(['connector', 'asset', 'reviewedBy'])
                ->where('status', 'pending')
                ->orderByDesc('discovered_at')
                ->orderByDesc('created_at')
                ->limit(12)
                ->get();
        }

        $lastSnmpDiscoveryAt = $hasSyncLogsTable
            ? CmdbSyncLog::query()
                ->whereHas('connector', static function ($query): void {
                    $query->where('connector_type', 'snmp');
                })
                ->max('started_at')
            : null;

        $cards = collect([
            ['label' => 'Conectores ativos', 'value' => (int) CmdbConnector::query()->where('is_active', true)->count()],
            ['label' => 'Conectores com erro', 'value' => (int) CmdbConnector::query()->where('status', 'error')->count()],
            ['label' => 'Conectores SNMP', 'value' => (int) CmdbConnector::query()->where('connector_type', 'snmp')->count()],
            ['label' => 'Jobs ativos', 'value' => (int) CmdbSyncJob::query()->where('is_active', true)->count()],
            ['label' => 'Testes SNMP com falha', 'value' => $hasSyncLogsTable ? (int) CmdbSyncLog::query()->where('status', 'failed')->whereHas('connector', static function ($query): void { $query->where('connector_type', 'snmp'); })->count() : 0],
            ['label' => 'Descobertas pendentes', 'value' => $hasDiscoveryTable ? (int) CmdbDiscoveredAsset::query()->where('source_type', 'snmp')->where('status', 'pending')->count() : 0],
            ['label' => 'Descobertas vinculadas', 'value' => $hasDiscoveryTable ? (int) CmdbDiscoveredAsset::query()->where('source_type', 'snmp')->where('status', 'linked')->count() : 0],
            ['label' => 'Descobertas criadas', 'value' => $hasDiscoveryTable ? (int) CmdbDiscoveredAsset::query()->where('source_type', 'snmp')->where('status', 'created')->count() : 0],
            ['label' => 'Descobertas ignoradas', 'value' => $hasDiscoveryTable ? (int) CmdbDiscoveredAsset::query()->where('source_type', 'snmp')->where('status', 'ignored')->count() : 0],
            ['label' => 'Logs com falha 24h', 'value' => $hasSyncLogsTable ? (int) CmdbSyncLog::query()->where('status', 'failed')->where('started_at', '>=', now()->subDay())->count() : 0],
            ['label' => 'Ativos nunca sincronizados', 'value' => $hasSyncAssetColumns ? (int) CmdbAsset::query()->whereNull('last_synced_at')->count() : 0],
            ['label' => 'Ativos com erro de sincronização', 'value' => $hasSyncAssetColumns ? (int) CmdbAsset::query()->where('sync_status', 'error')->count() : 0],
            ['label' => 'Última descoberta SNMP', 'value' => $lastSnmpDiscoveryAt ? \Illuminate\Support\Carbon::parse($lastSnmpDiscoveryAt)->format('d/m/Y H:i') : '-'],
        ]);

        return view('cmdb.discovery.index', [
            'setupPending' => ! $hasDiscoveryTable,
            'cards' => $cards,
            'activeConnectors' => CmdbConnector::query()->where('is_active', true)->orderBy('name')->limit(8)->get(),
            'errorConnectors' => CmdbConnector::query()->where('status', 'error')->orderByDesc('updated_at')->limit(8)->get(),
            'activeJobs' => CmdbSyncJob::query()->where('is_active', true)->with('connector')->orderBy('name')->limit(8)->get(),
            'recentLogs' => $recentLogs,
            'failureLogs' => $failureLogs,
            'recentDiscoveries' => $recentDiscoveries,
            'pendingDiscoveries' => $pendingDiscoveries,
            'lastSyncAt' => Schema::hasTable('cmdb_sync_logs')
                ? CmdbSyncLog::query()->max('started_at')
                : null,
            'lastSnmpDiscoveryAt' => $lastSnmpDiscoveryAt,
        ]);
    }
}
