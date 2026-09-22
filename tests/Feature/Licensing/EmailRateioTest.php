<?php

namespace Tests\Feature\Licensing;

use App\Models\CoretiCostCenter;
use App\Models\ServiceDeskEmail;
use App\Models\User;
use App\Services\ModuleAccessService;
use App\Support\LegacyModulePermissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Rateio de e-mail: a linha precisa ser identificável mesmo sem cadastro.
 *
 * Quem lança o custo trabalha em cima desta tela. Uma linha sem rótulo é uma
 * linha que ninguém consegue atribuir a lugar nenhum.
 */
class EmailRateioTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        LegacyModulePermissions::esquecerCache();
    }

    private function usuario(): User
    {
        $user = User::factory()->create(['role' => 'user', 'is_active' => true]);
        app(ModuleAccessService::class)->sync($user, ['administrativo' => ['view']]);

        return $user->fresh();
    }

    private function email(string $unicoop, string $area): ServiceDeskEmail
    {
        return ServiceDeskEmail::create([
            'scope' => 'sede',
            'email' => uniqid('conta', true) . '@cocari.com.br',
            'colaborador_nome' => 'Colaborador de Teste',
            'unicoop_sede' => $unicoop,
            'area_sede' => $area,
            'ativo' => true,
        ]);
    }

    public function test_usa_o_nome_do_catalogo_quando_o_centro_de_custo_existe(): void
    {
        CoretiCostCenter::create(['code' => '01.150', 'name' => 'Tecnologia da Informação', 'active' => true]);
        $this->email('01', '150');

        $this->actingAs($this->usuario())
            ->get(route('administrativo.licensing.email'))
            ->assertOk()
            ->assertSee('Tecnologia da Informação')
            ->assertDontSee('sem cadastro');
    }

    public function test_mostra_o_codigo_quando_o_centro_de_custo_nao_esta_cadastrado(): void
    {
        $this->email('01', '277');

        $this->actingAs($this->usuario())
            ->get(route('administrativo.licensing.email'))
            ->assertOk()
            // O código no lugar do traço: é ele que diz o que falta cadastrar.
            ->assertSee('01.277')
            ->assertSee('sem cadastro')
            ->assertSee('sem unidade cadastrada');
    }

    public function test_codigos_diferentes_nao_se_misturam_na_mesma_linha(): void
    {
        // Antes, tudo sem cadastro virava "-" e a tela dava a impressão de que
        // era um lugar só. São centros de custo distintos.
        $this->email('01', '277');
        $this->email('47', '003');

        $resposta = $this->actingAs($this->usuario())
            ->get(route('administrativo.licensing.email'))
            ->assertOk();

        $resposta->assertSee('01.277')->assertSee('47.003');
        $this->assertSame(2, $resposta->viewData('totals')['linhas_sem_cadastro']);
        $this->assertSame(2, $resposta->viewData('totals')['emails_sem_cadastro']);
    }

    public function test_conta_sem_unicoop_ou_area_nao_vira_codigo_vazio(): void
    {
        $this->email('', '');

        $this->actingAs($this->usuario())
            ->get(route('administrativo.licensing.email'))
            ->assertOk()
            ->assertSee('Sem unicoop/área', false);
    }

    public function test_centro_de_custo_inativo_nao_da_nome_a_linha(): void
    {
        // Catálogo inativo é catálogo que saiu de uso: a linha continua pendente.
        CoretiCostCenter::create(['code' => '01.277', 'name' => 'Marketing', 'active' => false]);
        $this->email('01', '277');

        $this->actingAs($this->usuario())
            ->get(route('administrativo.licensing.email'))
            ->assertOk()
            ->assertSee('01.277')
            ->assertDontSee('Marketing');
    }
}
