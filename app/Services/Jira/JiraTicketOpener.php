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
     * @param  string|null  $requestTypeId  id do tipo de solicitação (catálogo)
     *                                       só para este chamado (sobrepõe o padrão)
     * @param  bool  $semEquipe  não envia o campo de equipe (para catálogos que
     *                           não têm esse campo na tela — senão o Jira recusa)
     * @param  string|null  $assigneeEmail  e-mail do responsável a atribuir ao
     *                                       chamado (resolve o accountId no Jira)
     */
    public function abrir(string $titulo, string $descricao, string $contexto = 'coreti', array $extra = [], ?string $equipeNome = null, ?string $requestTypeId = null, bool $semEquipe = false, ?string $assigneeEmail = null): ?string
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

        if (! $semEquipe && $equipe = $this->resolverEquipe($equipeNome)) {
            $campos[(string) config('backups.jira.fields.team')] = $equipe;
        }

        if ($tipo = $this->resolverTipoDeSolicitacao($requestTypeId)) {
            $campos[(string) config('backups.jira.fields.request_type')] = $tipo;
        }

        if ($assigneeEmail !== null && trim($assigneeEmail) !== '') {
            if ($accountId = $this->resolverUsuarioPorEmail(trim($assigneeEmail))) {
                $campos['assignee'] = ['id' => $accountId];
            } else {
                Log::warning('jira.ticket.responsavel_nao_encontrado', ['email' => $assigneeEmail, 'contexto' => $contexto]);
            }
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

    /**
     * Posta um comentário num chamado existente. Cada linha do texto vira um
     * parágrafo. Devolve true se postou. Defensivo: nunca lança.
     */
    public function comentar(string $chave, string $texto): bool
    {
        $chave = trim($chave);
        $texto = trim($texto);
        if ($chave === '' || $texto === '' || ! $this->configurado()) {
            return false;
        }

        $paragrafos = [];
        foreach (preg_split('/\r?\n/', $texto) as $linha) {
            $linha = rtrim($linha);
            $paragrafos[] = $linha === ''
                ? ['type' => 'paragraph', 'content' => []]
                : ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => $linha]]];
        }

        try {
            $resposta = $this->client()->post($this->url("/rest/api/3/issue/{$chave}/comment"), [
                'body' => ['type' => 'doc', 'version' => 1, 'content' => $paragrafos],
            ]);
        } catch (\Throwable $e) {
            Log::error('jira.comentario.excecao', ['chave' => $chave, 'erro' => $e->getMessage()]);

            return false;
        }

        if (! $resposta->successful()) {
            Log::error('jira.comentario.falha', [
                'chave' => $chave,
                'status' => $resposta->status(),
                'corpo' => mb_substr($resposta->body(), 0, 300),
            ]);

            return false;
        }

        return true;
    }

    /**
     * Move o chamado para um status pelo NOME, descobrindo o caminho: tenta a
     * transição direta e, se não houver, passa por "Em andamento". Defensivo:
     * nunca lança e nunca exclui/cancela nada.
     */
    public function transicionarPara(string $chave, string $statusDestino, int $maxPassos = 3): bool
    {
        $chave = trim($chave);
        $statusDestino = trim($statusDestino);
        if ($chave === '' || $statusDestino === '' || ! $this->configurado()) {
            return false;
        }

        try {
            for ($passo = 0; $passo < $maxPassos; $passo++) {
                $atual = (string) ($this->client()->get($this->url("/rest/api/3/issue/{$chave}?fields=status"))->json('fields.status.name') ?? '');
                if (strcasecmp($atual, $statusDestino) === 0) {
                    return true;
                }

                $transicoes = (array) ($this->client()->get($this->url("/rest/api/3/issue/{$chave}/transitions"))->json('transitions') ?? []);
                $alvo = $this->escolherTransicao($transicoes, $statusDestino)
                    ?? $this->escolherTransicao($transicoes, 'Em andamento');

                if ($alvo === null) {
                    Log::warning('jira.transicao.sem_caminho', ['chave' => $chave, 'de' => $atual, 'destino' => $statusDestino]);

                    return false;
                }

                $r = $this->client()->post($this->url("/rest/api/3/issue/{$chave}/transitions"), ['transition' => ['id' => (string) $alvo['id']]]);
                if (! $r->successful()) {
                    Log::error('jira.transicao.falha', ['chave' => $chave, 'status' => $r->status(), 'corpo' => mb_substr($r->body(), 0, 200)]);

                    return false;
                }
            }

            $final = (string) ($this->client()->get($this->url("/rest/api/3/issue/{$chave}?fields=status"))->json('fields.status.name') ?? '');

            return strcasecmp($final, $statusDestino) === 0;
        } catch (\Throwable $e) {
            Log::error('jira.transicao.excecao', ['chave' => $chave, 'erro' => $e->getMessage()]);

            return false;
        }
    }

    /** @param array<int, array<string, mixed>> $transicoes */
    private function escolherTransicao(array $transicoes, string $statusNome): ?array
    {
        foreach ($transicoes as $t) {
            if (strcasecmp((string) ($t['to']['name'] ?? ''), $statusNome) === 0) {
                return $t;
            }
        }

        return null;
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

    /** Resolve o accountId do usuário pelo e-mail (null se não achar). */
    private function resolverUsuarioPorEmail(string $email): ?string
    {
        $resposta = $this->client()->get($this->url('/rest/api/3/user/search'), ['query' => $email]);
        if (! $resposta->successful()) {
            return null;
        }

        foreach ((array) $resposta->json() as $user) {
            if (mb_strtolower(trim((string) ($user['emailAddress'] ?? ''))) === mb_strtolower(trim($email))) {
                $accountId = (string) ($user['accountId'] ?? '');

                return $accountId !== '' ? $accountId : null;
            }
        }

        return null;
    }

    private function resolverTipoDeSolicitacao(?string $override = null): ?string
    {
        $serviceDesk = (string) config('backups.jira.service_desk_id');
        // Catálogo informado na chamada tem prioridade sobre o padrão global.
        $requestType = $override !== null && trim($override) !== ''
            ? trim($override)
            : (string) config('backups.jira.request_type_id');

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
