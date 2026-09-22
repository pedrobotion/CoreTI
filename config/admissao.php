<?php

/*
|--------------------------------------------------------------------------
| Módulo de Admissão — catálogo de tarefas e parâmetros do fluxo
|--------------------------------------------------------------------------
|
| Fase 1: o CoreTI centraliza a coleta dos dados e distribui as solicitações
| como chamados. Nenhum provisionamento automático acontece aqui — cada tarefa
| vira um item de checklist e um chamado para a área responsável.
|
*/

return [

    /*
     | Áreas que recebem as solicitações. 'jira_project_key' pode ficar vazio:
     | nesse caso o dispatcher usa services.jira.project_key como destino.
     */
    'areas' => [
        'ti' => [
            'label' => 'TI / Infraestrutura',
            'jira_project_key' => env('ADMISSAO_JIRA_PROJECT_TI'),
        ],
        'patrimonio' => [
            'label' => 'Patrimônio / Almoxarifado',
            'jira_project_key' => env('ADMISSAO_JIRA_PROJECT_PATRIMONIO'),
        ],
        'rh' => [
            'label' => 'Recursos Humanos',
            'jira_project_key' => env('ADMISSAO_JIRA_PROJECT_RH'),
        ],
        'seguranca' => [
            'label' => 'Segurança / Portaria',
            'jira_project_key' => env('ADMISSAO_JIRA_PROJECT_SEGURANCA'),
        ],
    ],

    /*
     | Catálogo de tarefas possíveis numa admissão.
     |
     | sla_dias_antes → prazo relativo à data de início do colaborador
     |                  (3 = deve estar pronto 3 dias úteis antes).
     | requer_dado    → campos do caso sem os quais a tarefa não pode ser
     |                  executada; usado para alertar o RH no formulário.
     */
    'task_types' => [
        'email_google' => [
            'label' => 'Criação de e-mail corporativo (Google Workspace)',
            'area' => 'ti',
            'sla_dias_antes' => 3,
            'requer_dado' => ['nome', 'matricula'],
        ],
        'licenca_m365' => [
            'label' => 'Atribuição de licença Microsoft 365',
            'area' => 'ti',
            'sla_dias_antes' => 3,
            'requer_dado' => ['nome'],
        ],
        'acesso_sic' => [
            'label' => 'Acesso ao SIC (Sistema Interno Cocari)',
            'area' => 'ti',
            'sla_dias_antes' => 2,
            'requer_dado' => ['matricula', 'cargo'],
        ],
        'acesso_ad' => [
            'label' => 'Usuário de rede / Active Directory',
            'area' => 'ti',
            'sla_dias_antes' => 3,
            'requer_dado' => ['nome', 'matricula'],
        ],
        'cadastro_intranet' => [
            'label' => 'Cadastro na intranet',
            'area' => 'ti',
            'sla_dias_antes' => 2,
            'requer_dado' => ['matricula'],
        ],
        'equipamento' => [
            'label' => 'Equipamento (notebook / desktop / periféricos)',
            'area' => 'ti',
            'sla_dias_antes' => 5,
            'requer_dado' => ['unidade'],
        ],
        'ramal' => [
            'label' => 'Ramal telefônico',
            'area' => 'ti',
            'sla_dias_antes' => 2,
            'requer_dado' => ['unidade'],
        ],
        'uniforme' => [
            'label' => 'Uniforme e EPI',
            'area' => 'patrimonio',
            'sla_dias_antes' => 5,
            'requer_dado' => ['tamanho_uniforme'],
        ],
        'cracha' => [
            'label' => 'Crachá de identificação',
            'area' => 'seguranca',
            'sla_dias_antes' => 2,
            'requer_dado' => ['nome', 'matricula'],
        ],
        'integracao' => [
            'label' => 'Agendamento da integração / treinamento inicial',
            'area' => 'rh',
            'sla_dias_antes' => 1,
            'requer_dado' => ['data_inicio'],
        ],
    ],

    /*
     | Antecedência mínima recomendada entre o envio do caso e a data de início.
     | Abaixo disso o formulário avisa o RH que o prazo está apertado (não bloqueia).
     */
    'antecedencia_recomendada_dias' => 5,

    /*
     | Um chamado por área (true) ou um chamado por tarefa (false).
     | O padrão resolve a queixa central do processo atual: "TI recebe pedidos picados".
     */
    'chamado_por_area' => env('ADMISSAO_CHAMADO_POR_AREA', true),
];
