<?php

/*
| Integração Microsoft 365 (sincronização de licenças Office).
|
| Estes valores precisam estar em config (e não lidos por env() em runtime):
| a produção roda com a config CACHEADA e, nesse modo, o Laravel não carrega o
| .env em tempo de execução — env() fora dos arquivos de config devolve o
| default. Era o que desligava a sync silenciosamente (MS_OFFICE_SYNC_ENABLED
| virava false). Lido aqui, o valor do .env é "assado" no config:cache.
*/

return [
    'sync_enabled' => filter_var(env('MS_OFFICE_SYNC_ENABLED', false), FILTER_VALIDATE_BOOL),
    'push_enabled' => filter_var(env('MS_OFFICE_PUSH_ENABLED', false), FILTER_VALIDATE_BOOL),
    'delete_missing' => filter_var(env('MS_OFFICE_DELETE_MISSING', true), FILTER_VALIDATE_BOOL),

    'tenant_id' => (string) env('MS_TENANT_ID', ''),
    'client_id' => (string) env('MS_CLIENT_ID', ''),
    'client_secret' => (string) env('MS_CLIENT_SECRET', ''),
];
