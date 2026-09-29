<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Expurgo de contas do Active Directory
    |--------------------------------------------------------------------------
    |
    | O servidor do AD envia a prévia, o CoreTI guarda e espera aprovação
    | humana, e o servidor do AD volta para buscar o que foi aprovado.
    |
    | O CoreTI nunca recebe credencial do AD: quem apaga é o servidor que já
    | tem o privilégio. Aqui fica apenas a decisão.
    |
    */

    'expurgo' => [

        // Token compartilhado, exigido no header X-Coreti-Ad-Token.
        'token' => env('AD_PURGE_TOKEN', ''),

        // Restringe a origem. IPs ou faixas CIDR separados por vírgula.
        'allowed_ips' => array_values(array_filter(array_map(
            'trim',
            explode(',', (string) env('AD_PURGE_ALLOWED_IPS', ''))
        ))),

        // Dias de desativação exigidos para uma conta entrar na prévia.
        'dias_padrao' => (int) env('AD_PURGE_DIAS', 30),

        /*
         | Validade da aprovação. Depois disso o lote expira e nada é excluído.
         |
         | Aprovação não é permanente: a lista foi conferida em um dia
         | específico e as contas mudam. Executar em janeiro o que foi aprovado
         | em outubro apagaria gente com base em informação vencida.
         */
        'validade_aprovacao_dias' => (int) env('AD_PURGE_VALIDADE_DIAS', 15),

        /*
         | Contas que o CoreTI recusa mesmo que cheguem na prévia.
         |
         | O servidor do AD já faz essa verificação. Repetir aqui é proposital:
         | é o CoreTI que apresenta a lista para aprovação, e ele não deve
         | confiar cegamente em quem envia. Uma conta protegida que chegasse
         | até a tela poderia ser aprovada por engano.
         |
         | krbtgt* cobre a krbtgt do domínio e as krbtgt_<RODC> de cada
         | controlador somente leitura — estas têm RID normal e nenhum grupo
         | privilegiado, então nenhum outro critério as pegaria.
         */
        'nunca_excluir' => [
            'krbtgt*',
            'MSOL_*',
            'Administrator', 'Administrador',
            'Guest', 'Convidado',
            'DefaultAccount', 'WDAGUtilityAccount',
        ],

        /*
         | Minutos que uma conta entregue fica fora da fila esperando o
         | resultado.
         |
         | Impede que duas execuções simultâneas do executor recebam a mesma
         | lista e tentem apagar as mesmas contas. Se o executor morrer no
         | meio, a conta volta para a fila sozinha ao vencer o prazo.
         |
         | Precisa ser maior que a execução mais longa: com 200 contas por
         | parte e pausa de 30 s entre elas, uma execução cheia leva minutos.
         */
        'minutos_de_entrega' => (int) env('AD_PURGE_MINUTOS_ENTREGA', 30),

        /*
         | Teto de exclusões que o servidor do AD executa por chamada.
         |
         | Apagar milhares de contas de uma vez gera onda de replicação entre
         | os controladores — consumo de RAM e rede observado em produção.
         | O restante fica para a chamada seguinte.
         */
        'max_por_execucao' => (int) env('AD_PURGE_MAX_POR_EXECUCAO', 200),

        /*
         | Os destinatários do aviso NÃO ficam aqui.
         |
         | São mantidos em Configurações > Notificações, no evento "Expurgo do
         | Active Directory", junto com as demais automações. Uma lista em .env
         | só muda com deploy e não fica visível para quem opera o sistema.
         */
    ],

    /*
    |--------------------------------------------------------------------------
    | Campo "Escritório" (physicalDeliveryOfficeName)
    |--------------------------------------------------------------------------
    |
    | Guarda o código do centro de custo. A regra é por OU: todo mundo do setor
    | recebe o código do setor.
    |
    */
    'escritorio' => [

        /*
         | Contas que a regra NÃO preenche.
         |
         | Contas modelo existem para clonar na criação de usuário — dar
         | centro de custo a elas propaga o código errado para quem for
         | clonado depois, e infla o rateio com gente que não existe.
         |
         | Contas de scanner e de serviço SÃO preenchidas: pertencem a um
         | setor de verdade e consomem custo dele.
         */
        'ignorar_contas' => [
            /*
             | Contas que não são de pessoa não recebem centro de custo.
             |
             | user.* eram modelos (clonados na criação de usuário) e
             | integrações; foram removidas do inventário porque atrapalhavam
             | o relatório de colaborador. O padrão fica como rede de segurança
             | caso voltem.
             |
             | scanner.* e ged.* são equipamento e repositório: existem num
             | setor, mas não consomem centro de custo.
             */
            'nome_usuario:user.*',
            'nome_usuario:scanner.*',
            'nome_usuario:ged.*',
        ],

        /*
         | OUs que aparecem no caminho mas não são setor — o nível "Usuários"
         | é pasta de organização, não lotação.
         */
        'niveis_ignorados' => ['Usuários', 'Usuarios', 'Users'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Relatório de cadastro incompleto
    |--------------------------------------------------------------------------
    */
    'relatorio' => [

        /*
         | Estruturas fora do escopo do cadastro de colaborador.
         |
         | Concessionárias e Terceiros seguem outro modelo: matrícula, cargo e
         | centro de custo não se aplicam do mesmo jeito. Cobrá-los aqui
         | encheria o relatório de pendência que ninguém vai resolver.
         |
         | CSC fica de FORA desta lista, apesar de estar sob Concessionárias:
         | 20 das suas 23 contas têm matrícula e cargo. Ignorar a árvore
         | inteira tiraria do relatório justamente quem está preenchido.
         |
         | Compara com os níveis de OU do DistinguishedName.
         */
        'ous_fora_de_escopo' => [
            'Tratorcase',
            'Tratornew',
            'Terceiros',
            // Representantes não são contratados: não têm matrícula, cargo
            // nem centro de custo nossos.
            'Representantes',
        ],

        /*
         | Prefixos de login que não pertencem a uma pessoa.
         |
         | Qualidade cadastral mede o cadastro de COLABORADOR. Um scanner, um
         | GED de unidade ou uma conta modelo nunca vão ter matrícula e cargo:
         | contá-los como pendência cria um número que não baixa nunca e
         | esconde quem de fato precisa ser preenchido. Só as contas modelo
         | (user.*) já são 340.
         |
         | Vale tanto para o painel quanto para o relatório detalhado — os dois
         | leem daqui para não divergirem.
         |
         | Comparação por início do login, sem diferenciar maiúsculas.
         */
        'prefixos_nao_pessoa' => [
            'scanner.',   // multifuncionais das unidades
            'ged.',       // digitalização por unidade
            'user.',      // contas modelo ("User Modelo ... Padrão/Restrito/Liberado")
            'svc',        // conta de serviço
            'srv',
            'ftp',
            'ctx',
            'dnadmin',
        ],

        /*
         | Campos que não se aplicam a uma estrutura específica.
         |
         | A Rodocoop é empresa afiliada: os colaboradores dela não têm
         | matrícula nossa, e o Escritório guarda o CNPJ da empresa em vez de
         | centro de custo. Cargo e unidade continuam valendo — 7 das 22 contas
         | já os têm preenchidos.
         |
         | Dispensa por CAMPO, e não a estrutura inteira: tirar a Rodocoop do
         | relatório esconderia as 15 contas dela que estão sem cargo.
         */
        'campos_dispensados' => [
            'Rodocoop' => ['matricula'],

            /*
             | Colari é a outra empresa sob Afiliados, no mesmo caso da
             | Rodocoop: os colaboradores não têm matrícula nossa.
             |
             | Só matrícula. O cargo continua cobrado de propósito: em 24/09
             | havia 42 contas sem cargo ali, e isso é informação que ainda
             | não foi levantada, não uma dispensa — tirá-la do relatório
             | esconderia trabalho que ainda vai acontecer.
             */
            'Colari' => ['matricula'],
        ],
    ],

];
