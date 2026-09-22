<?php

namespace Tests\Feature\Cmdb;

use App\Models\CmdbAsset;
use App\Models\User;
use App\Services\CmdbQuickCreateService;
use App\Services\ModuleAccessService;
use App\Support\LegacyModulePermissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Salvar a edição de um ativo dava 500.
 *
 * O closure da transação não capturava $request, mas chamava $request->user()
 * para registrar quem alterou. Toda edição de máquina quebrava — e o histórico
 * é exatamente o que se perde quando ninguém consegue salvar.
 */
class AtivoEdicaoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        LegacyModulePermissions::esquecerCache();
    }

    private function operador(): User
    {
        $user = User::factory()->create(['id' => 9201, 'role' => 'user', 'is_active' => true]);
        app(ModuleAccessService::class)->sync($user, ['cmdb' => ['manage']]);

        return $user->fresh();
    }

    public function test_edicao_de_ativo_salva_e_registra_o_historico(): void
    {
        $ativo = CmdbAsset::create([
            'name' => 'maquina-antiga',
            'hostname' => 'maquina-antiga.cocari.com.br',
            'asset_type' => 'virtual_machine',
            'status' => 'active',
        ]);

        $operador = $this->operador();

        $this->actingAs($operador)
            ->put(route('cmdb.assets.update', $ativo), [
                'name' => 'maquina-nova',
                'asset_type' => 'virtual_machine',
                'status' => 'active',
                'criticality' => 'medium',
                'environment' => 'production',
                'hostname' => 'maquina-nova.cocari.com.br',
            ])
            ->assertRedirect(route('cmdb.assets.show', $ativo));

        // Para VM e servidor o nome é derivado do hostname (HOSTNAME_DRIVEN_TYPES).
        $this->assertSame('maquina-nova.cocari.com.br', $ativo->fresh()->hostname);
        $this->assertSame('maquina-nova.cocari.com.br', $ativo->fresh()->name);

        // O histórico é o motivo de $request estar no closure.
        $this->assertDatabaseHas('cmdb_asset_histories', [
            'asset_id' => $ativo->id,
            'user_id' => $operador->id,
            'event_type' => 'updated',
        ]);
    }

    public function test_cadastro_rapido_usa_a_mensagem_de_historico_recebida(): void
    {
        // Mesmo defeito: $historyMessage não era capturado pelo closure.
        $operador = $this->operador();

        $ativo = app(CmdbQuickCreateService::class)->create(
            ['name' => 'servidor-rapido', 'asset_type' => 'server', 'status' => 'active'],
            $operador,
            'Criado no teste'
        );

        $this->assertDatabaseHas('cmdb_asset_histories', [
            'asset_id' => $ativo->id,
            'title' => 'Criado no teste',
        ]);
    }
}
