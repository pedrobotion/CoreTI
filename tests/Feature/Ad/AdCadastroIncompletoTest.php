<?php

namespace Tests\Feature\Ad;

use App\Models\AdUser;
use App\Models\User;
use App\Services\Ad\AdCadastroIncompletoService;
use App\Services\ModuleAccessService;
use App\Support\LegacyModulePermissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdCadastroIncompletoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        LegacyModulePermissions::esquecerCache();
    }

    private function conta(array $dados): AdUser
    {
        return AdUser::create(array_merge([
            'nome_usuario' => 'fulano',
            'nome_completo' => 'Fulano de Tal',
            'matricula' => '12345',
            'unidade_principal' => 'Sede',
            'unidade_setor' => 'TI',
            'Cargo' => 'Analista',
            'centro_custo' => '01.150',
            'e_ativo' => true,
            'excluido' => false,
        ], $dados));
    }

    private function servico(): AdCadastroIncompletoService
    {
        return app(AdCadastroIncompletoService::class);
    }

    public function test_conta_completa_nao_aparece(): void
    {
        $this->conta(['nome_usuario' => 'completo']);

        $this->assertSame(0, $this->servico()->consulta()->count());
    }

    public function test_encontra_por_campo_vazio_e_por_nulo(): void
    {
        // Vazio e nulo são a mesma pendência para quem vai preencher.
        $this->conta(['nome_usuario' => 'sem.matricula', 'matricula' => null]);
        $this->conta(['nome_usuario' => 'matricula.vazia', 'matricula' => '   ']);
        $this->conta(['nome_usuario' => 'completo']);

        $this->assertSame(2, $this->servico()->consulta(['matricula'])->count());
    }

    public function test_filtra_por_campo_especifico(): void
    {
        $this->conta(['nome_usuario' => 'sem.cargo', 'Cargo' => null]);
        $this->conta(['nome_usuario' => 'sem.cc', 'centro_custo' => null]);

        $this->assertSame(1, $this->servico()->consulta(['Cargo'])->count());
        $this->assertSame(1, $this->servico()->consulta(['centro_custo'])->count());
        $this->assertSame(2, $this->servico()->consulta()->count(), 'Sem filtro, qualquer campo vazio conta.');
    }

    public function test_conta_desativada_ou_excluida_nao_e_pendencia(): void
    {
        // Não é cadastro a preencher; é histórico.
        $this->conta(['nome_usuario' => 'desativada', 'Cargo' => null, 'e_ativo' => false]);
        $this->conta(['nome_usuario' => 'excluida', 'Cargo' => null, 'excluido' => true]);

        $this->assertSame(0, $this->servico()->consulta()->count());
    }

    public function test_conta_de_servico_fica_de_fora_por_padrao(): void
    {
        $this->conta(['nome_usuario' => 'svc.integracao', 'matricula' => null]);
        $this->conta(['nome_usuario' => 'pessoa.real', 'matricula' => null]);

        $this->assertSame(1, $this->servico()->consulta()->count());
        $this->assertSame(2, $this->servico()->consulta([], true)->count());
    }

    public function test_scanner_nao_entra_no_relatorio_de_colaborador(): void
    {
        /*
         | Scanner não tem matrícula nem cargo e nunca terá. As 100 contas
         | scanner.* entrariam como pendência permanente e afogariam o que
         | realmente precisa ser preenchido.
         |
         | Continuam recebendo centro de custo pela regra por OU — outro
         | caminho, coberto em AdOfficeRuleTest.
         */
        $this->conta(['nome_usuario' => 'scanner.ecommerce', 'matricula' => null, 'Cargo' => null]);
        $this->conta(['nome_usuario' => 'pessoa.real', 'matricula' => null]);

        $this->assertSame(1, $this->servico()->consulta()->count());
        $this->assertSame(2, $this->servico()->consulta([], true)->count(), 'Com o filtro ligado, aparecem.');
    }

    public function test_concessionarias_e_terceiros_ficam_fora_de_escopo(): void
    {
        // Seguem outro modelo: matrícula, cargo e centro de custo não se
        // aplicam do mesmo jeito. Cobrá-los encheria o relatório de pendência
        // que ninguém vai resolver.
        $this->conta([
            'nome_usuario' => 'vendedor.tratorcase',
            'matricula' => null,
            'distinguished_name' => 'CN=x,OU=Usuarios,OU=Tratorcase,OU=Concessionarias,DC=cocari,DC=com,DC=br',
        ]);
        $this->conta([
            'nome_usuario' => 'parceiro.trade',
            'matricula' => null,
            'distinguished_name' => 'CN=x,OU=Trade,OU=Terceiros,DC=cocari,DC=com,DC=br',
        ]);
        $this->conta([
            'nome_usuario' => 'pessoa.sede',
            'matricula' => null,
            'distinguished_name' => 'CN=x,OU=TI,OU=Sede,DC=cocari,DC=com,DC=br',
        ]);

        $this->assertSame(['pessoa.sede'], $this->servico()->consulta()->pluck('nome_usuario')->all());
        $this->assertSame(3, $this->servico()->consulta([], true)->count());
    }

    public function test_csc_continua_no_relatorio_apesar_de_ficar_sob_concessionarias(): void
    {
        /*
         | CSC está sob Concessionárias na árvore, mas 20 das suas 23 contas
         | têm matrícula e cargo. Ignorar a árvore inteira tiraria do relatório
         | justamente quem está preenchido.
         */
        $this->conta([
            'nome_usuario' => 'contador.csc',
            'matricula' => null,
            'distinguished_name' => 'CN=x,OU=Contabilidade,OU=CSC,OU=Concessionarias,DC=cocari,DC=com,DC=br',
        ]);

        $this->assertSame(1, $this->servico()->consulta()->count());
    }

    public function test_nome_de_ou_parecido_nao_e_excluido_por_engano(): void
    {
        // "Terceirizados" não é "Terceiros"; a comparação inclui a vírgula do
        // separador de nível justamente para não confundir os dois.
        $this->conta([
            'nome_usuario' => 'pessoa.terceirizados',
            'matricula' => null,
            'distinguished_name' => 'CN=x,OU=Terceirizados,OU=Sede,DC=cocari,DC=com,DC=br',
        ]);

        $this->assertSame(1, $this->servico()->consulta()->count());
    }

    public function test_rodocoop_nao_e_cobrada_por_matricula(): void
    {
        /*
         | A Rodocoop é empresa afiliada: os colaboradores não têm matrícula
         | nossa. Cobrá-la geraria linha que ninguém pode resolver.
         |
         | A dispensa é por CAMPO, não da estrutura: cargo e unidade continuam
         | valendo, e 7 das 22 contas já os têm.
         */
        $rodocoop = $this->conta([
            'nome_usuario' => 'lais.rissi',
            'matricula' => null,
            'Cargo' => 'Assistente',
            'distinguished_name' => 'CN=x,OU=Rodocoop,OU=Afiliados,DC=cocari,DC=com,DC=br',
        ]);
        $this->conta([
            'nome_usuario' => 'pessoa.sede',
            'matricula' => null,
            'distinguished_name' => 'CN=x,OU=TI,OU=Sede,DC=cocari,DC=com,DC=br',
        ]);

        $this->assertSame(['pessoa.sede'], $this->servico()->consulta(['matricula'])->pluck('nome_usuario')->all());
        $this->assertSame([], $this->servico()->faltando($rodocoop)->all());
    }

    public function test_rodocoop_continua_sendo_cobrada_pelos_demais_campos(): void
    {
        // Tirar a Rodocoop do relatório esconderia as contas dela sem cargo.
        $semCargo = $this->conta([
            'nome_usuario' => 'charles.bau',
            'matricula' => null,
            'Cargo' => null,
            'distinguished_name' => 'CN=x,OU=Rodocoop,OU=Afiliados,DC=cocari,DC=com,DC=br',
        ]);

        $this->assertSame(1, $this->servico()->consulta()->count());
        $this->assertSame(['Cargo'], $this->servico()->faltando($semCargo)->all());
    }

    public function test_conta_marcada_como_nao_colaborador_sai_do_relatorio(): void
    {
        // Balança, barraca, impressora e conta de máquina existem no domínio
        // mas não são pessoas: nunca terão matrícula nem cargo.
        $this->conta(['nome_usuario' => 'balanca.fiacao', 'matricula' => null, 'Cargo' => null]);
        $this->conta(['nome_usuario' => 'pessoa.real', 'matricula' => null]);

        $this->assertSame(2, $this->servico()->consulta()->count());

        \App\Models\AdNonCollaboratorAccount::create([
            'nome_usuario' => 'balanca.fiacao',
            'motivo' => 'Terminal de balança',
        ]);

        $this->assertSame(['pessoa.real'], $this->servico()->consulta()->pluck('nome_usuario')->all());
    }

    public function test_marcacao_ignora_caixa_das_letras(): void
    {
        // Marcar "BALANCA.FIACAO" não pode deixar balanca.fiacao no relatório.
        $this->conta(['nome_usuario' => 'balanca.fiacao', 'matricula' => null]);

        \App\Models\AdNonCollaboratorAccount::create(['nome_usuario' => 'BALANCA.FIACAO']);

        $this->assertSame(0, $this->servico()->consulta()->count());
    }

    public function test_nao_colaborador_tambem_sai_da_regra_de_centro_de_custo(): void
    {
        /*
         | Quem não é colaborador não recebe centro de custo. Uma lista só,
         | usada pelos dois lugares: marcar no relatório e continuar recebendo
         | código pela regra de OU seria contraditório.
         */
        $conta = $this->conta([
            'nome_usuario' => 'barraca.principal01',
            'centro_custo' => null,
            'distinguished_name' => 'CN=x,OU=Usuarios,OU=Marialva,OU=Entrepostos,DC=cocari,DC=com,DC=br',
        ]);

        $regra = app(\App\Services\Ad\AdOfficeRuleService::class);
        $this->assertFalse($regra->contaIgnorada($conta));

        \App\Models\AdNonCollaboratorAccount::create(['nome_usuario' => 'barraca.principal01']);

        $this->assertTrue(app(\App\Services\Ad\AdOfficeRuleService::class)->contaIgnorada($conta));
    }

    public function test_marcar_pela_tela_funciona_sem_informar_motivo(): void
    {
        /*
         | O formulário de cada linha manda só o nome_usuario. Como a regra do
         | motivo é 'nullable', a chave pode nem chegar — ler direto estourava
         | com "Undefined array key".
         */
        $this->conta(['nome_usuario' => 'balanca.fiacao', 'matricula' => null]);

        $usuario = User::factory()->create(['id' => 9403, 'role' => 'user', 'is_active' => true]);
        app(ModuleAccessService::class)->sync($usuario, ['servicedesk' => ['manage']]);

        $this->actingAs($usuario->fresh())
            ->post(route('service-desk.ad.cadastro-incompleto.nao-colaborador.store'), [
                'nome_usuario' => 'balanca.fiacao',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('ad_non_collaborator_accounts', [
            'nome_usuario' => 'balanca.fiacao',
            'motivo' => 'Marcada na tela',
        ]);

        $this->assertSame(0, $this->servico()->consulta()->count());
    }

    public function test_reverter_devolve_a_conta_ao_relatorio(): void
    {
        $this->conta(['nome_usuario' => 'balanca.fiacao', 'matricula' => null]);

        $marcada = \App\Models\AdNonCollaboratorAccount::create([
            'nome_usuario' => 'balanca.fiacao',
            'motivo' => 'Terminal de balança',
        ]);

        $usuario = User::factory()->create(['id' => 9404, 'role' => 'user', 'is_active' => true]);
        app(ModuleAccessService::class)->sync($usuario, ['servicedesk' => ['manage']]);

        $this->actingAs($usuario->fresh())
            ->delete(route('service-desk.ad.cadastro-incompleto.nao-colaborador.destroy', $marcada))
            ->assertRedirect();

        $this->assertSame(1, $this->servico()->consulta()->count());
    }

    public function test_quem_nao_tem_ad_execute_nao_marca(): void
    {
        $this->conta(['nome_usuario' => 'balanca.fiacao', 'matricula' => null]);

        $semPermissao = User::factory()->create(['id' => 9405, 'role' => 'user', 'is_active' => true]);
        app(ModuleAccessService::class)->sync($semPermissao, ['servicedesk' => ['view']]);

        $this->actingAs($semPermissao->fresh())
            ->post(route('service-desk.ad.cadastro-incompleto.nao-colaborador.store'), ['nome_usuario' => 'balanca.fiacao'])
            ->assertForbidden();

        $this->assertDatabaseCount('ad_non_collaborator_accounts', 0);
    }

    public function test_lista_quais_campos_faltam(): void
    {
        $conta = $this->conta(['nome_usuario' => 'incompleta', 'matricula' => null, 'Cargo' => '']);

        $this->assertSame(['Matrícula', 'Cargo'], $this->servico()->faltando($conta)->all());
    }

    public function test_exportacao_sai_sem_bom(): void
    {
        /*
         | O Import-Csv do PowerShell 5.1 não descarta BOM: a primeira coluna
         | viria com lixo no nome e o script leria tudo como vazio.
         */
        $this->conta(['nome_usuario' => 'sem.cargo', 'Cargo' => null]);

        $user = User::factory()->create(['id' => 9401, 'role' => 'user', 'is_active' => true]);
        app(ModuleAccessService::class)->sync($user, ['servicedesk' => ['view']]);

        $resposta = $this->actingAs($user->fresh())->get(route('service-desk.ad.cadastro-incompleto.exportar'));
        $resposta->assertOk();

        $conteudo = $resposta->streamedContent();

        $this->assertStringStartsNotWith("\xEF\xBB\xBF", $conteudo);
        $this->assertStringStartsWith('nome_usuario;', $conteudo);
        $this->assertStringContainsString('sem.cargo', $conteudo);
    }

    public function test_exige_permissao_do_modulo(): void
    {
        // Outro módulo qualquer: a tela vive sob ServiceDesk > AD.
        $semAcesso = User::factory()->create(['id' => 9402, 'role' => 'user', 'is_active' => true]);
        app(ModuleAccessService::class)->sync($semAcesso, ['unidades' => ['view']]);

        $this->actingAs($semAcesso->fresh())
            ->get(route('service-desk.ad.cadastro-incompleto.index'))
            ->assertForbidden();
    }
}
