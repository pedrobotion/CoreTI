<?php

namespace Tests\Feature\Administrativo;

use App\Models\User;
use App\Services\ModuleAccessService;
use App\Support\LegacyModulePermissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Conceder o módulo Administrativo precisa bastar.
 *
 * As rotas exigiam ['admin', 'module_access:administrativo'] — as duas coisas.
 * Quem recebia o módulo sem ser role=admin via 403, e o menu escondia o link
 * para não oferecer porta fechada. Isso esvaziava o sentido da permissão por
 * módulo: a tela de acessos dizia que a pessoa tinha, e ela não tinha.
 */
class AcessoPorModuloTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        LegacyModulePermissions::esquecerCache();

        // Sem isso, role=admin ignoraria as concessões e o teste passaria
        // sem exercer a regra.
        config(['modules.legacy_admin_bypass' => false]);
    }

    private function usuario(int $id, array $habilidades): User
    {
        $user = User::factory()->create(['id' => $id, 'role' => 'user', 'is_active' => true]);
        app(ModuleAccessService::class)->sync($user, ['administrativo' => $habilidades]);

        return $user->fresh();
    }

    public function test_quem_tem_o_modulo_abre_o_painel_sem_ser_admin(): void
    {
        $this->actingAs($this->usuario(9501, ['view']))
            ->get('/administrativo')
            ->assertOk();
    }

    public function test_quem_tem_so_view_nao_escreve(): void
    {
        // A leitura abriu; a escrita continua atrás de 'manage'.
        $this->actingAs($this->usuario(9502, ['view']))
            ->post(route('administrativo.cost-centers.store'), ['code' => '99.999', 'name' => 'Teste'])
            ->assertForbidden();

        $this->assertDatabaseMissing('coreti_cost_centers', ['code' => '99.999']);
    }

    public function test_quem_tem_manage_escreve(): void
    {
        $this->actingAs($this->usuario(9503, ['manage']))
            ->post(route('administrativo.cost-centers.store'), ['code' => '99.999', 'name' => 'Teste'])
            ->assertRedirect();

        $this->assertDatabaseHas('coreti_cost_centers', ['code' => '99.999']);
    }

    public function test_governanca_e_unidade_digital_nao_vem_junto_com_administrativo(): void
    {
        /*
         | Não pertencem ao módulo Administrativo, e a Governança vai virar
         | módulo próprio. Entrar junto com aquela permissão daria acesso a
         | quem nunca foi autorizado a ver isso.
         */
        $usuario = $this->usuario(9505, ['manage']);

        $this->actingAs($usuario)->get('/administrativo')->assertOk();

        $this->actingAs($usuario)->get('/governanca')->assertForbidden();
        $this->actingAs($usuario)->get('/unidade-digital')->assertForbidden();
        $this->actingAs($usuario)->get('/indicadores')->assertForbidden();
        $this->actingAs($usuario)->get('/monitoramento')->assertForbidden();
    }

    public function test_administrador_continua_vendo_governanca(): void
    {
        // Até o módulo próprio existir, o acesso é de administrador.
        $admin = User::factory()->create(['id' => 9506, 'role' => 'admin', 'is_active' => true]);

        $this->actingAs($admin)->get('/governanca')->assertOk();
        $this->actingAs($admin)->get('/unidade-digital')->assertOk();
    }

    public function test_quem_nao_tem_o_modulo_continua_barrado(): void
    {
        $outro = User::factory()->create(['id' => 9504, 'role' => 'user', 'is_active' => true]);
        app(ModuleAccessService::class)->sync($outro, ['servicedesk' => ['view']]);

        $this->actingAs($outro->fresh())
            ->get('/administrativo')
            ->assertForbidden();
    }
}
