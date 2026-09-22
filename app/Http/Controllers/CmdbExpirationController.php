<?php

namespace App\Http\Controllers;

use App\Models\CmdbAsset;
use App\Models\CmdbExpiration;
use App\Services\CmdbAssetHistoryService;
use App\Services\JiraIssueService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CmdbExpirationController extends Controller
{
    private const TYPES = ['warranty', 'license', 'ssl_certificate', 'contract', 'ups_battery', 'manufacturer_support', 'domain', 'backup_policy', 'other'];
    private const STATUS = ['pending', 'notified', 'renewed', 'expired', 'cancelled'];

    public function index(Request $request): View
    {
        if (! Schema::hasTable('cmdb_expirations')) {
            return view('cmdb.expirations.index', [
                'items' => collect(),
                'assets' => collect(),
                'filters' => [],
                'setupPending' => true,
            ]);
        }

        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
            'status' => ['nullable', Rule::in(self::STATUS)],
            'type' => ['nullable', Rule::in(self::TYPES)],
        ]);

        $query = CmdbExpiration::query()->with('asset');
        if ($search = trim((string) ($filters['q'] ?? ''))) {
            $query->where(function ($q) use ($search): void {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['type'])) {
            $query->where('expiration_type', $filters['type']);
        }

        return view('cmdb.expirations.index', [
            'items' => $query->orderBy('expiration_date')->paginate(15)->withQueryString(),
            'assets' => CmdbAsset::query()->orderBy('name')->get(),
            'filters' => $filters,
            'setupPending' => false,
        ]);
    }

    public function store(Request $request, JiraIssueService $jira, CmdbAssetHistoryService $history): RedirectResponse
    {
        $data = $this->validated($request);
        $expiration = CmdbExpiration::create($data);
        $this->maybeOpenJira($expiration, $jira, $history, $request->user());
        if ($asset = $expiration->asset) {
            $history->record(
                $asset,
                $request->user(),
                'expiration_created',
                'Vencimento criado',
                $expiration->title,
                [],
                $expiration->toArray()
            );
        }

        return back()->with('success', 'Vencimento cadastrado com sucesso.');
    }

    public function update(Request $request, CmdbExpiration $expiration, JiraIssueService $jira, CmdbAssetHistoryService $history): RedirectResponse
    {
        $oldValues = $expiration->toArray();
        $expiration->update($this->validated($request, $expiration));
        $this->maybeOpenJira($expiration->fresh(), $jira, $history, $request->user());
        if ($asset = $expiration->asset) {
            $history->record(
                $asset,
                $request->user(),
                'expiration_updated',
                'Vencimento alterado',
                $expiration->title,
                $oldValues,
                $expiration->fresh()->toArray()
            );
        }

        return back()->with('success', 'Vencimento atualizado com sucesso.');
    }

    public function openJira(Request $request, CmdbExpiration $expiration, JiraIssueService $jira, CmdbAssetHistoryService $history): RedirectResponse
    {
        if ($expiration->jira_issue_key) {
            return back()->with('success', 'Este vencimento já possui chamado Jira.');
        }

        $result = $jira->createIssueForExpiration($expiration);
        if (($result['status'] ?? null) !== 'created' && ($result['status'] ?? null) !== 'simulated') {
            return back()->with('error', $result['message'] ?? 'Não foi possível abrir o chamado Jira.');
        }

        $expiration->update([
            'jira_issue_key' => $result['issue_key'] ?? $expiration->jira_issue_key,
            'jira_issue_url' => $result['issue_url'] ?? $expiration->jira_issue_url,
            'status' => 'notified',
            'notified_at' => now(),
        ]);

        if ($asset = $expiration->asset) {
            $history->record(
                $asset,
                $request->user(),
                'jira_issue_created',
                'Chamado Jira criado',
                $expiration->title,
                [],
                $expiration->fresh()->toArray()
            );
        }

        return back()->with('success', 'Chamado Jira aberto com sucesso.');
    }

    public function toggle(CmdbExpiration $expiration): RedirectResponse
    {
        $expiration->update(['status' => $expiration->status === 'cancelled' ? 'pending' : 'cancelled']);

        return back()->with('success', 'Status do vencimento alterado.');
    }

    private function validated(Request $request, ?CmdbExpiration $expiration = null): array
    {
        return $request->validate([
            'asset_id' => ['required', 'integer', 'exists:cmdb_assets,id'],
            'expiration_type' => ['required', Rule::in(self::TYPES)],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'expiration_date' => ['required', 'date'],
            'alert_days_before' => ['nullable', 'integer', 'min:0', 'max:3650'],
            'status' => ['required', Rule::in(self::STATUS)],
        ]);
    }

    private function maybeOpenJira(CmdbExpiration $expiration, JiraIssueService $jira, ?CmdbAssetHistoryService $history = null, $user = null): void
    {
        if ($expiration->status !== 'pending') {
            return;
        }

        if ($expiration->jira_issue_key) {
            return;
        }

        $daysBefore = (int) ($expiration->alert_days_before ?? 30);
        if ($expiration->expiration_date && now()->addDays($daysBefore)->startOfDay()->lt($expiration->expiration_date->startOfDay())) {
            return;
        }

        $result = $jira->createIssueForExpiration($expiration);
        if (($result['status'] ?? null) === 'created' || ($result['status'] ?? null) === 'simulated') {
            $expiration->update([
                'jira_issue_key' => $result['issue_key'] ?? $expiration->jira_issue_key,
                'jira_issue_url' => $result['issue_url'] ?? $expiration->jira_issue_url,
                'status' => 'notified',
                'notified_at' => now(),
            ]);

            if (($result['status'] ?? null) === 'created' && $history && $asset = $expiration->asset) {
                $history->record(
                    $asset,
                    $user,
                    'jira_issue_created',
                    'Chamado Jira criado',
                    $expiration->title,
                    [],
                    $expiration->fresh()->toArray()
                );
            }
        }
    }
}
