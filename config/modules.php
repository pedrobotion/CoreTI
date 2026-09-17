<?php

/*
|--------------------------------------------------------------------------
| Registro de módulos do CoreTI
|--------------------------------------------------------------------------
|
| Fonte única de verdade dos módulos e das permissões que cada um expõe.
| Adicionar um módulo novo passa a ser editar este arquivo — sem migration,
| sem mexer no User, no middleware ou na tela de administração.
|
| Cada módulo declara:
|   label / description  → textos exibidos na administração
|   group                → agrupamento visual na tela de acessos
|   abilities            → níveis de permissão, do mais básico ao mais amplo
|   implies              → concessão de X concede também Y (mesmo módulo)
|   aliases              → chaves antigas que continuam resolvendo para este módulo
|
| A ability 'view' é obrigatória em todos os módulos: é o que o middleware
| 'module_access:<modulo>' exige quando nenhuma ability é informada.
|
*/

return [

    /*
     | Enquanto true, usuários com role = admin continuam com acesso irrestrito,
     | como era antes da normalização das permissões. A migration de backfill
     | concede a esses usuários todas as abilities explicitamente, então este
     | flag pode ser desligado assim que a concessão for conferida na tela de
     | administração — a partir daí todo acesso passa a ser explícito e audível.
     */
    'legacy_admin_bypass' => env('MODULES_LEGACY_ADMIN_BYPASS', true),

    'abilities' => [
        'view' => 'Visualizar',
        'request' => 'Solicitar',
        'execute' => 'Executar',
        'manage' => 'Administrar',
    ],

    'modules' => [

        'servicedesk' => [
            'label' => 'ServiceDesk',
            'description' => 'Chamados, e-mails corporativos e Active Directory.',
            'group' => 'Operação',
            'abilities' => ['view', 'manage'],
        ],

        'unidades' => [
            'label' => 'Unidades / Circuitos',
            'description' => 'Links de conectividade das unidades.',
            'group' => 'Operação',
            'abilities' => ['view', 'manage'],
        ],

        'aplicativos' => [
            'label' => 'Aplicativos',
            'description' => 'Repositório de instaladores e utilitários.',
            'group' => 'Operação',
            'abilities' => ['view', 'manage'],
        ],

        'bancada' => [
            'label' => 'Bancada de Serviços',
            'description' => 'Equipamentos em manutenção e logística de malote.',
            'group' => 'Operação',
            'abilities' => ['view', 'manage'],
        ],

        'administrativo' => [
            'label' => 'Administrativo',
            'description' => 'Licenciamento, cartões e cadastros de apoio da TI.',
            'group' => 'Gestão',
            'abilities' => ['view', 'manage'],
        ],

        'cmdb' => [
            'label' => 'CMDB',
            'description' => 'Inventário de ativos, serviços e relacionamentos.',
            'group' => 'Infraestrutura',
            'abilities' => ['view', 'manage'],
        ],

        'network_datacenter' => [
            'label' => 'Network / Datacenter',
            'description' => 'Serviços de conectividade, produtos e contratantes.',
            'group' => 'Infraestrutura',
            'abilities' => ['view', 'manage'],
        ],

        'coordenacao' => [
            'label' => 'Coordenação',
            'description' => 'Rateio de custos, fechamentos mensais e produtos.',
            'group' => 'Gestão',
            'abilities' => ['view', 'manage'],
        ],

        'ad' => [
            'label' => 'Active Directory',
            'description' => 'Exclusão de contas desativadas: conferência e aprovação das remoções no AD.',
            'group' => 'Infraestrutura',
            'abilities' => ['view', 'execute', 'manage'],
            'ability_labels' => [
                'view' => 'Consultar lotes de exclusão',
                'execute' => 'Aprovar exclusão de contas do AD',
                'manage' => 'Administrar a exclusão de contas',
            ],
        ],

        'telefonia' => [
            'label' => 'Telefonia Corporativa',
            'description' => 'Linhas corporativas, aparelhos celulares e os vínculos com colaboradores.',
            'group' => 'Gestão',
            'abilities' => ['view', 'manage'],
            'ability_labels' => [
                'view' => 'Consultar linhas, aparelhos e vínculos',
                'manage' => 'Cadastrar, vincular e desvincular',
            ],
        ],

        'equipamentos' => [
            'label' => 'Equipamentos',
            'description' => 'Inventário de equipamentos de TI e a entrega para colaboradores.',
            'group' => 'Operação',
            'abilities' => ['view', 'manage'],
            'ability_labels' => [
                'view' => 'Consultar o inventário',
                'manage' => 'Cadastrar, vincular e desvincular',
            ],
        ],

        'admissao' => [
            'label' => 'Admissão de Colaboradores',
            'description' => 'Fluxo único de admissão: RH registra, o sistema distribui as solicitações.',
            'group' => 'Pessoas',
            'abilities' => ['view', 'request', 'execute', 'manage'],
            'ability_labels' => [
                'view' => 'Consultar admissões',
                'request' => 'Registrar admissões (RH)',
                'execute' => 'Executar provisionamento (TI / apoio)',
                'manage' => 'Configurar perfis de acesso',
            ],
        ],

    ],
];
