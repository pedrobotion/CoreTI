<?php

namespace App\Http\Controllers;

use App\Models\NetworkContratante;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Illuminate\Support\Facades\Schema;

class NetworkContratanteController extends Controller
{
    public function index(Request $request): View
    {
        if (! Schema::hasTable('network_contratantes')) {
            return view('network-datacenter.contratantes.index', [
                'items' => collect(),
                'search' => '',
                'setupPending' => true,
            ]);
        }

        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
        ]);

        $search = trim((string) ($filters['q'] ?? ''));
        $query = NetworkContratante::query();

        if ($search !== '') {
            $query->where(function ($q) use ($search): void {
                $q->where('razao_social', 'like', "%{$search}%")
                    ->orWhere('nome_fantasia', 'like', "%{$search}%")
                    ->orWhere('cnpj', 'like', "%{$search}%");
            });
        }

        $items = $query->orderBy('razao_social')->get();

        return view('network-datacenter.contratantes.index', compact('items', 'search'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'nome_fantasia' => ['nullable', 'string', 'max:255'],
            'razao_social' => ['required', 'string', 'max:255'],
            'cnpj' => ['required', 'string', 'max:20', Rule::unique('network_contratantes', 'cnpj')],
            'inscricao_estadual' => ['nullable', 'string', 'max:255'],
            'observacao' => ['nullable', 'string'],
        ]);

        NetworkContratante::create([
            'nome_fantasia' => trim((string) ($data['nome_fantasia'] ?? '')) ?: null,
            'razao_social' => trim($data['razao_social']),
            'cnpj' => trim($data['cnpj']),
            'inscricao_estadual' => trim((string) ($data['inscricao_estadual'] ?? '')) ?: null,
            'observacao' => trim((string) ($data['observacao'] ?? '')) ?: null,
            'ativo' => true,
        ]);

        return back()->with('success', 'Contratante cadastrado com sucesso.');
    }

    public function update(Request $request, NetworkContratante $contratante): RedirectResponse
    {
        $data = $request->validate([
            'nome_fantasia' => ['nullable', 'string', 'max:255'],
            'razao_social' => ['required', 'string', 'max:255'],
            'cnpj' => ['required', 'string', 'max:20', Rule::unique('network_contratantes', 'cnpj')->ignore($contratante->id)],
            'inscricao_estadual' => ['nullable', 'string', 'max:255'],
            'observacao' => ['nullable', 'string'],
        ]);

        $contratante->update([
            'nome_fantasia' => trim((string) ($data['nome_fantasia'] ?? '')) ?: null,
            'razao_social' => trim($data['razao_social']),
            'cnpj' => trim($data['cnpj']),
            'inscricao_estadual' => trim((string) ($data['inscricao_estadual'] ?? '')) ?: null,
            'observacao' => trim((string) ($data['observacao'] ?? '')) ?: null,
        ]);

        return back()->with('success', 'Contratante atualizado com sucesso.');
    }

    public function toggle(NetworkContratante $contratante): RedirectResponse
    {
        $contratante->update(['ativo' => ! $contratante->ativo]);

        return back()->with('success', 'Status do contratante alterado com sucesso.');
    }
}
