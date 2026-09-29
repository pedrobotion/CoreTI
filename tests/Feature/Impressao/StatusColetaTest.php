<?php

namespace Tests\Feature\Impressao;

use App\Models\PrintCollectorStatus;
use App\Services\SyncStatusService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * O painel precisa acusar coletor parado.
 *
 * O log do Windows retém cerca de seis dias: coleta interrompida além disso
 * vira histórico perdido em definitivo. E "sem evento novo" não serve de
 * sinal — domingo tem zero impressão legitimamente.
 */
class StatusColetaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['impressao.servidores' => ['IMP01', 'IMP02', 'IMP03']]);
    }

    private function fonte(): ?array
    {
        return app(SyncStatusService::class)->todasAsFontes()->firstWhere('chave', 'impressao_log');
    }

    private function contato(string $servidor, string $quando = 'now'): void
    {
        PrintCollectorStatus::create([
            'servidor' => $servidor,
            'ultimo_contato_em' => $quando === 'now' ? now() : $quando,
        ]);
    }

    public function test_sem_nenhum_envio_o_status_e_desconhecido(): void
    {
        $fonte = $this->fonte();

        $this->assertSame(SyncStatusService::DESCONHECIDO, $fonte['status']);
        $this->assertStringContainsString('IMP01, IMP02, IMP03', $fonte['detalhe']);
    }

    public function test_todos_reportando_e_sucesso(): void
    {
        foreach (['IMP01', 'IMP02', 'IMP03'] as $s) {
            $this->contato($s);
        }

        $this->assertSame(SyncStatusService::OK, $this->fonte()['status']);
    }

    public function test_um_servidor_mudo_vira_erro_e_e_nomeado(): void
    {
        $this->contato('IMP01');
        $this->contato('IMP02');
        $this->contato('IMP03', now()->subHours(12)->toDateTimeString());

        $fonte = $this->fonte();

        $this->assertSame(SyncStatusService::ERRO, $fonte['status']);
        $this->assertStringContainsString('IMP03', $fonte['detalhe']);
        $this->assertStringNotContainsString('IMP01', $fonte['detalhe']);
    }

    public function test_servidor_que_nunca_enviou_conta_como_mudo(): void
    {
        // Tarefa agendada que ninguém criou é o caso mais provável.
        $this->contato('IMP01');
        $this->contato('IMP02');

        $this->assertSame(SyncStatusService::ERRO, $this->fonte()['status']);
        $this->assertStringContainsString('IMP03', $this->fonte()['detalhe']);
    }

    public function test_reinicio_curto_nao_dispara_alarme(): void
    {
        // A tarefa roda de hora em hora; 3h cobre reinício e manutenção.
        foreach (['IMP01', 'IMP02', 'IMP03'] as $s) {
            $this->contato($s, now()->subHours(3)->toDateTimeString());
        }

        $this->assertSame(SyncStatusService::OK, $this->fonte()['status']);
    }

    public function test_lote_vazio_conta_como_contato(): void
    {
        /*
         | É o caso do domingo: nenhuma impressão, mas o coletor está vivo.
         | Exigir evento para considerar contato transformaria todo fim de
         | semana em alarme falso — e alarme que grita à toa deixa de ser lido.
         */
        config(['impressao.token' => 'token-de-teste-longo-o-bastante', 'impressao.allowed_ips' => []]);

        $this->withHeaders(['X-Coreti-Impressao-Token' => 'token-de-teste-longo-o-bastante'])
            ->postJson(route('api.impressao.lote'), ['servidor' => 'IMP01', 'eventos' => []])
            ->assertOk();

        $this->assertSame(1, PrintCollectorStatus::count());
        $this->assertSame('IMP01', PrintCollectorStatus::firstOrFail()->servidor);
    }
}
