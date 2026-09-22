<?php

namespace Tests\Feature\Ad;

use App\Models\AdOfficeRule;
use App\Models\AdUser;
use App\Models\CoretiCostCenter;
use App\Models\User;
use App\Services\Ad\AdOfficeRuleService;
use App\Services\ModuleAccessService;
use App\Support\LegacyModulePermissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Regra do campo "Escritório": OU do setor → centro de custo.
 *
 * O Active Directory não tem atributo calculado — nada se preenche a partir da
 * OU sozinho. A regra vive no CoreTI e um script aplica.
 */
class AdOfficeRuleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        LegacyModulePermissions::esquecerCache();

        CoretiCostCenter::create(['code' => '65.154', 'name' => 'E-Commerce', 'active' => true]);
        CoretiCostCenter::create(['code' => '66.154', 'name' => 'Ponta Grossa', 'active' => true]);
        CoretiCostCenter::create(['code' => '01.150', 'name' => 'Tecnologia da Informação', 'active' => true]);
    }

    private function conta(string $usuario, string $ou, ?string $cc = null, ?string $nomeCompleto = null): AdUser
    {
        return AdUser::create([
            'nome_usuario' => $usuario,
            'nome_completo' => $nomeCompleto ?? ucfirst($usuario),
            'centro_custo' => $cc,
            'distinguished_name' => "CN={$usuario},OU=Usuários,OU={$ou},OU=Sede,DC=cocari,DC=com,DC=br",
            'e_ativo' => true,
            'excluido' => false,
        ]);
    }

    private function servico(): AdOfficeRuleService
    {
        return app(AdOfficeRuleService::class);
    }

    public function test_o_nivel_usuarios_nao_e_tratado_como_setor(): void
    {
        // "Usuários" é pasta de organização, não lotação.
        $caminho = $this->servico()->caminhoDaOu(
            'CN=fulano,OU=Usuários,OU=E-Commerce,OU=Sede,DC=cocari,DC=com,DC=br'
        );

        $this->assertSame('Sede / E-Commerce', $caminho);
    }

    public function test_conta_modelo_fica_de_fora_da_regra(): void
    {
        /*
         | Conta modelo existe para ser clonada na criação de usuário. Dar
         | centro de custo a ela propaga o código para quem for clonado depois
         | e infla o rateio com gente que não existe.
         */
        $this->conta('jorge.martinez', 'E-Commerce', '65.154');
        $this->conta('user.e-commerce.padrao', 'E-Commerce', null, 'User Modelo E-commerce Padrao');
        $this->conta('scanner.ecommerce', 'E-Commerce', null, 'Scanner E-commerce');
        $this->conta('ged.aquidaban', 'E-Commerce', null, 'GED Aquidaban');

        $alcancadas = $this->servico()->contasAlcancadas()->pluck('nome_usuario')->all();

        $this->assertSame(['jorge.martinez'], $alcancadas, 'Só pessoa recebe centro de custo.');
    }

    public function test_conta_de_integracao_tambem_fica_de_fora(): void
    {
        // user.simpress e user.hcm não são modelo nem pessoa: são integrações.
        // Nenhuma conta user.* consome centro de custo.
        $this->conta('user.simpress', 'E-Commerce', null, 'Usuários Simpress');
        $this->conta('user.hcm', 'E-Commerce', null, 'User HCM');
        $this->conta('maria.souza', 'E-Commerce', '65.154');

        $alcancadas = $this->servico()->contasAlcancadas()->pluck('nome_usuario')->all();

        $this->assertSame(['maria.souza'], $alcancadas);
    }

    public function test_sugestao_e_o_codigo_dominante_da_ou(): void
    {
        $this->conta('a.um', 'E-Commerce', '65.154');
        $this->conta('b.dois', 'E-Commerce', '65.154');
        $this->conta('c.tres', 'E-Commerce', '66.154');
        $this->conta('d.quatro', 'E-Commerce');

        $this->servico()->semear();

        $regra = AdOfficeRule::where('ou_caminho', 'Sede / E-Commerce')->firstOrFail();

        $this->assertSame('65.154', $regra->sugestao_code);
        $this->assertSame(4, $regra->contas_na_ou);
        // Só quem TEM código diferente diverge; conta vazia não.
        $this->assertSame(1, $regra->contas_divergentes);
    }

    public function test_previa_mostra_preenchimento_e_troca(): void
    {
        $this->conta('a.um', 'E-Commerce', '65.154');
        $this->conta('b.dois', 'E-Commerce');
        $this->conta('c.tres', 'E-Commerce', '66.154');

        $this->servico()->semear();

        $previa = $this->servico()->previa();

        $this->assertCount(2, $previa);
        $this->assertSame(['b.dois', 'c.tres'], $previa->pluck('nome_usuario')->sort()->values()->all());
        $this->assertNull($previa->firstWhere('nome_usuario', 'b.dois')['de']);
        $this->assertSame('66.154', $previa->firstWhere('nome_usuario', 'c.tres')['de']);
    }

    public function test_ou_marcada_para_nao_mexer_nao_gera_mudanca(): void
    {
        // A Superintendência Logística Integrada está em revisão de
        // nomenclatura e tem centro de custo por setor.
        $this->conta('a.um', 'Suprimentos', '01.189');
        $this->conta('b.dois', 'Suprimentos', '01.177');

        $this->servico()->semear();

        AdOfficeRule::where('ou_caminho', 'Sede / Suprimentos')->update([
            'ignorar' => true,
            'centro_custo_code' => null,
        ]);

        $this->assertCount(0, $this->servico()->previa());
    }

    public function test_ou_sem_definicao_nao_altera_ninguem(): void
    {
        $this->conta('a.um', 'Auditoria');
        $this->conta('b.dois', 'Auditoria');

        $this->servico()->semear();

        // Ninguém tinha código, então não há dominante para sugerir.
        $regra = AdOfficeRule::where('ou_caminho', 'Sede / Auditoria')->firstOrFail();
        $this->assertNull($regra->centro_custo_code);
        $this->assertSame('sem_definicao', $regra->situacao());
        $this->assertCount(0, $this->servico()->previa());
    }

    public function test_semear_nao_apaga_decisao_ja_tomada(): void
    {
        $this->conta('a.um', 'E-Commerce', '66.154');
        $this->conta('b.dois', 'E-Commerce', '66.154');

        $this->servico()->semear();

        // Uma pessoa corrige: o dominante estava errado.
        $regra = AdOfficeRule::where('ou_caminho', 'Sede / E-Commerce')->firstOrFail();
        $regra->update(['centro_custo_code' => '65.154', 'confirmado_em' => now(), 'confirmado_por' => null]);

        $this->servico()->semear();

        $this->assertSame('65.154', $regra->fresh()->centro_custo_code, 'Semear não pode desfazer decisão humana.');
        $this->assertSame('66.154', $regra->fresh()->sugestao_code);
    }

    public function test_empresa_terceira_recebe_cnpj_no_lugar_do_codigo(): void
    {
        /*
         | A Rodocoop é empresa terceira: o campo Escritório guarda o CNPJ.
         | Cadastrar o CNPJ como centro de custo o faria aparecer nas telas de
         | rateio e nos relatórios de custo como se fosse um.
         */
        $this->conta('dorival.junior', 'Rodocoop', '01.144');

        $this->servico()->semear();

        AdOfficeRule::where('ou_caminho', 'Sede / Rodocoop')->update([
            'tipo_valor' => AdOfficeRule::TIPO_CNPJ,
            'valor_livre' => '07.881.528/0001-66',
            'centro_custo_code' => null,
        ]);

        $previa = $this->servico()->previa();

        $this->assertCount(1, $previa);
        $this->assertSame('01.144', $previa->first()['de']);
        $this->assertSame('07.881.528/0001-66', $previa->first()['para']);
    }

    public function test_regra_com_cnpj_vazio_nao_altera_ninguem(): void
    {
        // Tipo CNPJ sem valor é "falta definir", não "escreva vazio".
        $this->conta('dorival.junior', 'Rodocoop', '01.144');
        $this->servico()->semear();

        AdOfficeRule::where('ou_caminho', 'Sede / Rodocoop')->update([
            'tipo_valor' => AdOfficeRule::TIPO_CNPJ,
            'valor_livre' => null,
            'centro_custo_code' => null,
        ]);

        $regra = AdOfficeRule::where('ou_caminho', 'Sede / Rodocoop')->firstOrFail();

        $this->assertSame('sem_definicao', $regra->situacao());
        $this->assertCount(0, $this->servico()->previa());
    }

    private function aprovador(): User
    {
        $user = User::factory()->create(['id' => 9302, 'role' => 'user', 'is_active' => true]);
        app(ModuleAccessService::class)->sync($user, ['servicedesk' => ['manage']]);

        return $user->fresh();
    }

    public function test_salva_varias_regras_de_uma_vez(): void
    {
        $this->conta('a.um', 'E-Commerce', '65.154');
        $this->conta('b.dois', 'Auditoria');
        $this->servico()->semear();

        $ecommerce = AdOfficeRule::where('ou_caminho', 'Sede / E-Commerce')->firstOrFail();
        $auditoria = AdOfficeRule::where('ou_caminho', 'Sede / Auditoria')->firstOrFail();

        $this->actingAs($this->aprovador())
            ->patch(route('service-desk.ad.centro-custo.atualizar-lote'), [
                'total_enviado' => 2,
                'regras' => [
                    $ecommerce->id => ['tipo_valor' => 'centro_custo', 'centro_custo_code' => '66.154'],
                    $auditoria->id => ['tipo_valor' => 'centro_custo', 'centro_custo_code' => '01.150'],
                ],
            ])
            ->assertRedirect();

        $this->assertSame('66.154', $ecommerce->fresh()->centro_custo_code);
        $this->assertSame('01.150', $auditoria->fresh()->centro_custo_code);
    }

    public function test_linha_que_nao_mudou_nao_vira_confirmada(): void
    {
        /*
         | Passar pelo formulário não é decidir. Marcar as 127 como confirmadas
         | porque foram enviadas apagaria a diferença entre "ninguém olhou" e
         | "alguém decidiu" — que é a informação que a tela existe para dar.
         */
        $this->conta('a.um', 'E-Commerce', '65.154');
        $this->servico()->semear();

        $regra = AdOfficeRule::firstOrFail();
        $this->assertSame('sugerida', $regra->situacao());

        $this->actingAs($this->aprovador())
            ->patch(route('service-desk.ad.centro-custo.atualizar-lote'), [
                'total_enviado' => 1,
                'regras' => [
                    $regra->id => ['tipo_valor' => 'centro_custo', 'centro_custo_code' => '65.154'],
                ],
            ]);

        $this->assertNull($regra->fresh()->confirmado_em);
        $this->assertSame('sugerida', $regra->fresh()->situacao());
    }

    public function test_formulario_truncado_nao_grava_nada(): void
    {
        // O PHP corta campos em silêncio ao passar de max_input_vars. Gravar
        // parte das alterações e descartar o resto sem avisar é pior que falhar.
        $this->conta('a.um', 'E-Commerce', '65.154');
        $this->servico()->semear();

        $regra = AdOfficeRule::firstOrFail();

        $this->actingAs($this->aprovador())
            ->patch(route('service-desk.ad.centro-custo.atualizar-lote'), [
                'total_enviado' => 50,   // o navegador mandou 50...
                'regras' => [            // ...mas só 1 chegou
                    $regra->id => ['tipo_valor' => 'centro_custo', 'centro_custo_code' => '66.154'],
                ],
            ])
            ->assertSessionHas('error');

        $this->assertSame('65.154', $regra->fresh()->centro_custo_code);
    }

    public function test_excecao_por_conta_tira_so_aquela_pessoa_da_regra(): void
    {
        /*
         | Nem toda exceção é da OU inteira: há quem tenha centro de custo
         | ligado à função e não ao lugar, e conta tratada caso a caso. A
         | exceção precisa valer para a pessoa sem desligar o setor todo.
         */
        $this->conta('jose.candido', 'DH', '66.154');
        $this->conta('maria.souza', 'DH', '66.154');

        $this->servico()->semear();

        AdOfficeRule::where('ou_caminho', 'Sede / DH')->update(['centro_custo_code' => '01.150']);

        // Sem exceção, as duas mudariam.
        $this->assertCount(2, $this->servico()->previa());

        \App\Models\AdOfficeAccountException::create([
            'nome_usuario' => 'jose.candido',
            'motivo' => 'Tratado à parte.',
        ]);

        $previa = app(AdOfficeRuleService::class)->previa();

        $this->assertCount(1, $previa);
        $this->assertSame('maria.souza', $previa->first()['nome_usuario']);
    }

    public function test_excecao_ignora_diferenca_de_maiusculas(): void
    {
        // Cadastrar "Jose.Candido" não pode deixar jose.candido de fora da
        // proteção por causa da caixa das letras.
        $this->conta('jose.candido', 'DH', '66.154');
        $this->servico()->semear();
        AdOfficeRule::where('ou_caminho', 'Sede / DH')->update(['centro_custo_code' => '01.150']);

        \App\Models\AdOfficeAccountException::create([
            'nome_usuario' => 'JOSE.CANDIDO',
            'motivo' => 'Tratado à parte.',
        ]);

        $this->assertCount(0, app(AdOfficeRuleService::class)->previa());
    }

    public function test_escopo_separa_quem_a_regra_alcanca(): void
    {
        /*
         | Sem este recorte, "N contas mudariam" não explica o que acontece com
         | as demais — e a maioria das contas do domínio está fora da regra.
         */
        $this->conta('pessoa.real', 'E-Commerce', '65.154');
        $this->conta('user.modelo', 'E-Commerce', null, 'User Modelo E-commerce');
        $this->conta('scanner.ecommerce', 'E-Commerce');
        $this->conta('excecao.individual', 'E-Commerce');
        $this->conta('nao.colaborador', 'E-Commerce');

        \App\Models\AdOfficeAccountException::create([
            'nome_usuario' => 'excecao.individual',
            'motivo' => 'Tratada à parte.',
        ]);
        \App\Models\AdNonCollaboratorAccount::create([
            'nome_usuario' => 'nao.colaborador',
            'motivo' => 'Terminal.',
        ]);

        $escopo = app(AdOfficeRuleService::class)->escopo();

        $this->assertSame(1, $escopo['alcancadas']);
        $this->assertSame(4, $escopo['fora']);
        $this->assertSame(1, $escopo['motivos']['Exceção individual']);
        $this->assertSame(1, $escopo['motivos']['Não é colaborador']);
        $this->assertSame(2, $escopo['motivos']['Conta de sistema ou modelo']);
    }

    public function test_botao_de_salvar_aparece_para_quem_tem_servicedesk_manage(): void
    {
        /*
         | As telas mudaram de módulo, mas a view continuava checando
         | module.ad.execute: quem tinha a permissão nova não via botão algum,
         | e quem tinha a antiga via o botão e tomava 403 ao enviar.
         */
        $this->conta('a.um', 'E-Commerce', '65.154');
        $this->servico()->semear();

        $this->actingAs($this->aprovador())
            ->get(route('service-desk.ad.centro-custo.index'))
            ->assertOk()
            ->assertSee('Salvar alterações');
    }

    public function test_quem_so_consulta_nao_ve_o_botao_de_salvar(): void
    {
        $this->conta('a.um', 'E-Commerce', '65.154');
        $this->servico()->semear();

        $leitor = User::factory()->create(['id' => 9310, 'role' => 'user', 'is_active' => true]);
        app(ModuleAccessService::class)->sync($leitor, ['servicedesk' => ['view']]);

        $this->actingAs($leitor->fresh())
            ->get(route('service-desk.ad.centro-custo.index'))
            ->assertOk()
            ->assertDontSee('Salvar alterações');
    }

    public function test_quem_nao_tem_ad_execute_nao_altera_regra(): void
    {
        $this->conta('a.um', 'E-Commerce', '65.154');
        $this->servico()->semear();

        $regra = AdOfficeRule::firstOrFail();

        // Consulta sim, escrita não: 'manage' é que libera alterar a regra.
        $semPermissao = User::factory()->create(['id' => 9301, 'role' => 'user', 'is_active' => true]);
        app(ModuleAccessService::class)->sync($semPermissao, ['servicedesk' => ['view']]);

        $this->actingAs($semPermissao->fresh())
            ->get(route('service-desk.ad.centro-custo.index'))
            ->assertOk();

        $this->actingAs($semPermissao->fresh())
            ->patch(route('service-desk.ad.centro-custo.update', $regra), ['centro_custo_code' => '66.154'])
            ->assertForbidden();

        $this->assertSame('65.154', $regra->fresh()->centro_custo_code);
    }
}
