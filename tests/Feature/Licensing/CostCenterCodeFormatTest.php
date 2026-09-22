<?php

namespace Tests\Feature\Licensing;

use App\Models\CoretiCostCenter;
use App\Models\User;
use App\Services\ModuleAccessService;
use App\Support\LegacyModulePermissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * O código do centro de custo é chave de busca, não texto livre.
 *
 * Fora do formato "unicoop.área" o registro não quebra nada — ele some das
 * seleções de e-mail e licença, e quem cadastrou não fica sabendo.
 */
class CostCenterCodeFormatTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        LegacyModulePermissions::esquecerCache();
    }

    private function gestor(): User
    {
        $user = User::factory()->create(['role' => 'user', 'is_active' => true]);
        app(ModuleAccessService::class)->sync($user, ['administrativo' => ['view', 'manage']]);

        return $user->fresh();
    }

    public function test_cadastra_codigo_no_formato_esperado(): void
    {
        $this->actingAs($this->gestor())
            ->post(route('administrativo.cost-centers.store'), ['code' => '01.226', 'name' => 'Ouvidoria'])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('coreti_cost_centers', ['code' => '01.226', 'name' => 'Ouvidoria']);
    }

    public function test_recusa_codigo_e_nome_trocados(): void
    {
        // O erro real que motivou a regra: o cadastro entrava e sumia da tela.
        $this->actingAs($this->gestor())
            ->post(route('administrativo.cost-centers.store'), ['code' => 'Ouvidoria', 'name' => '01.226'])
            ->assertSessionHasErrors('code');

        $this->assertDatabaseCount('coreti_cost_centers', 0);
    }

    public function test_normaliza_o_codigo_para_dois_e_tres_digitos(): void
    {
        $this->actingAs($this->gestor())
            ->post(route('administrativo.cost-centers.store'), ['code' => '1/26', 'name' => 'Ouvidoria'])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('coreti_cost_centers', ['code' => '01.026']);
    }

    public function test_normalizacao_alcanca_a_checagem_de_duplicado(): void
    {
        CoretiCostCenter::create(['code' => '01.226', 'name' => 'Ouvidoria', 'active' => true]);

        // "1.226" é o mesmo centro de custo: não pode virar um segundo registro.
        $this->actingAs($this->gestor())
            ->post(route('administrativo.cost-centers.store'), ['code' => '1.226', 'name' => 'Ouvidoria Nova'])
            ->assertSessionHasErrors('code');

        $this->assertDatabaseCount('coreti_cost_centers', 1);
    }

    public function test_edicao_tambem_recusa_codigo_invalido(): void
    {
        $center = CoretiCostCenter::create(['code' => '01.226', 'name' => 'Ouvidoria', 'active' => true]);

        $this->actingAs($this->gestor())
            ->patch(route('administrativo.cost-centers.update', $center), ['code' => 'Ouvidoria', 'name' => '01.226'])
            ->assertSessionHasErrors('code');

        $this->assertDatabaseHas('coreti_cost_centers', ['id' => $center->id, 'code' => '01.226']);
    }
}
