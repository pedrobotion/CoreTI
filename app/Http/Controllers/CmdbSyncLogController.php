<?php

namespace App\Http\Controllers;

use App\Models\CmdbConnector;
use App\Models\CmdbSyncJob;
use App\Models\CmdbSyncLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CmdbSyncLogController extends Controller
{
    public function index(Request $request): View
    {
        if (! Schema::hasTable('cmdb_sync_logs')) {
            return view('cmdb.sync-logs.index', [
                'items' => collect(),
                'filters' => [],
                'setupPending' => true,
                'statuses' => [],
                'connectors' => collect(),
                'jobs' => collect(),
            ]);
        }

        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
            'status' => ['nullable', Rule::in(['running', 'success', 'warning', 'failed', 'simulated'])],
            'connector_id' => ['nullable', 'integer', 'exists:cmdb_connectors,id'],
            'sync_job_id' => ['nullable', 'integer', 'exists:cmdb_sync_jobs,id'],
        ]);

        $query = CmdbSyncLog::query()->with(['connector', 'syncJob'])->orderByDesc('started_at');

        if ($search = trim((string) ($filters['q'] ?? ''))) {
            $query->where(function ($q) use ($search): void {
                $q->where('message', 'like', "%{$search}%")
                    ->orWhere('error_message', 'like', "%{$search}%");
            });
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['connector_id'])) {
            $query->where('connector_id', $filters['connector_id']);
        }

        if (! empty($filters['sync_job_id'])) {
            $query->where('sync_job_id', $filters['sync_job_id']);
        }

        return view('cmdb.sync-logs.index', [
            'items' => $query->paginate(15)->withQueryString(),
            'filters' => $filters,
            'setupPending' => false,
            'statuses' => [
                'running' => 'Em execução',
                'success' => 'Sucesso',
                'warning' => 'Com avisos',
                'failed' => 'Falhou',
                'simulated' => 'Simulado',
            ],
            'connectors' => CmdbConnector::query()->orderBy('name')->get(['id', 'name']),
            'jobs' => CmdbSyncJob::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function show(CmdbSyncLog $syncLog): View
    {
        $relations = ['connector', 'syncJob'];
        if (Schema::hasTable('cmdb_discovered_assets')) {
            $relations[] = 'discoveredAssets.asset';
        }

        $syncLog->load($relations);

        return view('cmdb.sync-logs.show', [
            'item' => $syncLog,
            'discoveredAssets' => Schema::hasTable('cmdb_discovered_assets') ? $syncLog->discoveredAssets : collect(),
        ]);
    }
}
