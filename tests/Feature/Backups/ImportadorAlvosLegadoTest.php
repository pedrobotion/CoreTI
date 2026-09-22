<?php

namespace Tests\Feature\Backups;

use App\Models\CmdbBackupCheck;
use App\Models\CmdbBackupTarget;
use App\Models\CmdbDatabaseBackup;
use App\Services\Backups\BackupTargetDiscoveryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Importador de migração (antigo BackupTargetDiscoveryService).
 *
 * Substitui o VerificacaoAtivaTest, que cobria a descoberta automática por
 * evento — comportamento removido de propósito na migração para auditoria
 * autônoma. O serviço permanece apenas como ferramenta manual: semeia o
 * cadastro a partir do histórico legado e NÃO participa do fluxo agendado.
 *
 * @see AuditoriaAutonomaTest cobre o fluxo real de auditoria
 */
class ImportadorAlvosLegadoTest extends TestCase
{
    use RefreshDatabase;

    private function evento(array $override = []): CmdbDatabaseBackup
    {
        static $n = 0;
        $n++;

        return CmdbDatabaseBackup::create(array_merge([
            'host' => 'sdb1',
            'database_name' => 'dbmon',
            'arquivo' => '/backup/dbmon/dbmon.onl',
            'ip_address' => '172.17.1.10',
            'status' => 'OK',
            'sucesso' => true,
            'tamanho' => 10.0,
            'data_backup' => now()->subHour(),
            'received_at' => now()->subHour(),
            'fingerprint' => hash('sha256', 'ev-'.$n),
        ], $override));
    }

    public function test_importa_alvos_do_historico_legado(): void
    {
        $this->evento();
        $this->evento(['host' => 'sdb2', 'database_name' => 'folha', 'arquivo' => '/backup/folha.onl']);

        $criados = app(BackupTargetDiscoveryService::class)->importarDoHistoricoLegado();

        $this->assertCount(2, $criados);
        $this->assertDatabaseHas('cmdb_backup_targets', [
            'host' => 'sdb1',
            'database_name' => 'dbmon',
            'ssh_host' => '172.17.1.10',
            'origem_cadastro' => 'importado_legado',
        ]);
    }

    /** Importado nasce inativo: alguém precisa conferir usuário e limites. */
    public function test_alvo_importado_nasce_inativo(): void
    {
        $this->evento();

        app(BackupTargetDiscoveryService::class)->importarDoHistoricoLegado();

        $this->assertFalse(CmdbBackupTarget::firstOrFail()->ativo);
    }

    public function test_importacao_e_idempotente(): void
    {
        $this->evento();

        $servico = app(BackupTargetDiscoveryService::class);
        $servico->importarDoHistoricoLegado();

        $this->assertCount(0, $servico->importarDoHistoricoLegado());
        $this->assertSame(1, CmdbBackupTarget::count());
    }

    public function test_evento_sem_arquivo_nao_vira_alvo(): void
    {
        $this->evento(['arquivo' => null]);

        $this->assertCount(0, app(BackupTargetDiscoveryService::class)->importarDoHistoricoLegado());
    }

    public function test_falha_do_agente_nao_define_arquivo_esperado(): void
    {
        $this->evento(['status' => 'ERRO', 'sucesso' => false]);

        $this->assertCount(0, app(BackupTargetDiscoveryService::class)->importarDoHistoricoLegado());
    }

    public function test_comando_de_importacao_avisa_que_e_ferramenta_de_migracao(): void
    {
        $this->evento();

        $this->artisan('backups:importar-alvos-legado')
            ->expectsOutputToContain('ferramenta de migração')
            ->assertExitCode(0);

        $this->assertSame(1, CmdbBackupTarget::count());
    }

    public function test_comando_pode_ativar_na_importacao(): void
    {
        $this->evento();

        $this->artisan('backups:importar-alvos-legado --ativar')->assertExitCode(0);

        $this->assertTrue(CmdbBackupTarget::firstOrFail()->ativo);
    }

    public function test_sem_historico_o_comando_orienta_o_cadastro_manual(): void
    {
        $this->artisan('backups:importar-alvos-legado')
            ->expectsOutputToContain('Cadastre os alvos manualmente')
            ->assertExitCode(0);
    }

    /**
     * Garantia arquitetural: o importador não é chamado pela auditoria.
     */
    public function test_auditoria_nao_importa_nada(): void
    {
        $this->evento();

        CmdbBackupTarget::create([
            'host' => 'cadastrado', 'database_name' => 'manual',
            'arquivo' => '/backup/manual.onl', 'tipo_backup' => 'progress',
            'metodo_verificacao' => 'ssh_arquivo', 'periodicidade' => 'diaria',
            'estrategia_tamanho' => 'automatico', 'ativo' => true,
            'origem_cadastro' => 'manual',
        ]);

        $this->artisan('backups:verificar')->assertExitCode(1);

        // Apenas o alvo cadastrado à mão existe; o evento legado foi ignorado.
        $this->assertSame(1, CmdbBackupTarget::count());
        $this->assertSame(1, CmdbBackupCheck::count());
        $this->assertSame('cadastrado', CmdbBackupTarget::firstOrFail()->host);
    }
}
