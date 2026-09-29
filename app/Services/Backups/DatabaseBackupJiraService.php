<?php

namespace App\Services\Backups;

use App\Models\CmdbBackupCheck;
use App\Models\CmdbBackupIncident;
use App\Models\CmdbBackupTarget;
use App\Models\CmdbDatabaseBackup;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Abre incidente no Jira quando o backup falha.
 *
 * Substitui os nós "Busca Time de Atendimento", "Busca o Catálogo de Serviços",
 * "Normaliza os Dados" e "Cria Chamado Jira" do n8n, mantendo os mesmos
 * campos, com duas mudanças deliberadas:
 *
 *  - a opção de "Equipe de atendimento" é resolvida pelo NOME (o fluxo pegava
 *    values[5], que quebra em silêncio se alguém reordenar as opções);
 *  - há janela de silêncio por host+banco, para uma falha recorrente não
 *    inundar a fila de chamados.
 */
class DatabaseBackupJiraService
{
    public function habilitado(): bool
    {
        return (bool) config('backups.jira.enabled', true)
            && (bool) config('services.jira.enabled', false)
            && $this->configuracaoCompleta();
    }

    /**
     * @return array{status: string, issue_key: ?string, issue_url: ?string, message?: string}
     */
    public function abrirChamado(CmdbDatabaseBackup $backup): array
    {
        if ($backup->sucesso) {
            return ['status' => 'skipped', 'issue_key' => null, 'issue_url' => null, 'message' => 'Backup com sucesso.'];
        }

        if ($recente = $this->chamadoRecente($backup)) {
            $backup->update([
                'jira_issue_key' => $recente->jira_issue_key,
                'jira_issue_url' => $recente->jira_issue_url,
                'jira_dispatched_at' => now(),
            ]);

            return [
                'status' => 'suppressed',
                'issue_key' => $recente->jira_issue_key,
                'issue_url' => $recente->jira_issue_url,
                'message' => 'Já existe chamado recente para este host/banco.',
            ];
        }

        if (! $this->habilitado()) {
            Log::info('backup.jira.simulado', [
                'host' => $backup->host,
                'database' => $backup->database_name,
                'status' => $backup->status,
            ]);

            $backup->update([
                'jira_dispatched_at' => now(),
                'jira_error' => 'Jira não configurado — chamado simulado.',
            ]);

            return ['status' => 'simulated', 'issue_key' => null, 'issue_url' => null];
        }

        $resumo = sprintf(
            'Problema Backup Servidor %s Banco %s',
            $backup->host,
            $backup->database_name
        );

        $campos = [
            'project' => ['id' => (string) config('backups.jira.project_id')],
            'issuetype' => ['id' => (string) config('backups.jira.issue_type_id')],
            'summary' => $resumo,
            'description' => [
                'type' => 'doc',
                'version' => 1,
                'content' => [[
                    'type' => 'paragraph',
                    'content' => [['type' => 'text', 'text' => $this->descricao($backup)]],
                ]],
            ],
        ];

        if ($equipe = $this->resolverEquipe()) {
            $campos[(string) config('backups.jira.fields.team')] = $equipe;
        }

        if ($requestType = $this->resolverTipoDeSolicitacao()) {
            $campos[(string) config('backups.jira.fields.request_type')] = $requestType;
        }

        $campos = $this->comRelator($campos);

        $resposta = $this->client()->post($this->url('/rest/api/3/issue'), ['fields' => $campos]);

        if (! $resposta->successful()) {
            Log::error('backup.jira.falha', [
                'host' => $backup->host,
                'database' => $backup->database_name,
                'status' => $resposta->status(),
                'body' => $resposta->body(),
            ]);

            $backup->update([
                'jira_error' => 'HTTP '.$resposta->status().': '.mb_substr($resposta->body(), 0, 500),
                'jira_dispatched_at' => now(),
            ]);

            return [
                'status' => 'error',
                'issue_key' => null,
                'issue_url' => null,
                'message' => 'Falha ao abrir chamado ('.$resposta->status().').',
            ];
        }

        $key = $resposta->json('key');
        $url = $key ? $this->url('/browse/'.$key) : null;

        $backup->update([
            'jira_issue_key' => $key,
            'jira_issue_url' => $url,
            'jira_dispatched_at' => now(),
            'jira_error' => null,
        ]);

        return ['status' => 'created', 'issue_key' => $key, 'issue_url' => $url];
    }

