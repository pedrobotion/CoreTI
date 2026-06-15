<?php

namespace Tests\Feature;

use App\Models\CircuitUnit;
use App\Models\Unidade;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CircuitUnitManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_active_user_can_create_update_and_delete_circuit_unit(): void
    {
        $user = User::factory()->create([
            'is_active' => true,
        ]);

        $unidade = Unidade::create([
            'unidade' => 'Unidade Teste',
        ]);

        $createResponse = $this->actingAs($user)->post(route('circuits.units.store'), [
            'operadora' => 'Operadora A',
            'unidades_circuitos' => 'Unidade A',
            'uf' => 'PR',
            'servico' => 'Link Dedicado',
            'endereco' => 'Rua 1',
            'contato' => 'Contato A',
            'id_unidades' => $unidade->id_unidades,
        ]);

        $createResponse->assertRedirect(route('circuits.units'));
        $this->assertDatabaseHas('circuitos_unidades', [
            'operadora' => 'Operadora A',
            'uf' => 'PR',
        ]);

        $unit = CircuitUnit::query()->firstOrFail();

        $updateResponse = $this->actingAs($user)->put(route('circuits.units.update', $unit), [
            'operadora' => 'Operadora B',
            'unidades_circuitos' => 'Unidade B',
            'uf' => 'SC',
            'servico' => 'MPLS',
            'endereco' => 'Rua 2',
            'contato' => 'Contato B',
            'id_unidades' => $unidade->id_unidades,
        ]);

        $updateResponse->assertRedirect(route('circuits.units'));
        $this->assertDatabaseHas('circuitos_unidades', [
            'id_circuitos' => $unit->id_circuitos,
            'operadora' => 'Operadora B',
            'uf' => 'SC',
        ]);

        $deleteResponse = $this->actingAs($user)->delete(route('circuits.units.destroy', $unit));

        $deleteResponse->assertRedirect(route('circuits.units'));
        $this->assertDatabaseMissing('circuitos_unidades', [
            'id_circuitos' => $unit->id_circuitos,
        ]);
    }

    public function test_circuits_listing_accepts_structured_filters(): void
    {
        $user = User::factory()->create([
            'is_active' => true,
        ]);

        $unidadeA = Unidade::create(['unidade' => 'Matriz']);
        $unidadeB = Unidade::create(['unidade' => 'Filial']);

        CircuitUnit::create([
            'operadora' => 'Ligga',
            'unidades_circuitos' => 'Matriz',
            'uf' => 'PR',
            'servico' => 'Link Dedicado',
            'endereco' => 'End A',
            'contato' => 'Contato A',
            'id_unidades' => $unidadeA->id_unidades,
        ]);

        CircuitUnit::create([
            'operadora' => 'OI',
            'unidades_circuitos' => 'Filial',
            'uf' => 'SC',
            'servico' => 'MPLS',
            'endereco' => 'End B',
            'contato' => 'Contato B',
            'id_unidades' => $unidadeB->id_unidades,
        ]);

        $response = $this->actingAs($user)->get(route('circuits.units', [
            'operadora' => 'Ligga',
            'uf' => 'PR',
            'id_unidades' => $unidadeA->id_unidades,
            'per_page' => 10,
        ]));

        $response->assertOk();
        $response->assertSee('Ligga');
        $response->assertDontSee('OI');
    }
}
