<?php

namespace App\Http\Controllers;

use App\Models\CmdbConnector;
use App\Models\CmdbDiscoveredAsset;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CmdbVlanController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
            'status' => ['nullable', Rule::in(['pending', 'linked', 'created', 'ignored', 'error'])],
            'per_page' => ['nullable', 'integer', 'in:10,15,25,50'],
        ]);

        $query = CmdbDiscoveredAsset::query()
            ->with(['connector', 'asset'])
            ->where('source_type', 'vcenter_network')
            ->orderBy('hostname');

        if ($search = trim((string) ($filters['q'] ?? ''))) {
            $query->where(function ($builder) use ($search): void {
                $builder->where('hostname', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhere('source_external_id', 'like', "%{$search}%");
            });
        }
        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        return view('cmdb.vlans.index', [
            'vlans' => $query->paginate((int) ($filters['per_page'] ?? 25))->withQueryString(),
            'filters' => $filters,
            'connectors' => CmdbConnector::query()->orderBy('name')->get(['id', 'name']),
            'statusLabels' => ['pending' => 'Pendente', 'linked' => 'Vinculada', 'created' => 'Criada', 'ignored' => 'Ignorada', 'error' => 'Erro'],
        ]);
    }
}
