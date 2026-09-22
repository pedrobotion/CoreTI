<?php

namespace Tests\Feature\Backups;

use App\Models\CmdbDatabaseBackup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class BackupWebhookTest extends TestCase
{
    use RefreshDatabase;

    private const TOKEN = 'token-de-teste';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'backups.webhook.token' => self::TOKEN,
            'backups.webhook.allowed_ips' => [],
            'services.jira.enabled' => false,
        ]);
    }

    /** Payload idêntico ao pinData do fluxo n8n. */
    private function payload(array $override = []): array
    {
        return array_merge([
            'ip' => '172.17.1.10',
            'fim' => 1749818011,
            'host' => 'sdb1',
            'inicio' => 1749818006,
            'status' => 'OK',
            'arquivo' => '/backup/dbmon/dbmon.onl',
            'tamanho' => 0.45,
            'database' => 'dbmon',
            'observacao' => 'Backup realizado com sucesso',
            'backup_type' => 'progress',
            'data_geracao' => 1749818011,
            'data_verificacao' => 1749818011,
        ], $override);
    }

    private function enviar(array $payload = [], array $headers = [])
    {
        return $this->postJson(
            route('webhooks.backup'),
            $payload ?: $this->payload(),
            array_merge(['X-Coreti-Token' => self::TOKEN], $headers)
        );
    }

    public function test_sem_token_o_webhook_recusa(): void
    {
        $this->postJson(route('webhooks.backup'), $this->payload())
            ->assertStatus(401);

        $this->assertSame(0, CmdbDatabaseBackup::count());
    }

    public function test_token_errado_recusa(): void
    {
        $this->enviar($this->payload(), ['X-Coreti-Token' => 'errado'])
            ->assertStatus(401);
    }

    public function test_aceita_token_via_bearer(): void
    {
        $this->postJson(route('webhooks.backup'), $this->payload(), [
            'Authorization' => 'Bearer '.self::TOKEN,
        ])->assertCreated();
    }

    public function test_sem_token_configurado_responde_503(): void
    {
        config(['backups.webhook.token' => null]);

        $this->enviar()->assertStatus(503);
    }

    public function test_ip_fora_da_lista_e_bloqueado(): void
    {
        config(['backups.webhook.allowed_ips' => ['10.0.0.0/8']]);

        $this->enviar()->assertStatus(403);
    }

    public function test_grava_evento_convertendo_os_timestamps(): void
    {
        $this->enviar()->assertCreated()->assertJson(['status' => 'registrado', 'sucesso' => true]);

        $backup = CmdbDatabaseBackup::firstOrFail();

        $this->assertSame('sdb1', $backup->host);
        $this->assertSame('dbmon', $backup->database_name);
        $this->assertTrue($backup->sucesso);
        $this->assertSame('172.17.1.10', $backup->ip_address);

        // 1749818006 -> 1749818011 = 5 segundos de execução.
        $this->assertSame(5, $backup->duracao_segundos);
        $this->assertSame(
            '2025-06-13 12:33:26',
            $backup->inicio_backup->utc()->format('Y-m-d H:i:s')
        );
    }

    public function test_reenvio_do_mesmo_evento_nao_duplica(): void
    {
        $this->enviar()->assertCreated();
        $this->enviar()->assertOk()->assertJson(['status' => 'duplicado']);

        $this->assertSame(1, CmdbDatabaseBackup::count());
    }

    public function test_status_diferente_de_ok_marca_falha(): void
    {
        $this->enviar($this->payload([
            'status' => 'ERRO',
            'observacao' => 'Falha ao gravar arquivo',
        ]))->assertCreated()->assertJson(['sucesso' => false]);

        $backup = CmdbDatabaseBackup::firstOrFail();

        $this->assertFalse($backup->sucesso);
        // Jira desligado: chamado simulado, mas a falha fica registrada.
        $this->assertNotNull($backup->jira_dispatched_at);
    }

    public function test_backup_com_sucesso_nao_abre_chamado(): void
    {
        Http::fake();

        $this->enviar()->assertCreated();

        $this->assertNull(CmdbDatabaseBackup::firstOrFail()->jira_dispatched_at);
        Http::assertNothingSent();
    }

    public function test_campos_obrigatorios_sao_validados(): void
    {
        $this->enviar(['status' => 'OK'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['host', 'database']);
    }

    public function test_timestamp_zerado_vira_nulo(): void
    {
        $this->enviar($this->payload(['data_verificacao' => 0]))->assertCreated();

        $this->assertNull(CmdbDatabaseBackup::firstOrFail()->data_verificacao);
    }
}
