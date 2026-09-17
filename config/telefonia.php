<?php

/*
|--------------------------------------------------------------------------
| Telefonia Corporativa
|--------------------------------------------------------------------------
*/

return [

    'termo' => [
        /*
         | A numeração dá sequência à série em papel da COCARI. O valor inicial
         | é gravado na tabela termo_sequencias pela migration; alterá-lo aqui
         | depois disso não tem efeito — para corrigir a série, atualize a
         | linha 'celular' em termo_sequencias.
         */
        'numero_inicial' => (int) env('TELEFONIA_TERMO_NUMERO_INICIAL', 1617),

        /* Cidade usada quando a unidade do colaborador não tem município. */
        'cidade_padrao' => env('TELEFONIA_TERMO_CIDADE_PADRAO', 'Mandaguari'),

        /* Instrução normativa citada na última cláusula. */
        'instrucao_normativa' => env('TELEFONIA_TERMO_IN', 'IN-06/18'),
    ],

];
