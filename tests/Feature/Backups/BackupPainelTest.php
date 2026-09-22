<?php

namespace Tests\Feature\Backups;

use App\Models\CmdbDatabaseBackup;
use App\Models\User;
use App\Services\ModuleAccessService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BackupPainelTest extends TestCase
{
    use RefreshDatabase;

    private function operador(): User
    {
        $user = User::factory()->create(['role' => 'user', 'is_active' => true]);
        app(ModuleAccessService::class)->sync($user, ['cmdb' => ['view']]);

        return $user->fresh();
    }

    private function evento(array $override = []): CmdbDatabaseBackup
    {
        static $n = 0;
        $n++;

        return CmdbDatabaseBackup::create(array_merge([
            'host' => 'sdb1',
            'database_name' => 'dbmon',
            'backup_type' => 'progress',
            'status' => 'OK',
            'sucesso' => true,
            'data_backup' => now()->subHour(),
            'received_at' => now()->subHour(),
            'fingerprint' => hash('sha256', 'evento-'.$n),
        ], $override));
    }

    public function test_painel_mostra_o_ultimo_evento_de_cada_par(): void
    {
        // Mesmo par, dois eventos: o painel deve mostrar apenas o mais recente.
        $this->evento(['data_backup' => now()->subDays(2), 'status' => 'ERRO', 'sucesso' => false]);
        $this->evento(['data_backup' => now()->subHour(), 'status' => 'OK', 'sucesso' => true]);

        // Par diferente, em falha.
        $this->evento(['host' => 'sdb2', 'database_name' => 'vendas', 'status' => 'ERRO', 'sucesso' => false]);

        $resposta = $this->actingAs($this->operador())->get(route('cmdb.database-backups.index'));

        $resposta->assertOk();
        $resposta->assertSee('sdb1');
        $resposta->assertSee('sdb2');
        // O status antigo do sdb1 não pode aparecer como situação atual.
        $resposta->assertSeeInOrder(['sdb1', 'OK']);
    }

    public function test_par_sem_reportar_e_marcado_como_atrasado(): void
    {
        config(['backups.stale_after_hours' => 24]);

        $this->evento(['data_backup' => now()->subDays(5), 'received_at' => now()->subDays(5)]);

        $resposta = $this->actingAs($this->operador())
            ->get(route('cmdb.database-backups.index', ['situacao' => 'atrasados']));

        $resposta->assertOk();
        $resposta->assertSee('sdb1');
        $resposta->assertSee('sem reportar');
    }

    public function test_filtro_de_falha_esconde_pares_saudaveis(): void
    {
        $this->evento(['host' => 'saudavel', 'status' => 'OK', 'sucesso' => true]);
        $this->evento(['host' => 'quebrado', 'status' => 'ERRO', 'sucesso' => false]);

        $resposta = $this->actingAs($this->operador())
            ->get(route('cmdb.database-backups.index', ['situacao' => 'falha']));

        $resposta->assertOk();
        $resposta->assertSee('quebrado');
        $resposta->assertDontSee('saudavel');
    }

    public function test_historico_e_detalhe_abrem(): void
    {
        $evento = $this->evento();
        $user = $this->operador();

        $this->actingAs($user)->get(route('cmdb.database-backups.events'))->assertOk()->assertSee('sdb1');
        $this->actingAs($user)->get(route('cmdb.database-backups.show', $evento))->assertOk()->assertSee('dbmon');
    }

    public function test_usuario_sem_cmdb_nao_acessa(): void
    {
        $semAcesso = User::factory()->create(['role' => 'user', 'is_active' => true]);

        $this->actingAs($semAcesso)->get(route('cmdb.database-backups.index'))->assertForbidden();
    }

    public function test_comando_detecta_par_em_silencio(): void
    {
        $this->evento(['data_backup' => now()->subDays(10), 'received_at' => now()->subDays(10)]);

        // Código de saída 1 permite ao cron reagir.
        $this->artisan('backups:verificar-atrasados --horas=24')
            ->expectsOutputToContain('sem reportar')
            ->assertExitCode(1);
    }

    public function test_comando_nao_alerta_quando_esta_em_dia(): void
    {
        $this->evento(['data_backup' => now()->subHour(), 'received_at' => now()->subHour()]);

        $this->artisan('backups:verificar-atrasados --horas=24')->assertExitCode(0);
    }
}
