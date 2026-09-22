<?php

namespace App\Http\Controllers;

use App\Models\AdmissionAccessProfile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Catálogo de perfis de acesso — o coração do módulo.
 *
 * É aqui que "Auxiliar Administrativo da Fiscal recebe e-mail, M365, SIC e
 * crachá" deixa de ser conhecimento tácito e vira regra.
 */
class AdmissionProfileController extends Controller
{
    public function index()
    {
        $this->authorize('manageProfiles', \App\Models\AdmissionCase::class);

        $profiles = AdmissionAccessProfile::with('items')
            ->orderByDesc('prioridade')
            ->orderBy('nome')
            ->get();

        $taskTypes = (array) config('admissao.task_types');

        return view('admissao.profiles.index', compact('profiles', 'taskTypes'));
    }

    public function store(Request $request)
    {
        $this->authorize('manageProfiles', \App\Models\AdmissionCase::class);

        $data = $this->validated($request);

        $profile = $this->persist(new AdmissionAccessProfile(), $data, $request);

        return redirect()
            ->route('admissao.profiles.index')
            ->with('success', "Perfil \"{$profile->nome}\" criado.");
    }

    public function update(Request $request, AdmissionAccessProfile $profile)
    {
        $this->authorize('manageProfiles', \App\Models\AdmissionCase::class);

        $data = $this->validated($request);
        $this->persist($profile, $data, $request);

        return redirect()
            ->route('admissao.profiles.index')
            ->with('success', 'Perfil atualizado. Casos em rascunho serão recalculados ao serem salvos.');
    }

    public function toggle(AdmissionAccessProfile $profile)
    {
        $this->authorize('manageProfiles', \App\Models\AdmissionCase::class);

        $profile->update(['ativo' => ! $profile->ativo]);

        return back()->with('success', 'Perfil '.($profile->ativo ? 'ativado' : 'desativado').'.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'nome' => ['required', 'string', 'max:255'],
            'descricao' => ['nullable', 'string', 'max:1000'],
            'cargo_source_id' => ['nullable', 'string', 'max:255'],
            'area_source_id' => ['nullable', 'string', 'max:255'],
            'departamento_source_id' => ['nullable', 'string', 'max:255'],
            'unidade_source_id' => ['nullable', 'string', 'max:255'],
            'prioridade' => ['nullable', 'integer', 'min:0', 'max:999'],
            'cumulativo' => ['nullable', 'boolean'],
            'ativo' => ['nullable', 'boolean'],
            'items' => ['nullable', 'array'],
            'items.*' => [Rule::in(array_keys((array) config('admissao.task_types')))],
        ]);
    }

    private function persist(AdmissionAccessProfile $profile, array $data, Request $request): AdmissionAccessProfile
    {
        return DB::transaction(function () use ($profile, $data, $request): AdmissionAccessProfile {
            $profile->fill([
                'nome' => $data['nome'],
                'descricao' => $data['descricao'] ?? null,
                'cargo_source_id' => $data['cargo_source_id'] ?? null,
                'area_source_id' => $data['area_source_id'] ?? null,
                'departamento_source_id' => $data['departamento_source_id'] ?? null,
                'unidade_source_id' => $data['unidade_source_id'] ?? null,
                'prioridade' => (int) ($data['prioridade'] ?? 0),
                'cumulativo' => (bool) ($data['cumulativo'] ?? false),
                'ativo' => (bool) ($data['ativo'] ?? true),
            ]);

            $profile->created_by ??= $request->user()?->id;
            $profile->save();

            $desejados = array_values(array_unique((array) ($data['items'] ?? [])));

            $profile->items()->whereNotIn('task_type', $desejados ?: ['__none__'])->delete();

            foreach ($desejados as $taskType) {
                $profile->items()->firstOrCreate(
                    ['task_type' => $taskType],
                    ['obrigatorio' => true]
                );
            }

            return $profile;
        });
    }
}
