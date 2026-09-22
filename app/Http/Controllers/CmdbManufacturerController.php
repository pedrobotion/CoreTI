<?php

namespace App\Http\Controllers;

use App\Models\CmdbManufacturer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\View\View;

class CmdbManufacturerController extends Controller
{
    public function index(Request $request): View
    {
        if (! Schema::hasTable('cmdb_manufacturers')) {
            return view('cmdb.manufacturers.index', [
                'items' => collect(),
                'filters' => [],
                'setupPending' => true,
            ]);
        }

        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
            'is_active' => ['nullable', 'in:0,1'],
        ]);

        $query = CmdbManufacturer::query()->withCount('models');

        if ($search = trim((string) ($filters['q'] ?? ''))) {
            $query->where(function ($subQuery) use ($search): void {
                $subQuery->where('name', 'like', "%{$search}%")
                    ->orWhere('slug', 'like', "%{$search}%")
                    ->orWhere('website', 'like', "%{$search}%")
                    ->orWhere('support_url', 'like', "%{$search}%");
            });
        }

        if (array_key_exists('is_active', $filters) && $filters['is_active'] !== '') {
            $query->where('is_active', (bool) ((int) $filters['is_active']));
        }

        return view('cmdb.manufacturers.index', [
            'items' => $query->orderBy('name')->paginate(15)->withQueryString(),
            'filters' => $filters,
            'setupPending' => false,
        ]);
    }

    public function create(): View
    {
        return view('cmdb.manufacturers.create', [
            'manufacturer' => new CmdbManufacturer(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $manufacturer = CmdbManufacturer::create($this->validated($request));

        return redirect()->route('cmdb.manufacturers.show', $manufacturer)->with('success', 'Fabricante cadastrado com sucesso.');
    }

    public function show(CmdbManufacturer $manufacturer): View
    {
        $manufacturer->load(['models' => fn ($query) => $query->orderBy('name')]);

        return view('cmdb.manufacturers.show', compact('manufacturer'));
    }

    public function edit(CmdbManufacturer $manufacturer): View
    {
        return view('cmdb.manufacturers.edit', compact('manufacturer'));
    }

    public function update(Request $request, CmdbManufacturer $manufacturer): RedirectResponse
    {
        $manufacturer->update($this->validated($request, $manufacturer));

        return redirect()->route('cmdb.manufacturers.show', $manufacturer)->with('success', 'Fabricante atualizado com sucesso.');
    }

    public function destroy(CmdbManufacturer $manufacturer): RedirectResponse
    {
        $manufacturer->delete();

        return redirect()->route('cmdb.manufacturers.index')->with('success', 'Fabricante removido com sucesso.');
    }

    private function validated(Request $request, ?CmdbManufacturer $manufacturer = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255'],
            'website' => ['nullable', 'string', 'max:255'],
            'support_url' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $data['is_active'] = (bool) ($data['is_active'] ?? false);
        $data['slug'] = filled($data['slug'] ?? null) ? Str::slug((string) $data['slug']) : Str::slug((string) $data['name']);

        return array_map(static fn ($value) => is_string($value) ? trim($value) : $value, $data);
    }
}
