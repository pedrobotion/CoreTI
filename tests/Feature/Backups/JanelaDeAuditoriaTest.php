<?php

namespace Tests\Feature\Backups;

use App\Models\CmdbBackupCheck;
use App\Models\CmdbBackupTarget;
use App\Services\Backups\BackupVerificationService;
use App\Services\Backups\Verification\BackupVerificationStrategy;
use App\Services\Backups\Verification\StrategyResolver;
use App\Services\Backups\Verification\VerificationResult;
use App\Support\Backups\BackupStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Janela de auditoria: horário esperado + tolerância decidem QUANDO auditar.
 *
 * Antes a decisão era só last_checked_at + periodicidade, o que fazia uma
 * verificação manual às 16h empurrar a auditoria automática para as 16h do
 * dia seguinte.
 */
class JanelaDeAuditoriaTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function simular(VerificationResult $r): void
    {
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

    private function saudavel(): VerificationResult
    {
        return new VerificationResult(
            status: BackupStatus::OK,
            arquivoExiste: true,
            tamanhoBytes: 10 * 1024 ** 3,
            modificadoEm: now()->subHours(2),
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
            'horario_esperado' => '02:00',
            'tolerancia_horas' => 2,
            'max_idade_horas' => 36,
            'estrategia_tamanho' => 'automatico',
            'ativo' => true,
            'origem_cadastro' => 'manual',
            'monitorar_a_partir_de' => now()->subMonth(),
        ], $override));
    }

    // ------------------------------------------------------ janela diária

    public function test_antes_do_horario_esperado_a_janela_do_dia_nao_e_cobrada(): void
    {
        Carbon::setTestNow('2026-09-01 01:00:00');
        $alvo = $this->alvo();

        // A janela relevante é a de ONTEM (02:00 + 2h = 04:00 de 31/08).
        $this->assertSame('2026-08-31 04:00:00', $alvo->fimDaJanelaMaisRecente()->format('Y-m-d H:i:s'));
    }

    public function test_dentro_da_tolerancia_a_janela_ainda_nao_fechou(): void
    {
        Carbon::setTestNow('2026-09-01 03:00:00');
        $alvo = $this->alvo();

        // 03:00 está dentro de 02:00+2h, então a janela de hoje não conta ainda.
        $this->assertSame('2026-08-31 04:00:00', $alvo->fimDaJanelaMaisRecente()->format('Y-m-d H:i:s'));
    }

    public function test_depois_da_tolerancia_a_janela_do_dia_passa_a_valer(): void
    {
        Carbon::setTestNow('2026-09-01 04:30:00');
        $alvo = $this->alvo();

        $this->assertSame('2026-09-01 04:00:00', $alvo->fimDaJanelaMaisRecente()->format('Y-m-d H:i:s'));
        $this->assertTrue($alvo->deveVerificarAgora());
    }

    public function test_janela_ja_auditada_nao_repete(): void
    {
        Carbon::setTestNow('2026-09-01 04:30:00');
        $alvo = $this->alvo(['ultima_janela_auditada' => Carbon::parse('2026-09-01 04:00:00')]);

        $this->assertFalse($alvo->deveVerificarAgora());

        // No dia seguinte, depois da tolerância, volta a ser devida.
        Carbon::setTestNow('2026-09-02 04:30:00');
        $this->assertTrue($alvo->deveVerificarAgora());
    }

    // ----------------------------------------- manual não desloca o scheduler

    public function test_verificacao_manual_nao_desloca_a_auditoria_do_dia_seguinte(): void
    {
        $this->simular($this->saudavel());

        Carbon::setTestNow('2026-09-01 04:30:00');
        $alvo = $this->alvo();
        app(BackupVerificationService::class)->verificar($alvo, 'scheduler');
        $alvo->refresh();

        // Execução manual às 16:00 do mesmo dia.
        Carbon::setTestNow('2026-09-01 16:00:00');
        app(BackupVerificationService::class)->verificar($alvo, 'manual');
        $alvo->refresh();

        $this->assertSame('2026-09-01 16:00:00', $alvo->last_checked_at->format('Y-m-d H:i:s'));
        $this->assertSame(
            '2026-09-01 04:00:00',
            $alvo->ultima_janela_auditada->format('Y-m-d H:i:s'),
            'a verificação manual não pode fechar janela'
        );

        // No dia seguinte, logo após a tolerância, o scheduler deve rodar —
        // e não 24h depois das 16:00.
        Carbon::setTestNow('2026-09-02 04:30:00');
        $this->assertTrue($alvo->deveVerificarAgora());
    }

    // ---------------------------------------------------------- periodicidade

    public function test_alvo_horario_usa_o_minuto_do_horario_esperado(): void
    {
        Carbon::setTestNow('2026-09-01 10:40:00');
        $alvo = $this->alvo(['periodicidade' => 'horaria', 'horario_esperado' => '00:15', 'tolerancia_horas' => 0]);

        $this->assertSame('2026-09-01 10:15:00', $alvo->fimDaJanelaMaisRecente()->format('Y-m-d H:i:s'));
        $this->assertTrue($alvo->deveVerificarAgora());
    }

    public function test_alvo_semanal_alinha_no_dia_da_semana_da_ancora(): void
    {
        // Âncora numa terça-feira.
        Carbon::setTestNow('2026-09-04 10:00:00'); // sexta
        $alvo = $this->alvo([
            'periodicidade' => 'semanal',
            'monitorar_a_partir_de' => Carbon::parse('2026-08-25 00:00:00'), // terça
        ]);

        $fim = $alvo->fimDaJanelaMaisRecente();

        $this->assertSame(Carbon::TUESDAY, $fim->copy()->subHours(2)->dayOfWeek);
        $this->assertSame('2026-09-01 04:00:00', $fim->format('Y-m-d H:i:s'));
    }

    public function test_alvo_mensal_usa_o_dia_do_mes_da_ancora(): void
    {
        Carbon::setTestNow('2026-09-20 10:00:00');
        $alvo = $this->alvo([
            'periodicidade' => 'mensal',
            'monitorar_a_partir_de' => Carbon::parse('2026-07-10 00:00:00'),
        ]);

        $this->assertSame('2026-09-10 04:00:00', $alvo->fimDaJanelaMaisRecente()->format('Y-m-d H:i:s'));
    }

    // -------------------------------------------------- alvo recém-cadastrado

    public function test_alvo_recem_cadastrado_nao_gera_falso_positivo(): void
    {
        // Cadastrado 31/08 12:00; o backup só é esperado em 01/09 02:00.
        Carbon::setTestNow('2026-08-31 12:00:00');
        $alvo = $this->alvo(['monitorar_a_partir_de' => now()]);

        Carbon::setTestNow('2026-08-31 18:00:00');

        // A janela mais recente (31/08 04:00) é anterior ao monitoramento.
        $this->assertNull($alvo->fimDaJanelaMaisRecente());
        $this->assertFalse($alvo->deveVerificarAgora());

        // Depois da primeira janela real, passa a ser auditado.
        Carbon::setTestNow('2026-09-01 04:30:00');
        $this->assertTrue($alvo->deveVerificarAgora());
    }

    public function test_verificacao_manual_e_permitida_mesmo_antes_da_primeira_janela(): void
    {
        // Relógio congelado antes de montar a observação, senão o mtime é
        // calculado contra o tempo real e não contra o do teste.
        Carbon::setTestNow('2026-08-31 18:00:00');
        $this->simular($this->saudavel());
        $alvo = $this->alvo(['monitorar_a_partir_de' => Carbon::parse('2026-08-31 12:00:00')]);

        $this->assertFalse($alvo->deveVerificarAgora());

        $check = app(BackupVerificationService::class)->verificar($alvo, 'manual');

        $this->assertNotNull($check);
        $this->assertSame(BackupStatus::OK, $check->status);
        $this->assertNull($alvo->fresh()->ultima_janela_auditada);
    }

    public function test_monitorar_a_partir_de_no_futuro_suspende_a_auditoria(): void
    {
        Carbon::setTestNow('2026-09-01 10:00:00');
        $alvo = $this->alvo(['monitorar_a_partir_de' => Carbon::parse('2026-09-10 00:00:00')]);

        $this->assertFalse($alvo->deveVerificarAgora());
    }

    // --------------------------------------------------------- problema aberto

    public function test_problema_aberto_e_reavaliado_antes_da_periodicidade(): void
    {
        Carbon::setTestNow('2026-09-01 05:00:00');
        $alvo = $this->alvo([
            'last_status' => BackupStatus::ATRASADO,
            'last_checked_at' => Carbon::parse('2026-09-01 04:30:00'),
            'ultima_janela_auditada' => Carbon::parse('2026-09-01 04:00:00'),
        ]);

        // Ainda dentro da janela de reverificação (55 min).
        $this->assertFalse($alvo->deveVerificarAgora());

        Carbon::setTestNow('2026-09-01 05:30:00');
        $this->assertTrue($alvo->deveVerificarAgora(), 'problema aberto deve voltar a ser checado dentro da hora');
    }

    public function test_alvo_inativo_nunca_e_devido(): void
    {
        Carbon::setTestNow('2026-09-01 04:30:00');

        $this->assertFalse($this->alvo(['ativo' => false])->deveVerificarAgora());
    }

    // ----------------------------------- idade máxima é conceito independente

    public function test_janela_fechada_com_arquivo_velho_acusa_atraso_pela_idade(): void
    {
        // Relógio congelado ANTES de montar a observação, para a idade ser
        // calculada contra o mesmo instante.
        Carbon::setTestNow('2026-09-01 04:30:00');

        $this->simular(new VerificationResult(
            status: BackupStatus::OK,
            arquivoExiste: true,
            tamanhoBytes: 10 * 1024 ** 3,
            modificadoEm: now()->subHours(50),
        ));

        $check = app(BackupVerificationService::class)->verificar($this->alvo(['max_idade_horas' => 36]));

        $this->assertSame(BackupStatus::ATRASADO, $check->status);
        $this->assertSame(50, $check->idade_horas);
    }

    /**
     * Relógio do servidor auditado adiantado: a auditoria precisa registrar
     * o resultado, não estourar. idade_horas é unsigned no banco.
     */
    public function test_arquivo_com_data_no_futuro_nao_derruba_a_verificacao(): void
    {
        Carbon::setTestNow('2026-09-01 10:00:00');

        $this->simular(new VerificationResult(
            status: BackupStatus::OK,
            arquivoExiste: true,
            tamanhoBytes: 10 * 1024 ** 3,
            modificadoEm: now()->addHours(3),
        ));

        $check = app(BackupVerificationService::class)->verificar($this->alvo(), 'manual');

        $this->assertNotNull($check);
        $this->assertSame(0, $check->idade_horas, 'idade negativa deve ser limitada a zero');
        $this->assertStringContainsString('à frente do relógio do CoreTI', $check->detalhe);
        $this->assertStringContainsString('sincronização de horário', $check->detalhe);
    }

    public function test_situacao_da_janela_e_legivel(): void
    {
        Carbon::setTestNow('2026-09-01 04:30:00');

        $this->assertStringContainsString('01/09/2026 04:00', $this->alvo()->situacaoDaJanela());
        $this->assertStringContainsString(
            'Sem janela definida',
            $this->alvo(['horario_esperado' => null, 'host' => 'outro'])->situacaoDaJanela()
        );
    }

    public function test_sem_horario_esperado_o_ciclo_usa_a_janela_auditada(): void
    {
        Carbon::setTestNow('2026-09-01 10:00:00');
        $alvo = $this->alvo([
            'horario_esperado' => null,
            'ultima_janela_auditada' => Carbon::parse('2026-09-01 09:00:00'),
            // Manual recente não pode antecipar o próximo ciclo.
            'last_checked_at' => Carbon::parse('2026-09-01 09:59:00'),
        ]);

        $this->assertFalse($alvo->deveVerificarAgora());

        Carbon::setTestNow('2026-09-02 09:30:00');
        $this->assertTrue($alvo->deveVerificarAgora());
    }
}
