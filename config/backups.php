<?php

/*
|--------------------------------------------------------------------------
| Auditoria de backups de banco de dados
|--------------------------------------------------------------------------
|
| Substitui o fluxo do n8n (webhook -> MySQL auditoria_backups -> Jira).
| Os agentes nos servidores passam a chamar o CoreTI diretamente.
|
*/

return [

    'webhook' => [
        /*
         | Token compartilhado exigido no header. O webhook do n8n era aberto;
         | aqui ele cria chamado no Jira, então precisa de autenticação.
         | Envie como: X-Coreti-Token: <token>  (ou Authorization: Bearer <token>)
         */
        'token' => env('BACKUP_WEBHOOK_TOKEN'),

        /*
         | Restrição adicional por origem. Vazio = qualquer IP (desde que o
         | token confira). Aceita IPs e faixas CIDR, separados por vírgula.
         */
        'allowed_ips' => array_values(array_filter(array_map(
            'trim',
            explode(',', (string) env('BACKUP_WEBHOOK_ALLOWED_IPS', ''))
        ))),
    ],

    /*
     | Valores de 'status' no payload que significam sucesso. Qualquer outro
     | valor é tratado como falha e dispara chamado.
     */
    'status_ok' => ['OK', 'SUCCESS', 'SUCESSO'],

    /*
     | Janela (em minutos) para não abrir chamados repetidos do mesmo
     | host+banco. O fluxo do n8n abria um chamado por evento recebido; se o
     | agente repetisse a chamada, repetia o chamado.
     */
    'jira_cooldown_minutes' => (int) env('BACKUP_JIRA_COOLDOWN_MINUTES', 360),

    'jira' => [
        'enabled' => env('BACKUP_JIRA_ENABLED', true),

        // Projeto "Infraestrutura de TI" e tipo "Incidente" do fluxo atual.
        'project_id' => env('BACKUP_JIRA_PROJECT_ID', '10005'),
        'issue_type_id' => env('BACKUP_JIRA_ISSUE_TYPE_ID', '10008'),

        // Catálogo de serviços (JSM): servicedesk 5, request type 713.
        'service_desk_id' => env('BACKUP_JIRA_SERVICE_DESK_ID', '5'),
        'request_type_id' => env('BACKUP_JIRA_REQUEST_TYPE_ID', '713'),

        'fields' => [
            'team' => env('BACKUP_JIRA_TEAM_FIELD', 'customfield_10087'),
            'request_type' => env('BACKUP_JIRA_REQUEST_TYPE_FIELD', 'customfield_10010'),
        ],

        // Contexto do campo "Equipe de atendimento" para listar as opções.
        'team_field_context' => env('BACKUP_JIRA_TEAM_FIELD_CONTEXT', '10192'),

        /*
         | O fluxo do n8n pegava values[5] — a 6ª opção da lista. Isso quebra
         | silenciosamente se alguém reordenar ou inserir uma opção no Jira.
         | Aqui a opção é resolvida pelo NOME; o índice fica só como último
         | recurso, para não mudar o comportamento se o nome não for achado.
         */
        'team_option_name' => env('BACKUP_JIRA_TEAM_OPTION_NAME'),
        'team_option_index' => (int) env('BACKUP_JIRA_TEAM_OPTION_INDEX', 5),
    ],

    /*
     | Verificação ativa por SSH.
     |
     | O CoreTI confere o arquivo de backup no servidor de origem, em vez de
     | confiar apenas no que o agente reporta sobre si mesmo. Usa o binário ssh
     | do sistema e as chaves já existentes — o agendador roda como root.
     */
    'ssh' => [
        'user' => env('BACKUP_SSH_USER', 'coreti-monitor'),
        'port' => (int) env('BACKUP_SSH_PORT', 22),
        // Vazio usa a chave padrão do usuário que executa o agendador.
        'identity_file' => env('BACKUP_SSH_IDENTITY_FILE'),
        'connect_timeout' => (int) env('BACKUP_SSH_CONNECT_TIMEOUT', 10),

        /*
         | Wrapper instalado no servidor auditado (ver scripts/coreti-backup-audit).
         | Preenchido, o CoreTI só invoca operações nomeadas e a allowlist de
         | caminhos passa a ser responsabilidade do servidor — permitindo uma
         | única chave SSH para todos os backups daquele host.
         | Vazio, o comando é montado localmente (stat direto).
         */
        'wrapper' => env('BACKUP_SSH_WRAPPER'),
        'timeout' => (int) env('BACKUP_SSH_TIMEOUT', 30),

        /*
         | Sem tamanho mínimo explícito, o alvo usa esta fração da média dos
         | últimos backups bem-sucedidos. Backup que cai à metade quase sempre
         | é truncamento, não economia de espaço.
         */
        'fracao_tamanho_minimo' => (float) env('BACKUP_SSH_FRACAO_TAMANHO_MINIMO', 0.5),
    ],

    /*
     | Detecção de backup ausente — a lacuna do fluxo atual: se o agente parar
     | de enviar, ninguém fica sabendo. Um par host+banco sem evento há mais
     | de N horas é considerado atrasado.
     */
    'stale_after_hours' => (int) env('BACKUP_STALE_AFTER_HOURS', 36),

    /*
     | Acima disto o par não está atrasado: está descontinuado.
     |
     | "Atrasado há 3 horas" é incidente; "atrasado há um ano" é registro que
     | deveria ter sido aposentado. Tratar os dois igual enche a tela de
     | alarme que ninguém vai resolver, e o alarme legítimo se perde no meio.
     |
     | O caso concreto: a nomenclatura dos bancos mudou de caminho completo
     | (/banco/trr/base/trr) para nome curto (trr). Os identificadores antigos
     | ficaram na base sem nunca mais receber evento, e apareciam como
     | atrasados ao lado de backups que realmente falharam ontem.
     */
    'inactive_after_days' => (int) env('BACKUP_INACTIVE_AFTER_DAYS', 30),

    /*
    |--------------------------------------------------------------------------
    | Quando abrir chamado automaticamente
    |--------------------------------------------------------------------------
    |
    | Chamado é interrupção do trabalho de alguém. Abrir na primeira suspeita
    | gasta atenção com o que se resolve sozinho, e quem recebe alarme demais
    | para de ler alarme — aí o problema real passa junto.
    |
    | Os quatro critérios abaixo se somam. Todos precisam ser satisfeitos.
    |
    */
    'abertura' => [

        /*
         | 1. Que tipo de problema abre chamado.
         |
         | Falha de BACKUP é problema: o backup não saiu, saiu velho ou saiu
         | pequeno demais. Falha de AUDITORIA é suspeita: o CoreTI não
         | conseguiu verificar — servidor fora do ar, chave recusada, host mal
         | cadastrado. Não afirma nada sobre o backup.
         |
         | Em 29/09 quatro chamados saíram numa rajada: três eram falha de
         | auditoria sobre alvos de teste mal cadastrados, e só um era backup
         | de verdade atrasado.
         */
        'tipos' => ['backup'],   // 'backup', 'auditoria', ou os dois

        /*
         | 2. Quantas verificações seguidas precisam falhar.
         |
         | Uma falha é suspeita: rede oscilou, o backup estava rodando na hora
         | da checagem, o disco respondeu devagar. Três seguidas é padrão.
         |
         | Isto também protege contra perda de estado: se o incidente for
         | apagado, a contagem volta a 1 e o chamado não reabre.
         */
        'minimo_ocorrencias' => (int) env('BACKUP_ABERTURA_MIN_OCORRENCIAS', 3),

        /*
         | 3. Por quanto tempo o problema precisa persistir, em minutos.
         |
         | Três verificações em dois minutos não são confirmação, são a mesma
         | leitura repetida. O problema precisa atravessar o tempo.
         */
        'minimo_minutos' => (int) env('BACKUP_ABERTURA_MIN_MINUTOS', 120),

        /*
         | 4. O alvo precisa ter funcionado alguma vez.
         |
         | Alvo que nunca teve um backup verificado com sucesso não tem
         | problema de backup: tem cadastro incompleto ou errado. Isso é
         | tarefa de configuração, e aparece no painel — não vira chamado.
         */
        'exigir_sucesso_anterior' => (bool) env('BACKUP_ABERTURA_EXIGIR_SUCESSO', true),
    ],

    /*
     | Alvo com problema em aberto é reavaliado neste intervalo, independente
     | da periodicidade — é assim que a recuperação é detectada rápido.
     */
    'reverificacao_problema_minutos' => (int) env('BACKUP_REVERIFICACAO_PROBLEMA_MINUTOS', 55),

    /*
     | Conexão com a base legada (auditoria_backups), usada apenas pelo
     | comando de importação do histórico. Configure em config/database.php
     | ou deixe vazio se não for importar.
     */
    'legacy_connection' => env('BACKUP_LEGACY_CONNECTION', 'auditoria_backups'),
    'legacy_table' => env('BACKUP_LEGACY_TABLE', 'backup_auditoria'),
];
