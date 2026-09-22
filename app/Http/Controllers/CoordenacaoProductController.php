<?php

namespace App\Http\Controllers;

use App\Models\CoordenacaoProduct;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CoordenacaoProductController extends Controller
{
    public function index(Request $request): View
    {
        $products = CoordenacaoProduct::query()->when($request->filled('q'), fn ($q) => $q->where('name', 'like', '%' . $request->string('q') . '%'))->orderBy('name')->paginate(20)->withQueryString();
        return view('coordenacao.products.index', compact('products'));
    }

    public function create(): View { return view('coordenacao.products.create', ['product' => new CoordenacaoProduct(['is_active' => true, 'billing_period' => 'monthly', 'allocation_basis' => 'all_collaborators', 'distribution_method' => 'per_collaborator', 'individual_appropriation_method' => 'shared'])]); }

    public function store(Request $request): RedirectResponse
    {
        CoordenacaoProduct::create($this->data($request));
        return redirect()->route('coordenacao.products.index')->with('success', 'Produto de TI cadastrado.');
    }

    public function edit(CoordenacaoProduct $product): View { return view('coordenacao.products.edit', compact('product')); }

    public function update(Request $request, CoordenacaoProduct $product): RedirectResponse
    {
        $product->update($this->data($request));
        return redirect()->route('coordenacao.products.index')->with('success', 'Produto de TI atualizado.');
    }

    public function toggle(CoordenacaoProduct $product): RedirectResponse
    {
        $product->update(['is_active' => ! $product->is_active]);
        return back()->with('success', 'Status do produto atualizado.');
    }

    public function duplicate(CoordenacaoProduct $product): RedirectResponse
    {
        $copy = $product->replicate();
        $copy->name = $product->name . ' (cópia)';
        $copy->configuration_status = 'draft';
        $copy->save();
        return redirect()->route('coordenacao.products.edit', $copy)->with('success', 'Produto duplicado para configuração.');
    }

    private function data(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'], 'supplier' => ['nullable', 'string', 'max:255'], 'description' => ['nullable', 'string'],
            'contract_amount' => ['required', 'numeric', 'min:0'], 'billing_period' => ['required', 'in:monthly,annual,one_time,custom'], 'period_months' => ['nullable', 'integer', 'min:1'],
            'monthly_maintenance_amount' => ['nullable', 'numeric', 'min:0'], 'allocation_basis' => ['required', 'in:all_collaborators,office_users,email_users,ad_group,all_units,selected_units,manual_users'],
            'distribution_method' => ['required', 'in:per_collaborator,equal_unit,fixed_unit,manual'], 'individual_appropriation_method' => ['required', 'in:individual,shared,mixed'],
            'starts_at' => ['nullable', 'date'], 'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'], 'configuration_status' => ['required', 'in:draft,active,paused'], 'notes' => ['nullable', 'string'],
            'is_active' => ['nullable', 'boolean'],
        ]) + ['is_active' => $request->boolean('is_active'), 'monthly_maintenance_amount' => $request->input('monthly_maintenance_amount', 0)];
    }
}
