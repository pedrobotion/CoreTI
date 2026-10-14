<?php

namespace Tests\Feature\Bancada;

use App\Models\CoretiCostCenter;
use App\Models\User;
use App\Services\ModuleAccessService;
use App\Support\LegacyModulePermissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A unidade da bancada vem do mesmo catálogo do ServiceDesk
 * (coreti_cost_centers). Mostramos o nome; gravamos o código.
 */
class UnidadeDoCatalogoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        LegacyModulePermissions::esquecerCache();

        CoretiCostCenter::create(['code' => '01.999', 'name' => 'Setor Teste TI', 'active' => true]);
        CoretiCostCenter::create(['code' => '99.154', 'name' => 'Unidade Teste CD', 'active' => true]);
    }

    private function tecnico(): User
    {
        $user = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        app(ModuleAccessService::class)->sync($user, ['bancada' => ['view', 'manage']]);

        return $user->fresh();
    }

    public function test_cadastra_com_unidade_do_catalogo_e_grava_o_codigo(): void
    {
        $this->actingAs($this->tecnico())
            ->post(route('bancada-servicos.assets.store'), [
                'tipo_equipamento' => 'Notebook',
                'plaqueta' => 'BAN-001',
                'data_chegada' => now()->toDateString(),
                'origem_tipo' => 'sede',
                'unidade_setor' => 'Setor Teste TI',
            ])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('bancada_equipments', [
            'plaqueta' => 'BAN-001',
            'unidade_setor' => 'Setor Teste TI',
            'cost_center_code' => '01.999',
        ]);
    }

    public function test_unidade_fora_do_catalogo_e_recusada(): void
    {
        $resposta = $this->actingAs($this->tecnico())
            ->post(route('bancada-servicos.assets.store'), [
                'tipo_equipamento' => 'Notebook',
                'plaqueta' => 'BAN-002',
                'data_chegada' => now()->toDateString(),
                'origem_tipo' => 'sede',
                'unidade_setor' => 'Unidade Que Nao Existe',
            ]);

        $resposta->assertStatus(422);
        $this->assertDatabaseMissing('bancada_equipments', ['plaqueta' => 'BAN-002']);
    }

    public function test_codigo_respeita_a_origem(): void
    {
        $this->actingAs($this->tecnico())
            ->post(route('bancada-servicos.assets.store'), [
                'tipo_equipamento' => 'Notebook',
                'plaqueta' => 'BAN-003',
                'data_chegada' => now()->toDateString(),
                'origem_tipo' => 'unidade',
                'unidade_setor' => 'Unidade Teste CD',
            ])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('bancada_equipments', [
            'plaqueta' => 'BAN-003',
            'cost_center_code' => '99.154',
        ]);
    }
}