    /**
     * Abre o chamado de um incidente da auditoria autônoma.
     *
     * Diferente de abrirChamado(), que atende o fluxo legado por evento de
     * agente, aqui a origem é a própria verificação do CoreTI.
     *
     * @return array{status: string, issue_key: ?string, issue_url: ?string, message?: string}
     */
    public function abrirParaIncidente(
        CmdbBackupTarget $target,
        CmdbBackupIncident $incidente,
        CmdbBackupCheck $check,
    ): array {
        if (! $this->habilitado()) {
            Log::info('backup.jira.nao_configurado', [
                'target' => $target->id,
                'incidente' => $incidente->id,
                'tipo_falha' => $incidente->tipo_falha,
            ]);

            // Nunca reportamos sucesso para algo que não foi criado.
            return [
                'status' => CmdbBackupIncident::JIRA_NAO_CONFIGURADO,
                'issue_key' => null,
                'issue_url' => null,
                'message' => 'Jira não configurado no CoreTI: defina JIRA_ENABLED, JIRA_BASE_URL, JIRA_EMAIL e JIRA_API_TOKEN.',
            ];
        }

        $campos = [
            'project' => ['id' => (string) config('backups.jira.project_id')],
            'issuetype' => ['id' => (string) config('backups.jira.issue_type_id')],
            'summary' => $this->resumoDoIncidente($target, $incidente),
            'description' => [
                'type' => 'doc',
                'version' => 1,
                'content' => [[
                    'type' => 'paragraph',
                    'content' => [['type' => 'text', 'text' => $this->descricaoDoIncidente($target, $incidente, $check)]],
                ]],
            ],
        ];

        if ($equipe = $this->resolverEquipe()) {
            $campos[(string) config('backups.jira.fields.team')] = $equipe;
        }

        if ($requestType = $this->resolverTipoDeSolicitacao()) {
            $campos[(string) config('backups.jira.fields.request_type')] = $requestType;
        }

        $campos = $this->comRelator($campos);

        try {
            $resposta = $this->client()->post($this->url('/rest/api/3/issue'), ['fields' => $campos]);
        } catch (\Throwable $e) {
            Log::error('backup.jira.excecao', ['target' => $target->id, 'erro' => $e->getMessage()]);

            return [
                'status' => CmdbBackupIncident::JIRA_ERRO,
                'issue_key' => null,
                'issue_url' => null,
                'message' => mb_substr($e->getMessage(), 0, 400),
            ];
        }

        if (! $resposta->successful()) {
            Log::error('backup.jira.falha_incidente', [
                'target' => $target->id,
                'status' => $resposta->status(),
                'body' => mb_substr($resposta->body(), 0, 500),
            ]);

            return [
                'status' => CmdbBackupIncident::JIRA_ERRO,
                'issue_key' => null,
                'issue_url' => null,
                'message' => 'HTTP '.$resposta->status().': '.mb_substr($resposta->body(), 0, 300),
            ];
        }

        $key = $resposta->json('key');

        return [
            'status' => CmdbBackupIncident::JIRA_CRIADO,
            'issue_key' => $key,
            'issue_url' => $key ? $this->url('/browse/'.$key) : null,
        ];
    }

    /**
     * Comenta no chamado que o backup voltou ao normal. Não fecha a issue —
     * quem encerra é a equipe, depois de conferir a causa.
     */
    public function comentarRecuperacao(
        CmdbBackupTarget $target,
        CmdbBackupIncident $incidente,
        CmdbBackupCheck $check,
    ): bool {
        if (! $this->habilitado() || ! $incidente->jira_issue_key) {
            return false;
        }

        $texto = implode("\n", [
            'Backup normalizado — detectado pela auditoria automática do CoreTI.',
            '',
            'Servidor: '.$target->host,
            'Banco: '.$target->database_name,
            'Situação anterior: '.\App\Support\Backups\BackupStatus::rotulo($incidente->tipo_falha),
            'Ocorrências registradas: '.$incidente->ocorrencias,
            'Problema aberto em: '.optional($incidente->aberto_em)->format('d/m/Y H:i'),
            'Normalizado em: '.optional($check->executado_em)->format('d/m/Y H:i'),
            'Duração do problema: '.($incidente->duracaoHoras() ?? '-').' hora(s)',
            '',
            'Último backup observado: '.(optional($check->arquivo_modificado_em)->format('d/m/Y H:i') ?: '-'),
            'Tamanho: '.$check->tamanhoLegivel(),
            '',
            'Este comentário é informativo; o chamado permanece aberto para avaliação da causa.',
        ]);

        try {
            $resposta = $this->client()->post(
                $this->url('/rest/api/3/issue/'.$incidente->jira_issue_key.'/comment'),
                ['body' => [
                    'type' => 'doc',
                    'version' => 1,
                    'content' => [[
                        'type' => 'paragraph',
                        'content' => [['type' => 'text', 'text' => $texto]],
                    ]],
                ]]
            );
        } catch (\Throwable $e) {
            Log::warning('backup.jira.comentario_falhou', ['erro' => $e->getMessage()]);

            return false;
        }

        return $resposta->successful();
    }

