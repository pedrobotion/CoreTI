<?php

/*
|--------------------------------------------------------------------------
| Coleta do log de impressão dos servidores Windows
|--------------------------------------------------------------------------
|
| O sentido é sempre servidor de impressão → CoreTI. O CoreTI não abre
| conexão com os servidores e não guarda credencial de domínio: quem lê o
| Evento 307 é uma tarefa agendada rodando lá, com a conta que já tem acesso
| ao log.
|
| Trocar o token é uma linha no .env dos dois lados; trocar uma senha de
| domínio guardada aqui seria bem mais caro.
|
*/

return [

    // Token compartilhado, exigido no header X-Coreti-Impressao-Token.
    'token' => env('IMPRESSAO_TOKEN', ''),

    // Restringe a origem. IPs ou faixas CIDR separados por vírgula.
    'allowed_ips' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('IMPRESSAO_ALLOWED_IPS', ''))
    ))),

    /*
     | Teto de eventos por requisição.
     |
     | Uma semana rendeu 6.910 trabalhos nos três servidores. O script quebra
     | em lotes para a requisição não crescer sem limite e para uma falha de
     | rede custar só o lote, não a janela inteira.
     */
    'max_por_lote' => (int) env('IMPRESSAO_MAX_POR_LOTE', 1000),

    /*
     | Janela padrão que o script busca no log, em dias.
     |
     | Sete cobre o pedido e dá sobreposição: as execuções se cruzam e um dia
     | perdido por servidor fora do ar entra na execução seguinte, em vez de
     | virar buraco permanente na estatística.
     */
    'dias_padrao' => (int) env('IMPRESSAO_DIAS', 7),

    /*
     | Servidores esperados, para o painel saber quem deixou de reportar.
     |
     | Sem esta lista, um servidor que parasse de enviar simplesmente sumiria
     | dos números sem ninguém notar a falta.
     */
    'servidores' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('IMPRESSAO_SERVIDORES', 'IMP01,IMP02,IMP03'))
    ))),

    /*
     | Drivers de fila que NÃO entram na conciliação com a Simpress.
     |
     | Impressora térmica de cupom (Bematech) e de etiqueta (Zebra) não são do
     | contrato da Simpress e não falam o SNMP de impressora — apareciam
     | eternamente como "sem resposta", virando ruído no painel. A filtragem é
     | por DRIVER, não pelo nome da fila: o driver é o que identifica o
     | equipamento de verdade (o nome TERM/BAL é convenção e escapa casos).
     |
     | Comparação por trecho, sem diferenciar maiúsculas.
     */
    'drivers_fora_da_conciliacao' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('IMPRESSAO_DRIVERS_IGNORADOS', 'bematech,mp-4200,zdesigner,zpl,tsc,elgin,epson tm,daruma'))
    ))),
];
