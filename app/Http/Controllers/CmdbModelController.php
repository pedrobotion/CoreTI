<?php

namespace App\Http\Controllers;

use App\Models\CmdbManufacturer;
use App\Models\CmdbModel;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CmdbModelController extends Controller
{
    public function index(Request $request): View
    {
        if (! Schema::hasTable('cmdb_models')) {
            return view('cmdb.models.index', [
                'items' => collect(),
                'filters' => [],
                'setupPending' => true,
                'manufacturers' => collect(),
            ]);
        }

        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
            'manufacturer_id' => ['nullable', 'integer', 'exists:cmdb_manufacturers,id'],
            'asset_type' => ['nullable', 'string', 'max:50'],
            'is_active' => ['nullable', 'in:0,1'],
        ]);

        $query = CmdbModel::query()->with('manufacturer');

        if ($search = trim((string) ($filters['q'] ?? ''))) {
            $query->where(function ($subQuery) use ($search): void {
                $subQuery->where('name', 'like', "%{$search}%")
                    ->orWhere('slug', 'like', "%{$search}%")
                    ->orWhere('part_number', 'like', "%{$search}%");
            });
        }

        if (! empty($filters['manufacturer_id'])) {
            $query->where('manufacturer_id', $filters['manufacturer_id']);
        }

        if (! empty($filters['asset_type'])) {
            $query->where('asset_type', $filters['asset_type']);
        }

        if (array_key_exists('is_active', $filters) && $filters['is_active'] !== '') {
            $query->where('is_active', (bool) ((int) $filters['is_active']));
        }

        return view('cmdb.models.index', [
            'items' => $query->orderBy('name')->paginate(15)->withQueryString(),
            'filters' => $filters,
            'setupPending' => false,
            'manufacturers' => CmdbManufacturer::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function create(): View
    {
        return view('cmdb.models.create', [
            'model' => new CmdbModel(),
            'manufacturers' => CmdbManufacturer::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $model = CmdbModel::create($this->validated($request));

        return redirect()->route('cmdb.models.show', $model)->with('success', 'Modelo cadastrado com sucesso.');
    }

    public function show(CmdbModel $model): View
    {
        $model->load('manufacturer');

        return view('cmdb.models.show', [
            'model' => $model,
        ]);
    }

    public function edit(CmdbModel $model): View
    {
        return view('cmdb.models.edit', [
            'model' => $model,
            'manufacturers' => CmdbManufacturer::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function update(Request $request, CmdbModel $model): RedirectResponse
    {
        $model->update($this->validated($request, $model));

        return redirect()->route('cmdb.models.show', $model)->with('success', 'Modelo atualizado com sucesso.');
    }

    public function destroy(CmdbModel $model): RedirectResponse
    {
        $model->delete();

        return redirect()->route('cmdb.models.index')->with('success', 'Modelo removido com sucesso.');
    }

    private function validated(Request $request, ?CmdbModel $model = null): array
    {
        $data = $request->validate([
            'manufacturer_id' => ['nullable', 'integer', 'exists:cmdb_manufacturers,id'],
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255'],
            'asset_type' => ['nullable', 'string', 'max:50'],
            'part_number' => ['nullable', 'string', 'max:255'],
            'rack_units' => ['nullable', 'integer', 'min:0'],
            'eol_date' => ['nullable', 'date'],
            'eos_date' => ['nullable', 'date'],
            'support_end_date' => ['nullable', 'date'],
            'manual_url' => ['nullable', 'string', 'max:255'],
            'image_url' => ['nullable', 'string', 'max:255'],
            'power_consumption_watts' => ['nullable', 'integer', 'min:0'],
            'notes' => ['nullable', 'string'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $data['is_active'] = (bool) ($data['is_active'] ?? false);
        $data['slug'] = filled($data['slug'] ?? null) ? Str::slug((string) $data['slug']) : Str::slug((string) $data['name']);

        return array_map(static fn ($value) => is_string($value) ? trim($value) : $value, $data);
    }
}
