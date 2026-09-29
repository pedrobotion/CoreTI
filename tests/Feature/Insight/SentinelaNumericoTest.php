<?php

namespace Tests\Feature\Insight;

use App\Services\Insight\PrinterSyncService;
use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionMethod;
use Tests\TestCase;

/**
 * O portal usa -1 e -2 como "desconhecido", não como quantidade.
 *
 * Aparece em consumível recém-instalado, que ainda não tem histórico para
 * projetar dias ou páginas restantes. As 38 colunas numéricas do módulo são
 * unsigned: deixar o -1 passar derruba a sincronização inteira com "Out of
 * range value" por causa de um toner novo — foi o que aconteceu na primeira
 * execução contra o parque real.
 */
class SentinelaNumericoTest extends TestCase
{
    private function inteiro(mixed $valor, ?int $min = 0, ?int $max = null): ?int
    {
        $m = new ReflectionMethod(PrinterSyncService::class, 'inteiro');
        $m->setAccessible(true);

        return $m->invoke(app(PrinterSyncService::class), $valor, $min, $max);
    }

    #[DataProvider('sentinelas')]
    public function test_sentinela_vira_nulo(int $sentinela): void
    {
        $this->assertNull($this->inteiro($sentinela));
    }

    public static function sentinelas(): array
    {
        return [['-1' => -1], ['-2' => -2], ['-999' => -999]];
    }

    public function test_quantidade_valida_passa_intacta(): void
    {
        $this->assertSame(0, $this->inteiro(0), 'zero é leitura válida, não ausência');
        $this->assertSame(3500, $this->inteiro(3500));
        $this->assertSame(1, $this->inteiro('1'));
    }

    public function test_ausencia_continua_sendo_nulo(): void
    {
        $this->assertNull($this->inteiro(null));
        $this->assertNull($this->inteiro(''));
    }

    public function test_teto_continua_valendo(): void
    {
        // percentLeft passa 0..100; o portal já devolveu valores acima.
        $this->assertSame(100, $this->inteiro(150, 0, 100));
        $this->assertNull($this->inteiro(-1, 0, 100));
    }

    public function test_minimo_explicito_ainda_pode_ser_desligado(): void
    {
        // Quem precisar de negativo declara; o padrão é que não precise.
        $this->assertSame(-1, $this->inteiro(-1, null));
    }
}
