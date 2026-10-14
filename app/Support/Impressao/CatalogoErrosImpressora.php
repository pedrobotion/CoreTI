<?php

namespace App\Support\Impressao;

/**
 * Traduz os alertas da EKM/Insight para português claro.
 *
 * A API devolve o erro de três jeitos diferentes, dependendo do modelo:
 *  - um código de status (ex.: "U1-2320 The temperature of fuser...") nos HP
 *    de base Samsung (432fdn, 408dn);
 *  - uma palavra-chave em inglês (ex.: "cartridgeLow", "outOfMedia") nos HP Pro;
 *  - só o número do mibCode (ex.: "66086") quando nem texto vem.
 *
 * Por isso a busca é em cascata: código de status → palavra-chave → classe do
 * alerta (SYSTEM_FAIL, CONS_LOW…). Sempre sobra uma explicação, nunca o texto
 * cru em inglês.
 *
 * Cada entrada tem: título curto, "o que é" (explica o componente e a causa
 * provável) e a ação recomendada.
 */
class CatalogoErrosImpressora
{
    /** Códigos de status das impressoras (HP/Samsung). Chave = código. */
    private const CODIGOS = [
        // Fusor — peça que fixa o toner no papel com calor.
        'U1-2320' => [
            'Fusor com falha de temperatura',
            'O fusor é a peça que esquenta para fixar o toner no papel. A impressora não está conseguindo controlar a temperatura dele — normalmente defeito no próprio fusor ou no sensor de temperatura (termistor). A impressão para por segurança.',
            'Desligue e ligue a impressora. Se o erro voltar, o fusor precisa ser substituído — acionar a Simpress.',
        ],
        'U1-2315' => [
            'Fusor com temperatura anormal',
            'O fusor (peça que fixa o toner no papel com calor) está com a temperatura fora da faixa normal. É o estágio que costuma anteceder a falha total do fusor.',
            'Desligue e ligue a impressora. Se persistir, programar a troca do fusor com a Simpress.',
        ],
        // Scanner.
        'S3-3122' => [
            'Scanner travado',
            'O scanner não consegue movimentar a barra de leitura (CIS). Em geral é a trava de transporte do scanner ainda acionada, alguma obstrução sobre o vidro, ou falha mecânica/erro de sombreamento da barra de leitura.',
            'Destrave o scanner (a chave/parafuso de transporte fica na lateral ou embaixo da tampa), remova objetos sobre o vidro e reinicie. Se continuar, é falha mecânica — acionar a Simpress.',
        ],
        // Papel / bandejas.
        'M1-5113' => [
            'Bandeja 2 sem papel',
            'A Bandeja 2 está sem papel. A impressão pode seguir por outra bandeja, se houver papel nela.',
            'Abastecer a Bandeja 2 com papel.',
        ],
        'M1-5120' => [
            'Todas as bandejas sem papel',
            'Nenhuma bandeja da impressora tem papel. A impressão parou.',
            'Abastecer as bandejas com papel.',
        ],
        // Toner.
        'C1-1110' => [
            'Toner acabando — providenciar reposição',
            'O cartucho de toner está chegando ao fim. A impressora ainda imprime, mas é hora de pedir um novo para não parar.',
            'Solicitar/separar um toner de reposição. Não precisa trocar agora.',
        ],
        // Unidade de imagem (cilindro).
        'C3-1110' => [
            'Cilindro (unidade de imagem) acabando',
            'A unidade de imagem — o cilindro fotocondutor — está chegando ao fim. A impressora ainda imprime.',
            'Solicitar/separar uma unidade de imagem de reposição.',
        ],
        'C3-1150' => [
            'Cilindro (unidade de imagem) esgotado',
            'A unidade de imagem/cilindro acabou. A qualidade de impressão cai e logo a impressão para.',
            'Trocar a unidade de imagem.',
        ],
    ];

