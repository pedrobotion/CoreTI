<?php

namespace Tests\Feature\Cmdb;

use App\Models\CmdbDiscoveredAsset;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Regras da aprovação automática de descobertas do vCenter.
 *
 * Aprovar cegamente traria templates, cópias de backup, restaurações e VMs
 * que já sumiram do vCenter para o inventário.
 */
class AprovacaoAutomaticaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        DB::table('cmdb_connectors')->insert([
            'id' => 1, 'name' => 'vCenter', 'connector_type' => 'vcenter',
            'host' => 'https://vcenter.local', 'is_active' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function descoberta(string $hostname, array $override = []): CmdbDiscoveredAsset
    {
        static $n = 0;
        $n++;

        return CmdbDiscoveredAsset::create(array_merge([
            'connector_id' => 1,
            'source_type' => 'vcenter_virtual_machine',
            'object_type' => 'virtual_machine',
            'source_external_id' => 'vm-'.(1000 + $n),
            'source_hash' => hash('sha256', 'descoberta-'.$n),
            'hostname' => $hostname,
            'status' => 'pending',
            // Fora da carência por padrão.
            'discovered_at' => now()->subDays(5),
            'last_seen_at' => now(),
            'raw_data' => ['payload' => ['source' => ['name' => $hostname, 'power_state' => 'POWERED_ON']]],
        ], $override));
    }

    private function aprovar(array $opcoes = []): void
    {
        $this->artisan('cmdb:aprovar-descobertas', $opcoes)->run();
    }

    public function test_vm_normal_e_aprovada(): void
    {
        $d = $this->descoberta('servidor01.cocari.com.br');

        $this->aprovar();

        $this->assertNotNull($d->fresh()->asset_id, 'a VM deveria virar ativo');
    }

    /** @dataProvider nomesExcluidos */
    public function test_nomes_suspeitos_nao_sao_aprovados(string $hostname): void
    {
        $d = $this->descoberta($hostname);

        $this->aprovar();

        $d->refresh();

        $this->assertNull($d->asset_id, "{$hostname} não deveria virar ativo");
        $this->assertSame('ignored', $d->status);
        $this->assertStringContainsString('aprovação automática', (string) $d->review_notes);
    }

    public static function nomesExcluidos(): array
    {
        return [
            'template' => ['Template_Ubuntu_26.04-x64'],
            'backup do appliance' => ['VMware vCenter Server 8 - BKP'],
            'restauracao' => ['new_espanha01.cocari.com.br_restored'],
        ];
    }

    /**
     * Objeto que o vCenter não confirma há dias provavelmente não existe.
     *
     * É adiamento, não exclusão: se a VM voltar a aparecer, o próximo ciclo
     * aprova. Por isso segue pendente, e não marcada como ignorada.
     */
    public function test_descoberta_nao_vista_recentemente_e_adiada(): void
    {
        $d = $this->descoberta('fantasma.cocari.com.br', [
            'last_seen_at' => now()->subDays(5),
        ]);

        $this->aprovar();

        $d->refresh();

        $this->assertNull($d->asset_id);
        $this->assertSame('pending', $d->status, 'motivo temporário não pode virar exclusão definitiva');

        // Voltou a ser vista: agora entra.
        $d->update(['last_seen_at' => now()]);
        $this->aprovar();

        $this->assertNotNull($d->fresh()->asset_id);
    }

    /** Carência evita cadastrar VM criada e destruída no mesmo dia. */
    public function test_descoberta_recente_aguarda_carencia(): void
    {
        // Fixa o valor em vez de herdar o do ambiente: é a carência que está
        // sob teste aqui, e ela é ajustável por .env em cada instalação.
        config(['cmdb.auto_aprovar.horas_de_carencia' => 24]);

        $d = $this->descoberta('recemcriada.cocari.com.br', [
            'discovered_at' => now()->subHours(2),
        ]);

        $this->aprovar();

        $this->assertNull($d->fresh()->asset_id);

        // Segue pendente, não ignorada: a carência é temporária.
        $this->assertSame('pending', $d->fresh()->status);

        // Passada a carência (o inventário segue vendo a VM), entra.
        Carbon::setTestNow(now()->addDays(2));
        $d->fresh()->update(['last_seen_at' => now()]);
        $this->aprovar();

        $this->assertNotNull($d->fresh()->asset_id);
    }

    /*
     | Os testes de adiamento acima têm UMA descoberta na fila, e é por isso
     | que deixavam passar o defeito: com a fila inteira adiada o comando
     | encerra antes de mandar criar qualquer coisa. O problema só aparece
     | quando uma aprovável divide o ciclo com uma adiada — era o momento em
     | que a adiada entrava de carona e virava ativo.
     */

    public function test_adiada_nao_entra_junto_com_uma_aprovavel(): void
    {
        $adiada = $this->descoberta('fantasma.cocari.com.br', [
            'last_seen_at' => now()->subDays(5),
        ]);
        $aprovavel = $this->descoberta('servidor02.cocari.com.br');

        $this->aprovar();

        $this->assertNotNull($aprovavel->fresh()->asset_id, 'a aprovável deveria virar ativo');
        $this->assertNull($adiada->fresh()->asset_id, 'a adiada não pode entrar de carona');
        $this->assertSame('pending', $adiada->fresh()->status);
    }

    public function test_em_carencia_nao_entra_junto_com_uma_aprovavel(): void
    {
        config(['cmdb.auto_aprovar.horas_de_carencia' => 24]);

        $emCarencia = $this->descoberta('recemcriada.cocari.com.br', [
            'discovered_at' => now()->subHours(2),
        ]);
        $aprovavel = $this->descoberta('servidor03.cocari.com.br');

        $this->aprovar();

        $this->assertNotNull($aprovavel->fresh()->asset_id);
        $this->assertNull($emCarencia->fresh()->asset_id, 'a carência precisa valer mesmo com outra aprovável na fila');
    }

    public function test_ignorada_por_nome_nao_entra_junto_com_uma_aprovavel(): void
    {
        $template = $this->descoberta('template-ubuntu-22');
        $aprovavel = $this->descoberta('servidor04.cocari.com.br');

        $this->aprovar();

        $this->assertNotNull($aprovavel->fresh()->asset_id);
        $this->assertNull($template->fresh()->asset_id, 'template não vira ativo em hipótese alguma');
        $this->assertSame('ignored', $template->fresh()->status);
    }

    public function test_carencia_zero_aprova_a_recem_descoberta(): void
    {
        // Como o ambiente está configurado hoje: servidor novo entra direto.
        config(['cmdb.auto_aprovar.horas_de_carencia' => 0]);

        $d = $this->descoberta('novissima.cocari.com.br', [
            'discovered_at' => now()->subMinutes(5),
        ]);

        $this->aprovar();

        $this->assertNotNull($d->fresh()->asset_id, 'sem carência, a descoberta recente entra no mesmo ciclo');
    }

    public function test_dry_run_nao_altera_nada(): void
    {
        $ok = $this->descoberta('normal.cocari.com.br');
        $bloqueada = $this->descoberta('Template_X');

        $this->aprovar(['--dry-run' => true]);

        $this->assertNull($ok->fresh()->asset_id);
        $this->assertSame('pending', $bloqueada->fresh()->status);
    }

    public function test_sem_regras_aprova_tudo(): void
    {
        $template = $this->descoberta('Template_Ubuntu');

        $this->aprovar(['--sem-regras' => true]);

        $this->assertNotNull($template->fresh()->asset_id, '--sem-regras deve ignorar as exclusões');
    }

    public function test_tipos_fora_da_lista_nao_sao_tocados(): void
    {
        $rede = $this->descoberta('NET-DMZ-903', [
            'source_type' => 'vcenter_network',
            'object_type' => 'network',
        ]);

        $this->aprovar();

        $rede->refresh();

        $this->assertNull($rede->asset_id);
        // Continua pendente: rede não é ativo, mas também não é "ignorada".
        $this->assertSame('pending', $rede->status);
    }

    public function test_desabilitado_na_config_nao_faz_nada(): void
    {
        config(['cmdb.auto_aprovar.habilitado' => false]);

        $d = $this->descoberta('servidor02.cocari.com.br');

        $this->aprovar();

        $this->assertNull($d->fresh()->asset_id);
    }

    public function test_ignorado_pode_ser_reaberto_na_revisao(): void
    {
        $d = $this->descoberta('Template_Ubuntu');

        $this->aprovar();
        $this->assertSame('ignored', $d->fresh()->status);

        // A exclusão é reversível: o item volta para a fila de revisão.
        app(\App\Services\CmdbDiscoveredAssetService::class)->reopen($d->fresh());

        $this->assertSame('pending', $d->fresh()->status);
    }
}
