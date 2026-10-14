<?php

namespace Tests\Feature\Administrativo;

use App\Models\InternalStockMovement;
use App\Models\InternalStockProduct;
use App\Models\InternalStockUnit;
use App\Models\User;
use App\Services\Estoque\InternalStockService;
use App\Services\ModuleAccessService;
use App\Support\LegacyModulePermissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EstoqueInternoTest extends TestCase
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

    public function test_cadastra_produto_serial_e_da_entrada_de_uma_unidade(): void
    {
        $gestor = $this->gestor();

        $this->actingAs($gestor)->post(route('administrativo.estoque-interno.produtos.store'), [
            'categoria' => 'Monitor', 'nome' => 'Monitor Dell 24', 'controle' => 'serial', 'quantidade_minima' => 1,
        ])->assertSessionHasNoErrors();

        $produto = InternalStockProduct::firstWhere('nome', 'Monitor Dell 24');
        $this->assertSame('serial', $produto->controle);

        $this->actingAs($gestor)->post(route('administrativo.estoque-interno.entrada.store', $produto), [
            'serial_number' => 'ABC123', 'plaqueta' => '99999', 'modelo' => 'P2419H', 'estado' => 'Usado',
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('internal_stock_units', ['serial_number' => 'ABC123', 'status' => 'em_estoque']);
        $this->assertSame(1, $produto->fresh()->saldo);
        $this->assertDatabaseHas('internal_stock_movements', ['tipo' => 'entrada', 'quantidade' => 1, 'saldo_apos' => 1]);
    }

    public function test_entrada_e_baixa_por_quantidade_ajustam_o_saldo(): void
    {
        $gestor = $this->gestor();
        $ssd = InternalStockProduct::create(['categoria' => 'SSD', 'nome' => 'SSD 480GB', 'controle' => 'quantidade', 'quantidade_minima' => 3]);

        $this->actingAs($gestor)->post(route('administrativo.estoque-interno.entrada.store', $ssd), ['quantidade' => 10])->assertSessionHasNoErrors();
        $this->assertSame(10, $ssd->fresh()->saldo);

        $this->actingAs($gestor)->post(route('administrativo.estoque-interno.baixa.store', $ssd), [
            'quantidade' => 4, 'motivo' => 'entrega_colaborador', 'destino_tipo' => 'colaborador', 'destino_descricao' => 'Fulano',
        ])->assertSessionHasNoErrors();

        $this->assertSame(6, $ssd->fresh()->saldo);
        $this->assertDatabaseHas('internal_stock_movements', ['tipo' => 'baixa', 'quantidade' => 4, 'saldo_apos' => 6, 'destino_descricao' => 'Fulano']);
    }

    public function test_baixa_por_quantidade_recusa_sem_saldo(): void
    {
        $gestor = $this->gestor();
        $ssd = InternalStockProduct::create(['categoria' => 'SSD', 'nome' => 'SSD 240GB', 'controle' => 'quantidade', 'quantidade_atual' => 2]);

        $this->actingAs($gestor)->post(route('administrativo.estoque-interno.baixa.store', $ssd), [
            'quantidade' => 5, 'motivo' => 'entrega_colaborador',
        ])->assertSessionHasErrors('quantidade');

        $this->assertSame(2, $ssd->fresh()->saldo);
    }

    public function test_baixa_serial_marca_a_unidade_como_entregue(): void
    {
        $gestor = $this->gestor();
        $produto = InternalStockProduct::create(['categoria' => 'Computador', 'nome' => 'Desktop i5', 'controle' => 'serial']);
        $unit = $produto->units()->create(['serial_number' => 'PC1', 'estado' => 'Usado', 'status' => 'em_estoque']);

        $this->actingAs($gestor)->post(route('administrativo.estoque-interno.baixa.store', $produto), [
            'internal_stock_unit_id' => $unit->id, 'motivo' => 'entrega_colaborador', 'destino_descricao' => 'TI',
        ])->assertSessionHasNoErrors();

        $this->assertSame('entregue', $unit->fresh()->status);
        $this->assertSame(0, $produto->fresh()->saldo);
    }

    public function test_alerta_de_abaixo_do_minimo_aparece_no_painel(): void
    {
        $gestor = $this->gestor();
        $ssd = InternalStockProduct::create(['categoria' => 'SSD', 'nome' => 'SSD baixo', 'controle' => 'quantidade', 'quantidade_atual' => 1, 'quantidade_minima' => 5]);

        $this->assertTrue($ssd->abaixo_do_minimo);
        $this->actingAs($gestor)->get(route('administrativo.estoque-interno'))
            ->assertOk()
            ->assertSee('SSD baixo');
    }

    public function test_consumo_da_bancada_gera_baixa_quando_o_produto_casa(): void
    {
        $ssd = InternalStockProduct::create(['categoria' => 'SSD', 'nome' => 'SSD 480GB', 'controle' => 'quantidade', 'quantidade_atual' => 5]);

        $baixou = app(InternalStockService::class)->registrarConsumoBancada('ssd 480gb', 2, 'bancada#1', null);

        $this->assertTrue($baixou);
        $this->assertSame(3, $ssd->fresh()->saldo);
        $this->assertDatabaseHas('internal_stock_movements', ['tipo' => 'baixa', 'motivo' => 'uso_bancada', 'quantidade' => 2]);
    }
}
