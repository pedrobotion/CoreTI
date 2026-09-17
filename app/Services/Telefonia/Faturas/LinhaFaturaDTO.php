<?php

namespace App\Services\Telefonia\Faturas;

/** Um item de linha lido do PDF da fatura. */
class LinhaFaturaDTO
{
    public function __construct(
        public readonly string $ddd,
        public readonly string $numero,
        public readonly string $nomePlano,
        public readonly float $valor,
    ) {}

    public function chave(): string
    {
        return $this->ddd.'|'.$this->numero;
    }
}
