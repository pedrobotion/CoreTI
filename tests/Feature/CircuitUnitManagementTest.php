<?php

namespace Tests\Feature;

use App\Models\CircuitUnit;
use App\Models\OrganizationUnit;
use App\Models\User;
use App\Services\ModuleAccessService;
use App\Support\LegacyModulePermissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Cadastro de circuitos por unidade.
 *
 * Este teste nasceu antes de duas mudanças que o deixaram falso:
 *
 *  - as rotas passaram a exigir o módulo "unidades", e o usuário criado aqui
 *    não tinha permissão nenhuma — tudo respondia 403;
 *  - a unidade migrou da tabela legada "unidades" para organization_units, e
 *    a operadora virou lista cadastrada em vez de texto livre.
 *
 * A UF também deixou de ser informada: o controller a grava vazia de
 * propósito, porque a unidade já responde por onde o circuito está.
 */
class CircuitUnitManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        LegacyModulePermissions::esquecerCache();
    }

    private function usuario(): User
    {
        $user = User::factory()->create(['id' => 9601, 'is_active' => true]);
        app(ModuleAccessService::class)->sync($user, ['unidades' => ['view', 'manage']]);

        return $user->fresh();
    }

    private function unidade(string $nome, string $codigo): OrganizationUnit
    {
        return OrganizationUnit::create([
            'source_system' => 'intranet',
            'source_id' => $codigo,
            'code' => $codigo,
            'name' => $nome,
            'active' => true,
        ]);
    }

    public function test_authenticated_active_user_can_create_update_and_delete_circuit_unit(): void
    {
        $user = $this->usuario();
        $unidade = $this->unidade('Unidade Teste', '01');
        $outra = $this->unidade('Outra Unidade', '02');

        // "Ligga" e "Oi" saem da lista padrão de operadoras do controller.
        $createResponse = $this->actingAs($user)->post(route('circuits.units.store'), [
            'operadora' => 'Ligga',
            'servico' => 'Link Dedicado',
            'endereco' => 'Rua 1',
            'contato' => 'Contato A',
            'organization_unit_id' => $unidade->id,
        ]);

        $createResponse->assertRedirect(route('circuits.units'));
        $this->assertDatabaseHas('circuitos_unidades', [
            'operadora' => 'Ligga',
            'organization_unit_id' => $unidade->id,
            // O nome da unidade é derivado, não informado pelo formulário.
            'unidades_circuitos' => 'Unidade Teste',
        ]);

        $unit = CircuitUnit::query()->firstOrFail();

        $updateResponse = $this->actingAs($user)->put(route('circuits.units.update', $unit), [
            'operadora' => 'Oi',
            'servico' => 'MPLS',
            'endereco' => 'Rua 2',
            'contato' => 'Contato B',
            'organization_unit_id' => $outra->id,
        ]);

        $updateResponse->assertRedirect(route('circuits.units'));
        $this->assertDatabaseHas('circuitos_unidades', [
            'id_circuitos' => $unit->id_circuitos,
            'operadora' => 'Oi',
            'organization_unit_id' => $outra->id,
            'unidades_circuitos' => 'Outra Unidade',
        ]);

        $deleteResponse = $this->actingAs($user)->delete(route('circuits.units.destroy', $unit));

        $deleteResponse->assertRedirect(route('circuits.units'));
        $this->assertDatabaseMissing('circuitos_unidades', [
            'id_circuitos' => $unit->id_circuitos,
        ]);
    }

    public function test_operadora_fora_da_lista_e_recusada(): void
    {
        // Texto livre deixou de valer: a operadora precisa estar cadastrada,
        // senão a listagem ganha variações do mesmo nome e o filtro racha.
        $unidade = $this->unidade('Unidade Teste', '01');

        $this->actingAs($this->usuario())
            ->post(route('circuits.units.store'), [
                'operadora' => 'Operadora Inventada',
                'servico' => 'Link Dedicado',
                'endereco' => 'Rua 1',
                'contato' => 'Contato A',
                'organization_unit_id' => $unidade->id,
            ])
            ->assertSessionHasErrors('operadora');

        $this->assertDatabaseCount('circuitos_unidades', 0);
    }

    public function test_as_rotas_exigem_o_modulo_unidades(): void
    {
        $semAcesso = User::factory()->create(['id' => 9602, 'is_active' => true]);

        $this->actingAs($semAcesso->fresh())
            ->get(route('circuits.units'))
            ->assertForbidden();
    }

    public function test_circuits_listing_accepts_structured_filters(): void
    {
        $user = $this->usuario();
        $unidadeA = $this->unidade('Matriz', '01');
        $unidadeB = $this->unidade('Filial', '02');

        CircuitUnit::create([
            'operadora' => 'Ligga',
            'unidades_circuitos' => 'Matriz',
            'uf' => '',
            'servico' => 'Link Dedicado',
            'endereco' => 'End A',
            'contato' => 'Contato A',
            'organization_unit_id' => $unidadeA->id,
        ]);

        CircuitUnit::create([
            'operadora' => 'Oi',
            'unidades_circuitos' => 'Filial',
            'uf' => '',
            'servico' => 'MPLS',
            'endereco' => 'End B',
            'contato' => 'Contato B',
            'organization_unit_id' => $unidadeB->id,
        ]);

        $response = $this->actingAs($user)->get(route('circuits.units', [
            'operadora' => 'Ligga',
            'organization_unit_id' => $unidadeA->id,
            'per_page' => 10,
        ]));

        $response->assertOk();
        $response->assertSee('Ligga');
        $response->assertDontSee('MPLS');
    }
}
