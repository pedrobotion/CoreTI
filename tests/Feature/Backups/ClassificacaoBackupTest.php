<?php

namespace Tests\Feature\Backups;

use App\Models\CmdbDatabaseBackup;
use App\Models\User;
use App\Services\Backups\DatabaseBackupJiraService;
use App\Services\ModuleAccessService;
use App\Support\LegacyModulePermissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * "Atrasado" e "descontinuado" são coisas diferentes.
 *
 * Um par que parou de reportar ontem é incidente; um que parou há um ano é
 * registro que deveria ter sido aposentado. Na base real eram 8 identificadores
 * antigos de bancos que hoje reportam com outro nome — /banco/trr/base/trr
 * virou trr — e eles apareciam como atrasados ao lado de falhas de verdade.
 */
class ClassificacaoBackupTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        LegacyModulePermissions::esquecerCache();
        config(['backups.stale_after_hours' => 36, 'backups.inactive_after_days' => 30]);
    }

    private function usuario(): User
    {
        $user = User::factory()->create(['id' => 9701, 'role' => 'user', 'is_active' => true]);
        app(ModuleAccessService::class)->sync($user, ['cmdb' => ['view']]);

        return $user->fresh();
    }

    private function backup(string $banco, string $quando, bool $sucesso = true): CmdbDatabaseBackup
    {
        return CmdbDatabaseBackup::create([
            'host' => 'sdb1',
            'database_name' => $banco,
            'status' => $sucesso ? 'OK' : 'ERRO',
            // Identifica o evento na origem; único por host+banco+instante.
            'fingerprint' => hash('sha256', 'sdb1|'.$banco.'|'.$quando),
            'sucesso' => $sucesso,
            'data_backup' => $quando,
            'received_at' => $quando,
        ]);
    }

    private function resumo(): array
    {
        return $this->actingAs($this->usuario())
            ->get(route('cmdb.database-backups.index'))
            ->assertOk()
            ->viewData('resumo');
    }

    public function test_backup_recente_e_saudavel(): void
    {
        $this->backup('trr', now()->subHours(8)->toDateTimeString());

        $r = $this->resumo();

        $this->assertSame(1, $r['ok']);
        $this->assertSame(0, $r['atrasados']);
        $this->assertSame(0, $r['inativos']);
    }

    public function test_parou_ontem_e_atraso_e_precisa_aparecer(): void
    {
        // Este é o alarme que importa: alguém precisa olhar hoje.
        $this->backup('trr', now()->subHours(40)->toDateTimeString());

        $r = $this->resumo();

        $this->assertSame(1, $r['atrasados']);
        $this->assertSame(0, $r['inativos']);
    }

    public function test_parou_ha_um_ano_e_descontinuado_e_sai_da_contagem(): void
    {
        $this->backup('/banco/trr/base/trr', now()->subYear()->toDateTimeString());

        $r = $this->resumo();

        $this->assertSame(1, $r['inativos']);
        $this->assertSame(0, $r['atrasados'], 'não pode contar como atraso');
        $this->assertSame(0, $r['pares'], 'nem entrar no total de monitorados');
    }

    public function test_identificador_antigo_nao_polui_o_banco_que_continua_ativo(): void
    {
        /*
         | O caso real: os dois existem na base, mas só o nome curto reporta.
         | Sem a separação, o mesmo banco aparecia duas vezes — uma saudável e
         | uma "atrasada há um ano".
         */
        $this->backup('trr', now()->subHours(8)->toDateTimeString());
        $this->backup('/banco/trr/base/trr', now()->subYear()->toDateTimeString());

        $r = $this->resumo();

        $this->assertSame(1, $r['pares']);
        $this->assertSame(1, $r['ok']);
        $this->assertSame(0, $r['atrasados']);
        $this->assertSame(1, $r['inativos']);
    }

    public function test_o_filtro_mostra_os_descontinuados_quando_pedido(): void
    {
        // Saem da contagem, mas continuam consultáveis.
        $this->backup('/banco/trr/base/trr', now()->subYear()->toDateTimeString());

        $pares = $this->actingAs($this->usuario())
            ->get(route('cmdb.database-backups.index', ['situacao' => 'inativos']))
            ->assertOk()
            ->viewData('pares');

        $this->assertCount(1, $pares);
        $this->assertSame('/banco/trr/base/trr', $pares->first()->database_name);
    }

    public function test_a_tela_distingue_jira_desligado_de_jira_sem_configurar(): void
    {
        /*
         | A tela mandava "preencha JIRA_ENABLED, JIRA_BASE_URL..." mesmo com
         | tudo preenchido, quando o caso era a abertura automática estar
         | desligada de propósito.
         */
        config([
            'services.jira.enabled' => true,
            'services.jira.base_url' => 'https://exemplo.atlassian.net',
            'services.jira.email' => 'x@exemplo.com',
            'services.jira.api_token' => 'token',
            'backups.jira.project_id' => '10005',
            'backups.jira.issue_type_id' => '10008',
            'backups.jira.enabled' => false,
        ]);

        $servico = app(DatabaseBackupJiraService::class);

        $this->assertTrue($servico->configurado());
        $this->assertFalse($servico->habilitado());
        $this->assertSame('desligado', $servico->motivoDesligado());

        $this->actingAs($this->usuario())
            ->get(route('cmdb.database-backups.index'))
            ->assertOk()
            ->assertSee('Abertura automática de chamado desligada', false)
            ->assertDontSee('Jira não configurado no CoreTI', false);
    }

    public function test_sem_credencial_a_tela_pede_configuracao(): void
    {
        config(['services.jira.enabled' => false]);

        $this->assertSame('nao_configurado', app(DatabaseBackupJiraService::class)->motivoDesligado());

        $this->actingAs($this->usuario())
            ->get(route('cmdb.database-backups.index'))
            ->assertOk()
            ->assertSee('Jira não configurado no CoreTI', false);
    }
}
