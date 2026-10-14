<?php

/*
|--------------------------------------------------------------------------
| Monitoramento de validade de certificados TLS
|--------------------------------------------------------------------------
|
| O CoreTI verifica a validade pela conexão TLS real — o que o navegador do
| usuário enxerga — e não lendo arquivo no disco. Assim pega certificado
| trocado por fora, cadeia incompleta e qualquer host, não só o do próprio
| servidor.
|
*/

return [

    // Quantos dias antes do vencimento abrir o chamado de renovação.
    'avisar_dias_antes' => (int) env('CERT_AVISAR_DIAS_ANTES', 5),

    /*
     | Hosts monitorados. "host:porta" ou só "host" (porta 443 por padrão).
     |
     | O certificado é wildcard *.cocari.com.br e serve vários sites no mesmo
     | servidor. Monitorar um já cobre a validade de todos — mas listar os
     | principais detecta o caso de um vhost ficar apontando para o fullchain
     | antigo por engano, que foi o risco desta renovação.
     */
    'hosts' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('CERT_HOSTS', 'coreti.cocari.com.br'))
    ))),

    // Timeout da conexão TLS, em segundos.
    'timeout' => (int) env('CERT_TIMEOUT', 10),
];
