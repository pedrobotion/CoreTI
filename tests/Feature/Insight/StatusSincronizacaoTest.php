<?php

namespace Tests\Feature\Insight;

use App\Models\Printer;
use App\Models\User;
use App\Services\ModuleAccessService;
use App\Services\SyncStatusService;
use App\Support\LegacyModulePermissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * A sincronização das impressoras no painel de status.
 *
 * Além da frescura do dado, ela precisa acusar o teto da API de origem: se o
 * teto estoura, as execuções agendadas param sem que nada mude na base, e
 * olhar só a data levaria horas para perceber — com a causa escondida.
 */
class StatusSincronizacaoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        LegacyModulePermissions::esquecerCache();
        Cache::flush();
        config(['insight.limites.por_dia' => 60]);
    }

    private function fonte(): ?array
    {
        return app(SyncStatusService::class)->todasAsFontes()->firstWhere('chave', 'impressoras');
    }

    private function impressora(array $dados = []): Printer
    {
        static $n = 0;
        $n++;

        return Printer::create(array_merge([
            'insight_device_id' => 800000 + $n,
            'insight_customer_id' => 1163,
            'serial_number' => 'STA'.$n,
            'model' => 'Laser 408dn',
            'last_synced_at' => now(),
        ], $dados));
    }

    private function gastarChamadas(int $quantas): void
    {
        Cache::put('insight:chamadas-do-dia:'.now()->toDateString(), $quantas, now()->endOfDay());
    }

    public function test_aparece_no_painel(): void
    {
        $this->impressora();

        $this->assertNotNull($this->fonte());
        $this->assertSame('Sincronização Impressoras', $this->fonte()['rotulo']);
    }

    public function test_sem_nenhuma_sincronizacao_o_status_e_desconhecido(): void
    {
        $this->assertSame(SyncStatusService::DESCONHECIDO, $this->fonte()['status']);
        $this->assertStringContainsString('Nenhuma sincronização', $this->fonte()['detalhe']);
    }

    public function test_dado_recente_e_sucesso_e_mostra_o_consumo(): void
    {
        $this->impressora(['last_synced_at' => now()->subMinutes(30)]);
        $this->gastarChamadas(17);

        $fonte = $this->fonte();

        $this->assertSame(SyncStatusService::OK, $fonte['status']);
        $this->assertStringContainsString('17 de 60', $fonte['detalhe']);
    }

    public function test_dado_velho_acusa_atraso(): void
    {
        // 4 execuções por dia: 12h sem atualizar significa execução perdida.
        $this->impressora(['last_synced_at' => now()->subHours(12)]);

        $fonte = $this->fonte();

        $this->assertSame(SyncStatusService::ATRASADO, $fonte['status']);
        $this->assertStringContainsString('execução agendada pode ter falhado', $fonte['detalhe']);
    }

    public function test_intervalo_normal_entre_execucoes_nao_vira_alarme(): void
    {
        // O maior intervalo agendado é 07:00 → 12:30, cinco horas e meia.
        $this->impressora(['last_synced_at' => now()->subHours(6)]);

        $this->assertSame(SyncStatusService::OK, $this->fonte()['status']);
    }

    public function test_teto_estourado_vira_erro_mesmo_com_dado_recente(): void
    {
        /*
         | O dado está fresco porque a última execução rodou — mas as
         | próximas não vão rodar. É isso que o painel precisa dizer.
         */
        $this->impressora(['last_synced_at' => now()->subMinutes(5)]);
        $this->gastarChamadas(60);

        $fonte = $this->fonte();

        $this->assertSame(SyncStatusService::ERRO, $fonte['status']);
        $this->assertStringContainsString('Teto diário', $fonte['detalhe']);
    }

    public function test_so_quem_tem_o_modulo_enxerga(): void
    {
        $this->impressora();

        /*
         | Ids explícitos longe de User::MASTER_USER_ID (2): a conta mestra
         | ignora qualquer checagem de módulo, e o segundo usuário criado pela
         | factory cairia justo nela — o teste passaria por engano.
         */
        $com = User::factory()->create(['id' => 9401, 'role' => 'user', 'is_active' => true]);
        app(ModuleAccessService::class)->sync($com, ['impressoras' => ['view']]);

        $sem = User::factory()->create(['id' => 9402, 'role' => 'user', 'is_active' => true]);
        app(ModuleAccessService::class)->sync($sem, ['bancada' => ['view']]);

        $servico = app(SyncStatusService::class);

        $this->assertNotNull($servico->fontes($com->fresh())->firstWhere('chave', 'impressoras'));
        $this->assertNull($servico->fontes($sem->fresh())->firstWhere('chave', 'impressoras'));
    }

    public function test_numeros_saem_com_separador_de_milhar(): void
    {
        for ($i = 0; $i < 3; $i++) {
            $this->impressora();
        }

        $this->assertStringContainsString('3 impressoras', $this->fonte()['numeros']);
    }
}
