<?php

namespace App\Services\Telefonia\Faturas;

use InvalidArgumentException;

/**
 * Cada operadora emite a fatura num layout próprio, então cada uma precisa do
 * seu leitor. Hoje só a VIVO está implementada; as demais entram aqui sem
 * mexer no controller nem nas telas.
 */
class FaturaParserFactory
{
    /** @var array<string, class-string<FaturaParserInterface>> */
    private const PARSERS = [
        'VIVO' => Parsers\VivoFaturaParser::class,
    ];

    public static function make(string $operadora): FaturaParserInterface
    {
        $operadora = mb_strtoupper(trim($operadora));

        if (! isset(self::PARSERS[$operadora])) {
            throw new InvalidArgumentException(
                "Ainda não há leitor de fatura para a operadora {$operadora}. Disponíveis: "
                .implode(', ', self::disponiveis()).'.'
            );
        }

        $classe = self::PARSERS[$operadora];

        return new $classe();
    }

    /** @return array<int, string> */
    public static function disponiveis(): array
    {
        return array_keys(self::PARSERS);
    }
}
