<?php

namespace App\Services\Jira;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Abre chamado no Jira em nome do CoreTI, para qualquer módulo.
 *
 * A lógica de abertura — token, relator de sistema, equipe de atendimento e
 * tipo de solicitação do catálogo — nasceu no fluxo de backup. Aqui ela fica
 * em um só lugar, para que monitor de certificado, impressoras e o que vier
 * depois não a copiem. Cada módulo decide SE e QUANDO abrir; este serviço só
 | sabe COMO.
 *
 * Toda abertura sai como a conta de sistema (coreti@cocari.com.br): chamado da
 * automação não pode nascer em nome de quem autentica, senão some com a pessoa.
 */
class JiraTicketOpener
{
    /** Há credencial e projeto para abrir chamado? */
    public function configurado(): bool
    {
        if (! (bool) config('services.jira.enabled', false)) {
            return false;
        }

        foreach (['base_url', 'email', 'api_token'] as $chave) {
            if (blank(config("services.jira.{$chave}"))) {
                return false;
            }
        }

        return filled(config('backups.jira.project_id')) && filled(config('backups.jira.issue_type_id'));
    }

    /**
     * Abre um chamado. Devolve a chave (ex.: TIC-123) ou null em falha/simulado.
     *
     * @param  array<string, mixed>  $extra  campos adicionais do Jira
     * @param  string|null  $equipeNome  nome da "Equipe de atendimento" só para
     *                                    este chamado (sobrepõe o padrão global)
     */
    public function abrir(string $titulo, string $descricao, string $contexto = 'coreti', array $extra = [], ?string $equipeNome = null): ?string
    {
        if (! $this->configurado()) {
            Log::warning('jira.ticket.nao_configurado', ['contexto' => $contexto, 'titulo' => $titulo]);

            return null;
        }

        $campos = array_merge([
            'project' => ['id' => (string) config('backups.jira.project_id')],
            'issuetype' => ['id' => (string) config('backups.jira.issue_type_id')],
            'summary' => $titulo,
            'description' => [
                'type' => 'doc',
                'version' => 1,
                'content' => [[
                    'type' => 'paragraph',
                    'content' => [['type' => 'text', 'text' => $descricao]],
                ]],
            ],
        ], $extra);

        if ($equipe = $this->resolverEquipe($equipeNome)) {
            $campos[(string) config('backups.jira.fields.team')] = $equipe;
        }

        if ($tipo = $this->resolverTipoDeSolicitacao()) {
            $campos[(string) config('backups.jira.fields.request_type')] = $tipo;
        }

        $campos = $this->comRelator($campos);

        try {
            $resposta = $this->client()->post($this->url('/rest/api/3/issue'), ['fields' => $campos]);
        } catch (\Throwable $e) {
            Log::error('jira.ticket.excecao', ['contexto' => $contexto, 'erro' => $e->getMessage()]);

            return null;
        }

        if (! $resposta->successful()) {
            Log::error('jira.ticket.falha', [
                'contexto' => $contexto,
                'status' => $resposta->status(),
                'corpo' => mb_substr($resposta->body(), 0, 300),
            ]);

            return null;
        }

        $chave = (string) ($resposta->json('key') ?? '');

        Log::info('jira.ticket.criado', ['contexto' => $contexto, 'chave' => $chave]);

        return $chave !== '' ? $chave : null;
    }

    public function urlDoChamado(string $chave): string
    {
        return rtrim((string) config('services.jira.base_url'), '/').'/browse/'.$chave;
    }

    private function resolverEquipe(?string $nomeOverride = null): ?array
    {
        $campo = (string) config('backups.jira.fields.team');
        $contexto = (string) config('backups.jira.team_field_context');

        if ($campo === '' || $contexto === '') {
            return null;
        }

        $resposta = $this->client()->get(
            $this->url("/rest/api/3/field/{$campo}/context/{$contexto}/option"),
            ['maxResults' => 100]
        );

        if (! $resposta->successful()) {
            return null;
        }

        $opcoes = (array) $resposta->json('values', []);
        if ($opcoes === []) {
            return null;
        }

        // Nome informado na chamada tem prioridade sobre o padrão global.
        $nome = $nomeOverride !== null && trim($nomeOverride) !== ''
            ? trim($nomeOverride)
            : (string) config('backups.jira.team_option_name', '');
        if ($nome !== '') {
            foreach ($opcoes as $opcao) {
                if (mb_strtolower(trim((string) ($opcao['value'] ?? ''))) === mb_strtolower(trim($nome))) {
                    return ['id' => (string) $opcao['id']];
                }
            }

            // Pediram uma equipe específica e ela não existe: não cair no índice
            // genérico (mandaria para o time errado). Avisa e deixa sem equipe.
            if ($nomeOverride !== null && trim($nomeOverride) !== '') {
                Log::warning('jira.ticket.equipe_nao_encontrada', ['nome' => $nome]);

                return null;
            }
        }

        $indice = (int) config('backups.jira.team_option_index', 5);

        return isset($opcoes[$indice]['id']) ? ['id' => (string) $opcoes[$indice]['id']] : null;
    }

    private function resolverTipoDeSolicitacao(): ?string
    {
        $serviceDesk = (string) config('backups.jira.service_desk_id');
        $requestType = (string) config('backups.jira.request_type_id');

        if ($serviceDesk === '' || $requestType === '') {
            return null;
        }

        $resposta = $this->client()->get(
            $this->url("/rest/servicedeskapi/servicedesk/{$serviceDesk}/requesttype/{$requestType}")
        );

        return $resposta->successful() ? (string) ($resposta->json('id') ?? $requestType) : $requestType;
    }

    private function comRelator(array $campos): array
    {
        $accountId = trim((string) config('services.jira.reporter_account_id'));

        if ($accountId !== '') {
            $campos['reporter'] = ['id' => $accountId];
        }

        return $campos;
    }

    private function client()
    {
        return Http::withBasicAuth(
            (string) config('services.jira.email'),
            (string) config('services.jira.api_token')
        )->acceptJson()->timeout(15);
    }

    private function url(string $caminho): string
    {
        return rtrim((string) config('services.jira.base_url'), '/').$caminho;
    }
}
