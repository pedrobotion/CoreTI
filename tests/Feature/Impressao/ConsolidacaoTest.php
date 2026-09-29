<?php

namespace Tests\Feature\Impressao;

use App\Models\PrintJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A planilha e o log trazem os mesmos trabalhos.
 *
 * A chave de duplicidade inclui a origem — necessário, porque o número do
 * trabalho recicla dentro de cada fila. O efeito colateral é que a linha da
 * planilha e a mesma linha do servidor convivem, e o total dobra no período
 * em que as duas se cruzam.
 */
class ConsolidacaoTest extends TestCase
{
    use RefreshDatabase;

    private function trabalho(string $servidor, array $dados = []): PrintJob
    {
        static $n = 0;
        $n++;

        return PrintJob::create(array_merge([
            'servidor' => $servidor,
            'chave_evento' => hash('sha256', $servidor.'-'.$n),
            'impresso_em' => '2026-09-22 13:26:58',
            'usuario' => 'fulano.silva',
            'impressora' => 'SED-LASER-FIN01',
            'doc_numero' => '546',
            'paginas' => 2,
        ], $dados));
    }

    public function test_remove_o_que_o_servidor_ja_entregou(): void
    {
        $this->trabalho('PLANILHA');
        $this->trabalho('IMP01');

        $this->artisan('impressao:consolidar')->assertSuccessful();

        $this->assertSame(1, PrintJob::count());
        $this->assertSame('IMP01', PrintJob::firstOrFail()->servidor, 'fica a versão com a origem correta');
    }

    public function test_preserva_o_que_so_existe_na_planilha(): void
    {
        // O trecho que o log do Windows já girou e apagou é justamente o
        // histórico que se perderia — não pode ser removido junto.
        $this->trabalho('PLANILHA', ['impresso_em' => '2026-09-21 20:13:53', 'doc_numero' => '100']);
        $this->trabalho('PLANILHA');
        $this->trabalho('IMP01');

        $this->artisan('impressao:consolidar')->assertSuccessful();

        $this->assertSame(2, PrintJob::count());
        $this->assertTrue(PrintJob::where('servidor', 'PLANILHA')->where('doc_numero', '100')->exists());
    }

    public function test_sem_envio_dos_servidores_nada_e_removido(): void
    {
        $this->trabalho('PLANILHA');
        $this->trabalho('PLANILHA', ['doc_numero' => '547']);

        $this->artisan('impressao:consolidar')
            ->expectsOutputToContain('Nenhuma sobreposição')
            ->assertSuccessful();

        $this->assertSame(2, PrintJob::count());
    }

    public function test_simular_nao_apaga(): void
    {
        $this->trabalho('PLANILHA');
        $this->trabalho('IMP01');

        $this->artisan('impressao:consolidar', ['--simular' => true])->assertSuccessful();

        $this->assertSame(2, PrintJob::count());
    }

    public function test_trabalho_parecido_mas_de_outro_usuario_nao_e_removido(): void
    {
        // Mesmo instante e mesma impressora, usuário diferente: são dois
        // trabalhos reais, não uma cópia.
        $this->trabalho('PLANILHA', ['usuario' => 'outra.pessoa']);
        $this->trabalho('IMP01');

        $this->artisan('impressao:consolidar')->assertSuccessful();

        $this->assertSame(2, PrintJob::count());
    }

    public function test_nao_mexe_em_trabalho_de_um_servidor_real(): void
    {
        $this->trabalho('IMP01');
        $this->trabalho('IMP02', ['impressora' => 'ROD-LASER-FIN01']);

        $this->artisan('impressao:consolidar')->assertSuccessful();

        $this->assertSame(2, PrintJob::count());
    }
}
