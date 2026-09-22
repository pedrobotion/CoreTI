<?php

namespace App\Http\Controllers;

use App\Models\CmdbAsset;
use App\Models\CmdbRack;
use App\Models\Departamento;
use App\Models\Unidade;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class CmdbRackController extends Controller
{
    public function index(Request $request): View
    {
        if (! Schema::hasTable('cmdb_racks')) {
            return view('cmdb.racks.index', [
                'items' => collect(),
                'unitOptions' => collect(),
                'filters' => [],
                'setupPending' => true,
                'rack' => null,
            ]);
        }

        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
        ]);

        $query = CmdbRack::query()->withCount('assets');
        if ($search = trim((string) ($filters['q'] ?? ''))) {
            $query->where(function ($q) use ($search): void {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('location', 'like', "%{$search}%")
                    ->orWhere('room', 'like', "%{$search}%")
                    ->orWhere('floor', 'like', "%{$search}%");
            });
        }

        return view('cmdb.racks.index', [
            'items' => $query->orderBy('name')->paginate(20)->withQueryString(),
            'unitOptions' => $this->unitOptions(),
            'unitLabels' => $this->unitLabels(),
            'filters' => $filters,
            'setupPending' => false,
            'rack' => null,
        ]);
    }

    public function create(): View
    {
        return view('cmdb.racks.create', [
            'rack' => null,
            'unitOptions' => $this->unitOptions(),
            'unitLabels' => $this->unitLabels(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $rack = CmdbRack::create($this->validated($request));

        return redirect()->route('cmdb.racks.show', $rack)->with('success', 'Rack cadastrado com sucesso.');
    }

    public function show(CmdbRack $rack): View
    {
        $rack->load(['assets', 'ups']);

        return view('cmdb.racks.show', [
            'rack' => $rack,
            'unitOptions' => $this->unitOptions(),
            'unitLabels' => $this->unitLabels(),
        ]);
    }

    public function edit(CmdbRack $rack): View
    {
        return view('cmdb.racks.edit', [
            'rack' => $rack,
            'unitOptions' => $this->unitOptions(),
            'unitLabels' => $this->unitLabels(),
        ]);
    }

    public function update(Request $request, CmdbRack $rack): RedirectResponse
    {
        $rack->update($this->validated($request, $rack));

        return redirect()->route('cmdb.racks.show', $rack)->with('success', 'Rack atualizado com sucesso.');
    }

    public function destroy(CmdbRack $rack): RedirectResponse
    {
        $rack->delete();

        return redirect()->route('cmdb.racks.index')->with('success', 'Rack removido com sucesso.');
    }

    private function validated(Request $request, ?CmdbRack $rack = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'unit_id' => ['nullable', 'integer', 'min:1'],
            'organization_unit_id' => ['nullable', 'integer', 'exists:organization_units,id'],
            'organization_department_id' => ['nullable', 'integer', 'exists:organization_departments,id'],
            'location' => ['nullable', 'string', 'max:255'],
            'room' => ['nullable', 'string', 'max:255'],
            'floor' => ['nullable', 'string', 'max:255'],
            'capacity_u' => ['nullable', 'integer', 'min:0'],
            'notes' => ['nullable', 'string'],
        ]);
    }

    private function unitOptions(): array
    {
        return app(\App\Services\OrganizationLookupService::class)
            ->unitOptions()
            ->map(fn ($item) => [
                'id' => (string) ($item->organization_unit_id ?? ''),
                'label' => $item->name,
                'scope' => 'Unidade',
            ])
            ->filter(fn (array $item) => $item['id'] !== '')
            ->values()
            ->all();
    }

    private function unitLabels(): array
    {
        $labels = [];

        foreach ($this->unitOptions() as $option) {
            $labels[$option['id']] = $option['scope'] . ' - ' . $option['label'];
        }

        return $labels;
    }
}
