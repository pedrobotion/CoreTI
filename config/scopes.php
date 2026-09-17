<?php

/*
|--------------------------------------------------------------------------
| Escopos operacionais do CoreTI
|--------------------------------------------------------------------------
|
| A TI atende duas operações que não compartilham inventário: a Sede (PR) e
| o Cerrado (GO/MG). Linhas, aparelhos, equipamentos e vínculos pertencem a
| uma delas, e quem tem acesso a uma não enxerga a outra.
|
| O de/para de unidade → escopo NÃO mora aqui: está na tabela unit_scopes,
| editável em tela. Unidade que a intranet criar e ninguém classificar fica
| sem escopo e aparece nas pendências — nunca é adivinhada.
|
*/

return [

    'scopes' => [

        'sede' => [
            'label' => 'Sede',
            'description' => 'Unidades do Paraná, incluindo a matriz em Mandaguari.',
        ],

        'cerrado' => [
            'label' => 'Cerrado',
            'description' => 'Unidades de Goiás e Minas Gerais.',
        ],

    ],

    /*
     | Escopo sugerido para registros antigos que entrarem sem classificação.
     | Vale apenas como rótulo de partida em tela; nada é gravado com ele sem
     | alguém confirmar.
     */
    'default' => 'sede',

];