    /**
     * Título distinto para os dois tipos de problema.
     *
     * Falha de auditoria (SSH recusado, servidor fora) NÃO afirma que o backup
     * falhou — afirma que não foi possível verificar. Tratar as duas como a
     * mesma coisa manda o chamado para a equipe errada.
     */
    private function resumoDoIncidente(CmdbBackupTarget $target, CmdbBackupIncident $incidente): string
    {
        $status = \App\Support\Backups\BackupStatus::rotulo($incidente->tipo_falha);

        if (\App\Support\Backups\BackupStatus::ehFalhaDeAuditoria($incidente->tipo_falha)) {
            return sprintf(
                'Não foi possível auditar o backup — %s / %s (%s)',
                $target->host,
                $target->database_name,
                $status
            );
        }

        return sprintf(
            'Falha de backup — %s / %s (%s)',
            $target->host,
            $target->database_name,
            $status
        );
    }

    /**
     * Descrição com o contexto que a equipe precisa para agir sem ter que
     * abrir o CoreTI — servidor, banco, evidência do arquivo e os limites
     * que foram violados.
     */
    private function descricaoDoIncidente(
        CmdbBackupTarget $target,
        CmdbBackupIncident $incidente,
        CmdbBackupCheck $check,
    ): string {
        $status = \App\Support\Backups\BackupStatus::rotulo($incidente->tipo_falha);

        $ehAuditoria = \App\Support\Backups\BackupStatus::ehFalhaDeAuditoria($incidente->tipo_falha);

        $linhas = [
            $ehAuditoria
                ? 'NÃO FOI POSSÍVEL AUDITAR O BACKUP'
                : 'FALHA DE BACKUP detectada pela auditoria automática',
            '',
            $ehAuditoria
                ? 'Atenção: isto NÃO afirma que o backup falhou. O CoreTI não conseguiu '
                  .'verificar o artefato — o problema está no acesso ao servidor, e a ação '
                  .'necessária é de infraestrutura, não de backup.'
                : 'O artefato de backup foi verificado e está fora do esperado.',
            '',
            'Servidor: '.$target->host,
            'IP / host SSH: '.$target->enderecoSsh(),
            'Banco: '.$target->database_name,
            'Tipo: '.$target->tipoLabel(),
            '',
            'Arquivo esperado:',
            (string) $target->arquivo,
            '',
            'Situação: '.mb_strtoupper($status),
            \App\Support\Backups\BackupStatus::descricao($incidente->tipo_falha),
            '',
            'Última modificação do arquivo: '.(optional($check->arquivo_modificado_em)->format('d/m/Y H:i') ?: 'não observada'),
            'Verificação CoreTI: '.optional($check->executado_em)->format('d/m/Y H:i'),
            'Idade: '.$check->idadeLegivel(),
            'Tamanho: '.$check->tamanhoLegivel(),
            'Limite de idade: '.$target->idadeMaximaHoras().' horas',
        ];

        if ($minimo = $target->tamanhoMinimoBytes()) {
            $linhas[] = 'Tamanho mínimo esperado: '.$this->humano($minimo).' (referência '.$target->origemTamanhoMinimo().')';
        }

        if ($target->horario_esperado) {
            $linhas[] = 'Horário esperado: '.$target->horario_esperado.' (tolerância '.$target->tolerancia_horas.'h)';
            $linhas[] = 'Periodicidade: '.$target->periodicidadeLabel();
        }

        if ($check->detalhe) {
            $linhas[] = '';
            $linhas[] = 'Detalhe da verificação:';
            $linhas[] = $check->detalhe;
        }

        if ($check->erro_conexao) {
            $linhas[] = '';
            $linhas[] = 'Erro de conexão:';
            $linhas[] = mb_substr($check->erro_conexao, 0, 500);
        }

        $linhas[] = '';
        $linhas[] = 'Ativo CMDB: '.($target->asset_id
            ? route('cmdb.assets.show', $target->asset_id)
            : 'não vinculado');
        $linhas[] = 'Alvo no CoreTI: '.route('cmdb.backup-targets.show', $target);
        $linhas[] = '';
        $linhas[] = 'Origem: Auditoria automática CoreTI (sem n8n, sem agente).';

        return implode("\n", $linhas);
    }

    private function humano(int $bytes): string
    {
        foreach ([['GB', 1024 ** 3], ['MB', 1024 ** 2], ['KB', 1024]] as [$unidade, $divisor]) {
            if ($bytes >= $divisor) {
                return number_format($bytes / $divisor, 2, ',', '.').' '.$unidade;
            }
        }

        return $bytes.' B';
    }

