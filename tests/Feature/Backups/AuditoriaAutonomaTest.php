<?php

namespace Tests\Feature\Backups;

use App\Models\CmdbBackupCheck;
use App\Models\CmdbBackupIncident;
use App\Models\CmdbBackupTarget;
use App\Models\CmdbDatabaseBackup;
use App\Models\User;
use App\Services\Backups\BackupVerificationService;
use App\Services\Backups\Verification\BackupVerificationStrategy;
use App\Services\Backups\Verification\StrategyResolver;
use App\Services\Backups\Verification\VerificationResult;
use App\Services\ModuleAccessService;
use App\Support\Backups\BackupStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Auditoria autônoma: o CoreTI decide o que verificar pelo próprio cadastro.
 *
 * A estratégia de SSH é substituída por um duplo controlado, para que os
 * testes exercitem as REGRAS (idade, tamanho, incidentes, Jira) sem depender
 * de um servidor remoto.
 */
class AuditoriaAutonomaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['backups.abertura' => [
            'tipos' => ['backup', 'auditoria'],
            'minimo_ocorrencias' => 1,
            'minimo_minutos' => 0,
            'exigir_sucesso_anterior' => false,
        ]]);

        config([
            'services.jira.enabled' => false,
            'backups.stale_after_hours' => 36,
        ]);
    }

    /**
     * Instala uma estratégia que devolve exatamente o resultado informado.
     */
    private function simularObservacao(VerificationResult $resultado): void
    {
        $estrategia = new class($resultado) implements BackupVerificationStrategy
        {
            public function __construct(private VerificationResult $r)
            {
            }

            public function chave(): string
            {
                return 'ssh_arquivo';
            }

            public function rotulo(): string
            {
                return 'Duplo de teste';
            }

            public function tiposSuportados(): array
            {
                return ['progress'];
            }

            public function verificar(CmdbBackupTarget $t): VerificationResult
            {
                return $this->r;
            }
        };

        $resolver = new class($estrategia) extends StrategyResolver
        {
            public function __construct(private BackupVerificationStrategy $e)
            {
            }

            public function para(CmdbBackupTarget $t): BackupVerificationStrategy
            {
                return $this->e;
            }

            public function opcoes(): array
            {
                return ['ssh_arquivo' => 'Duplo de teste'];
            }
        };

        $this->app->instance(StrategyResolver::class, $resolver);
    }

    private function arquivoSaudavel(int $gb = 10, int $horasAtras = 2): VerificationResult
    {
        return new VerificationResult(
            status: BackupStatus::OK,
            arquivoExiste: true,
            tamanhoBytes: $gb * 1024 ** 3,
            modificadoEm: now()->subHours($horasAtras),
            duracaoMs: 42,
        );
    }

    private function alvo(array $override = []): CmdbBackupTarget
    {
        return CmdbBackupTarget::create(array_merge([
            'host' => 'sdb1',
            'database_name' => 'dbmon',
            'arquivo' => '/backup/dbmon/dbmon.onl',
            'tipo_backup' => 'progress',
            'metodo_verificacao' => 'ssh_arquivo',
            'periodicidade' => 'diaria',
            'tolerancia_horas' => 6,
            'max_idade_horas' => 36,
            'estrategia_tamanho' => 'automatico',
            'ativo' => true,
            'origem_cadastro' => 'manual',
        ], $override));
    }

    private function verificar(CmdbBackupTarget $alvo): ?CmdbBackupCheck
    {
        return app(BackupVerificationService::class)->verificar($alvo);
    }

    // ------------------------------------------------ independência do webhook

    /** O teste central do pedido: nada de webhook, nada de agente. */
    public function test_alvo_cadastrado_e_auditado_sem_nenhum_evento_de_webhook(): void
    {
        CmdbDatabaseBackup::query()->delete();
        $this->assertSame(0, CmdbDatabaseBackup::count());

        $this->simularObservacao($this->arquivoSaudavel());
        $alvo = $this->alvo();

        $this->artisan('backups:verificar')->assertExitCode(0);

        $this->assertSame(0, CmdbDatabaseBackup::count(), 'a auditoria não deve criar nem exigir eventos');
        $this->assertSame(BackupStatus::OK, $alvo->fresh()->last_status);
        $this->assertSame(1, CmdbBackupCheck::where('target_id', $alvo->id)->count());
    }

    public function test_auditoria_nao_usa_o_servico_de_descoberta(): void
    {
        // Se o comando dependesse da descoberta, este duplo explodiria.
        $this->app->bind(\App\Services\Backups\BackupTargetDiscoveryService::class, function () {
            throw new \RuntimeException('A auditoria não pode depender da descoberta por evento.');
        });

        $this->simularObservacao($this->arquivoSaudavel());
        $this->alvo();

        $this->artisan('backups:verificar')->assertExitCode(0);
    }

    public function test_evento_de_webhook_isolado_nao_gera_auditoria(): void
    {
        CmdbDatabaseBackup::create([
            'host' => 'outro', 'database_name' => 'x', 'status' => 'OK', 'sucesso' => true,
            'arquivo' => '/backup/x.onl', 'fingerprint' => hash('sha256', 'x'),
            'data_backup' => now(), 'received_at' => now(),
        ]);

        $this->artisan('backups:verificar')
            ->expectsOutputToContain('Nenhum alvo de backup ativo cadastrado')
            ->assertExitCode(0);

        $this->assertSame(0, CmdbBackupCheck::count());
    }

    // ---------------------------------------------------------------- estados

    public function test_arquivo_saudavel_resulta_em_ok(): void
    {
        $this->simularObservacao($this->arquivoSaudavel());
        $check = $this->verificar($this->alvo());

        $this->assertSame(BackupStatus::OK, $check->status);
        $this->assertSame(0, CmdbBackupIncident::count());
    }

    public function test_arquivo_ausente(): void
    {
        $this->simularObservacao(new VerificationResult(
            status: BackupStatus::ARQUIVO_AUSENTE,
            arquivoExiste: false,
            detalhe: 'Arquivo não encontrado.',
        ));

        $check = $this->verificar($this->alvo());

        $this->assertSame(BackupStatus::ARQUIVO_AUSENTE, $check->status);
        $this->assertSame('Arquivo ausente', BackupStatus::rotulo($check->status));
    }

    public function test_arquivo_velho_vira_atrasado(): void
    {
        $this->simularObservacao($this->arquivoSaudavel(horasAtras: 55));
        $check = $this->verificar($this->alvo(['max_idade_horas' => 36]));

        $this->assertSame(BackupStatus::ATRASADO, $check->status);
        $this->assertSame(55, $check->idade_horas);
        $this->assertStringContainsString('55h', $check->detalhe);
    }

    public function test_servidor_inacessivel_nao_e_falha_de_backup(): void
    {
        $this->simularObservacao(VerificationResult::falha(
            BackupStatus::SERVIDOR_INACESSIVEL,
            'Servidor não respondeu na porta 22.',
            erroConexao: 'connection timed out',
        ));

        $check = $this->verificar($this->alvo());

        $this->assertSame(BackupStatus::SERVIDOR_INACESSIVEL, $check->status);
        $this->assertTrue(BackupStatus::ehFalhaDeAuditoria($check->status));
        $this->assertFalse(BackupStatus::ehFalhaDeBackup($check->status));
        $this->assertNotNull($check->erro_conexao);
    }

    public function test_autenticacao_negada_e_estado_proprio(): void
    {
        $this->simularObservacao(VerificationResult::falha(
            BackupStatus::ERRO_AUTENTICACAO,
            'Chave SSH não autorizada.',
        ));

        $check = $this->verificar($this->alvo());

        $this->assertSame(BackupStatus::ERRO_AUTENTICACAO, $check->status);
        $this->assertSame('Erro de autenticação', BackupStatus::rotulo($check->status));
        $this->assertTrue(BackupStatus::ehFalhaDeAuditoria($check->status));
    }

    // ---------------------------------------------------------------- tamanho

    public function test_tamanho_minimo_manual_prevalece(): void
    {
        $this->simularObservacao($this->arquivoSaudavel(gb: 1));

        $check = $this->verificar($this->alvo([
            'estrategia_tamanho' => 'manual',
            'min_tamanho_bytes' => 5 * 1024 ** 3,
        ]));

        $this->assertSame(BackupStatus::TAMANHO_ANORMAL, $check->status);
        $this->assertStringContainsString('abaixo do esperado', $check->detalhe);
        // Nunca afirmamos corrupção.
        $this->assertStringNotContainsString('corromp', mb_strtolower($check->detalhe));
    }

    public function test_tamanho_minimo_historico_usa_verificacoes_anteriores(): void
    {
        $alvo = $this->alvo();

        // Três observações saudáveis de 10 GB formam a referência.
        $this->simularObservacao($this->arquivoSaudavel(gb: 10));
        for ($i = 0; $i < 3; $i++) {
            $this->verificar($alvo);
        }

        $this->assertSame((int) round(10 * 1024 ** 3 * 0.5), $alvo->fresh()->tamanhoMinimoBytes());

        // 2 GB é menos que a metade da média.
        $this->simularObservacao($this->arquivoSaudavel(gb: 2));
        $check = $this->verificar($alvo->fresh());

        $this->assertSame(BackupStatus::TAMANHO_ANORMAL, $check->status);
        $this->assertStringContainsString('histórico', $check->detalhe);
    }

    public function test_sem_historico_nao_acusa_tamanho_anormal(): void
    {
        $this->simularObservacao($this->arquivoSaudavel(gb: 1));

        $this->assertSame(BackupStatus::OK, $this->verificar($this->alvo())->status);
    }

    // -------------------------------------------------------------- incidentes

    public function test_incidente_e_criado_e_nao_duplica_chamado(): void
    {
        Http::fake();
        $this->simularObservacao($this->arquivoSaudavel(horasAtras: 60));
        $alvo = $this->alvo();

        $this->verificar($alvo);
        $this->verificar($alvo->fresh());
        $this->verificar($alvo->fresh());

        $this->assertSame(1, CmdbBackupIncident::count(), 'três falhas seguidas devem gerar um único incidente');

        $incidente = CmdbBackupIncident::firstOrFail();
        $this->assertSame(3, $incidente->ocorrencias);
        $this->assertTrue($incidente->ehRecorrente());
        $this->assertSame(3, CmdbBackupCheck::count(), 'o histórico registra todas as ocorrências');
    }

    public function test_mudanca_do_tipo_de_falha_encerra_o_incidente_anterior(): void
    {
        $alvo = $this->alvo();

        $this->simularObservacao($this->arquivoSaudavel(horasAtras: 60));
        $this->verificar($alvo);

        $this->simularObservacao(new VerificationResult(
            status: BackupStatus::ARQUIVO_AUSENTE, arquivoExiste: false, detalhe: 'sumiu'
        ));
        $this->verificar($alvo->fresh());

        $this->assertSame(2, CmdbBackupIncident::count());
        $this->assertSame(1, CmdbBackupIncident::abertos()->count());
        $this->assertSame(BackupStatus::ARQUIVO_AUSENTE, CmdbBackupIncident::abertos()->first()->tipo_falha);
    }

    public function test_recuperacao_fecha_o_incidente(): void
    {
        $alvo = $this->alvo();

        $this->simularObservacao($this->arquivoSaudavel(horasAtras: 60));
        $this->verificar($alvo);
        $this->assertSame(1, CmdbBackupIncident::abertos()->count());

        $this->simularObservacao($this->arquivoSaudavel(horasAtras: 2));
        $check = $this->verificar($alvo->fresh());

        $this->assertSame(BackupStatus::OK, $check->status);
        $this->assertSame(0, CmdbBackupIncident::abertos()->count());

        $incidente = CmdbBackupIncident::firstOrFail();
        $this->assertSame(CmdbBackupIncident::STATUS_RECUPERADO, $incidente->status);
        $this->assertNotNull($incidente->recuperado_em);
    }

    public function test_jira_nao_configurado_nao_reporta_sucesso(): void
    {
        config(['services.jira.enabled' => false]);
        $this->simularObservacao($this->arquivoSaudavel(horasAtras: 60));

        $this->verificar($this->alvo());

        $incidente = CmdbBackupIncident::firstOrFail();
        $this->assertSame(CmdbBackupIncident::JIRA_NAO_CONFIGURADO, $incidente->jira_status);
        $this->assertNull($incidente->jira_issue_key);
        $this->assertSame('Jira não configurado', $incidente->jiraStatusLabel());
    }

    public function test_jira_configurado_cria_chamado_uma_unica_vez(): void
    {
        config([
            'services.jira.enabled' => true,
            'services.jira.base_url' => 'https://exemplo.atlassian.net',
            'services.jira.email' => 'bot@exemplo.com',
            'services.jira.api_token' => 'token',
            // Fixa a chave em vez de herdar o .env: a abertura automática é
            // desligável por ambiente, e é justamente ela que está sob teste.
            'backups.jira.enabled' => true,
            'backups.jira.project_id' => '10005',
            'backups.jira.issue_type_id' => '10008',
            // Critérios de confirmação neutralizados: este teste é sobre
            // outra coisa, e herdar a regra de abertura faria ele falhar por
            // um motivo que não é o dele.
            'backups.abertura' => [
                'tipos' => ['backup', 'auditoria'],
                'minimo_ocorrencias' => 1,
                'minimo_minutos' => 0,
                'exigir_sucesso_anterior' => false,
            ],
        ]);

        Http::fake([
            '*/rest/api/3/issue' => Http::response(['key' => 'TIC-123'], 201),
            '*' => Http::response(['values' => []], 200),
        ]);

        $this->simularObservacao($this->arquivoSaudavel(horasAtras: 60));
        $alvo = $this->alvo();

        $this->verificar($alvo);
        $this->verificar($alvo->fresh());

        $incidente = CmdbBackupIncident::firstOrFail();
        $this->assertSame(CmdbBackupIncident::JIRA_CRIADO, $incidente->jira_status);
        $this->assertSame('TIC-123', $incidente->jira_issue_key);

        // Duas falhas seguidas, uma única criação de chamado.
        $criacoes = collect(Http::recorded())
            ->filter(fn ($par) => str_ends_with($par[0]->url(), '/rest/api/3/issue') && $par[0]->method() === 'POST')
            ->count();

        $this->assertSame(1, $criacoes, 'o Jira só pode ser acionado na primeira ocorrência do incidente');
    }

    // ------------------------------------------------------------- agendamento

    public function test_alvo_desativado_nao_e_verificado(): void
    {
        $this->simularObservacao($this->arquivoSaudavel());
        $this->alvo(['ativo' => false]);

        $this->artisan('backups:verificar')
            ->expectsOutputToContain('Nenhum alvo de backup ativo cadastrado')
            ->assertExitCode(0);

        $this->assertSame(0, CmdbBackupCheck::count());
    }

    public function test_periodicidade_evita_ssh_desnecessario(): void
    {
        $this->simularObservacao($this->arquivoSaudavel());
        $alvo = $this->alvo(['periodicidade' => 'diaria']);

        $this->artisan('backups:verificar')->assertExitCode(0);
        $this->assertSame(1, CmdbBackupCheck::count());

        // Logo em seguida, o alvo diário não é devido.
        $this->artisan('backups:verificar')->assertExitCode(0);
        $this->assertSame(1, CmdbBackupCheck::count());

        // --todos ignora a periodicidade.
        $this->artisan('backups:verificar --todos')->assertExitCode(0);
        $this->assertSame(2, CmdbBackupCheck::count());
    }

    public function test_alvo_com_problema_e_reavaliado_antes_do_periodo(): void
    {
        $this->simularObservacao($this->arquivoSaudavel(horasAtras: 60));
        $alvo = $this->alvo(['periodicidade' => 'diaria']);
        $this->verificar($alvo);

        // Passou a janela de reverificação, mas não o dia inteiro.
        Carbon::setTestNow(now()->addHour());
        $this->assertTrue($alvo->fresh()->deveVerificarAgora());
        Carbon::setTestNow();
    }

    public function test_execucao_manual_marca_a_origem(): void
    {
        $this->simularObservacao($this->arquivoSaudavel());
        $alvo = $this->alvo();

        app(BackupVerificationService::class)->verificar($alvo, 'manual');

        $this->assertSame('manual', CmdbBackupCheck::firstOrFail()->origem);
    }

    // ---------------------------------------------------------------- segurança

    public function test_host_malicioso_nao_chega_ao_shell(): void
    {
        $alvo = $this->alvo(['ssh_host' => 'sdb1; rm -rf /']);

        $check = $this->verificar($alvo);

        $this->assertSame(BackupStatus::ERRO_VERIFICACAO, $check->status);
        $this->assertStringContainsString('inválido', $check->detalhe);
    }

    public function test_caminho_com_espacos_e_acentos_e_aceito(): void
    {
        $alvo = $this->alvo(['arquivo' => '/backup/base de dados/backup diário.onl']);

        $this->assertTrue(app(\App\Services\Backups\SshRunner::class)->caminhoValido($alvo->arquivo));
    }

    public function test_caminho_relativo_ou_com_quebra_de_linha_e_recusado(): void
    {
        $runner = app(\App\Services\Backups\SshRunner::class);

        $this->assertFalse($runner->caminhoValido('backup/x.onl'));
        $this->assertFalse($runner->caminhoValido("/backup/x.onl\nrm -rf /"));
        $this->assertFalse($runner->caminhoValido(''));
    }

    // ---------------------------------------------------------------------- UI

    public function test_tela_de_alvos_exige_permissao_e_lista(): void
    {
        $this->simularObservacao($this->arquivoSaudavel());
        $alvo = $this->alvo();
        $this->verificar($alvo);

        $semAcesso = User::factory()->create(['role' => 'user', 'is_active' => true]);
        $this->actingAs($semAcesso)->get(route('cmdb.backup-targets.index'))->assertForbidden();

        $comAcesso = User::factory()->create(['role' => 'user', 'is_active' => true]);
        app(ModuleAccessService::class)->sync($comAcesso, ['cmdb' => ['view']]);

        $this->actingAs($comAcesso->fresh())
            ->get(route('cmdb.backup-targets.index'))
            ->assertOk()
            ->assertSee('sdb1')
            ->assertSee('dbmon');
    }

    public function test_crud_de_alvo_pela_tela(): void
    {
        $gestor = User::factory()->create(['role' => 'user', 'is_active' => true]);
        app(ModuleAccessService::class)->sync($gestor, ['cmdb' => ['manage']]);
        $gestor = $gestor->fresh();

        $this->actingAs($gestor)->post(route('cmdb.backup-targets.store'), [
            'host' => 'sdb2',
            'database_name' => 'folha',
            'arquivo' => '/backup/folha/folha.onl',
            'tipo_backup' => 'progress',
            'metodo_verificacao' => 'progress_arquivo',
            'periodicidade' => 'diaria',
            'estrategia_tamanho' => 'automatico',
            'ativo' => 1,
        ])->assertRedirect();

        $alvo = CmdbBackupTarget::where('host', 'sdb2')->firstOrFail();
        $this->assertSame('manual', $alvo->origem_cadastro);

        $this->actingAs($gestor)->patch(route('cmdb.backup-targets.toggle', $alvo))->assertRedirect();
        $this->assertFalse($alvo->fresh()->ativo);

        $this->actingAs($gestor)->get(route('cmdb.backup-targets.show', $alvo))->assertOk()->assertSee('folha');
    }

    public function test_caminho_invalido_e_recusado_no_cadastro(): void
    {
        $gestor = User::factory()->create(['role' => 'user', 'is_active' => true]);
        app(ModuleAccessService::class)->sync($gestor, ['cmdb' => ['manage']]);

        $this->actingAs($gestor->fresh())->post(route('cmdb.backup-targets.store'), [
            'host' => 'sdb1; rm -rf /',
            'database_name' => 'x',
            'arquivo' => 'relativo.onl',
            'tipo_backup' => 'progress',
            'metodo_verificacao' => 'ssh_arquivo',
            'periodicidade' => 'diaria',
            'estrategia_tamanho' => 'automatico',
        ])->assertSessionHasErrors(['host', 'arquivo']);
    }
}
