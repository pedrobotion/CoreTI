<?php

/*
|--------------------------------------------------------------------------
| Origem: COCARI Gestão TI
|--------------------------------------------------------------------------
|
| Usado apenas pela importação. O sistema de origem continua em produção:
| nada aqui pode alterá-lo. A conexão 'gestaoti' (config/database.php) é
| protegida em código por GestaoTiReadOnlyConnection, e o caminho abaixo é
| lido, nunca escrito.
|
| Pode ser removido depois que a importação estiver conferida e o gestaoTI
| deixar de ser a origem dos dados.
|
*/

return [

    /*
     | Onde o gestaoTI guarda os PDFs dos termos assinados.
     | Os caminhos gravados no banco dele são relativos a esta pasta.
     */
    'storage_path' => env('GESTAOTI_STORAGE_PATH', '/var/www/html/cocari_gestaoTI/storage/app/public'),

];
