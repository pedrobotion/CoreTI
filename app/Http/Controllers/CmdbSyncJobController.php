<?php

namespace App\Http\Controllers;

use App\Models\CmdbConnector;
use App\Models\CmdbSyncJob;
use App\Services\CmdbSyncService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CmdbSyncJobController extends Controller
{
    public function index(Request $request): View
    {
        if (! Schema::hasTable('cmdb_sync_jobs')) {
            return view('cmdb.sync-jobs.index', [
                'items' => collect(),
                'filters' => [],
                'setupPending' => true,
                'scheduleTypes' => CmdbSyncJob::SCHEDULE_TYPES,
            ]);
        }

        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
            'connector_id' => ['nullable', 'integer', 'exists:cmdb_connectors,id'],
            'schedule_type' => ['nullable', Rule::in(array_keys(CmdbSyncJob::SCHEDULE_TYPES))],
            'is_active' => ['nullable', Rule::in(['0', '1'])],
        ]);

        $query = CmdbSyncJob::query()->with('connector')->withCount('syncLogs');

        if ($search = trim((string) ($filters['q'] ?? ''))) {
            $query->where('name', 'like', "%{$search}%");
        }

        if (! empty($filters['connector_id'])) {
            $query->where('connector_id', $filters['connector_id']);
        }

        if (! empty($filters['schedule_type'])) {
            $query->where('schedule_type', $filters['schedule_type']);
        }

        if (array_key_exists('is_active', $filters)) {
            $query->where('is_active', (bool) ((int) $filters['is_active']));
        }

        return view('cmdb.sync-jobs.index', [
            'items' => $query->orderBy('name')->paginate(15)->withQueryString(),
            'filters' => $filters,
            'setupPending' => false,
            'scheduleTypes' => CmdbSyncJob::SCHEDULE_TYPES,
        ]);
    }

    public function create(): View
    {
        return view('cmdb.sync-jobs.create', [
            'job' => new CmdbSyncJob(),
            'connectors' => CmdbConnector::query()->orderBy('name')->get(),
            'scheduleTypes' => CmdbSyncJob::SCHEDULE_TYPES,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $job = CmdbSyncJob::create($this->validated($request));

        return redirect()->route('cmdb.sync-jobs.show', $job)->with('success', 'Job de sincronização criado com sucesso.');
    }

    public function show(CmdbSyncJob $job): View
    {
        $job->load(['connector', 'syncLogs' => fn ($query) => $query->orderByDesc('started_at')->limit(15)]);

        return view('cmdb.sync-jobs.show', [
            'job' => $job,
            'scheduleTypes' => CmdbSyncJob::SCHEDULE_TYPES,
        ]);
    }

    public function edit(CmdbSyncJob $job): View
    {
        return view('cmdb.sync-jobs.edit', [
            'job' => $job,
            'connectors' => CmdbConnector::query()->orderBy('name')->get(),
            'scheduleTypes' => CmdbSyncJob::SCHEDULE_TYPES,
        ]);
    }

    public function update(Request $request, CmdbSyncJob $job): RedirectResponse
    {
        $job->update($this->validated($request));

        return redirect()->route('cmdb.sync-jobs.show', $job)->with('success', 'Job atualizado com sucesso.');
    }

    public function destroy(CmdbSyncJob $job): RedirectResponse
    {
        $job->delete();

        return redirect()->route('cmdb.sync-jobs.index')->with('success', 'Job removido com sucesso.');
    }

    public function runNow(Request $request, CmdbSyncJob $job, CmdbSyncService $syncService): RedirectResponse
    {
        $result = $syncService->syncJob($job, $request->user());
        $summary = $result['summary'] ?? [];
        $message = sprintf(
            '%s | Encontrados: %d | Atualizados: %d | Sem alteração: %d',
            $result['message'] ?? 'Execução concluída.',
            (int) ($summary['assets_found'] ?? 0),
            (int) ($summary['assets_updated'] ?? 0),
            (int) ($summary['assets_unchanged'] ?? 0)
        );

        return $result['status'] === 'failed'
            ? back()->with('error', $message)
            : back()->with('success', $message);
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'connector_id' => ['nullable', 'integer', 'exists:cmdb_connectors,id'],
            'name' => ['required', 'string', 'max:255'],
            'schedule_type' => ['required', Rule::in(array_keys(CmdbSyncJob::SCHEDULE_TYPES))],
            'frequency_minutes' => ['nullable', 'integer', 'min:1', 'max:525600'],
            'run_at' => ['nullable', 'date_format:H:i'],
            'is_active' => ['nullable', 'boolean'],
            'settings' => ['nullable', 'string'],
        ]);

        $data['is_active'] = (bool) ($data['is_active'] ?? false);
        $data['settings'] = $this->decodeSettings($data['settings'] ?? null);

        return $data;
    }

    private function decodeSettings(?string $settings): ?array
    {
        $settings = trim((string) $settings);

        if ($settings === '') {
            return null;
        }

        try {
            return json_decode($settings, true, 512, JSON_THROW_ON_ERROR);
        } catch (\Throwable) {
            return ['raw' => $settings];
        }
    }
}
