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

    // Destinatário(s) do e-mail diário de alertas de impressoras (vírgula separa).
    // Serve de fallback quando a região não tem destinatário próprio configurado.
    'alertas_email' => (string) env('INSIGHT_ALERTAS_EMAIL', 'ti@cocari.com.br'),

    /*
     | Destinatários dos alertas por região (recorte Sede×Cerrado, ver
     | config/scopes.php). Cada impressora cai na sua região pelo scope (ou,
     | sem scope, pela unidade vinculada). Impressoras sem região definida
     | entram no e-mail de TODAS as regiões (decisão do usuário 2026-10-09).
     | Vírgula separa vários destinatários.
     */
    'alertas_emails' => [
        'sede' => array_values(array_filter(array_map('trim', explode(
            ',',
            (string) env('INSIGHT_ALERTAS_EMAIL_SEDE', '')
        )))),
        'cerrado' => array_values(array_filter(array_map('trim', explode(
            ',',
            (string) env('INSIGHT_ALERTAS_EMAIL_CERRADO', '')
        )))),
    ],

    /*
     | Mapa das unidades do Cerrado (Goiás/Minas) por sinais do dispositivo.
     |
     | Muitas impressoras não têm unidade vinculada e o texto de localização da
     | Insight é bagunçado ("COCARI", "New Device Zone"), então o sync e o
     | roteamento reconhecem o Cerrado por dois sinais estáveis, levantados do
     | Atlas (atlas.cocari.com.br, impressoras_monitor + const LOCAIS) em
     | 2026-10-09:
     |
     |  - 'prefixos': a sigla no hostname (ex.: PIR-LASER-ADM-1 → Piracanjuba);
     |  - 'subredes': o bloco de IP 172.20.78.x–172.20.92.x, uma cidade por
     |    octeto — pega até quem não tem sigla (ex.: .90 = São João d'Aliança,
     |    hostname genérico HP84790A).
     |
     | Cada entrada aponta [source_id da unidade, cidade]. O source_id é a chave
     | de negócio da unidade (organization_units.source_id / unit_scopes), mais
     | estável que o id local. Todas essas unidades já estão como 'cerrado' no
     | unit_scopes. Nenhuma unidade do Paraná usa esse bloco de IP nem essas
     | siglas — por isso "não-Cerrado = Paraná" é seguro. Unidade nova do
     | Cerrado: some o prefixo e/ou o octeto aqui.
     */
    'cerrado' => [
        'prefixos' => [
            'CA1' => [19, 'Campo Alegre de Goiás'],
            'CA2' => [19, 'Campo Alegre de Goiás'],
            'CAT' => [77, 'Catalão'],
            'COR' => [72, 'Coromandel'],
            'CR1' => [16, 'Cristalina'],
            'CR2' => [59, 'Cristalina II'],
            'GMO' => [56, 'Guarda-Mor'],
            'IPA' => [60, 'Ipameri'],
            'ORI' => [70, 'Orizona'],
            'PIR' => [78, 'Piracanjuba'],
            'PTM' => [79, 'Patos de Minas'],
            'PTU' => [63, 'Paracatu'],
            'SARV' => [53, 'Santo Antônio do Rio Verde'],
            'SIL' => [55, 'Silvânia'],
        ],
        'subredes' => [
            '172.20.78.' => [79, 'Patos de Minas'],
            '172.20.79.' => [78, 'Piracanjuba'],
            '172.20.80.' => [77, 'Catalão'],
            '172.20.81.' => [19, 'Campo Alegre de Goiás'],
            '172.20.82.' => [19, 'Campo Alegre de Goiás'],
            '172.20.83.' => [55, 'Silvânia'],
            '172.20.84.' => [63, 'Paracatu'],
            '172.20.85.' => [56, 'Guarda-Mor'],
            '172.20.86.' => [72, 'Coromandel'],
            '172.20.87.' => [60, 'Ipameri'],
            '172.20.88.' => [53, 'Santo Antônio do Rio Verde'],
            '172.20.89.' => [70, 'Orizona'],
            '172.20.90.' => [58, "São João d'Aliança"],
            '172.20.91.' => [16, 'Cristalina'],
            '172.20.92.' => [59, 'Cristalina II'],
        ],
    ],

    // Classes de alerta que entram no e-mail (hardware/mecânico; fora suprimentos/papel).
    'alertas_classes' => array_values(array_filter(array_map('trim', explode(
        ',',
        (string) env('INSIGHT_ALERTAS_CLASSES', 'SYSTEM_FAIL,SYSTEM_WARNING,OPEN')
    )))),

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
