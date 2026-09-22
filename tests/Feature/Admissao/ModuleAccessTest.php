<?php

namespace Tests\Feature\Admissao;

use App\Models\User;
use App\Services\ModuleAccessService;
use App\Support\LegacyModulePermissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ModuleAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        LegacyModulePermissions::esquecerCache();
    }

    private function usuarioRh(): User
    {
        $user = User::factory()->create(['role' => 'user', 'is_active' => true]);

        app(ModuleAccessService::class)->sync($user, ['admissao' => ['request']]);

        return $user->fresh();
    }

    public function test_permissao_request_implica_view(): void
    {
        $user = $this->usuarioRh();

        $this->assertTrue($user->canModule('admissao', 'view'));
        $this->assertTrue($user->canModule('admissao', 'request'));
        $this->assertTrue($user->hasModuleAccess('admissao'));
    }

    public function test_usuario_do_rh_nao_alcanca_outros_modulos(): void
    {
        $user = $this->usuarioRh();

        $this->assertFalse($user->canModule('cmdb'));
        $this->assertFalse($user->canModule('servicedesk'));
        $this->assertFalse($user->canModule('coordenacao'));

        $this->actingAs($user)->get('/cmdb')->assertForbidden();
        $this->actingAs($user)->get('/service-desk')->assertForbidden();
    }

    public function test_usuario_do_rh_entra_na_admissao_mas_nao_administra(): void
    {
        $user = $this->usuarioRh();

        $this->actingAs($user)->get(route('admissao.cases.index'))->assertOk();
        $this->actingAs($user)->get(route('admissao.cases.create'))->assertOk();

        // 'manage' não foi concedido: catálogo de perfis fica fora do alcance.
        $this->actingAs($user)->get(route('admissao.profiles.index'))->assertForbidden();
    }

    public function test_manage_concede_todas_as_abilities_do_modulo(): void
    {
        $user = User::factory()->create(['role' => 'user', 'is_active' => true]);
        app(ModuleAccessService::class)->sync($user, ['admissao' => ['manage']]);
        $user->refresh();

        foreach (['view', 'request', 'execute', 'manage'] as $ability) {
            $this->assertTrue($user->canModule('admissao', $ability), "esperava {$ability}");
        }
    }

    public function test_sync_descarta_modulo_e_ability_desconhecidos(): void
    {
        $user = User::factory()->create(['role' => 'user', 'is_active' => true]);

        app(ModuleAccessService::class)->sync($user, [
            'admissao' => ['request', 'deploy'],
            'financeiro' => ['view'],
        ]);

        $this->assertEqualsCanonicalizing(
            ['request', 'view'],
            $user->fresh()->grantedAbilities('admissao')->all()
        );
        $this->assertSame([], $user->fresh()->grantedAbilities('financeiro')->all());
    }

    public function test_sync_revoga_o_que_nao_foi_reenviado(): void
    {
        $user = User::factory()->create(['role' => 'user', 'is_active' => true]);
        $service = app(ModuleAccessService::class);

        $service->sync($user, ['admissao' => ['manage'], 'cmdb' => ['view']]);
        $this->assertTrue($user->fresh()->canModule('cmdb'));

        $service->sync($user, ['admissao' => ['request']]);
        $user = $user->fresh();

        $this->assertFalse($user->canModule('cmdb'));
        $this->assertFalse($user->canModule('admissao', 'manage'));
        $this->assertTrue($user->canModule('admissao', 'request'));
    }

    public function test_sem_bypass_legado_o_admin_depende_de_concessao_explicita(): void
    {
        config(['modules.legacy_admin_bypass' => false]);

        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $this->assertFalse($admin->canModule('admissao'));

        app(ModuleAccessService::class)->sync($admin, ['admissao' => ['manage']]);
        $this->assertTrue($admin->fresh()->canModule('admissao', 'manage'));
    }

    public function test_com_bypass_legado_o_admin_mantem_acesso_total(): void
    {
        config(['modules.legacy_admin_bypass' => true]);

        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);

        $this->assertTrue($admin->canModule('admissao', 'manage'));
        $this->assertTrue($admin->canModule('cmdb'));
    }
}
