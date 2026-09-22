<?php

/*
|--------------------------------------------------------------------------
| CMDB — aprovação automática de descobertas
|--------------------------------------------------------------------------
|
| O inventário do vCenter deposita tudo numa fila de revisão. Aprovar cega-
| mente polui o CMDB: a fila contém templates, cópias de backup, restaurações
| temporárias, VMs de teste e objetos que já sumiram do vCenter.
|
| As regras abaixo separam o que pode entrar sozinho do que continua exigindo
| olho humano. O que for excluído não some: é marcado como "ignorado", fica
| visível na tela e pode ser reaberto.
|
*/

return [

    /*
    |--------------------------------------------------------------------------
    | Tipos de descoberta que são apenas referência
    |--------------------------------------------------------------------------
    |
    | Objetos do vSphere que não são itens de inventário: rede alimenta a tela
    | de VLANs; datastore, cluster e datacenter não são lidos por nenhuma tela.
    |
    | Ficavam na fila de revisão para sempre, porque não existe decisão a tomar
    | sobre eles — e uma fila cheia de itens que ninguém vai aprovar esconde os
    | poucos que realmente esperam alguém.
    |
    | É lista de EXCLUSÃO, não de permissão: um tipo novo que apareça no
    | vSphere continua visível na fila até alguém decidir o que fazer com ele.
    | Esconder o desconhecido é como se perde item de verdade.
    |
    */
    'tipos_de_referencia' => [
        'vcenter_network',
        'vcenter_datastore',
        'vcenter_cluster',
        'vcenter_datacenter',
    ],

    'auto_aprovar' => [

        'habilitado' => env('CMDB_AUTO_APROVAR', true),

        /*
         | Só VM e host viram ativo. Rede, datastore, cluster e datacenter
         | nunca foram aprovados neste ambiente — não são tratados como ativo.
         */
        'tipos' => ['vcenter_virtual_machine', 'vcenter_host'],

        /*
         | Nomes que NÃO devem virar ativo automaticamente.
         |
         | Comparação sem diferenciar maiúsculas. Cada padrão é um trecho do
         | nome; quem casar é marcado como ignorado com o motivo registrado.
         */
        'ignorar_nomes_contendo' => [
            'template',      // imagem base, não é máquina em operação
            '_restored',     // restauração temporária de backup
            '-restored',
            ' - bkp',        // cópia de segurança do appliance
            '_bkp',
            '-bkp',
            'backup de',
            'clone de',
            'copia de',
            'cópia de',
        ],

        /*
         | Descoberta que o vCenter não confirma há mais de N horas
         | provavelmente já não existe. Aprovar criaria ativo fantasma —
         | foi o caso de uma VM recriada com moref novo, cujo registro antigo
         | continuava na fila.
         */
        'max_horas_sem_ver' => (int) env('CMDB_AUTO_APROVAR_MAX_HORAS_SEM_VER', 24),

        /*
         | Carência antes de aprovar: evita cadastrar VM criada e destruída no
         | mesmo dia (teste, migração, restauração). Zero desliga a carência.
         */
        'horas_de_carencia' => (int) env('CMDB_AUTO_APROVAR_CARENCIA_HORAS', 24),

        /*
         | Marcar como ignorado o que foi excluído pelas regras. Falso apenas
         | pula, deixando pendente — a fila volta a crescer.
         */
        'marcar_excluidos_como_ignorados' => true,
    ],
];
