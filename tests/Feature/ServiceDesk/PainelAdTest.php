<?php

namespace Tests\Feature\ServiceDesk;

use App\Models\AdUser;
use App\Models\User;
use App\Services\ModuleAccessService;
use App\Services\ServiceDeskAdDirectoryService;
use App\Support\LegacyModulePermissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PainelAdTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        LegacyModulePermissions::esquecerCache();
        config(['modules.legacy_admin_bypass' => false]);
    }

    private function conta(array $dados): AdUser
    {
        return AdUser::create(array_merge([
            'nome_usuario' => 'fulano',
            'nome_completo' => 'Fulano de Tal',
            'e_ativo' => true,
            'excluido' => false,
            'distinguished_name' => 'CN=x,OU=TI,OU=Sede,DC=cocari,DC=com,DC=br',
        ], $dados));
    }

    public function test_conta_excluida_do_dominio_nao_conta_no_painel(): void
    {
        /*
         | A linha de quem foi excluído é preservada para guardar o histórico
         | de quem teve acesso — mas ela não é uma conta viva. Sem o filtro, o
         | painel somava 1.869 contas apagadas às 1.784 reais.
         */
        $this->conta(['nome_usuario' => 'ativa']);
        $this->conta(['nome_usuario' => 'excluida', 'excluido' => true, 'e_ativo' => false]);

        $contas = app(ServiceDeskAdDirectoryService::class)->users();

        $this->assertCount(1, $contas);
        $this->assertSame('ativa', $contas->first()->nome_usuario);
    }

    public function test_desativada_mas_ainda_no_dominio_continua_aparecendo(): void
    {
        // Desativada é diferente de excluída: a conta existe e pode voltar.
        $this->conta(['nome_usuario' => 'desativada', 'e_ativo' => false]);

        $servico = app(ServiceDeskAdDirectoryService::class);
        $contas = $servico->users();

        $this->assertCount(1, $contas);
        $this->assertSame(1, $servico->summary($contas)['inativos']);
    }

    public function test_as_telas_de_ad_agora_pedem_o_modulo_servicedesk(): void
    {
        $usuario = User::factory()->create(['id' => 9601, 'role' => 'user', 'is_active' => true]);
        app(ModuleAccessService::class)->sync($usuario, ['servicedesk' => ['view']]);

        $this->actingAs($usuario->fresh())
            ->get(route('service-desk.ad.cadastro-incompleto.index'))
            ->assertOk();

        $this->actingAs($usuario->fresh())
            ->get(route('service-desk.ad.centro-custo.index'))
            ->assertOk();
    }

    public function test_exclusao_de_contas_continua_exigindo_o_modulo_proprio(): void
    {
        // Operação destrutiva: não vem junto com o ServiceDesk.
        $usuario = User::factory()->create(['id' => 9602, 'role' => 'user', 'is_active' => true]);
        app(ModuleAccessService::class)->sync($usuario, ['servicedesk' => ['manage']]);

        $this->actingAs($usuario->fresh())
            ->get(route('ad.exclusao.index'))
            ->assertForbidden();
    }

    public function test_as_telas_ficam_dentro_do_contexto_do_servicedesk(): void
    {
        /*
         | O menu lateral do ServiceDesk só é montado quando a rota se chama
         | service-desk.* — é assim que o layout sabe em que seção você está.
         |
         | Enquanto as rotas se chamavam ad.*, abrir a tela derrubava o menu do
         | ServiceDesk e devolvia o menu principal: o link tinha mudado de
         | lugar, a página não.
         */
        $usuario = User::factory()->create(['id' => 9604, 'role' => 'user', 'is_active' => true]);
        app(ModuleAccessService::class)->sync($usuario, ['servicedesk' => ['view']]);

        foreach (['service-desk.ad.cadastro-incompleto.index', 'service-desk.ad.centro-custo.index'] as $rota) {
            $this->assertStringStartsWith('service-desk.ad.', $rota);

            $resposta = $this->actingAs($usuario->fresh())->get(route($rota));
            $resposta->assertOk();

            // O menu do ServiceDesk precisa estar montado na página.
            $resposta->assertSee('Voltar ao CoreTI');
            $resposta->assertSee('Active Directory');
        }
    }

    public function test_escrita_nas_telas_de_ad_exige_manage(): void
    {
        $this->conta(['nome_usuario' => 'balanca.teste', 'matricula' => null]);

        $somenteLeitura = User::factory()->create(['id' => 9603, 'role' => 'user', 'is_active' => true]);
        app(ModuleAccessService::class)->sync($somenteLeitura, ['servicedesk' => ['view']]);

        $this->actingAs($somenteLeitura->fresh())
            ->post(route('service-desk.ad.cadastro-incompleto.nao-colaborador.store'), ['nome_usuario' => 'balanca.teste'])
            ->assertForbidden();
    }
}
