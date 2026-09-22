<?php

namespace App\Http\Controllers;

use App\Models\CmdbAsset;
use App\Models\CmdbRelationship;
use App\Services\CmdbAssetHistoryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class CmdbRelationshipController extends Controller
{
    private const TYPES = [
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
    ];

    public function index(Request $request): View
    {
        if (! Schema::hasTable('cmdb_relationships')) {
            return view('cmdb.relationships.index', [
                'items' => collect(),
                'assets' => collect(),
                'filters' => [],
                'setupPending' => true,
                'types' => self::TYPES,
            ]);
        }

        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
            'type' => ['nullable', Rule::in(array_keys(self::TYPES))],
        ]);

        $query = CmdbRelationship::query()->with(['sourceAsset', 'targetAsset']);
        if ($search = trim((string) ($filters['q'] ?? ''))) {
            $query->where(function ($q) use ($search): void {
                $q->where('description', 'like', "%{$search}%")
                    ->orWhere('relationship_type', 'like', "%{$search}%");
            });
        }

        if (! empty($filters['type'])) {
            $query->where('relationship_type', $filters['type']);
        }

        return view('cmdb.relationships.index', [
            'items' => $query->orderByDesc('created_at')->paginate(15)->withQueryString(),
            'assets' => CmdbAsset::query()->orderBy('name')->get(),
            'filters' => $filters,
            'setupPending' => false,
            'types' => self::TYPES,
        ]);
    }

    public function edit(CmdbRelationship $relationship): View
    {
        $relationship->load(['sourceAsset', 'targetAsset']);

        return view('cmdb.relationships.edit', [
            'relationship' => $relationship,
            'assets' => CmdbAsset::query()->orderBy('name')->get(),
            'types' => self::TYPES,
        ]);
    }

    public function store(Request $request, CmdbAssetHistoryService $history): RedirectResponse
    {
        $data = $this->validated($request);
        $relationship = CmdbRelationship::create($data);
        $this->recordForAssets($history, $relationship, 'relationship_added', 'Relacionamento criado', null, [], $relationship->toArray(), $request->user());

        return back()->with('success', 'Relacionamento cadastrado com sucesso.');
    }

    public function update(Request $request, CmdbRelationship $relationship, CmdbAssetHistoryService $history): RedirectResponse
    {
        $oldValues = $relationship->toArray();
        $relationship->update($this->validated($request, $relationship));
        $relationship->refresh();
        $this->recordForAssets($history, $relationship, 'relationship_updated', 'Relacionamento alterado', null, $oldValues, $relationship->toArray(), $request->user());

        return redirect()->route('cmdb.relationships.index')->with('success', 'Relacionamento atualizado com sucesso.');
    }

    public function destroy(Request $request, CmdbRelationship $relationship, CmdbAssetHistoryService $history): RedirectResponse
    {
        $oldValues = $relationship->toArray();
        $this->recordForAssets($history, $relationship, 'relationship_deleted', 'Relacionamento removido', null, $oldValues, [], $request->user());
        $relationship->delete();

        return back()->with('success', 'Relacionamento removido com sucesso.');
    }

    private function recordForAssets(
        CmdbAssetHistoryService $history,
        CmdbRelationship $relationship,
        string $eventType,
        string $title,
        ?string $description,
        array $oldValues,
        array $newValues,
        $user
    ): void {
        foreach ([$relationship->sourceAsset, $relationship->targetAsset] as $asset) {
            if ($asset) {
                $history->record($asset, $user, $eventType, $title, $description, $oldValues, $newValues);
            }
        }
    }

    private function validated(Request $request, ?CmdbRelationship $relationship = null): array
    {
        $data = $request->validate([
            'source_asset_id' => ['required', 'integer', 'exists:cmdb_assets,id', 'different:target_asset_id'],
            'target_asset_id' => ['required', 'integer', 'exists:cmdb_assets,id', 'different:source_asset_id'],
            'relationship_type' => ['required', Rule::in(array_keys(self::TYPES))],
            'description' => ['nullable', 'string'],
        ]);

        $exists = CmdbRelationship::query()
            ->where('source_asset_id', $data['source_asset_id'])
            ->where('target_asset_id', $data['target_asset_id'])
            ->where('relationship_type', $data['relationship_type'])
            ->when($relationship, fn ($query) => $query->where('id', '!=', $relationship->id))
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'target_asset_id' => 'Já existe um relacionamento igual para esses ativos.',
            ]);
        }

        return $data;
    }
}
