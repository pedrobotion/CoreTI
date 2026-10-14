<?php

namespace Tests\Feature\Backups;

use App\Models\CmdbBackupCheck;
use App\Models\CmdbBackupIncident;
use App\Models\CmdbBackupTarget;
use App\Services\Backups\BackupIncidentService;
use App\Support\Backups\BackupStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Chamado só para problema confirmado, nunca para suspeita.
 *
 * Em 29/09 quatro chamados saíram numa rajada e se repetiram cinco vezes em
 * trinta minutos. Três dos quatro eram falha de AUDITORIA sobre alvos de teste
 * mal cadastrados — o CoreTI não conseguiu verificar, o que não afirma nada
 * sobre o backup. Só um era backup de verdade atrasado.
 *
 * Os quatro critérios abaixo se somam; todos precisam passar.
 */
class ConfirmacaoAntesDoChamadoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.jira.enabled' => true,
            'services.jira.base_url' => 'https://exemplo.atlassian.net',
            'services.jira.email' => 'bot@exemplo.com',
            'services.jira.api_token' => 'token',
            'backups.jira.enabled' => true,
            'backups.jira.project_id' => '10005',
            'backups.jira.issue_type_id' => '10008',
            'backups.abertura' => [
                'tipos' => ['backup'],
                'minimo_ocorrencias' => 3,
                'minimo_minutos' => 120,
                'exigir_sucesso_anterior' => true,
            ],
        ]);

        Http::fake([
            '*/rest/api/3/issue' => Http::response(['key' => 'TIC-999'], 201),
            '*' => Http::response(['values' => []], 200),
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function alvo(): CmdbBackupTarget
    {
        return CmdbBackupTarget::create([
            'host' => 'sdb1', 'database_name' => 'dbmon',
            'arquivo' => '/backup/dbmon/dbmon.onl', 'tipo_backup' => 'progress',
            'metodo_verificacao' => 'ssh_arquivo', 'periodicidade' => 'diaria',
            'estrategia_tamanho' => 'automatico', 'max_idade_horas' => 36,
            'ativo' => true, 'origem_cadastro' => 'manual',
        ]);
    }

    private function check(CmdbBackupTarget $alvo, string $status, string $quando): CmdbBackupCheck
    {
        return CmdbBackupCheck::create([
            'target_id' => $alvo->id,
            'executado_em' => Carbon::parse($quando),
            'status' => $status,
            'arquivo_existe' => $status === BackupStatus::OK,
            'origem' => 'scheduler',
        ]);
    }

    /** Roda N verificações com falha, espaçadas no tempo. */
    private function falharVezes(CmdbBackupTarget $alvo, int $vezes, string $status, int $horasEntre = 6): CmdbBackupIncident
    {
        $servico = app(BackupIncidentService::class);
        $incidente = null;

        for ($i = 0; $i < $vezes; $i++) {
            Carbon::setTestNow(Carbon::parse('2026-09-01 08:00:00')->addHours($i * $horasEntre));
            $incidente = $servico->registrar($alvo, $this->check($alvo, $status, now()->toDateTimeString()));
        }

        return $incidente->fresh();
    }

    public function test_uma_falha_sozinha_nao_abre_chamado(): void
    {
        $alvo = $this->alvo();
        $this->check($alvo, BackupStatus::OK, '2026-08-30 02:00:00');

        $incidente = $this->falharVezes($alvo, 1, BackupStatus::ATRASADO);

        $this->assertNull($incidente->jira_issue_key);
        $this->assertSame(CmdbBackupIncident::JIRA_AGUARDANDO_CONFIRMACAO, $incidente->jira_status);
        $this->assertStringContainsString('1 de 3', $incidente->jira_erro);
    }

    public function test_tres_falhas_espacadas_abrem_chamado(): void
    {
        $alvo = $this->alvo();
        $this->check($alvo, BackupStatus::OK, '2026-08-30 02:00:00');

        $incidente = $this->falharVezes($alvo, 3, BackupStatus::ATRASADO);

        $this->assertSame('TIC-999', $incidente->jira_issue_key);
    }

    public function test_tres_falhas_no_mesmo_instante_nao_confirmam(): void
    {
        /*
         | Três verificações em dois minutos são a mesma leitura repetida, não
         | confirmação. Foi o padrão da rajada de 29/09: cinco execuções
         | manuais em trinta minutos.
         */
        $alvo = $this->alvo();
        $this->check($alvo, BackupStatus::OK, '2026-08-30 02:00:00');

        $incidente = $this->falharVezes($alvo, 3, BackupStatus::ATRASADO, horasEntre: 0);

        $this->assertNull($incidente->jira_issue_key);
        $this->assertStringContainsString('menos de 120 minutos', $incidente->jira_erro);
    }

    public function test_falha_de_auditoria_nao_abre_chamado(): void
    {
        // O CoreTI não conseguiu verificar — isso não afirma que o backup
        // falhou. Três dos quatro chamados de 29/09 eram deste tipo.
        $alvo = $this->alvo();
        $this->check($alvo, BackupStatus::OK, '2026-08-30 02:00:00');

        $incidente = $this->falharVezes($alvo, 5, BackupStatus::ERRO_VERIFICACAO);

        $this->assertNull($incidente->jira_issue_key);
        $this->assertStringContainsString('não conseguiu verificar', $incidente->jira_erro);
    }

    public function test_alvo_que_nunca_funcionou_nao_abre_chamado(): void
    {
        // Sem nenhum sucesso no histórico, o problema é de cadastro.
        $alvo = $this->alvo();

        $incidente = $this->falharVezes($alvo, 5, BackupStatus::ATRASADO);

        $this->assertNull($incidente->jira_issue_key);
        $this->assertStringContainsString('nunca teve um backup verificado', $incidente->jira_erro);
    }

    public function test_perder_o_incidente_nao_reabre_chamado(): void
    {
        /*
         | Foi o que multiplicou a rajada: os incidentes eram apagados junto
         | com os alvos e cada execução recomeçava do zero. Com contagem
         | mínima, o incidente recriado volta a 1 e não abre nada.
         */
        $alvo = $this->alvo();
        $this->check($alvo, BackupStatus::OK, '2026-08-30 02:00:00');

        $incidente = $this->falharVezes($alvo, 3, BackupStatus::ATRASADO);
        $this->assertSame('TIC-999', $incidente->jira_issue_key);

        CmdbBackupIncident::query()->delete();

        $novo = $this->falharVezes($alvo, 1, BackupStatus::ATRASADO);

        $this->assertNull($novo->jira_issue_key);
    }

    public function test_o_incidente_continua_visivel_enquanto_nao_confirma(): void
    {
        // O que espera é o chamado, não o registro: o painel mostra desde a
        // primeira falha.
        $alvo = $this->alvo();
        $incidente = $this->falharVezes($alvo, 1, BackupStatus::ATRASADO);

        $this->assertSame(CmdbBackupIncident::STATUS_ABERTO, $incidente->status);
        $this->assertSame(1, $incidente->ocorrencias);
        $this->assertNotNull($incidente->detalhe ?? $incidente->aberto_em);
    }
}
