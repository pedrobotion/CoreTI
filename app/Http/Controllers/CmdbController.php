<?php

namespace App\Http\Controllers;

use App\Models\CmdbAsset;
use App\Models\CmdbAssetDocument;
use App\Models\CmdbAssetHistory;
use App\Models\CmdbConnector;
use App\Models\CmdbDiscoveredAsset;
use App\Models\CmdbExpiration;
use App\Models\CmdbSyncJob;
use App\Models\CmdbSyncLog;
use App\Services\CmdbDiscoveryQueryService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class CmdbController extends Controller
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

    private const STATUS_LABELS = [
        'active' => 'Ativo',
        'inactive' => 'Inativo',
        'maintenance' => 'Em manutenção',
        'decommissioned' => 'Descomissionado',
        'planned' => 'Planejado',
    ];

    public function dashboard(CmdbDiscoveryQueryService $discoveryQuery): View
    {
        if (! Schema::hasTable('cmdb_assets')) {
            return view('cmdb.dashboard', [
                'setupPending' => true,
                'priorityCards' => [],
                'urgentExpirations' => collect(),
                'criticalAssets' => collect(),
                'maintenanceAssets' => collect(),
                'pendingDiscoveries' => collect(),
                'recentSyncFailures' => collect(),
                'assetsWithoutIp' => collect(),
                'serversWithoutService' => collect(),
            ]);
        }

        $hasSyncAssetColumns = Schema::hasColumn('cmdb_assets', 'last_synced_at') && Schema::hasColumn('cmdb_assets', 'source_type');
        $hasSyncConnectorsTable = Schema::hasTable('cmdb_connectors');
        $hasSyncJobsTable = Schema::hasTable('cmdb_sync_jobs');
        $hasSyncLogsTable = Schema::hasTable('cmdb_sync_logs');
        $hasDiscoveryTable = Schema::hasTable('cmdb_discovered_assets');
        $lastSnmpDiscoveryAt = $hasSyncLogsTable
            ? CmdbSyncLog::query()
                ->whereHas('connector', static function ($query): void {
                    $query->where('connector_type', 'snmp');
                })
                ->max('started_at')
            : null;
        $snmpFailures = $hasSyncLogsTable
            ? (int) CmdbSyncLog::query()->where('status', 'failed')->whereHas('connector', static function ($query): void { $query->where('connector_type', 'snmp'); })->count()
            : 0;
        $discoveriesPending = $hasDiscoveryTable ? $discoveryQuery->counts()['pending'] : 0;
        $discoveriesLinked = $hasDiscoveryTable ? (int) CmdbDiscoveredAsset::query()->where('source_type', 'snmp')->where('status', 'linked')->count() : 0;
        $discoveriesCreated = $hasDiscoveryTable ? (int) CmdbDiscoveredAsset::query()->where('source_type', 'snmp')->where('status', 'created')->count() : 0;
        $discoveriesIgnored = $hasDiscoveryTable ? (int) CmdbDiscoveredAsset::query()->where('source_type', 'snmp')->where('status', 'ignored')->count() : 0;

        $priorityCards = collect([
            ['key' => 'expirations', 'label' => 'Vencidos ou urgentes (7 dias)', 'value' => (int) DB::table('cmdb_expirations')->whereIn('status', ['pending', 'notified', 'expired'])->whereDate('expiration_date', '<=', now()->addDays(7)->toDateString())->count(), 'tone' => 'rose'],
            ['key' => 'critical', 'label' => 'Ativos críticos', 'value' => (int) DB::table('cmdb_assets')->where('criticality', 'critical')->count(), 'tone' => 'amber'],
            ['key' => 'maintenance', 'label' => 'Em manutenção', 'value' => (int) DB::table('cmdb_assets')->where('status', 'maintenance')->count(), 'tone' => 'amber'],
            ['key' => 'sync', 'label' => 'Falhas de sincronização', 'value' => $hasSyncLogsTable ? (int) CmdbSyncLog::query()->where('status', 'failed')->count() : 0, 'tone' => 'rose'],
            ['key' => 'discoveries', 'label' => 'Descobertas pendentes', 'value' => $discoveriesPending, 'tone' => 'blue'],
            ['key' => 'ip', 'label' => 'Ativos sem IP', 'value' => (int) CmdbAsset::query()->whereDoesntHave('ipAddresses')->count(), 'tone' => 'slate'],
            ['key' => 'vms', 'label' => 'Máquinas virtuais', 'value' => (int) CmdbAsset::query()->where('asset_type', 'virtual_machine')->count(), 'tone' => 'blue'],
        ]);

        if (Schema::hasTable('cmdb_asset_backups')) {
            $veeamStatuses = DB::table('cmdb_asset_backups')
                ->join('cmdb_assets', 'cmdb_assets.id', '=', 'cmdb_asset_backups.asset_id')
                ->where('cmdb_assets.asset_type', 'virtual_machine')
                ->select('protection_status', DB::raw('COUNT(*) as total'))
                ->groupBy('protection_status')
                ->pluck('total', 'protection_status');
            foreach ([
                ['protected', 'VMs protegidas', 'emerald'],
                ['overdue', 'Backups atrasados', 'amber'],
                ['failed', 'Backups com falha', 'rose'],
                ['unprotected', 'VMs sem proteção', 'rose'],
                ['unknown', 'Proteção desconhecida', 'slate'],
            ] as [$status, $label, $tone]) {
                $priorityCards->push([
                    'key' => 'veeam_'.$status,
                    'label' => $label,
                    'value' => (int) ($veeamStatuses[$status] ?? 0),
                    'tone' => $tone,
                ]);
            }
        }

        $serversWithoutService = CmdbAsset::query()
            ->with('serverDetails')
            ->whereIn('asset_type', ['server', 'virtual_machine'])
            ->where(function ($query): void {
                $query->whereDoesntHave('serverDetails')
                    ->orWhereHas('serverDetails', function ($serverQuery): void {
                        $serverQuery->where(function ($query): void {
                            $query->whereNull('service_id')
                                ->where(function ($query): void {
                                    $query->whereNull('service_type')
                                        ->orWhereNull('service_description')
                                        ->orWhere('service_type', '')
                                        ->orWhere('service_description', '');
                                });
                        });
                    });
            })
            ->orderBy('hostname')
            ->limit(12)
            ->get();

        $serversWithoutServiceCount = CmdbAsset::query()
            ->whereIn('asset_type', ['server', 'virtual_machine'])
            ->where(function ($query): void {
                $query->whereDoesntHave('serverDetails')
                    ->orWhereHas('serverDetails', function ($serverQuery): void {
                        $serverQuery->where(function ($query): void {
                            $query->whereNull('service_id')
                                ->where(function ($query): void {
                                    $query->whereNull('service_type')
                                        ->orWhereNull('service_description')
                                        ->orWhere('service_type', '')
                                        ->orWhere('service_description', '');
                                });
                        });
                    });
            })
            ->count();

        $priorityCards->push([
            'key' => 'service',
            'label' => 'Servidores/VMs sem serviço',
            'value' => $serversWithoutServiceCount,
            'tone' => $serversWithoutServiceCount > 0 ? 'rose' : 'slate',
        ]);

        $urgentExpirations = CmdbExpiration::query()
            ->with('asset')
            ->whereIn('status', ['pending', 'notified', 'expired'])
            ->whereDate('expiration_date', '<=', now()->addDays(30)->toDateString())
            ->orderBy('expiration_date')
            ->limit(8)
            ->get();

        return view('cmdb.dashboard', [
            'setupPending' => false,
            'priorityCards' => $priorityCards,
            'urgentExpirations' => $urgentExpirations,
            'criticalAssets' => CmdbAsset::query()->where('criticality', 'critical')->orderByDesc('updated_at')->limit(8)->get(),
            'maintenanceAssets' => CmdbAsset::query()->where('status', 'maintenance')->orderByDesc('updated_at')->limit(8)->get(),
            'pendingDiscoveries' => $hasDiscoveryTable ? $discoveryQuery->pending()->with(['connector', 'asset'])->orderByDesc('discovered_at')->orderByDesc('created_at')->limit(8)->get() : collect(),
            'recentSyncFailures' => $hasSyncLogsTable ? CmdbSyncLog::query()->with(['connector', 'syncJob'])->where('status', 'failed')->orderByDesc('started_at')->limit(8)->get() : collect(),
            'assetsWithoutIp' => CmdbAsset::query()->whereDoesntHave('ipAddresses')->orderByDesc('updated_at')->limit(8)->get(),
            'serversWithoutService' => $serversWithoutService,
            'serversWithoutServiceCount' => $serversWithoutServiceCount,
            'types' => self::TYPES,
            'statusLabels' => self::STATUS_LABELS,
            'lastSnmpDiscoveryAt' => $lastSnmpDiscoveryAt ? \Illuminate\Support\Carbon::parse($lastSnmpDiscoveryAt)->format('d/m/Y H:i') : null,
            'snmpFailures' => $snmpFailures,
            'discoveriesLinked' => $discoveriesLinked,
            'discoveriesCreated' => $discoveriesCreated,
            'discoveriesIgnored' => $discoveriesIgnored,
            'jiraNotifications' => (int) DB::table('cmdb_expirations')->whereNotNull('jira_issue_key')->count(),
            'syncedAssets' => $hasSyncAssetColumns ? (int) CmdbAsset::query()->whereNotNull('last_synced_at')->count() : 0,
        ]);
    }

    public function operations(): View
    {
        if (! Schema::hasTable('cmdb_assets')) {
            return view('cmdb.operations', [
                'setupPending' => true,
                'cards' => [],
                'criticalAssets' => collect(),
                'urgentExpirations' => collect(),
                'incompleteAssets' => collect(),
                'recentHistories' => collect(),
                'recentDocuments' => collect(),
                'recentSyncFailures' => collect(),
            ]);
        }

        $hasHistories = Schema::hasTable('cmdb_asset_histories');
        $hasDocuments = Schema::hasTable('cmdb_asset_documents');
        $hasSyncAssetColumns = Schema::hasColumn('cmdb_assets', 'last_synced_at') && Schema::hasColumn('cmdb_assets', 'source_type');
        $hasSyncConnectorsTable = Schema::hasTable('cmdb_connectors');
        $hasSyncJobsTable = Schema::hasTable('cmdb_sync_jobs');
        $hasSyncLogsTable = Schema::hasTable('cmdb_sync_logs');
        $hasDiscoveryTable = Schema::hasTable('cmdb_discovered_assets');
        $lastSnmpDiscoveryAt = $hasSyncLogsTable
            ? CmdbSyncLog::query()
                ->whereHas('connector', static function ($query): void {
                    $query->where('connector_type', 'snmp');
                })
                ->max('started_at')
            : null;
        $snmpFailures = $hasSyncLogsTable
            ? (int) CmdbSyncLog::query()->where('status', 'failed')->whereHas('connector', static function ($query): void { $query->where('connector_type', 'snmp'); })->count()
            : 0;
        $discoveriesPending = $hasDiscoveryTable ? (int) CmdbDiscoveredAsset::query()->where('source_type', 'snmp')->where('status', 'pending')->count() : 0;
        $discoveriesLinked = $hasDiscoveryTable ? (int) CmdbDiscoveredAsset::query()->where('source_type', 'snmp')->where('status', 'linked')->count() : 0;
        $discoveriesCreated = $hasDiscoveryTable ? (int) CmdbDiscoveredAsset::query()->where('source_type', 'snmp')->where('status', 'created')->count() : 0;
        $discoveriesIgnored = $hasDiscoveryTable ? (int) CmdbDiscoveredAsset::query()->where('source_type', 'snmp')->where('status', 'ignored')->count() : 0;

        $urgentExpirations = CmdbExpiration::query()
            ->with('asset')
            ->where(function ($query): void {
                $query->where('status', 'pending')
                    ->orWhere('status', 'notified');
            })
            ->whereDate('expiration_date', '<=', now()->addDays(30)->toDateString())
            ->orderBy('expiration_date')
            ->limit(12)
            ->get();

        $recentSyncFailures = $hasSyncLogsTable
            ? CmdbSyncLog::query()->with(['connector', 'syncJob'])->where('status', 'failed')->orderByDesc('started_at')->limit(8)->get()
            : collect();
        $pendingDiscoveries = $hasDiscoveryTable
            ? CmdbDiscoveredAsset::query()->with(['connector', 'asset'])->where('status', 'pending')->orderByDesc('discovered_at')->orderByDesc('created_at')->limit(8)->get()
            : collect();

        $cards = collect([
            ['label' => 'Total de ativos', 'value' => (int) CmdbAsset::query()->count()],
            ['label' => 'Ativos críticos', 'value' => (int) CmdbAsset::query()->where('criticality', 'critical')->count()],
            ['label' => 'Ativos em manutenção', 'value' => (int) CmdbAsset::query()->where('status', 'maintenance')->count()],
            ['label' => 'Ativos sem IP', 'value' => (int) CmdbAsset::query()->whereDoesntHave('ipAddresses')->count()],
            ['label' => 'Ativos sem rack', 'value' => (int) CmdbAsset::query()->whereNull('rack_id')->count()],
            ['label' => 'Ativos sem relacionamento', 'value' => (int) CmdbAsset::query()->whereDoesntHave('outgoingRelationships')->whereDoesntHave('incomingRelationships')->count()],
            ['label' => 'Vencimentos expirados', 'value' => (int) DB::table('cmdb_expirations')->where('status', 'expired')->count()],
            ['label' => 'Vencimentos 7 dias', 'value' => (int) DB::table('cmdb_expirations')->whereIn('status', ['pending', 'notified'])->whereDate('expiration_date', '<=', now()->addDays(7)->toDateString())->count()],
            ['label' => 'Vencimentos 30 dias', 'value' => (int) DB::table('cmdb_expirations')->whereIn('status', ['pending', 'notified'])->whereDate('expiration_date', '<=', now()->addDays(30)->toDateString())->count()],
            ['label' => 'Chamados Jira gerados', 'value' => (int) DB::table('cmdb_expirations')->whereNotNull('jira_issue_key')->count()],
            ['label' => 'Conectores', 'value' => $hasSyncConnectorsTable ? (int) CmdbConnector::query()->count() : 0],
            ['label' => 'Conectores SNMP', 'value' => $hasSyncConnectorsTable ? (int) CmdbConnector::query()->where('connector_type', 'snmp')->count() : 0],
            ['label' => 'Jobs de sincronização', 'value' => $hasSyncJobsTable ? (int) CmdbSyncJob::query()->count() : 0],
            ['label' => 'Sincronizações hoje', 'value' => $hasSyncLogsTable ? (int) CmdbSyncLog::query()->whereDate('started_at', today())->count() : 0],
            ['label' => 'Falhas de sincronização', 'value' => $hasSyncLogsTable ? (int) CmdbSyncLog::query()->where('status', 'failed')->count() : 0],
            ['label' => 'Testes SNMP com falha', 'value' => $snmpFailures],
            ['label' => 'Descobertas pendentes', 'value' => $discoveriesPending],
            ['label' => 'Descobertas vinculadas', 'value' => $discoveriesLinked],
            ['label' => 'Descobertas criadas', 'value' => $discoveriesCreated],
            ['label' => 'Descobertas ignoradas', 'value' => $discoveriesIgnored],
            ['label' => 'Última descoberta SNMP', 'value' => $lastSnmpDiscoveryAt ? \Illuminate\Support\Carbon::parse($lastSnmpDiscoveryAt)->format('d/m/Y H:i') : '-'],
            ['label' => 'Ativos sincronizados', 'value' => $hasSyncAssetColumns ? (int) CmdbAsset::query()->whereNotNull('last_synced_at')->count() : 0],
            ['label' => 'Ativos manuais', 'value' => $hasSyncAssetColumns ? (int) CmdbAsset::query()->where(function ($query): void { $query->whereNull('source_type')->orWhere('source_type', 'manual'); })->count() : 0],
        ]);

        return view('cmdb.operations', [
            'setupPending' => false,
            'cards' => $cards,
            'criticalAssets' => CmdbAsset::query()->where('criticality', 'critical')->with('rack')->orderByDesc('updated_at')->limit(10)->get(),
            'urgentExpirations' => $urgentExpirations,
            'recentSyncFailures' => $recentSyncFailures,
            'incompleteAssets' => CmdbAsset::query()
                ->with(['ipAddresses', 'outgoingRelationships', 'incomingRelationships'])
                ->where(function ($query): void {
                    $query->whereDoesntHave('ipAddresses')
                        ->orWhereNull('rack_id')
                        ->orWhereDoesntHave('outgoingRelationships')
                        ->orWhereDoesntHave('incomingRelationships');
                })
                ->orderByDesc('updated_at')
                ->limit(12)
                ->get(),
            'recentHistories' => $hasHistories ? CmdbAssetHistory::query()->with(['asset', 'user'])->orderByDesc('created_at')->limit(12)->get() : collect(),
            'recentDocuments' => $hasDocuments ? CmdbAssetDocument::query()->with(['asset', 'user'])->orderByDesc('created_at')->limit(12)->get() : collect(),
            'pendingDiscoveries' => $pendingDiscoveries,
        ]);
    }

    private function expiringCount(string $type): int
    {
        return (int) DB::table('cmdb_expirations')
            ->where('expiration_type', $type)
            ->where(function ($query): void {
                $query->where('status', 'pending')
                    ->orWhere('status', 'notified');
            })
            ->whereDate('expiration_date', '<=', now()->addDays(90)->toDateString())
            ->count();
    }

    public function reports(): View
    {
        $summary = [
            'ativos' => (int) DB::table('cmdb_assets')->count(),
            'em_manutencao' => (int) DB::table('cmdb_assets')->where('status', 'maintenance')->count(),
            'criticos' => (int) DB::table('cmdb_assets')->where('criticality', 'critical')->count(),
            'vencendo_90' => (int) DB::table('cmdb_expirations')
                ->where('status', 'pending')
                ->whereDate('expiration_date', '<=', now()->addDays(90)->toDateString())
                ->count(),
        ];

        return view('cmdb.reports', compact('summary'));
    }
}
