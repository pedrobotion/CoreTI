<?php

return [
    /*
    | Pendências administrativas da bancada: limiares (em dias) a partir dos
    | quais um equipamento parado numa etapa administrativa vira pendência.
    | O tempo é medido pelo período aberto do status atual
    | (bancada_equipment_status_histories.end_time IS NULL).
    */
    'pendencias' => [
        'entrada_fiscal_dias' => (int) env('BANCADA_PEND_ENTRADA_FISCAL_DIAS', 5),
        'saida_nf_dias' => (int) env('BANCADA_PEND_SAIDA_NF_DIAS', 1),
        'terceiros_dias' => (int) env('BANCADA_PEND_TERCEIROS_DIAS', 15),
        'aguardando_peca_dias' => (int) env('BANCADA_PEND_AGUARDANDO_PECA_DIAS', 10),

        // Destinatários do e-mail diário de pendências (separados por vírgula).
        'email_destinatarios' => array_values(array_filter(array_map(
            'trim',
            explode(',', (string) env('BANCADA_PEND_EMAIL', 'ti@cocari.com.br'))
        ))),

        // Teto de chamados Jira abertos por execução, para não inundar o Jira
        // com o backlog inteiro de uma vez (os demais entram nos dias seguintes).
        'jira_max_por_execucao' => (int) env('BANCADA_PEND_JIRA_MAX', 10),

        // Equipe de atendimento no Jira para os chamados de pendência da bancada
        // (sobrepõe o padrão global; precisa bater com o NOME da opção no Jira).
        'jira_equipe' => env('BANCADA_PEND_JIRA_EQUIPE', 'Time Administrativo de TI'),

        // Catálogo (tipo de solicitação) no Jira: "ADM - Atualização Pendente"
        // (id 1701 no service desk 5), criado para as pendências administrativas.
        // Sem isto cairia no padrão global (713 = "Falha de Backup").
        'jira_request_type' => env('BANCADA_PEND_JIRA_REQUEST_TYPE', '1701'),
    ],

    /*
    | Nota fiscal de saída: abertura automática de chamado para emitir a nota
    | dos equipamentos NÃO-Sede que estão em "Pronto para entrega" na manhã do
    | dia de separação da rota (para embarcarem no mesmo dia, até ~15:00).
    */
    'notas_saida' => [
        // Equipe e catálogo no Jira (ADM - Solicitação de Notas Fiscais de
        // Equipamentos = id 160 no service desk 5).
        'jira_equipe' => env('BANCADA_NF_SAIDA_JIRA_EQUIPE', 'Time Administrativo de TI'),
        'jira_request_type' => env('BANCADA_NF_SAIDA_JIRA_REQUEST_TYPE', '160'),

        // Teto de chamados por execução, para não inundar o Jira num dia cheio.
        'jira_max_por_execucao' => (int) env('BANCADA_NF_SAIDA_JIRA_MAX', 20),

        // Rotas que NÃO geram chamado de nota de saída (nome da rota, separados
        // por vírgula). A rota interna da TI entrega na própria Sede e não
        // precisa de nota.
        'rotas_excluidas' => array_values(array_filter(array_map(
            'trim',
            explode(',', (string) env('BANCADA_NF_SAIDA_ROTAS_EXCLUIDAS', 'TI'))
        ))),
    ],

    /*
    | Manutenção: ao concluir a entrada do equipamento (status "Em bancada"),
    | abre no Jira um chamado do catálogo "Configuração e Manutenção de
    | Equipamentos de Informática" (request type 98, issue type 10010 no service
    | desk 5) com descrição em branco, para a equipe confirmar o defeito com a
    | unidade. Quando o chamado é finalizado, a descrição volta para a
    | observação do equipamento (webhook — parte B).
    */
    'manutencao' => [
        // Trava de segurança: a automação só abre chamado quando ativada
        // explicitamente (BANCADA_MANUT_ATIVO=true no .env). Fica desligada por
        // padrão para não abrir o backlog sozinha antes de tudo estar pronto
        // (inclusive o webhook da parte B).
        'ativo' => (bool) env('BANCADA_MANUT_ATIVO', false),

        // Só abre chamado a partir desta data (Y-m-d). Vazio = assim que ativo.
        // Serve para "começar amanhã" sem pegar os equipamentos de hoje.
        'inicio' => env('BANCADA_MANUT_INICIO'),

        'jira_request_type' => env('BANCADA_MANUT_JIRA_REQUEST_TYPE', '98'),
        'jira_issue_type' => env('BANCADA_MANUT_JIRA_ISSUE_TYPE', '10010'),

        // Campos do catálogo 98 (ver /rest/servicedeskapi/.../requesttype/98/field).
        'campo_unidade' => env('BANCADA_MANUT_CAMPO_UNIDADE', 'customfield_10067'),     // Unidade/Departamento (cascata)
        'campo_patrimonio' => env('BANCADA_MANUT_CAMPO_PATRIMONIO', 'customfield_10366'), // Patrimônio do Equipamento

        // Departamento (2º nível da cascata) usado quando o do equipamento não
        // é conhecido/não casa (ex.: unidades não-Sede).
        'departamento_padrao' => env('BANCADA_MANUT_DEPTO_PADRAO', 'Administrativo'),

        // Teto de chamados por execução.
        'jira_max_por_execucao' => (int) env('BANCADA_MANUT_JIRA_MAX', 20),

        // Apelidos: nome da unidade no CoreTI => rótulo da unidade (nível 1) no
        // campo do Jira, para os nomes que divergem. Sem casar, NÃO abre chamado.
        'apelidos_unidade' => [
            'Associação Atlética COCARI' => 'A.A.COCARI',
            'Fábrica de Ração COCARI' => 'Fábrica de Rações',
            'Fiação COCARI' => 'Fiação',
            'Unidades - Colari' => 'Colari',
            'Loja Agropecuária - Mandaguari' => 'Loja Agropecuária Mandaguari',
            'CTC - Centro Tecnológico COCARI' => 'Centro Tecnológico',
        ],

        // Webhook do Jira (parte B): token compartilhado e IPs permitidos.
        'webhook' => [
            'token' => env('BANCADA_MANUT_WEBHOOK_TOKEN'),
            'allowed_ips' => array_values(array_filter(array_map(
                'trim',
                explode(',', (string) env('BANCADA_MANUT_WEBHOOK_IPS', ''))
            ))),
        ],
    ],
];
