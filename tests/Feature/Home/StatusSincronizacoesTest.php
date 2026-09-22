<?php

namespace Tests\Feature\Home;

use App\Models\User;
use App\Services\SyncStatusService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Painel de status das sincronizações na tela inicial.
 */
class StatusSincronizacoesTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function servico(): SyncStatusService
    {
        return app(SyncStatusService::class);
    }

    /**
     * Usa todasAsFontes(): estes testes cobrem o MAPEAMENTO de status, não a
     * filtragem por permissão — que tem arquivo próprio.
     */
    private function fonte(string $chave): ?array
    {
        return $this->servico()->todasAsFontes()->firstWhere('chave', $chave);
    }

    private function logVcenter(string $status, string $quando, ?string $erro = null): void
    {
        DB::table('cmdb_connectors')->insertOrIgnore([
            'id' => 1, 'name' => 'vCenter', 'connector_type' => 'vcenter',
            'host' => 'https://vcenter.local', 'is_active' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        DB::table('cmdb_sync_logs')->insert([
            'connector_id' => 1, 'status' => $status,
            'started_at' => $quando, 'finished_at' => $quando,
            'error_message' => $erro,
            'created_at' => $quando, 'updated_at' => $quando,
        ]);
    }

    public function test_sucesso_do_vcenter_vira_status_ok(): void
    {
        $this->logVcenter('success', '2026-09-08 10:00:00');

        $f = $this->fonte('vcenter');

        $this->assertSame(SyncStatusService::OK, $f['status']);
        $this->assertSame('Sucesso', $f['rotulo_status']);
        $this->assertTrue($f['status_proprio']);
    }

    public function test_erro_do_vcenter_vira_status_erro(): void
    {
        $this->logVcenter('failed', '2026-09-08 10:00:00', 'Credencial recusada.');

        $f = $this->fonte('vcenter');

        $this->assertSame(SyncStatusService::ERRO, $f['status']);
        $this->assertSame('Credencial recusada.', $f['detalhe']);
    }

    /**
     * 'warning' é o estado normal deste conector, que conclui e envia para
     * revisão. Tratar como erro encheria o painel de alarme falso.
     */
    public function test_warning_do_vcenter_nao_e_erro(): void
    {
        $this->logVcenter('warning', '2026-09-08 10:00:00');

        $f = $this->fonte('vcenter');

        $this->assertSame(SyncStatusService::ATENCAO, $f['status']);
        $this->assertNotSame(SyncStatusService::ERRO, $f['status']);
    }

    public function test_sem_execucao_o_status_e_desconhecido(): void
    {
        $f = $this->fonte('vcenter');

        $this->assertSame(SyncStatusService::DESCONHECIDO, $f['status']);
        $this->assertNull($f['executado_em']);
        $this->assertSame('Sem informação', $f['rotulo_status']);
    }

    public function test_origem_recente_sem_status_proprio_fica_ok_e_marcada_como_inferida(): void
    {
        Carbon::setTestNow('2026-09-08 12:00:00');

        DB::table('ad_users')->insert([
            'nome_usuario' => 'teste', 'last_sync' => '2026-09-08 07:00:00',
        ]);

        $f = $this->fonte('ad');

        $this->assertSame(SyncStatusService::OK, $f['status']);
        $this->assertFalse($f['status_proprio'], 'a origem só carimba data; o status é inferido');
    }

    public function test_origem_sem_atualizacao_recente_fica_atrasada(): void
    {
        Carbon::setTestNow('2026-09-08 12:00:00');

        DB::table('ad_users')->insert([
            'nome_usuario' => 'antigo', 'last_sync' => '2026-09-05 07:00:00',
        ]);

        $f = $this->fonte('ad');

        $this->assertSame(SyncStatusService::ATRASADO, $f['status']);
        $this->assertStringContainsString('Sem atualização', $f['detalhe']);
    }

    public function test_a_tela_inicial_mostra_o_painel(): void
    {
        $this->logVcenter('success', now()->toDateTimeString());

        $user = User::factory()->create(['role' => 'admin', 'is_active' => true]);

        $resposta = $this->actingAs($user)->get(route('home'));

        $resposta->assertOk();
        $resposta->assertSee('Status das sincronizações');
        $resposta->assertSee('Sincronização vCenter');
        // Os blocos antigos saíram.
        $resposta->assertDontSee('Circuitos recentes');
        $resposta->assertDontSee('Operadoras com mais circuitos');
    }

    public function test_classes_de_cor_sao_literais(): void
    {
        foreach ([SyncStatusService::OK, SyncStatusService::ATENCAO, SyncStatusService::ERRO,
                  SyncStatusService::ATRASADO, SyncStatusService::DESCONHECIDO] as $status) {
            $c = SyncStatusService::classes($status);

            $this->assertArrayHasKey('ponto', $c);
            $this->assertArrayHasKey('selo', $c);
            // Interpolação não é gerada pelo Tailwind; as classes têm de vir inteiras.
            $this->assertStringNotContainsString('{', $c['selo']);
        }
    }
}
