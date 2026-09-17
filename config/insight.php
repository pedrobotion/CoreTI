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

];
