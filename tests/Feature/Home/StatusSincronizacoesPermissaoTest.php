<?php

namespace Tests\Feature\Home;

use App\Models\User;
use App\Services\ModuleAccessService;
use App\Services\SyncStatusService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * O painel de sincronizações respeita a permissão de módulo.
 *
 * Cada fonte é vinculada ao módulo que já protege a tela correspondente:
 * quem só tem ServiceDesk não precisa saber do estado do vCenter.
 */
class StatusSincronizacoesPermissaoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Sem o bypass, todo acesso é explícito — que é o que queremos testar.
        config(['modules.legacy_admin_bypass' => false]);

        $this->semearOrigens();
    }

    /** Dados mínimos para que todas as fontes existam. */
    private function semearOrigens(): void
    {
        DB::table('cmdb_connectors')->insert([
            'id' => 1, 'name' => 'vCenter', 'connector_type' => 'vcenter',
            'host' => 'https://vcenter.local', 'is_active' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('cmdb_sync_logs')->insert([
            'connector_id' => 1, 'status' => 'success',
            'started_at' => now(), 'finished_at' => now(),
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('organization_sync_batches')->insert([
            'source_system' => 'intranet', 'sync_type' => 'full', 'status' => 'success',
            'started_at' => now(), 'finished_at' => now(),
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('ad_users')->insert(['nome_usuario' => 'x', 'last_sync' => now()]);
        DB::table('hr_collaborators')->insert([
            'source_system' => 'intranet', 'source_id' => '1', 'name' => 'Fulano',
            'last_synced_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('coreti_google_emails')->insert([
            'email' => 'a@b.com', 'importado_em' => now(),
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('office_licenses')->insert([
            'nome' => 'Fulano', 'email' => 'fulano@teste.com',
            'departamento_unidade' => 'TI',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('cmdb_database_backups')->insert([
            'host' => 'srv', 'database_name' => 'db', 'status' => 'OK', 'sucesso' => 1,
            'fingerprint' => hash('sha256', 'x'), 'received_at' => now(),
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    /**
     * A conta master é identificada por ID fixo (User::MASTER_USER_ID = 2) e
     * ignora qualquer permissão. Como o auto-increment não zera entre testes,
     * um usuário comum podia cair nesse id e passar a enxergar tudo — por isso
     * o id é forçado para fora da faixa.
     */
    private function usuarioCom(array $modulos): User
    {
        $user = User::factory()->create([
            'id' => 9000 + User::count(),
            'role' => 'user',
            'is_active' => true,
        ]);

        if ($modulos !== []) {
            app(ModuleAccessService::class)->sync(
                $user,
                collect($modulos)->mapWithKeys(fn ($m) => [$m => ['view']])->all()
            );
        }

        return $user->fresh();
    }

    /** @return array<int, string> */
    private function chavesVisiveis(User $user): array
    {
        return app(SyncStatusService::class)->fontes($user)->pluck('chave')->sort()->values()->all();
    }

    public function test_todas_as_fontes_existem_independente_de_permissao(): void
    {
        $this->assertCount(7, app(SyncStatusService::class)->todasAsFontes());
    }

    public function test_service_desk_ve_apenas_ad_emails_e_office(): void
    {
        $visiveis = $this->chavesVisiveis($this->usuarioCom(['servicedesk']));

        $this->assertSame(['ad', 'google', 'office'], $visiveis);
        $this->assertNotContains('vcenter', $visiveis);
        $this->assertNotContains('backups', $visiveis);
    }

    public function test_cmdb_ve_vcenter_e_backups(): void
    {
        $this->assertSame(['backups', 'vcenter'], $this->chavesVisiveis($this->usuarioCom(['cmdb'])));
    }

    public function test_network_datacenter_tambem_ve_o_cmdb(): void
    {
        // A rota do CMDB aceita os dois módulos; o painel espelha isso.
        $this->assertSame(['backups', 'vcenter'], $this->chavesVisiveis($this->usuarioCom(['network_datacenter'])));
    }

    public function test_coordenacao_ve_organizacao_e_rh(): void
    {
        $this->assertSame(['colaboradores', 'organizacao'], $this->chavesVisiveis($this->usuarioCom(['coordenacao'])));
    }

    public function test_modulo_sem_sincronizacao_nao_ve_nada(): void
    {
        $this->assertSame([], $this->chavesVisiveis($this->usuarioCom(['unidades'])));
    }

    public function test_usuario_sem_modulo_nao_ve_nada(): void
    {
        $this->assertSame([], $this->chavesVisiveis($this->usuarioCom([])));
    }

    public function test_sem_usuario_a_lista_vem_vazia(): void
    {
        $this->assertCount(0, app(SyncStatusService::class)->fontes(null));
    }

    public function test_soma_de_modulos_soma_as_fontes(): void
    {
        $visiveis = $this->chavesVisiveis($this->usuarioCom(['servicedesk', 'cmdb']));

        $this->assertSame(['ad', 'backups', 'google', 'office', 'vcenter'], $visiveis);
    }

    public function test_master_enxerga_todas(): void
    {
        // Conta master: acesso total, sem nenhuma permissão explícita.
        $master = User::factory()->create([
            'id' => User::MASTER_USER_ID,
            'role' => 'user',
            'is_active' => true,
        ]);

        $this->assertCount(7, app(SyncStatusService::class)->fontes($master));
    }

    public function test_tela_do_service_desk_nao_mostra_vcenter(): void
    {
        $resposta = $this->actingAs($this->usuarioCom(['servicedesk']))->get(route('home'));

        $resposta->assertOk();
        $resposta->assertSee('Sincronização Active Directory');
        $resposta->assertDontSee('Sincronização vCenter');
        $resposta->assertDontSee('Auditoria de Backups');
    }

    public function test_tela_avisa_quando_nao_ha_fonte_visivel(): void
    {
        $resposta = $this->actingAs($this->usuarioCom(['unidades']))->get(route('home'));

        $resposta->assertOk();
        $resposta->assertSee('Nenhuma sincronização disponível para os seus acessos.');
    }
}
