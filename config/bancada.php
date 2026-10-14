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
    ],
];
