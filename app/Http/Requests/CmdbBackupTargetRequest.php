<?php

namespace App\Http\Requests;

use App\Models\CmdbBackupTarget;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CmdbBackupTargetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->canAnyModule(['cmdb.manage', 'network_datacenter.manage']);
    }

    public function rules(): array
    {
        return [
            'nome' => ['nullable', 'string', 'max:255'],
            'host' => ['required', 'string', 'max:191', 'regex:/^[A-Za-z0-9]([A-Za-z0-9._-]{0,253}[A-Za-z0-9])?$/'],
            'database_name' => ['required', 'string', 'max:191'],
            // Caminho absoluto; espaços e acentos são permitidos, quebra de linha não.
            'arquivo' => ['required', 'string', 'max:512', 'regex:/^\/[^\r\n\x00]*$/', 'not_regex:/(^|\/)\.\.(\/|$)/'],
            // Opcional: log da tecnologia (ex.: .lg do Progress).
            'log_path' => ['nullable', 'string', 'max:512', 'regex:/^\/[^\r\n\x00]*$/', 'not_regex:/(^|\/)\.\.(\/|$)/'],
            'monitorar_a_partir_de' => ['nullable', 'date'],

            'tipo_backup' => ['required', Rule::in(array_keys(CmdbBackupTarget::TIPOS_BACKUP))],
            'metodo_verificacao' => ['required', 'string', 'max:40'],
            'periodicidade' => ['required', Rule::in(array_keys(CmdbBackupTarget::PERIODICIDADES))],
            'horario_esperado' => ['nullable', 'date_format:H:i'],
            'tolerancia_horas' => ['nullable', 'integer', 'min:0', 'max:168'],
            'max_idade_horas' => ['nullable', 'integer', 'min:1', 'max:8760'],

            'ssh_host' => ['nullable', 'string', 'max:191', 'regex:/^[A-Za-z0-9]([A-Za-z0-9._-]{0,253}[A-Za-z0-9])?$/'],
            'ssh_user' => ['nullable', 'string', 'max:100', 'regex:/^[a-z_][a-z0-9_-]{0,31}$/i'],
            'ssh_port' => ['nullable', 'integer', 'min:1', 'max:65535'],

            'estrategia_tamanho' => ['required', Rule::in(['automatico', 'manual'])],
            'min_tamanho_mb' => ['nullable', 'numeric', 'min:0'],

            'asset_id' => ['nullable', 'integer', 'exists:cmdb_assets,id'],
            'ativo' => ['nullable', 'boolean'],
            'observacao' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function messages(): array
    {
        return [
            'host.regex' => 'O host deve conter apenas letras, números, ponto, hífen ou underscore.',
            'ssh_host.regex' => 'O host SSH deve conter apenas letras, números, ponto, hífen ou underscore.',
            'ssh_user.regex' => 'O usuário SSH tem formato inválido.',
            'arquivo.regex' => 'Informe o caminho absoluto do arquivo (começando por /), sem quebras de linha.',
            'arquivo.not_regex' => 'O caminho não pode conter "..".',
            'log_path.regex' => 'Informe o caminho absoluto do log.',
            'log_path.not_regex' => 'O caminho do log não pode conter "..".',
        ];
    }

    /**
     * Converte a entrada amigável (MB) para o que o modelo guarda (bytes).
     */
    public function dadosDoAlvo(): array
    {
        $dados = $this->validated();

        $dados['ativo'] = (bool) ($dados['ativo'] ?? false);
        $dados['min_tamanho_bytes'] = ! empty($dados['min_tamanho_mb'])
            ? (int) round(((float) $dados['min_tamanho_mb']) * 1024 * 1024)
            : null;

        unset($dados['min_tamanho_mb']);

        return $dados;
    }
}
