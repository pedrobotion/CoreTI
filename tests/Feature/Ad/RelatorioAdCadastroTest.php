<?php

namespace Tests\Feature\Ad;

use App\Models\AdOfficeRule;
use App\Models\AdUser;
use App\Models\CoretiCostCenter;
use App\Support\LegacyModulePermissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

class RelatorioAdCadastroTest extends TestCase
{
    use RefreshDatabase;

    private string $saida;

    protected function setUp(): void
    {
        parent::setUp();

        LegacyModulePermissions::esquecerCache();
        $this->saida = sys_get_temp_dir().'/relatorio_cc_'.uniqid().'.xlsx';
    }

    protected function tearDown(): void
    {
        @unlink($this->saida);
        parent::tearDown();
    }

    private function conta(string $login, array $dados = []): AdUser
    {
        return AdUser::create(array_merge([
            'nome_usuario' => $login,
            'nome_completo' => ucfirst($login),
            'matricula' => '12345',
            'unidade_principal' => 'Sede',
            'unidade_setor' => 'TI',
            'Cargo' => 'Analista',
            'centro_custo' => null,
            'e_ativo' => true,
            'excluido' => false,
            'distinguished_name' => 'CN=x,OU=Usuários,OU=Loja,OU=Entrepostos,DC=cocari,DC=com,DC=br',
        ], $dados));
    }

    private function gerar(): \PhpOffice\PhpSpreadsheet\Spreadsheet
    {
        $this->artisan('ad:relatorio-cadastro', [
            '--campos' => 'centro_custo',
            '--saida' => $this->saida,
        ])->assertSuccessful();

        return IOFactory::load($this->saida);
    }

    public function test_codigo_terminado_em_zero_nao_perde_digito(): void
    {
        /*
         | O bug que motivou este teste: "64.200" saía como 64.2 porque a
         | planilha o tratava como número. Um código assim não casa mais com o
         | cadastro, e 10 centros de custo terminam em zero.
         */
        CoretiCostCenter::create(['code' => '64.200', 'name' => 'Loja', 'active' => true]);
        AdOfficeRule::create([
            'ou_caminho' => 'Entrepostos / Loja',
            'centro_custo_code' => '64.200',
            'confirmado_em' => now(),
        ]);
        $this->conta('joao.silva');

        $resumo = $this->gerar()->getSheetByName('Resumo por OU');

        $valor = $resumo->getCell('D2')->getValue();
        $this->assertIsString($valor, 'o código precisa ser gravado como texto');
        $this->assertSame('64.200', $valor);
    }

    public function test_codigo_com_zero_a_esquerda_tambem_sobrevive(): void
    {
        CoretiCostCenter::create(['code' => '01.150', 'name' => 'TI', 'active' => true]);
        AdOfficeRule::create([
            'ou_caminho' => 'Entrepostos / Loja',
            'centro_custo_code' => '01.150',
            'confirmado_em' => now(),
        ]);
        $this->conta('maria.souza');

        $planilha = $this->gerar();

        $this->assertSame('01.150', $planilha->getSheetByName('Resumo por OU')->getCell('D2')->getValue());
        $this->assertSame('01.150', $planilha->getSheetByName('Sem Centro de custo')->getCell('I2')->getValue());
    }

    public function test_situacao_reflete_o_estado_da_regra_da_ou(): void
    {
        AdOfficeRule::create(['ou_caminho' => 'Entrepostos / Loja', 'centro_custo_code' => '64.200', 'confirmado_em' => now()]);
        AdOfficeRule::create(['ou_caminho' => 'Entrepostos / Feira', 'ignorar' => true]);

        $this->conta('com.regra');
        $this->conta('ignorada', ['distinguished_name' => 'CN=x,OU=Usuários,OU=Feira,OU=Entrepostos,DC=cocari,DC=com,DC=br']);
        $this->conta('sem.regra', ['distinguished_name' => 'CN=x,OU=Usuários,OU=Praca,OU=Entrepostos,DC=cocari,DC=com,DC=br']);

        $detalhe = $this->gerar()->getSheetByName('Sem Centro de custo')->toArray();
        $situacoes = collect(array_slice($detalhe, 1))->pluck(7)->filter()->sort()->values()->all();

        $this->assertSame(
            ['OU marcada para ignorar', 'OU sem regra definida', 'Regra pronta para aplicar'],
            $situacoes
        );
    }

    public function test_conta_que_nao_e_de_colaborador_fica_de_fora(): void
    {
        // Mesmo recorte do painel e da tela de cadastro incompleto.
        $this->conta('joao.silva');
        $this->conta('scanner.sede');
        $this->conta('user.ti.padrao');

        $detalhe = $this->gerar()->getSheetByName('Sem Centro de custo')->toArray();

        $this->assertCount(2, $detalhe, 'cabeçalho + uma única pessoa');
        $this->assertSame('joao.silva', $detalhe[1][0]);
    }

    public function test_separa_quem_nao_tem_nenhum_campo_de_quem_tem_alguma_pendencia(): void
    {
        /*
         | São duas perguntas diferentes e as duas aparecem: por onde começar
         | (quem está totalmente em branco) e o trabalho inteiro.
         */
        $this->conta('sem.tudo', ['matricula' => null, 'Cargo' => null, 'centro_custo' => null]);
        $this->conta('so.sem.cargo', ['matricula' => '999', 'Cargo' => null, 'centro_custo' => '01.150']);
        $this->conta('completo', ['matricula' => '888', 'Cargo' => 'Analista', 'centro_custo' => '01.150']);

        $this->artisan('ad:relatorio-cadastro', [
            '--campos' => 'matricula,Cargo,centro_custo',
            '--saida' => $this->saida,
        ])->assertSuccessful();

        $planilha = IOFactory::load($this->saida);

        $curta = $planilha->getSheetByName('Sem nenhum dos campos')->toArray();
        $this->assertCount(2, $curta, 'só quem não tem nenhum dos três');
        $this->assertSame('sem.tudo', $curta[1][0]);

        $longa = $planilha->getSheetByName('Qualquer pendência')->toArray();
        $this->assertCount(3, $longa, 'quem tem qualquer pendência');

        // A coluna "O que falta" precisa dizer exatamente o que falta.
        $porLogin = collect(array_slice($longa, 1))->keyBy(0);
        $this->assertSame('Matrícula, Cargo, Centro de custo', $porLogin['sem.tudo'][10]);
        $this->assertSame('Cargo', $porLogin['so.sem.cargo'][10]);
        $this->assertSame('Sim', $porLogin['sem.tudo'][11]);
        $this->assertSame('Não', $porLogin['so.sem.cargo'][11]);
    }

    public function test_campo_invalido_em_campos_falha_com_aviso(): void
    {
        $this->conta('joao.silva');

        $this->artisan('ad:relatorio-cadastro', ['--campos' => 'inexistente', '--saida' => $this->saida])
            ->expectsOutputToContain('Nenhum campo válido')
            ->assertFailed();
    }

    public function test_sem_pendencia_nao_gera_arquivo(): void
    {
        $this->conta('joao.silva', ['centro_custo' => '01.150']);

        $this->artisan('ad:relatorio-cadastro', [
            '--campos' => 'centro_custo',
            '--saida' => $this->saida,
        ])->expectsOutputToContain('Nenhum colaborador com pendência')->assertSuccessful();

        $this->assertFileDoesNotExist($this->saida);
    }
}
