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
        'modulo' => 'impressoras',
        'cor' => '#0ea5e9',
        'icone' => 'impressora',
    ],

];
