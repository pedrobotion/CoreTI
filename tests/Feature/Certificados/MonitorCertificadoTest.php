<?php

namespace Tests\Feature\Certificados;

use App\Models\CertificateCheck;
use App\Services\Certificados\CertificateMonitorService;
use App\Services\Certificados\CertificateReader;
use App\Services\Jira\JiraTicketOpener;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Mockery;
use Tests\TestCase;

/**
 * Aviso de renovação de certificado.
 *
 * O chamado abre dentro da janela (5 dias antes), uma vez por certificado. Não
 * repete enquanto a validade segue curta — repetir viraria um chamado por dia
 * nos últimos dias. Renovou o certificado, o ciclo recomeça.
 */
class MonitorCertificadoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['certificados.hosts' => ['site.cocari.com.br'], 'certificados.avisar_dias_antes' => 5]);
    }

    private function monitor(Carbon $expira, ?string $fingerprint, ?string $jiraDevolve = 'TIC-100'): CertificateMonitorService
    {
        $reader = Mockery::mock(CertificateReader::class);
        $reader->shouldReceive('ler')->andReturn([
            'ok' => true, 'expira_em' => $expira, 'fingerprint' => $fingerprint,
            'subject' => '*.cocari.com.br', 'erro' => null,
        ]);

        $jira = Mockery::mock(JiraTicketOpener::class);
        $jira->shouldReceive('abrir')->andReturn($jiraDevolve);

        return new CertificateMonitorService($reader, $jira);
    }

    public function test_longe_do_vencimento_nao_abre_chamado(): void
    {
        $r = $this->monitor(now()->addDays(180), 'fp-a')->verificarTodos();

        $this->assertSame(0, $r['chamados_abertos']);
        $this->assertSame('ok', CertificateCheck::firstOrFail()->status);
    }

    public function test_dentro_da_janela_abre_chamado(): void
    {
        $r = $this->monitor(now()->addDays(4), 'fp-a')->verificarTodos();

        $this->assertSame(1, $r['chamados_abertos']);
        $c = CertificateCheck::firstOrFail();
        $this->assertSame('a_vencer', $c->status);
        $this->assertSame('TIC-100', $c->jira_issue_key);
    }

    public function test_exatamente_no_limite_de_5_dias_abre(): void
    {
        $r = $this->monitor(now()->addDays(5)->subHour(), 'fp-a')->verificarTodos();

        $this->assertSame(1, $r['chamados_abertos']);
    }

    public function test_nao_duplica_enquanto_o_certificado_for_o_mesmo(): void
    {
        $this->monitor(now()->addDays(4), 'fp-a')->verificarTodos();
        $r = $this->monitor(now()->addDays(3), 'fp-a')->verificarTodos();

        $this->assertSame(0, $r['chamados_abertos'], 'não abre um segundo chamado');
        $this->assertSame('TIC-100', CertificateCheck::firstOrFail()->jira_issue_key);
    }

    public function test_renovacao_reabre_o_ciclo(): void
    {
        $this->monitor(now()->addDays(4), 'fp-antigo')->verificarTodos();

        $r = $this->monitor(now()->addDays(4), 'fp-novo', 'TIC-200')->verificarTodos();

        $this->assertSame(1, $r['chamados_abertos']);
        $this->assertSame('TIC-200', CertificateCheck::firstOrFail()->jira_issue_key);
    }

    public function test_falha_de_leitura_registra_erro_e_nao_abre_chamado(): void
    {
        $reader = Mockery::mock(CertificateReader::class);
        $reader->shouldReceive('ler')->andReturn([
            'ok' => false, 'expira_em' => null, 'fingerprint' => null,
            'subject' => null, 'erro' => 'Não foi possível conectar.',
        ]);
        $jira = Mockery::mock(JiraTicketOpener::class);
        $jira->shouldReceive('abrir')->never();

        $r = (new CertificateMonitorService($reader, $jira))->verificarTodos();

        $this->assertSame(1, $r['erros']);
        $this->assertSame(0, $r['chamados_abertos']);
        $this->assertSame('erro', CertificateCheck::firstOrFail()->status);
    }

    public function test_jira_indisponivel_tenta_de_novo_no_dia_seguinte(): void
    {
        $this->monitor(now()->addDays(4), 'fp-a', jiraDevolve: null)->verificarTodos();
        $this->assertNull(CertificateCheck::firstOrFail()->jira_issue_key);

        $r = $this->monitor(now()->addDays(3), 'fp-a', 'TIC-300')->verificarTodos();
        $this->assertSame(1, $r['chamados_abertos']);
    }
}
