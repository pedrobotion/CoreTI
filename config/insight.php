<?php

/*
|--------------------------------------------------------------------------
| EKM Insight API — plataforma de MPS por trás do serviço da Simpress
|--------------------------------------------------------------------------
|
| A chave e o segredo NÃO são o login do portal: são um par gerado dentro do
| próprio Insight, em System Administration → Insight API → API Users. O par
| é exibido uma única vez na criação e depois só pode ser regerado.
|
| Escopo e papéis são definidos na criação do usuário de API. Para o CoreTI
| basta escopo limitado à COCARI e o papel DATA_READER; DATA_WRITER só é
| necessário se formos abrir pedido de toner ou gravar patrimônio de volta
| no portal.
|
*/

return [

    'base_url' => rtrim((string) env('INSIGHT_BASE_URL', 'https://hp-sds-latam2.insightportal.net/PortalAPI'), '/'),

    'key' => env('INSIGHT_API_KEY'),
    'secret' => env('INSIGHT_API_SECRET'),

    /*
     | Os clientes da COCARI dentro do Insight. Quase toda consulta exige
     | customerId. Descobrimos os valores com `php artisan insight:testar`,
     | que lista o que a credencial enxerga.
     */
    'customer_ids' => array_values(array_filter(array_map(
        'intval',
        explode(',', (string) env('INSIGHT_CUSTOMER_IDS', ''))
    ))),

    'timeout' => (int) env('INSIGHT_TIMEOUT', 30),

    /*
     | O token JWT vale 24h (expires_in = 86400). Guardamos com uma margem de
     | folga para não usar um token que expira no meio da sincronização.
     */
    'token_margem_segundos' => (int) env('INSIGHT_TOKEN_MARGEM', 600),

    /*
    |--------------------------------------------------------------------------
    | Teto de requisições
    |--------------------------------------------------------------------------
    |
    | A API é de terceiro e compartilhada com a operação da Simpress. Não é
    | nossa para consumir à vontade: um laço mal escrito daqui vira problema
    | no portal deles, e a credencial pode ser cortada.
    |
    | Os contadores ficam no cache, não em memória do processo — dois comandos
    | rodando ao mesmo tempo somam no mesmo teto em vez de dobrarem o volume.
    |
    | Para dimensionar: uma sincronização completa custa 4 chamadas por
    | cliente (devices, consumíveis, contadores, pedidos), mais 1 autenticação
    | por dia, já que o token vive 24h.
    |
    */
    'limites' => [

        // Pausa mínima entre duas chamadas, em milissegundos. Evita rajada.
        'intervalo_ms' => (int) env('INSIGHT_INTERVALO_MS', 400),

        // Teto por minuto. Estourando, espera a janela abrir.
        'por_minuto' => (int) env('INSIGHT_POR_MINUTO', 20),

        /*
         | Teto por dia. Este NÃO espera: interrompe.
         |
         | É o disjuntor. Se algum código entrar em laço, o estrago para em
         | poucas dezenas de chamadas em vez de rodar a noite inteira.
         |
         | Dimensionado pelo uso real: 4 execuções agendadas × 4 chamadas,
         | mais 1 autenticação por dia, dão 17. O padrão deixa folga para
         | execuções manuais durante um diagnóstico sem travar o agendamento
         | do dia — mas continua apertado o bastante para um laço parar cedo.
         */
        'por_dia' => (int) env('INSIGHT_POR_DIA', 60),

        // Quanto aceitamos esperar pela janela do minuto antes de desistir.
        'espera_maxima_segundos' => (int) env('INSIGHT_ESPERA_MAX', 90),
    ],

];
