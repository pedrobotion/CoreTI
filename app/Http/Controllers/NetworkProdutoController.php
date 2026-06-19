<?php

namespace App\Http\Controllers;

use App\Models\NetworkProduto;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Illuminate\Support\Facades\Schema;

class NetworkProdutoController extends Controller
{
    public function index(Request $request): View
    {
        if (! Schema::hasTable('network_produtos')) {
            return view('network-datacenter.produtos.index', [
                'items' => collect(),
                'search' => '',
                'setupPending' => true,
            ]);
        }

        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
        ]);

        $search = trim((string) ($filters['q'] ?? ''));
        $query = NetworkProduto::query();

        if ($search !== '') {
            $query->where(function ($q) use ($search): void {
                $q->where('nome', 'like', "%{$search}%")
                    ->orWhere('descricao', 'like', "%{$search}%");
            });
        }

        $items = $query->orderBy('nome')->get();

        return view('network-datacenter.produtos.index', compact('items', 'search'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'nome' => ['required', 'string', 'max:255', Rule::unique('network_produtos', 'nome')],
            'descricao' => ['nullable', 'string'],
        ]);

        NetworkProduto::create([
            'nome' => trim($data['nome']),
            'descricao' => trim((string) ($data['descricao'] ?? '')) ?: null,
            'ativo' => true,
        ]);

        return back()->with('success', 'Produto cadastrado com sucesso.');
    }

    public function update(Request $request, NetworkProduto $produto): RedirectResponse
    {
        $data = $request->validate([
            'nome' => ['required', 'string', 'max:255', Rule::unique('network_produtos', 'nome')->ignore($produto->id)],
            'descricao' => ['nullable', 'string'],
        ]);

        $produto->update([
            'nome' => trim($data['nome']),
            'descricao' => trim((string) ($data['descricao'] ?? '')) ?: null,
        ]);

        return back()->with('success', 'Produto atualizado com sucesso.');
    }

    public function toggle(NetworkProduto $produto): RedirectResponse
    {
        $produto->update(['ativo' => ! $produto->ativo]);

        return back()->with('success', 'Status do produto alterado com sucesso.');
    }
}
