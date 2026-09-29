<?php

namespace App\Services\Insight;

use RuntimeException;

class InsightApiException extends RuntimeException
{
    public function __construct(
        string $mensagem,
        public readonly ?int $status = null,
        public readonly ?string $corpo = null,
    ) {
        parent::__construct($mensagem);
    }

    public static function semCredencial(): self
    {
        return new self(
            'Chave e segredo da Insight API não configurados. Defina INSIGHT_API_KEY e INSIGHT_API_SECRET no .env.'
        );
    }

    /**
     * Teto diário estourado.
     *
     * Não é erro da API: é o nosso disjuntor. Chegar aqui quase sempre
     * significa laço indevido, não uso legítimo — uma sincronização completa
     * custa 4 chamadas por cliente.
     */
    public static function tetoDiario(int $teto): self
    {
        return new self(sprintf(
            'Teto diário de %d chamadas à Insight API atingido. Nenhuma nova requisição será feita hoje. '
            .'Se o uso for legítimo, ajuste INSIGHT_POR_DIA no .env; se não for, procure o laço antes de aumentar.',
            $teto
        ));
    }

    /** Janela do minuto cheia e a espera passaria do aceitável. */
    public static function esperaLonga(int $segundos, int $maximo): self
    {
        return new self(sprintf(
            'Limite de %d chamadas por minuto à Insight API atingido; a próxima janela abre em %ds, '
            .'acima do máximo de %ds que aceitamos esperar. Rode de novo daqui a pouco.',
            (int) config('insight.limites.por_minuto', 20),
            $segundos,
            $maximo
        ));
    }

    /** Traduz os códigos do gateway para algo acionável. */
    public static function doHttp(int $status, string $corpo, string $caminho): self
    {
        $mensagem = match (true) {
            $status === 401 => 'Credencial recusada pela Insight API. Confira a chave e o segredo, ou gere um novo par no portal.',
            $status === 403 => "Sem permissão para {$caminho}. O usuário de API pode estar com escopo ou papel insuficiente (DATA_READER/DATA_WRITER).",
            $status === 404 => "Recurso não encontrado na Insight API: {$caminho}.",
            $status === 429 => 'Limite de chamadas da Insight API atingido. Tente de novo em instantes.',
            $status >= 500 => "Insight API indisponível (HTTP {$status}).",
            default => "Insight API respondeu HTTP {$status} em {$caminho}.",
        };

        return new self($mensagem, $status, mb_substr($corpo, 0, 500));
    }
}
