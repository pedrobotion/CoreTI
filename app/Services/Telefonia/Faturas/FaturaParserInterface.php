<?php

namespace App\Services\Telefonia\Faturas;

interface FaturaParserInterface
{
    /** Extrai os dados estruturados do PDF. */
    public function parse(string $caminhoPdf): FaturaParseResult;

    /** Operadora atendida por este parser ('VIVO', 'TIM'…). */
    public function operadora(): string;
}