    /** Palavras-chave em inglês dos HP Pro. Chave = palavra (minúscula). */
    private const PALAVRAS = [
        'cartridgelow' => [
            'Toner baixo',
            'O nível de toner está baixo. A impressora ainda imprime normalmente.',
            'Separar um toner de reposição.',
        ],
        'cartridgeverylowcontinue' => [
            'Toner muito baixo',
            'O toner está quase no fim. A impressora segue imprimindo por ora, mas vai acabar em breve.',
            'Trocar o toner em breve.',
        ],
        'cartridgeout' => [
            'Toner esgotado',
            'O cartucho de toner acabou.',
            'Trocar o toner.',
        ],
        'alltraysempty' => [
            'Todas as bandejas sem papel',
            'Nenhuma bandeja da impressora tem papel.',
            'Abastecer as bandejas com papel.',
        ],
        'outofmedia' => [
            'Sem papel',
            'A impressora está sem papel.',
            'Abastecer de papel.',
        ],
    ];

    /** Último recurso: a classe do alerta (quando só vem o número do mibCode). */
    private const CLASSES = [
        'SYSTEM_FAIL' => [
            'Falha de hardware',
            'A impressora reportou uma falha de hardware que interrompe o funcionamento.',
            'Acionar o suporte técnico / Simpress.',
        ],
        'SYSTEM_WARNING' => [
            'Aviso de hardware',
            'A impressora reportou um aviso de hardware — algo começando a falhar.',
            'Monitorar; se repetir, acionar o suporte.',
        ],
        'OPEN' => [
            'Tampa/porta aberta ou componente destravado',
            'Uma porta/tampa está aberta ou um componente não está travado no lugar.',
            'Fechar as tampas e verificar o encaixe dos componentes.',
        ],
        'CONS_LOW' => [
            'Suprimento baixo',
            'Toner ou cilindro em nível baixo. A impressora ainda imprime.',
            'Providenciar a reposição do suprimento.',
        ],
        'CONS_OUT' => [
            'Suprimento esgotado',
            'Toner ou cilindro acabou.',
            'Repor o suprimento.',
        ],
        'MEDIA_OUT' => [
            'Sem papel',
            'Uma bandeja (ou todas) está sem papel.',
            'Abastecer de papel.',
        ],
        'OTHER' => [
            'Alerta da impressora',
            'A impressora reportou um alerta geral.',
            'Verificar o painel da impressora.',
        ],
        'AVAILABILITY' => [
            'Sem comunicação (monitoramento suspenso)',
            'A impressora parou de se comunicar com o monitoramento — pode ter trocado de IP, sido desligada ou removida da rede.',
            'Verificar se está ligada e conectada à rede.',
        ],
    ];

    /**
     * @return array{codigo:string,titulo:string,o_que_e:string,acao:string}
     */
    public static function explicar(string $alertClass, int $mibCode, ?string $descricao = null): array
    {
        $descricao = trim((string) $descricao);

        // 1) Código de status no início da descrição (ex.: "S3-3122 ...").
        if ($descricao !== '' && preg_match('/\b([A-Z]\d-\d{3,4})\b/', $descricao, $m)) {
            $codigo = $m[1];
            if (isset(self::CODIGOS[$codigo])) {
                return self::montar($codigo, self::CODIGOS[$codigo]);
            }
        }

        // 2) Palavra-chave em inglês (ex.: "cartridgeLow", "outOfMedia").
        if ($descricao !== '') {
            $token = preg_replace('/[^a-z]/', '', mb_strtolower($descricao));
            if ($token !== '' && isset(self::PALAVRAS[$token])) {
                return self::montar($descricao, self::PALAVRAS[$token]);
            }
        }

        // 3) Classe do alerta.
        $classe = strtoupper(trim($alertClass));
        if (isset(self::CLASSES[$classe])) {
            $codigo = $descricao !== '' ? $descricao : $classe;

            return self::montar($codigo, self::CLASSES[$classe]);
        }

        // 4) Nada bateu: mostra o que veio, sem explicação inventada.
        return [
            'codigo' => $descricao !== '' ? $descricao : ($classe ?: '—'),
            'titulo' => 'Alerta da impressora',
            'o_que_e' => $descricao !== '' ? $descricao : 'A impressora reportou um alerta.',
            'acao' => 'Verificar o painel da impressora.',
        ];
    }

    /**
     * @param  array{0:string,1:string,2:string}  $dados
     * @return array{codigo:string,titulo:string,o_que_e:string,acao:string}
     */
    private static function montar(string $codigo, array $dados): array
    {
        return [
            'codigo' => $codigo,
            'titulo' => $dados[0],
            'o_que_e' => $dados[1],
            'acao' => $dados[2],
        ];
    }
}
