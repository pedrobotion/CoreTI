<?php

namespace Tests\Feature\Backups;

use App\Models\CmdbBackupCheck;
use App\Models\CmdbBackupIncident;
use App\Models\CmdbBackupTarget;
use App\Services\Backups\BackupVerificationService;
use App\Services\Backups\SshRunner;
use App\Services\Backups\Verification\BackupVerificationStrategy;
use App\Services\Backups\Verification\StrategyResolver;
use App\Services\Backups\Verification\VerificationResult;
use App\Support\Backups\BackupStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Cobre os três achados da revisão além do agendamento:
 * média histórica real, cooldown temporal do Jira e ciclo de vida do incidente.
 */
class TamanhoCooldownIncidenteTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
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
            'estrategia_tamanho' => 'automatico',
            'max_idade_horas' => 36,
            'ativo' => true,
            'origem_cadastro' => 'manual',
        ], $override));
    }

    private function check(CmdbBackupTarget $alvo, float $gb, string $status, string $quando): CmdbBackupCheck
    {
        return CmdbBackupCheck::create([
            'target_id' => $alvo->id,
            'executado_em' => Carbon::parse($quando),
            'status' => $status,
            'arquivo_existe' => $status === BackupStatus::OK,
            'arquivo_tamanho_bytes' => (int) round($gb * 1024 ** 3),
            'arquivo_modificado_em' => Carbon::parse($quando),
            'origem' => 'scheduler',
        ]);
    }

    // ------------------------------------------------------ média histórica

    public function test_somente_os_cinco_checks_mais_recentes_entram_na_media(): void
    {
        $alvo = $this->alvo();

        // Backup antigo e enorme, que não pode influenciar.
        $this->check($alvo, 100, BackupStatus::OK, '2024-01-01 02:00:00');

        foreach ([10.0, 10.0, 9.8, 10.2, 10.1] as $i => $gb) {
            $this->check($alvo, $gb, BackupStatus::OK, '2026-08-'.(20 + $i).' 02:00:00');
        }

        $mediaEsperada = (10.0 + 10.0 + 9.8 + 10.2 + 10.1) / 5;
        $bytesEsperados = (int) round($mediaEsperada * 1024 ** 3 * 0.5);

        $this->assertEqualsWithDelta(
            $bytesEsperados,
            $alvo->fresh()->tamanhoMinimoHistorico(),
            1024,
            'o check de 2024 não pode entrar na conta'
        );
    }

    public function test_checks_com_falha_nao_entram_na_media(): void
    {
        $alvo = $this->alvo();

        $this->check($alvo, 10, BackupStatus::OK, '2026-08-25 02:00:00');
        $this->check($alvo, 0.1, BackupStatus::TAMANHO_ANORMAL, '2026-08-26 02:00:00');
        $this->check($alvo, 0, BackupStatus::ARQUIVO_AUSENTE, '2026-08-27 02:00:00');

        // Só o de 10 GB conta.
        $this->assertEqualsWithDelta(
            (int) round(10 * 1024 ** 3 * 0.5),
            $alvo->fresh()->tamanhoMinimoHistorico(),
            1024
        );
    }

    public function test_tamanho_manual_prevalece_sobre_o_historico(): void
    {
        $alvo = $this->alvo(['estrategia_tamanho' => 'manual', 'min_tamanho_bytes' => 999]);

        foreach (range(1, 6) as $i) {
            $this->check($alvo, 10, BackupStatus::OK, '2026-08-2'.$i.' 02:00:00');
        }

        $this->assertSame(999, $alvo->fresh()->tamanhoMinimoBytes());
        $this->assertSame('manual', $alvo->fresh()->origemTamanhoMinimo());
    }

    // ------------------------------------------------------ cooldown do Jira

    private function jiraConfigurado(): void
    {
        config([
            'services.jira.enabled' => true,
            'services.jira.base_url' => 'https://exemplo.atlassian.net',
            'services.jira.email' => 'bot@exemplo.com',
            'services.jira.api_token' => 'token-fake-de-teste',
            'backups.jira_cooldown_minutes' => 360,
        ]);
    }

    private function simularFalha(): void
    {
        $r = new VerificationResult(
            status: BackupStatus::ARQUIVO_AUSENTE,
            arquivoExiste: false,
            detalhe: 'arquivo não encontrado',
        );

        $estrategia = new class($r) implements BackupVerificationStrategy
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
                return 'duplo';
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

        $this->app->instance(StrategyResolver::class, new class($estrategia) extends StrategyResolver
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
                return [];
            }
        });
    }

    private function criacoesDeChamado(): int
    {
        return collect(Http::recorded())
            ->filter(fn ($par) => str_ends_with($par[0]->url(), '/rest/api/3/issue') && $par[0]->method() === 'POST')
            ->count();
    }

    public function test_cooldown_do_jira_dura_a_janela_inteira(): void
    {
        $this->jiraConfigurado();
        $this->simularFalha();

        Http::fake([
            '*/rest/api/3/issue' => Http::response(['errors' => ['indisponivel']], 500),
            '*' => Http::response(['values' => []], 200),
        ]);

        $servico = app(BackupVerificationService::class);

        Carbon::setTestNow('2026-09-01 10:00:00');
        $alvo = $this->alvo();
        $servico->verificar($alvo);

        $incidente = CmdbBackupIncident::firstOrFail();
        $this->assertSame(CmdbBackupIncident::JIRA_ERRO, $incidente->jira_status);
        $this->assertSame(
            '2026-09-01 16:00:00',
            $incidente->jira_proxima_tentativa_em->format('Y-m-d H:i:s')
        );
        $this->assertSame(1, $this->criacoesDeChamado());

        // Durante a janela, nenhuma nova tentativa — nem na segunda execução,
        // que era exatamente onde o bug anterior escapava.
        foreach (['2026-09-01 11:00:00', '2026-09-01 12:00:00', '2026-09-01 15:59:00'] as $momento) {
            Carbon::setTestNow($momento);
            $servico->verificar($alvo->fresh());

            $this->assertSame(1, $this->criacoesDeChamado(), "não podia tentar às {$momento}");
            $this->assertSame(
                CmdbBackupIncident::JIRA_EM_COOLDOWN,
                CmdbBackupIncident::firstOrFail()->jira_status
            );
        }

        // Ao fim da janela, tenta de novo.
        Carbon::setTestNow('2026-09-01 16:00:00');
        $servico->verificar($alvo->fresh());

        $this->assertSame(2, $this->criacoesDeChamado());
    }

    public function test_chamado_criado_nao_e_recriado(): void
    {
        $this->jiraConfigurado();
        $this->simularFalha();

        Http::fake([
            '*/rest/api/3/issue' => Http::response(['key' => 'TIC-500'], 201),
            '*' => Http::response(['values' => []], 200),
        ]);

        $servico = app(BackupVerificationService::class);

        Carbon::setTestNow('2026-09-01 10:00:00');
        $alvo = $this->alvo();
        $servico->verificar($alvo);

        Carbon::setTestNow('2026-09-01 11:00:00');
        $servico->verificar($alvo->fresh());

        $this->assertSame(1, $this->criacoesDeChamado());
        $this->assertSame('TIC-500', CmdbBackupIncident::firstOrFail()->jira_issue_key);
    }

    public function test_jira_nao_configurado_nao_bloqueia_tentativa_futura(): void
    {
        config(['services.jira.enabled' => false]);
        $this->simularFalha();

        Carbon::setTestNow('2026-09-01 10:00:00');
        $servico = app(BackupVerificationService::class);
        $servico->verificar($this->alvo());

        $incidente = CmdbBackupIncident::firstOrFail();

        $this->assertSame(CmdbBackupIncident::JIRA_NAO_CONFIGURADO, $incidente->jira_status);
        $this->assertNull($incidente->jira_issue_key);
        // Sem cooldown: configurar as credenciais deve valer na próxima execução.
        $this->assertNull($incidente->jira_proxima_tentativa_em);
        $this->assertTrue($incidente->podeTentarJira());
    }

    // -------------------------------------------------- ciclo do incidente

    private function simularStatus(string $status): void
    {
        // O serviço deriva o status a partir da OBSERVAÇÃO, então o duplo
        // precisa devolver evidências coerentes: "atrasado" é arquivo que
        // existe e está velho, não arquivo ausente.
        $r = match ($status) {
            BackupStatus::OK => new VerificationResult(
                status: BackupStatus::OK,
                arquivoExiste: true,
                tamanhoBytes: 10 * 1024 ** 3,
                modificadoEm: now()->subHour(),
            ),
            BackupStatus::ATRASADO => new VerificationResult(
                status: BackupStatus::OK,
                arquivoExiste: true,
                tamanhoBytes: 10 * 1024 ** 3,
                modificadoEm: now()->subHours(72),
            ),
            default => new VerificationResult(
                status: $status,
                arquivoExiste: false,
                detalhe: 'falha '.$status,
            ),
        };

        $estrategia = new class($r) implements BackupVerificationStrategy
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
                return 'duplo';
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

        $this->app->instance(StrategyResolver::class, new class($estrategia) extends StrategyResolver
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
                return [];
            }
        });
    }

    public function test_mesma_falha_mantem_o_incidente(): void
    {
        config(['services.jira.enabled' => false]);

        Carbon::setTestNow('2026-09-01 08:00:00');
        $this->simularStatus(BackupStatus::ATRASADO);
        $alvo = $this->alvo();
        app(BackupVerificationService::class)->verificar($alvo);

        Carbon::setTestNow('2026-09-01 09:00:00');
        app(BackupVerificationService::class)->verificar($alvo->fresh());

        $this->assertSame(1, CmdbBackupIncident::count());
        $this->assertSame(2, CmdbBackupIncident::firstOrFail()->ocorrencias);
    }

    public function test_mudanca_de_tipo_marca_substituido_e_nao_recuperado(): void
    {
        config(['services.jira.enabled' => false]);

        Carbon::setTestNow('2026-09-01 08:00:00');
        $this->simularStatus(BackupStatus::ATRASADO);
        $alvo = $this->alvo();
        app(BackupVerificationService::class)->verificar($alvo);

        Carbon::setTestNow('2026-09-01 10:00:00');
        $this->simularStatus(BackupStatus::ARQUIVO_AUSENTE);
        app(BackupVerificationService::class)->verificar($alvo->fresh());

        $anterior = CmdbBackupIncident::where('tipo_falha', BackupStatus::ATRASADO)->firstOrFail();

        $this->assertSame(CmdbBackupIncident::STATUS_SUBSTITUIDO, $anterior->status);
        $this->assertNull($anterior->recuperado_em, 'o backup não se recuperou; o problema mudou de natureza');
        $this->assertNotNull($anterior->encerrado_em);
        $this->assertSame(CmdbBackupIncident::MOTIVO_TIPO_ALTERADO, $anterior->motivo_encerramento);
        $this->assertNull($anterior->duracaoDaIndisponibilidadeHoras());
        $this->assertSame('Substituído por outra falha', $anterior->statusLabel());

        $novo = CmdbBackupIncident::where('tipo_falha', BackupStatus::ARQUIVO_AUSENTE)->firstOrFail();
        $this->assertTrue($novo->estaAberto());
    }

    public function test_retorno_a_ok_e_recuperacao_real(): void
    {
        config(['services.jira.enabled' => false]);

        Carbon::setTestNow('2026-09-01 10:00:00');
        $this->simularStatus(BackupStatus::ARQUIVO_AUSENTE);
        $alvo = $this->alvo();
        app(BackupVerificationService::class)->verificar($alvo);

        Carbon::setTestNow('2026-09-01 14:00:00');
        $this->simularStatus(BackupStatus::OK);
        app(BackupVerificationService::class)->verificar($alvo->fresh());

        $incidente = CmdbBackupIncident::firstOrFail();

        $this->assertSame(CmdbBackupIncident::STATUS_RECUPERADO, $incidente->status);
        $this->assertNotNull($incidente->recuperado_em);
        $this->assertSame(CmdbBackupIncident::MOTIVO_RECUPERACAO, $incidente->motivo_encerramento);
        $this->assertSame(4, $incidente->duracaoDaIndisponibilidadeHoras());
    }

    public function test_mudanca_de_tipo_nao_comenta_normalizacao_no_jira(): void
    {
        $this->jiraConfigurado();
        Http::fake([
            '*/rest/api/3/issue' => Http::response(['key' => 'TIC-900'], 201),
            '*' => Http::response(['values' => []], 200),
        ]);

        Carbon::setTestNow('2026-09-01 08:00:00');
        $this->simularStatus(BackupStatus::ATRASADO);
        $alvo = $this->alvo();
        app(BackupVerificationService::class)->verificar($alvo);

        Carbon::setTestNow('2026-09-01 10:00:00');
        $this->simularStatus(BackupStatus::ARQUIVO_AUSENTE);
        app(BackupVerificationService::class)->verificar($alvo->fresh());

        $comentarios = collect(Http::recorded())
            ->filter(fn ($par) => str_contains($par[0]->url(), '/comment'))
            ->count();

        $this->assertSame(0, $comentarios, 'troca de tipo de falha não é normalização');
    }

    public function test_recuperacao_real_comenta_no_jira(): void
    {
        $this->jiraConfigurado();
        Http::fake([
            '*/rest/api/3/issue' => Http::response(['key' => 'TIC-901'], 201),
            '*/comment' => Http::response([], 201),
            '*' => Http::response(['values' => []], 200),
        ]);

        Carbon::setTestNow('2026-09-01 08:00:00');
        $this->simularStatus(BackupStatus::ATRASADO);
        $alvo = $this->alvo();
        app(BackupVerificationService::class)->verificar($alvo);

        Carbon::setTestNow('2026-09-01 14:00:00');
        $this->simularStatus(BackupStatus::OK);
        app(BackupVerificationService::class)->verificar($alvo->fresh());

        $comentarios = collect(Http::recorded())
            ->filter(fn ($par) => str_contains($par[0]->url(), '/comment'))
            ->count();

        $this->assertSame(1, $comentarios);
        $this->assertTrue(CmdbBackupIncident::firstOrFail()->jira_recuperacao_comentada);
    }

    /**
     * Regressão: MariaDB atribuía ON UPDATE CURRENT_TIMESTAMP à primeira
     * coluna TIMESTAMP NOT NULL, reescrevendo estas datas a cada update.
     */
    public function test_datas_nao_sao_reescritas_a_cada_atualizacao(): void
    {
        config(['services.jira.enabled' => false]);

        Carbon::setTestNow('2026-09-01 08:00:00');
        $this->simularStatus(BackupStatus::ARQUIVO_AUSENTE);
        $alvo = $this->alvo();
        $primeiro = app(BackupVerificationService::class)->verificar($alvo);

        Carbon::setTestNow('2026-09-01 09:00:00');
        app(BackupVerificationService::class)->verificar($alvo->fresh());

        $this->assertSame(
            '2026-09-01 08:00:00',
            $primeiro->fresh()->executado_em->format('Y-m-d H:i:s'),
            'a data do check não pode mudar quando o registro é atualizado'
        );

        $this->assertSame(
            '2026-09-01 08:00:00',
            CmdbBackupIncident::firstOrFail()->aberto_em->format('Y-m-d H:i:s'),
            'a abertura do incidente não pode ser reescrita a cada ocorrência'
        );
    }

    // ------------------------------------------------------------- segurança

    public function test_politica_de_caminho(): void
    {
        $runner = app(SshRunner::class);

        $this->assertTrue($runner->caminhoValido('/backup/base de dados/backup diário.onl'));
        $this->assertFalse($runner->caminhoValido('/backup/../etc/shadow'), '".." deve ser recusado');
        $this->assertFalse($runner->caminhoValido('/backup/..'));
        $this->assertFalse($runner->caminhoValido('relativo.onl'));
        $this->assertFalse($runner->caminhoValido("/backup/x\nrm -rf /"));
    }

    /** Sem duplo: exercita a validação real do SshRunner. */
    public function test_usuario_ssh_malicioso_e_recusado(): void
    {
        $check = app(BackupVerificationService::class)
            ->verificar($this->alvo(['ssh_user' => 'root; cat /etc/shadow']));

        $this->assertSame(BackupStatus::ERRO_VERIFICACAO, $check->status);
        $this->assertStringContainsString('inválido', $check->detalhe);
    }

    public function test_host_ssh_malicioso_e_recusado(): void
    {
        $check = app(BackupVerificationService::class)
            ->verificar($this->alvo(['ssh_host' => 'sdb1 && curl evil.example']));

        $this->assertSame(BackupStatus::ERRO_VERIFICACAO, $check->status);
    }

    public function test_wrapper_e_usado_quando_configurado(): void
    {
        config(['backups.ssh.wrapper' => '/usr/local/bin/coreti-backup-audit']);

        $comando = app(SshRunner::class)->comandoDeOperacao('stat', '/backup/base de dados/x.onl');

        $this->assertStringContainsString("'/usr/local/bin/coreti-backup-audit' 'stat'", $comando);
        // Espaços preservados por escaping, não por concatenação livre.
        $this->assertStringContainsString("'/backup/base de dados/x.onl'", $comando);
        $this->assertStringNotContainsString('stat -c', $comando);
    }

    public function test_sem_wrapper_o_comando_direto_escapa_o_caminho(): void
    {
        config(['backups.ssh.wrapper' => null]);

        $comando = app(SshRunner::class)->comandoDeOperacao('stat', '/backup/base de dados/x.onl');

        $this->assertStringStartsWith('stat -c', $comando);
        $this->assertStringContainsString("'/backup/base de dados/x.onl'", $comando);
    }
}
