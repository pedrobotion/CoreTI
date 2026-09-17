<?php

namespace App\Services\Telefonia\Faturas;

use App\Models\TelefoniaFatura;

/** Resultado da leitura de um PDF de fatura. */
class FaturaParseResult
{
    /** @var array<int, LinhaFaturaDTO> */
    public array $linhas = [];

    public ?string $numeroConta = null;

    /** Formato Y-m-01. */
    public ?string $competencia = null;

    /** Formato Y-m-d. */
    public ?string $vencimento = null;

    public ?float $valorTotalDeclarado = null;

    public ?float $valorTotalCalculado = null;

    /** @var array<int, string> */
    public array $avisos = [];

    public string $parserUsado = '';

    public function totaisBatem(): bool
    {
        if ($this->valorTotalDeclarado === null || $this->valorTotalCalculado === null) {
            return false;
        }

        return abs($this->valorTotalDeclarado - $this->valorTotalCalculado) < TelefoniaFatura::TOLERANCIA_TOTAL;
    }

    public function diferencaTotal(): float
    {
        return round(($this->valorTotalCalculado ?? 0) - ($this->valorTotalDeclarado ?? 0), 2);
    }

    public function quantidade(): int
    {
        return count($this->linhas);
    }
}
