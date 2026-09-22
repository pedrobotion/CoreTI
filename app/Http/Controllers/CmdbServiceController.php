<?php

namespace App\Http\Controllers;

use App\Models\CmdbService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CmdbServiceController extends Controller
{
    public function index(Request $request): View
    {
        $services = CmdbService::query()
            ->withCount('servers')
            ->when($request->filled('q'), fn ($query) => $query->where('name', 'like', '%' . trim((string) $request->input('q')) . '%'))
            ->orderBy('name')
            ->get();

        return view('cmdb.services.index', compact('services'));
    }

    public function store(Request $request): RedirectResponse
    {
        CmdbService::create($this->validated($request));

        return back()->with('success', 'Serviço cadastrado com sucesso.');
    }

    public function update(Request $request, CmdbService $service): RedirectResponse
    {
        $service->update($this->validated($request, $service));

        return back()->with('success', 'Serviço atualizado com sucesso.');
    }

    public function toggle(CmdbService $service): RedirectResponse
    {
        $service->update(['active' => ! $service->active]);

        return back()->with('success', $service->active ? 'Serviço ativado.' : 'Serviço desativado.');
    }

    private function validated(Request $request, ?CmdbService $service = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:cmdb_services,name' . ($service ? ',' . $service->id : '')],
            'description' => ['nullable', 'string'],
            'category' => ['nullable', 'string', 'max:120'],
            'criticality' => ['nullable', 'in:low,medium,high,critical'],
            'active' => ['nullable', 'boolean'],
        ]) + ['active' => $request->boolean('active', true)];
    }
}
