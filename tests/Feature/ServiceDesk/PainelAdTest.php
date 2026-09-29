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

    /*
     | Qualidade cadastral conta PESSOA.
     |
     | Scanner, GED, conta modelo e conta de serviço não têm matrícula nem
     | cargo e nunca terão. Contadas como pendência, eram 966 dos 1.656
     | "pontos de atenção" do painel — quase dois terços de um número que não
     | baixaria nunca.
     */

    public function test_conta_que_nao_e_de_pessoa_fica_fora_da_qualidade_cadastral(): void
    {
        $this->conta(['nome_usuario' => 'joao.silva', 'matricula' => null, 'Cargo' => null]);
        $this->conta(['nome_usuario' => 'scanner.marialva', 'matricula' => null, 'Cargo' => null]);
        $this->conta(['nome_usuario' => 'ged.cambira', 'matricula' => null, 'Cargo' => null]);
        $this->conta(['nome_usuario' => 'user.ti.padrao', 'matricula' => null, 'Cargo' => null]);
        $this->conta(['nome_usuario' => 'svcbackup', 'matricula' => null, 'Cargo' => null]);

        $servico = app(ServiceDeskAdDirectoryService::class);
        $qualidade = $servico->dataQuality($servico->users());

        $this->assertSame(1, $qualidade['sem_matricula'], 'só a pessoa deveria contar');
        $this->assertSame(1, $qualidade['sem_cargo']);
        $this->assertSame(4, $qualidade['fora_da_contagem']);
    }

    public function test_as_contas_ignoradas_continuam_no_restante_do_painel(): void
    {
        // Elas existem no domínio e ocupam lugar na base: sumir do total
        // seria esconder contas reais, não é isso que se pediu.
        $this->conta(['nome_usuario' => 'joao.silva']);
        $this->conta(['nome_usuario' => 'scanner.marialva']);

        $servico = app(ServiceDeskAdDirectoryService::class);

        $this->assertSame(2, $servico->users()->count());
        $this->assertSame(2, $servico->summary($servico->users())['total']);
    }

    public function test_pessoa_com_cadastro_completo_nao_gera_ponto_de_atencao(): void
    {
        $this->conta([
            'nome_usuario' => 'maria.souza',
            'matricula' => '12345',
            'unidade_setor' => 'TI',
            'Cargo' => 'Analista',
            'centro_custo' => '01.150',
        ]);

        $servico = app(ServiceDeskAdDirectoryService::class);
        $qualidade = $servico->dataQuality($servico->users());

        $this->assertSame(0, $qualidade['sem_matricula']);
        $this->assertSame(0, $qualidade['sem_cargo']);
        $this->assertSame(0, $qualidade['sem_centro_custo']);
        $this->assertSame(0, $qualidade['fora_da_contagem']);
    }

    public function test_painel_e_relatorio_detalhado_usam_a_mesma_regra(): void
    {
        /*
         | As duas telas tinham listas separadas e já divergiam: o relatório
         | ignorava scanner.* e ged.*, o painel contava tudo. Agora ambas leem
         | config('ad.relatorio.prefixos_nao_pessoa').
         */
        foreach (['joao.silva', 'scanner.um', 'ged.dois', 'user.tres'] as $login) {
            $this->conta(['nome_usuario' => $login, 'matricula' => null, 'Cargo' => null]);
        }

        \App\Models\AdNonCollaboratorAccount::create(['nome_usuario' => 'balanca.um', 'motivo' => 'Balança']);
        $this->conta(['nome_usuario' => 'balanca.um', 'matricula' => null, 'Cargo' => null]);

        $servico = app(ServiceDeskAdDirectoryService::class);
        $painel = $servico->dataQuality($servico->users());
        $relatorio = app(\App\Services\Ad\AdCadastroIncompletoService::class);

        // Os quatro campos, não só um: a divergência anterior aparecia em
        // matrícula e não em cargo, e um teste de campo único não a pegava.
        foreach ([
            'sem_matricula' => 'matricula',
            'sem_unidade' => 'unidade_principal',
            'sem_cargo' => 'Cargo',
            'sem_centro_custo' => 'centro_custo',
        ] as $chave => $campo) {
            $this->assertSame(
                $relatorio->consulta([$campo])->count(),
                $painel[$chave],
                "painel e relatório divergiram em {$campo}"
            );
        }

        $this->assertSame(1, $painel['sem_matricula']);
    }

    public function test_conta_marcada_como_nao_colaborador_sai_da_qualidade(): void
    {
        $this->conta(['nome_usuario' => 'joao.silva', 'matricula' => null]);
        $this->conta(['nome_usuario' => 'guarita.sede', 'matricula' => null]);

        $servico = app(ServiceDeskAdDirectoryService::class);
        $this->assertSame(2, $servico->dataQuality($servico->users())['sem_matricula']);

        // A marcação é feita na tela de cadastro incompleto e precisa valer
        // aqui também — antes só o relatório a enxergava.
        \App\Models\AdNonCollaboratorAccount::create([
            'nome_usuario' => 'guarita.sede',
            'motivo' => 'Não é colaborador: Guarita Sede',
        ]);

        $servico = app(ServiceDeskAdDirectoryService::class);
        $this->assertSame(1, $servico->dataQuality($servico->users())['sem_matricula']);
    }

    public function test_ou_fora_de_escopo_sai_da_qualidade(): void
    {
        config(['ad.relatorio.ous_fora_de_escopo' => ['Terceiros']]);

        $this->conta(['nome_usuario' => 'joao.silva', 'matricula' => null]);
        $this->conta([
            'nome_usuario' => 'parceiro.um',
            'matricula' => null,
            'distinguished_name' => 'CN=x,OU=Usuários,OU=Terceiros,DC=cocari,DC=com,DC=br',
        ]);

        $servico = app(ServiceDeskAdDirectoryService::class);

        $this->assertSame(1, $servico->dataQuality($servico->users())['sem_matricula']);
    }

    public function test_campo_dispensado_nao_vira_pendencia_no_painel(): void
    {
        // A Rodocoop não tem matrícula nossa: em branco ali não é pendência.
        config(['ad.relatorio.campos_dispensados' => ['Rodocoop' => ['matricula']]]);

        $this->conta([
            'nome_usuario' => 'afiliado.um',
            'matricula' => null,
            'Cargo' => null,
            'distinguished_name' => 'CN=x,OU=Usuários,OU=Rodocoop,OU=Afiliados,DC=cocari,DC=com,DC=br',
        ]);

        $servico = app(ServiceDeskAdDirectoryService::class);
        $qualidade = $servico->dataQuality($servico->users());

        $this->assertSame(0, $qualidade['sem_matricula'], 'matrícula é dispensada nesta OU');
        $this->assertSame(1, $qualidade['sem_cargo'], 'cargo continua sendo cobrado');
    }

    public function test_prefixo_novo_no_config_vale_para_as_duas_telas(): void
    {
        config(['ad.relatorio.prefixos_nao_pessoa' => ['balanca.']]);

        $this->conta(['nome_usuario' => 'balanca.sede', 'matricula' => null]);
        $this->conta(['nome_usuario' => 'scanner.sede', 'matricula' => null]);

        $servico = app(ServiceDeskAdDirectoryService::class);

        // Só o prefixo configurado sai; scanner voltou a contar.
        $this->assertSame(1, $servico->dataQuality($servico->users())['sem_matricula']);
        $this->assertSame(1, app(\App\Services\Ad\AdCadastroIncompletoService::class)
            ->consulta(['matricula'])->count());
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
