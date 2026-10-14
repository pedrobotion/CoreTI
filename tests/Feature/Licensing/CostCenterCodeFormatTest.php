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

        // "1.226" normaliza para "01.226"; mesmo código + mesmo nome é duplicado.
        $this->actingAs($this->gestor())
            ->post(route('administrativo.cost-centers.store'), ['code' => '1.226', 'name' => 'Ouvidoria'])
            ->assertSessionHasErrors('name');

        $this->assertSame(1, CoretiCostCenter::where('code', '01.226')->count());
    }

    public function test_permite_varias_unidades_com_o_mesmo_codigo(): void
    {
        // As lojas Colari compartilham o código 01.316 — todas precisam entrar.
        $gestor = $this->gestor();
        foreach (['Colari Loja 1', 'Colari Loja 2', 'Colari Loja 3'] as $nome) {
            $this->actingAs($gestor)
                ->post(route('administrativo.cost-centers.store'), ['code' => '01.316', 'name' => $nome])
                ->assertSessionHasNoErrors();
        }

        $this->assertSame(3, CoretiCostCenter::where('code', '01.316')->count());
    }

    public function test_aceita_cnpj_como_codigo(): void
    {
        /*
         | A Rodocoop é empresa afiliada: o campo "Escritório" no AD guarda o
         | CNPJ dela em vez de um centro de custo nosso, e a regra de OU já
         | devolve esse CNPJ. Exigir só unicoop.área impedia dar nome a ele no
         | catálogo — o código aparecia em "usados no AD sem nome cadastrado"
         | e não havia como sair de lá.
         */
        $this->actingAs($this->gestor())
            ->post(route('administrativo.cost-centers.store'), [
                'code' => '07.881.528/0001-66',
                'name' => 'Rodocoop',
            ])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('coreti_cost_centers', [
            'code' => '07.881.528/0001-66',
            'name' => 'Rodocoop',
        ]);
    }

    public function test_cnpj_nao_e_deformado_pela_normalizacao(): void
    {
        // A normalização de unicoop.área não pode encostar num CNPJ.
        $this->actingAs($this->gestor())
            ->post(route('administrativo.cost-centers.store'), [
                'code' => '  07.881.528/0001-66  ',
                'name' => 'Rodocoop',
            ])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('coreti_cost_centers', ['code' => '07.881.528/0001-66']);
    }

    public function test_cnpj_incompleto_continua_sendo_recusado(): void
    {
        $this->actingAs($this->gestor())
            ->post(route('administrativo.cost-centers.store'), [
                'code' => '07.881.528/0001',
                'name' => 'Rodocoop',
            ])
            ->assertSessionHasErrors('code');

        $this->assertDatabaseCount('coreti_cost_centers', 0);
    }

    public function test_recadastra_par_codigo_nome_que_foi_excluido(): void
    {
        /*
         | Soft delete não libera o índice único do banco. Recadastrar o mesmo
         | par (código + nome) que foi removido reaproveita a linha em vez de
         | estourar 500 no INSERT.
         */
        $center = CoretiCostCenter::create(['code' => '01.291', 'name' => 'Segurança do Trabalho', 'active' => true]);
        $center->delete();

        $this->actingAs($this->gestor())
            ->post(route('administrativo.cost-centers.store'), ['code' => '01.291', 'name' => 'Segurança do Trabalho'])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('coreti_cost_centers', [
            'id' => $center->id,
            'code' => '01.291',
            'name' => 'Segurança do Trabalho',
            'deleted_at' => null,
        ]);
        $this->assertSame(1, CoretiCostCenter::withTrashed()->where('code', '01.291')->where('name', 'Segurança do Trabalho')->count());
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
