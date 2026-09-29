<?php

namespace Tests\Feature\Impressao;

use App\Models\PrintJob;
use App\Models\User;
use App\Services\Impressao\PrintStatsService;
use App\Services\ModuleAccessService;
use App\Support\LegacyModulePermissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardImpressoesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        LegacyModulePermissions::esquecerCache();
        config(['impressao.servidores' => ['IMP01', 'IMP02', 'IMP03']]);
    }

    private function usuario(array $modulos = ['impressoras' => ['view']]): User
    {
        static $id = 9500;
        $id++;

        $user = User::factory()->create(['id' => $id, 'role' => 'user', 'is_active' => true]);
        app(ModuleAccessService::class)->sync($user, $modulos);

        return $user->fresh();
    }

    private function trabalho(array $dados = []): PrintJob
    {
        static $n = 0;
        $n++;

        return PrintJob::create(array_merge([
            'servidor' => 'IMP01',
            'chave_evento' => hash('sha256', 'evento-'.$n),
            'impresso_em' => now()->subDay(),
            'usuario' => 'fulano.silva',
            'estacao' => 'SED-PC01',
            'impressora' => 'SED-LASER-FIN01',
            'paginas' => 2,
        ], $dados));
    }

    private function contaAd(string $login, string $lotacao, ?string $cargo = 'Analista'): void
    {
        \App\Models\AdUser::create([
            'nome_usuario' => $login,
            'nome_completo' => ucwords(str_replace('.', ' ', $login)),
            'unidade_setor' => $lotacao,
            'Cargo' => $cargo,
            'e_ativo' => true,
            'excluido' => false,
        ]);
    }

    public function test_usuario_aparece_com_a_lotacao_do_ad(): void
    {
        // "imprimiu 900 páginas" diz pouco; com a lotação dá para cobrar o
        // setor certo.
        $this->contaAd('camila.castelo', 'UBS - Faxinal');
        $this->trabalho(['usuario' => 'camila.castelo', 'paginas' => 9]);

        $linha = PrintStatsService::periodo(now()->subDays(3)->toDateString(), now()->toDateString())
            ->porUsuario()->firstWhere('usuario', 'camila.castelo');

        $this->assertSame('UBS - Faxinal', $linha->lotacao);
        $this->assertSame('Camila Castelo', $linha->nome_completo);
        $this->assertSame('Analista', $linha->cargo);
    }

    public function test_cargo_em_branco_no_ad_vira_nulo_e_nao_string_vazia(): void
    {
        /*
         | 39 dos 661 que imprimem estão sem cargo — os mesmos que o relatório
         | de cadastro incompleto aponta. A tela precisa distinguir "não
         | preenchido" de "preenchido com vazio", senão mostra um travessão
         | onde deveria mostrar pendência.
         */
        $this->contaAd('sem.cargo', 'Jaguapita', '   ');
        $this->trabalho(['usuario' => 'sem.cargo']);

        $linha = PrintStatsService::periodo(now()->subDays(3)->toDateString(), now()->toDateString())
            ->porUsuario()->firstWhere('usuario', 'sem.cargo');

        $this->assertNull($linha->cargo);
        $this->assertSame('Jaguapita', $linha->lotacao, 'a lotação continua valendo');
    }

    public function test_cargo_aparece_nos_maiores_trabalhos(): void
    {
        $this->contaAd('chefe.setor', 'Marialva', 'Coordenador');
        $this->trabalho(['usuario' => 'chefe.setor', 'paginas' => 200]);

        $maior = PrintStatsService::periodo(now()->subDays(3)->toDateString(), now()->toDateString())
            ->maioresTrabalhos()->first();

        $this->assertSame('Coordenador', $maior->cargo);
        $this->assertSame('Marialva', $maior->lotacao);
    }

    public function test_usuario_sem_conta_no_ad_nao_derruba_a_tela(): void
    {
        /*
         | ad_users vem de outra sincronização: pode estar vazia ou defasada.
         | A coluna fica em branco, o painel continua de pé.
         */
        $this->trabalho(['usuario' => 'fantasma.sem.ad']);

        $linha = PrintStatsService::periodo(now()->subDays(3)->toDateString(), now()->toDateString())
            ->porUsuario()->firstWhere('usuario', 'fantasma.sem.ad');

        $this->assertNull($linha->lotacao);

        $this->actingAs($this->usuario())->get(route('dashboards.impressoes'))->assertOk();
    }

    public function test_agrupa_paginas_por_lotacao(): void
    {
        $this->contaAd('pessoa.um', 'UBS - Faxinal');
        $this->contaAd('pessoa.dois', 'UBS - Faxinal');
        $this->contaAd('pessoa.tres', 'Marialva');

        $this->trabalho(['usuario' => 'pessoa.um', 'paginas' => 10]);
        $this->trabalho(['usuario' => 'pessoa.dois', 'paginas' => 5]);
        $this->trabalho(['usuario' => 'pessoa.tres', 'paginas' => 3]);

        $lotacoes = PrintStatsService::periodo(now()->subDays(3)->toDateString(), now()->toDateString())
            ->porLotacao();

        $faxinal = $lotacoes->firstWhere('lotacao', 'UBS - Faxinal');
        $this->assertSame(15, $faxinal->paginas);
        $this->assertSame(2, $faxinal->pessoas, 'conta pessoas, não trabalhos');
        $this->assertSame('UBS - Faxinal', $lotacoes->first()->lotacao, 'a maior vem primeiro');
    }

    public function test_lotacao_funciona_apesar_da_collation_diferente(): void
    {
        /*
         | print_jobs é utf8mb4_unicode_ci e ad_users é utf8mb4_general_ci.
         | Um JOIN por texto entre as duas devolve "Illegal mix of
         | collations" e derruba a consulta — por isso o cruzamento é feito
         | em PHP. Este teste falha se alguém "otimizar" com um JOIN.
         */
        $this->contaAd('acentuado.usuario', 'São Pedro do Ivaí');
        $this->trabalho(['usuario' => 'acentuado.usuario', 'paginas' => 4]);

        $lotacoes = PrintStatsService::periodo(now()->subDays(3)->toDateString(), now()->toDateString())
            ->porLotacao();

        $this->assertSame('São Pedro do Ivaí', $lotacoes->first()->lotacao);
        $this->assertSame(4, $lotacoes->first()->paginas);
    }

    /*
     | O nome do documento não existe: o driver universal descarta o título
     | em todo o parque. O peso por página é o que resta para distinguir
     | texto de material gráfico — não é o nome do arquivo, mas responde à
     | pergunta prática de onde sai papel caro.
     */

    public function test_perfil_separa_texto_de_imagem(): void
    {
        $this->trabalho(['paginas' => 1, 'tamanho_bytes' => 50 * 1024]);        // texto
        $this->trabalho(['paginas' => 1, 'tamanho_bytes' => 500 * 1024]);       // misto
        $this->trabalho(['paginas' => 1, 'tamanho_bytes' => 20 * 1024 * 1024]); // imagem

        $perfil = PrintStatsService::periodo(now()->subDays(3)->toDateString(), now()->toDateString())
            ->porPerfil()->keyBy('chave');

        $this->assertSame(1, $perfil['texto']->trabalhos);
        $this->assertSame(1, $perfil['misto']->trabalhos);
        $this->assertSame(1, $perfil['pesado']->trabalhos);
    }

    public function test_perfil_usa_peso_por_pagina_e_nao_peso_total(): void
    {
        // 40 MB em 100 páginas é apresentação comum; 2 MB numa página não é.
        $this->trabalho(['paginas' => 100, 'tamanho_bytes' => 40 * 1024 * 1024]);
        $this->trabalho(['paginas' => 1, 'tamanho_bytes' => 2 * 1024 * 1024]);

        $perfil = PrintStatsService::periodo(now()->subDays(3)->toDateString(), now()->toDateString())
            ->porPerfil()->keyBy('chave');

        $this->assertSame(1, $perfil['misto']->trabalhos, 'o de 100 páginas é misto');
        $this->assertSame(1, $perfil['pesado']->trabalhos, 'o de 1 página é pesado');
    }

    public function test_faixa_vazia_continua_aparecendo(): void
    {
        // Faixa ausente sumiria do gráfico e da comparação entre períodos.
        $this->trabalho(['paginas' => 1, 'tamanho_bytes' => 10 * 1024]);

        $perfil = PrintStatsService::periodo(now()->subDays(3)->toDateString(), now()->toDateString())
            ->porPerfil();

        $this->assertCount(3, $perfil);
        $this->assertSame(0, $perfil->firstWhere('chave', 'pesado')->trabalhos);
    }

    public function test_mais_pesados_ordena_por_pagina_e_nao_por_total(): void
    {
        $this->trabalho(['usuario' => 'marketing.arte', 'paginas' => 1, 'tamanho_bytes' => 20 * 1024 * 1024]);
        $this->trabalho(['usuario' => 'relatorio.longo', 'paginas' => 300, 'tamanho_bytes' => 60 * 1024 * 1024]);

        $pesados = PrintStatsService::periodo(now()->subDays(3)->toDateString(), now()->toDateString())
            ->maisPesadosPorPagina();

        $this->assertSame('marketing.arte', $pesados->first()->usuario);
    }

    public function test_trabalho_sem_tamanho_nao_entra_no_perfil(): void
    {
        // 134 dos 52 mil vieram sem tamanho; dividir por nada daria zero e
        // jogaria todos na faixa de texto.
        $this->trabalho(['paginas' => 1, 'tamanho_bytes' => null]);

        $perfil = PrintStatsService::periodo(now()->subDays(3)->toDateString(), now()->toDateString())
            ->porPerfil();

        $this->assertSame(0, $perfil->sum('trabalhos'));
    }

    public function test_exige_o_modulo(): void
    {
        $this->actingAs($this->usuario(['bancada' => ['view']]))
            ->get(route('dashboards.impressoes'))->assertForbidden();

        $this->actingAs($this->usuario())
            ->get(route('dashboards.impressoes'))->assertOk();
    }

    public function test_o_atalho_aparece_na_tela_inicial_para_quem_tem_o_modulo(): void
    {
        $this->actingAs($this->usuario())->get(route('home'))
            ->assertOk()->assertSee('Impressões', false);
    }

    public function test_o_atalho_nao_aparece_para_quem_nao_tem_o_modulo(): void
    {
        // O atalho nunca pode levar a um 403.
        $this->actingAs($this->usuario(['bancada' => ['view']]))->get(route('home'))
            ->assertOk()->assertDontSee('Volume por usuário, impressora, dia e horário.', false);
    }

    public function test_periodo_sem_dado_mostra_estado_vazio_em_vez_de_zeros(): void
    {
        $this->actingAs($this->usuario())
            ->get(route('dashboards.impressoes'))
            ->assertOk()
            ->assertSee('Nenhuma impressão registrada neste período.', false);
    }

    public function test_resumo_conta_paginas_trabalhos_e_media(): void
    {
        $this->trabalho(['paginas' => 3]);
        $this->trabalho(['paginas' => 1, 'usuario' => 'outro.usuario']);

        $stats = PrintStatsService::periodo(now()->subDays(3)->toDateString(), now()->toDateString());
        $resumo = $stats->resumo();

        $this->assertSame(2, $resumo['trabalhos']);
        $this->assertSame(4, $resumo['paginas']);
        $this->assertSame(2, $resumo['usuarios']);
        $this->assertSame(2.0, $resumo['media_paginas']);
    }

    public function test_trabalho_sem_pagina_conta_separado(): void
    {
        $this->trabalho(['paginas' => 0]);
        $this->trabalho(['paginas' => 5]);

        $resumo = PrintStatsService::periodo(now()->subDays(3)->toDateString(), now()->toDateString())->resumo();

        $this->assertSame(1, $resumo['sem_pagina']);
        $this->assertSame(2, $resumo['trabalhos'], 'continua sendo um trabalho');
    }

    public function test_dia_sem_impressao_aparece_zerado_em_vez_de_sumir(): void
    {
        /*
         | Dia ausente do GROUP BY emendaria a segunda na sexta anterior, como
         | se o fim de semana não existisse.
         */
        $this->trabalho(['impresso_em' => now()->subDays(3)]);

        $dias = PrintStatsService::periodo(now()->subDays(4)->toDateString(), now()->toDateString())->porDia();

        $this->assertCount(5, $dias);
        $this->assertSame(1, $dias->sum(fn ($d): int => $d->paginas > 0 ? 1 : 0));
    }

    public function test_por_horario_cobre_as_24_horas(): void
    {
        $this->trabalho();

        $horas = PrintStatsService::periodo(now()->subDays(3)->toDateString(), now()->toDateString())->porHorario();

        $this->assertCount(24, $horas);
        $this->assertSame(range(0, 23), $horas->pluck('hora')->all());
    }

    public function test_por_horario_usa_o_fuso_de_brasilia(): void
    {
        /*
         | Os dados estão em UTC. Sem o deslocamento, um trabalho das 14h
         | apareceria às 17h — e CONVERT_TZ não serve porque as tabelas de
         | fuso do MySQL não estão carregadas neste servidor.
         */
        $this->trabalho(['impresso_em' => '2026-09-22 17:30:00']); // 14:30 em Brasília

        $horas = PrintStatsService::periodo('2026-09-22', '2026-09-22')->porHorario();

        $this->assertSame(1, $horas->firstWhere('hora', 14)->trabalhos);
        $this->assertSame(0, $horas->firstWhere('hora', 17)->trabalhos);
    }

    public function test_periodo_invertido_e_corrigido_em_vez_de_dar_tela_vazia(): void
    {
        $this->trabalho(['impresso_em' => '2026-09-22 12:00:00']);

        $stats = PrintStatsService::periodo('2026-09-25', '2026-09-20');

        $this->assertSame(1, $stats->resumo()['trabalhos']);
    }

    public function test_servidor_esperado_que_nunca_enviou_aparece_na_lista(): void
    {
        // Servidor que para de enviar sumiria dos números sem ninguém notar.
        $this->trabalho(['servidor' => 'IMP01']);

        $servidores = PrintStatsService::periodo(null, null)->porServidor();

        $this->assertSame(['IMP01', 'IMP02', 'IMP03'], $servidores->pluck('servidor')->all());
        $this->assertSame(0, $servidores->firstWhere('servidor', 'IMP02')->trabalhos);
        $this->assertNull($servidores->firstWhere('servidor', 'IMP02')->ultimo);
    }

    public function test_origem_nao_listada_aparece_marcada(): void
    {
        // A planilha importada entra como PLANILHA e precisa ser visível.
        $this->trabalho(['servidor' => 'PLANILHA']);

        $planilha = PrintStatsService::periodo(null, null)->porServidor()->firstWhere('servidor', 'PLANILHA');

        $this->assertNotNull($planilha);
        $this->assertFalse($planilha->esperado);
    }
}