    /**
     * Última falha do mesmo host+banco que já gerou chamado dentro da janela.
     */
    private function chamadoRecente(CmdbDatabaseBackup $backup): ?CmdbDatabaseBackup
    {
        $minutos = (int) config('backups.jira_cooldown_minutes', 360);

        if ($minutos <= 0) {
            return null;
        }

        return CmdbDatabaseBackup::doPar($backup->host, $backup->database_name)
            ->where('id', '!=', $backup->id)
            ->whereNotNull('jira_issue_key')
            ->where('jira_dispatched_at', '>=', now()->subMinutes($minutos))
            ->latest('jira_dispatched_at')
            ->first();
    }

    private function descricao(CmdbDatabaseBackup $backup): string
    {
        $linhas = [
            sprintf('Problema Backup Servidor %s Banco %s', $backup->host, $backup->database_name),
            '',
            'Status informado: '.$backup->status,
            'Tipo de backup: '.($backup->backup_type ?: '-'),
            'Servidor: '.$backup->host.($backup->ip_address ? ' ('.$backup->ip_address.')' : ''),
            'Banco: '.$backup->database_name,
            'Arquivo: '.($backup->arquivo ?: '-'),
            'Tamanho: '.$backup->tamanhoLegivel(),
            'Início: '.(optional($backup->inicio_backup)->format('d/m/Y H:i:s') ?: '-'),
            'Fim: '.(optional($backup->fim_backup)->format('d/m/Y H:i:s') ?: '-'),
            'Duração: '.$backup->duracaoLegivel(),
            'Verificação: '.(optional($backup->data_verificacao)->format('d/m/Y H:i:s') ?: '-'),
            'Observação: '.($backup->observacao ?: '-'),
        ];

        if ($backup->asset_id) {
            $linhas[] = '';
            $linhas[] = 'Ativo no CMDB: '.route('cmdb.assets.show', $backup->asset_id);
        }

        $linhas[] = '';
        $linhas[] = 'Registro no CoreTI: '.route('cmdb.database-backups.show', $backup);

        return implode("\n", $linhas);
    }

    /**
     * Opção do campo "Equipe de atendimento".
     *
     * Preferimos casar pelo nome configurado; só caímos no índice fixo (o que
     * o n8n fazia) se nenhum nome for configurado ou encontrado.
     */
    private function resolverEquipe(): ?array
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
            Log::warning('backup.jira.equipe_nao_resolvida', ['status' => $resposta->status()]);

            return null;
        }

        $opcoes = (array) $resposta->json('values', []);

        if ($opcoes === []) {
            return null;
        }

        $nome = (string) config('backups.jira.team_option_name', '');

        if ($nome !== '') {
            foreach ($opcoes as $opcao) {
                if (mb_strtolower(trim((string) ($opcao['value'] ?? ''))) === mb_strtolower(trim($nome))) {
                    return ['id' => (string) $opcao['id']];
                }
            }

            Log::warning('backup.jira.equipe_por_nome_nao_encontrada', ['nome' => $nome]);
        }

        $indice = (int) config('backups.jira.team_option_index', 5);

        return isset($opcoes[$indice]['id'])
            ? ['id' => (string) $opcoes[$indice]['id']]
            : null;
    }

    /**
     * Tipo de solicitação do catálogo de serviços (JSM).
     */
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

        if (! $resposta->successful()) {
            Log::warning('backup.jira.catalogo_nao_resolvido', ['status' => $resposta->status()]);

            // O id configurado costuma ser o próprio valor esperado no campo.
            return $requestType;
        }

        return (string) ($resposta->json('id') ?? $requestType);
    }

    private function client()
    {
        return Http::withBasicAuth(
            (string) config('services.jira.email'),
            (string) config('services.jira.api_token')
        )->acceptJson()->timeout(15);
    }

    /**
     * Define quem assina o chamado.
     *
     * Sem isto o relator é a conta que autentica — uma pessoa. O chamado
     * apareceria em nome dela na fila, e a automação morreria junto com a
     * conta no dia em que ela saísse da empresa.
     *
     * @param  array<string, mixed>  $campos
     * @return array<string, mixed>
     */
    private function comRelator(array $campos): array
    {
        $accountId = trim((string) config('services.jira.reporter_account_id'));

        if ($accountId !== '') {
            $campos['reporter'] = ['id' => $accountId];
        }

        return $campos;
    }

    private function url(string $caminho): string
    {
        return rtrim((string) config('services.jira.base_url'), '/').$caminho;
    }

    private function configuracaoCompleta(): bool
    {
        foreach (['base_url', 'email', 'api_token'] as $chave) {
            if (blank(config("services.jira.{$chave}"))) {
                return false;
            }
        }

        return ! blank(config('backups.jira.project_id'))
            && ! blank(config('backups.jira.issue_type_id'));
    }
}
