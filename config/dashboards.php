<?php

/*
|--------------------------------------------------------------------------
| Dashboards do CoreTI
|--------------------------------------------------------------------------
|
| Registro dos painéis analíticos que aparecem como atalho na tela inicial.
|
| Ficam aqui, e não espalhados pela view, para que acrescentar um painel novo
| seja uma entrada nesta lista: o menu, a permissão e o atalho saem daqui
| juntos, sem três lugares para esquecer de mexer.
|
| 'modulo' é o mesmo módulo que protege a rota. Quem não tem o módulo não vê
| o atalho — e não veria a tela de qualquer forma.
|
*/

return [

    'impressoes' => [
        'titulo' => 'Impressões',
        'descricao' => 'Volume por usuário, impressora, dia e horário.',
        'rota' => 'dashboards.impressoes',
        'modulo' => 'dashboard_impressoes',
        'cor' => '#0ea5e9',
        'icone' => 'impressora',
    ],

    'auditoria-backups' => [
        'titulo' => 'Auditoria de Backups',
        'descricao' => 'Taxa de sucesso, falhas, silêncio e volume por servidor e banco.',
        'rota' => 'dashboards.auditoria-backups',
        'modulo' => 'dashboard_auditoria_backups',
        'cor' => '#f43f5e',
        'icone' => 'backup',
    ],

    'telefonia' => [
        'titulo' => 'Telefonia',
        'descricao' => 'Custo mensal por operadora, operação e unidade, linhas e aparelhos.',
        'rota' => 'dashboards.telefonia',
        'modulo' => 'dashboard_telefonia',
        'cor' => '#8b5cf6',
        'icone' => 'telefone',
    ],

    'servicedesk' => [
        'titulo' => 'ServiceDesk',
        'descricao' => 'Chamados, e-mails corporativos, Active Directory e licenças Office.',
        'rota' => 'dashboards.servicedesk',
        'modulo' => 'dashboard_servicedesk',
        'cor' => '#10b981',
        'icone' => 'suporte',
    ],

    'service-desk-apresentacao' => [
        'titulo' => 'Service Desk — Infraestrutura TI',
        'descricao' => 'Indicadores mensais do Service Desk ao vivo: atendidos, backlog, internet, Top 5 e Pivot.',
        'rota' => 'dashboards.service-desk-apresentacao',
        'modulo' => 'dashboard_sd_apresentacao',
        'cor' => '#38bdf8',
        'icone' => 'suporte',
    ],

];
