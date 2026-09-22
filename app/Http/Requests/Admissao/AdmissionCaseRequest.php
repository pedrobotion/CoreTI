<?php

namespace App\Http\Requests\Admissao;

use App\Models\AdmissionCase;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AdmissionCaseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->canModule('admissao', 'request');
    }

    public function rules(): array
    {
        return [
            'tipo' => ['required', Rule::in([
                AdmissionCase::TIPO_ADMISSAO,
                AdmissionCase::TIPO_TRANSFERENCIA,
                AdmissionCase::TIPO_PROMOCAO,
            ])],

            'nome' => ['required', 'string', 'max:255'],
            'matricula' => ['nullable', 'string', 'max:32'],
            'cpf' => ['nullable', 'string', 'max:14'],
            'email_pessoal' => ['nullable', 'email', 'max:255'],
            'telefone' => ['nullable', 'string', 'max:32'],

            'hr_collaborator_id' => ['nullable', 'integer', 'exists:hr_collaborators,id'],

            'cargo_source_id' => ['nullable', 'string', 'max:255'],
            'cargo_nome' => ['nullable', 'string', 'max:255'],
            'area_source_id' => ['nullable', 'string', 'max:255'],
            'area_nome' => ['nullable', 'string', 'max:255'],
            'departamento_source_id' => ['nullable', 'string', 'max:255'],
            'unidade_source_id' => ['nullable', 'string', 'max:255'],
            'unidade_nome' => ['nullable', 'string', 'max:255'],
            'centro_custo' => ['nullable', 'string', 'max:32'],

            'gestor_source_id' => ['nullable', 'string', 'max:255'],
            'gestor_nome' => ['nullable', 'string', 'max:255'],
            'gestor_email' => ['nullable', 'email', 'max:255'],

            'data_admissao' => ['nullable', 'date'],
            'data_inicio' => ['nullable', 'date'],

            'tamanho_uniforme' => ['nullable', 'string', 'max:16'],
            'tamanho_calcado' => ['nullable', 'string', 'max:16'],
            'epi' => ['nullable', 'array'],
            'epi.*' => ['string', 'max:64'],

            'substitui_matricula' => ['nullable', 'string', 'max:32'],
            'substitui_nome' => ['nullable', 'string', 'max:255'],

            'observacoes' => ['nullable', 'string', 'max:5000'],
        ];
    }

    public function attributes(): array
    {
        return [
            'nome' => 'nome do colaborador',
            'data_inicio' => 'data de início',
            'unidade_source_id' => 'unidade',
            'tamanho_uniforme' => 'tamanho de uniforme',
        ];
    }

    protected function prepareForValidation(): void
    {
        // O formulário envia epi[] com campos vazios quando o RH não preenche;
        // sem esta limpeza o caso guardaria [''] e o chamado exibiria um item vazio.
        $epi = array_values(array_filter(
            array_map(fn ($item) => trim((string) $item), (array) $this->input('epi', [])),
            fn (string $item) => $item !== ''
        ));

        $this->merge([
            'tipo' => $this->input('tipo', AdmissionCase::TIPO_ADMISSAO),
            'matricula' => $this->filled('matricula') ? trim((string) $this->input('matricula')) : null,
            'epi' => $epi === [] ? null : $epi,
        ]);
    }
}
