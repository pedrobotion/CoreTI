<?php

namespace Tests\Feature\Impressao;

use App\Models\Printer;
use App\Models\PrintQueueDiscovery;
use App\Models\User;
use App\Services\Impressao\PrinterFleetReconciliationService;
use App\Services\Impressao\SnmpReader;
use App\Services\ModuleAccessService;
use App\Support\LegacyModulePermissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

/**
 * Conciliação do parque pelo número de série.
 *
 * O serial é a chave porque não muda: IP vem de DHCP, nome de fila é escolha
 * de quem cadastrou, serial é do equipamento. É ele que revela o erro da
 * Simpress em vez de herdá-lo — 112 impressoras lá com IP 127.0.0.1 ou trocado.
 */
class ConciliacaoSerialTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        LegacyModulePermissions::esquecerCache();
    }

    /** Serviço com um SNMP falso: mapa de IP => serial. */
    private function servico(array $seriaisPorIp): PrinterFleetReconciliationService
    {
        $snmp = Mockery::mock(SnmpReader::class);
        // serial() encapsula a tentativa de múltiplos OIDs; o serviço chama ela.
        $snmp->shouldReceive('serial')->andReturnUsing(fn (string $ip) => $seriaisPorIp[$ip] ?? null);
        $snmp->shouldReceive('get')->andReturnUsing(
            fn (string $ip, string $oid) => isset($seriaisPorIp[$ip]) ? 'HP Laser 408dn' : null
        );

        return new PrinterFleetReconciliationService($snmp);
    }

    private function printer(string $serial, string $ip): Printer
    {
        static $n = 0;
        $n++;

        return Printer::create([
            'insight_device_id' => 700000 + $n,
            'insight_customer_id' => 1163,
            'serial_number' => $serial,
            'model' => 'HP Laser 408dn',
            'ip_address' => $ip,
        ]);
    }

    public function test_serial_casa_a_fila_com_a_impressora_da_simpress(): void
    {
        $this->printer('BRBSSC0073', '172.19.4.225');

        $svc = $this->servico(['172.19.4.225' => 'BRBSSC0073']);
        $svc->registrarFilas('IMP02', [['fila' => 'JAN-LASER-COM01', 'ip_porta' => '172.19.4.225']]);
        $r = $svc->consultarPendentes();

        $this->assertSame(1, $r['casadas']);
        $this->assertSame(0, $r['divergentes']);

        $d = PrintQueueDiscovery::firstOrFail();
        $this->assertNotNull($d->printer_id);
        $this->assertSame('BRBSSC0073', $d->serial_number);
    }

    public function test_ip_diferente_na_simpress_e_marcado_como_divergente(): void
    {
        // O caso central: mesma impressora (serial), IP errado no portal.
        $this->printer('BRBSSBG03C', '127.0.0.1');

        $svc = $this->servico(['172.19.3.225' => 'BRBSSBG03C']);
        $svc->registrarFilas('IMP02', [['fila' => 'CSP-LASER-ADM01', 'ip_porta' => '172.19.3.225']]);
        $r = $svc->consultarPendentes();

        $this->assertSame(1, $r['divergentes']);

        $d = PrintQueueDiscovery::firstOrFail();
        $this->assertTrue($d->ip_divergente);
        $this->assertSame('172.19.3.225', $d->ip_porta, 'o IP real vem do SNMP');
        $this->assertSame('127.0.0.1', $d->ip_simpress, 'o IP errado vem da Simpress');
    }

    public function test_serial_sem_correspondencia_nao_casa(): void
    {
        // Impressora no servidor que a Simpress não inventaria.
        $svc = $this->servico(['172.19.4.240' => 'DESCONHECIDO123']);
        $svc->registrarFilas('IMP02', [['fila' => 'NOVA-LASER-01', 'ip_porta' => '172.19.4.240']]);
        $svc->consultarPendentes();

        $d = PrintQueueDiscovery::firstOrFail();
        $this->assertNull($d->printer_id);
        $this->assertSame('DESCONHECIDO123', $d->serial_number);
        $this->assertFalse($d->ip_divergente);
    }

    public function test_impressora_sem_resposta_snmp_fica_registrada(): void
    {
        // A térmica Bematech não fala SNMP de impressora: não pode sumir.
        $svc = $this->servico([]); // ninguém responde
        $svc->registrarFilas('IMP02', [['fila' => 'JAN-TERM-NFCE01', 'ip_porta' => '172.17.16.43']]);
        $r = $svc->consultarPendentes();

        $this->assertSame(1, $r['sem_resposta']);

        $d = PrintQueueDiscovery::firstOrFail();
        $this->assertNull($d->serial_number);
        $this->assertSame(PrintQueueDiscovery::SNMP_SEM_RESPOSTA, $d->snmp_status);
    }

    public function test_ip_loopback_e_descartado_antes_do_snmp(): void
    {
        // 127.0.0.1 é a própria máquina — consultar SNMP ali seria inútil.
        $svc = $this->servico(['127.0.0.1' => 'NAO_DEVE_LER']);
        $svc->registrarFilas('IMP02', [['fila' => 'X', 'ip_porta' => '127.0.0.1']]);

        $d = PrintQueueDiscovery::firstOrFail();
        $this->assertNull($d->ip_porta, 'loopback não vira IP consultável');
    }

    public function test_mudanca_de_ip_forca_releitura(): void
    {
        $this->printer('BRBSSC0073', '172.19.4.225');
        $svc = $this->servico(['172.19.4.225' => 'BRBSSC0073', '172.19.4.999' => 'BRBSSC0073']);

        $svc->registrarFilas('IMP02', [['fila' => 'Q', 'ip_porta' => '172.19.4.225']]);
        $svc->consultarPendentes();
        $this->assertSame('ok', PrintQueueDiscovery::firstOrFail()->snmp_status);

        // Reenvia a mesma fila com IP novo: precisa voltar a pendente.
        $svc->registrarFilas('IMP02', [['fila' => 'Q', 'ip_porta' => '172.19.4.226']]);
        $this->assertSame(PrintQueueDiscovery::SNMP_NAO_CONSULTADO, PrintQueueDiscovery::firstOrFail()->snmp_status);
    }

    public function test_a_rodada_diaria_reconsulta_quem_nao_respondeu(): void
    {
        /*
         | Impressora offline hoje pode responder amanhã. Se o comando só
         | olhasse "nunca consultado", o agendamento diário jamais tentaria de
         | novo, e uma impressora que voltou ficaria eternamente "sem resposta".
         */
        $this->printer('BRBSSC0073', '172.19.4.225');

        // Dia 1: a impressora está fora do ar.
        $offline = $this->servico([]);
        $offline->registrarFilas('IMP02', [['fila' => 'Q', 'ip_porta' => '172.19.4.225']]);
        $offline->consultarPendentes();
        $this->assertSame(PrintQueueDiscovery::SNMP_SEM_RESPOSTA, PrintQueueDiscovery::firstOrFail()->snmp_status);

        // Dia 2: voltou. A rodada diária a pega de novo, sem reenvio de fila.
        $online = $this->servico(['172.19.4.225' => 'BRBSSC0073']);
        $r = $online->consultarPendentes();

        $this->assertSame(1, $r['casadas']);
        $this->assertNotNull(PrintQueueDiscovery::firstOrFail()->printer_id);
    }

    public function test_o_que_ja_casou_nao_volta_na_rodada_diaria(): void
    {
        // Relê tudo todo dia seria SNMP desperdiçado no que está resolvido.
        $this->printer('BRBSSC0073', '172.19.4.225');
        $svc = $this->servico(['172.19.4.225' => 'BRBSSC0073']);
        $svc->registrarFilas('IMP02', [['fila' => 'Q', 'ip_porta' => '172.19.4.225']]);
        $svc->consultarPendentes();

        // Nada pendente: a casada não é reconsultada.
        $r = $svc->consultarPendentes();
        $this->assertSame(0, $r['consultadas']);

        // Mas --tudo relê inclusive as casadas.
        $r = $svc->consultarPendentes(null, tudo: true);
        $this->assertSame(1, $r['consultadas']);
    }

    public function test_termica_e_etiqueta_nao_entram_na_conciliacao(): void
    {
        /*
         | Bematech (cupom) e Zebra (etiqueta) não são da Simpress e não falam
         | SNMP de impressora: apareceriam para sempre como "sem resposta". O
         | filtro é por DRIVER, porque o nome da fila (TERM/BAL) deixa escapar
         | casos — havia Bematech sem TERM no nome.
         */
        config(['impressao.drivers_fora_da_conciliacao' => ['bematech', 'zdesigner']]);

        $svc = $this->servico([]);
        $r = $svc->registrarFilas('IMP02', [
            ['fila' => 'SED-LASER-ADM01', 'ip_porta' => '172.19.4.224', 'driver' => 'HP Universal Printing PCL 6'],
            ['fila' => 'SED-TERM-BAL01', 'ip_porta' => '172.19.4.250', 'driver' => 'Bematech MP-4200 HS'],
            ['fila' => 'ETIQUETA-01', 'ip_porta' => '172.19.4.251', 'driver' => 'ZDesigner ZT230-200dpi ZPL'],
        ]);

        $this->assertSame(1, $r['recebidas'], 'só a laser é registrada');
        $this->assertSame(2, $r['ignoradas']);
        $this->assertSame(1, \App\Models\PrintQueueDiscovery::count());
        $this->assertSame('SED-LASER-ADM01', \App\Models\PrintQueueDiscovery::firstOrFail()->fila);
    }

    public function test_limpar_remove_termicas_ja_gravadas(): void
    {
        // Quando o filtro passa a existir, o que foi gravado antes precisa sair.
        \App\Models\PrintQueueDiscovery::create(['servidor' => 'IMP01', 'fila' => 'X-TERM', 'driver' => 'Bematech MP-4200 HS']);
        \App\Models\PrintQueueDiscovery::create(['servidor' => 'IMP01', 'fila' => 'X-LASER', 'driver' => 'HP Universal Printing PCL 6']);

        config(['impressao.drivers_fora_da_conciliacao' => ['bematech']]);
        $removidas = app(\App\Services\Impressao\PrinterFleetReconciliationService::class)->limparForaDaConciliacao();

        $this->assertSame(1, $removidas);
        $this->assertSame(1, \App\Models\PrintQueueDiscovery::count());
        $this->assertSame('X-LASER', \App\Models\PrintQueueDiscovery::firstOrFail()->fila);
    }

    public function test_a_mesma_fila_em_dois_servidores_sao_registros_distintos(): void
    {
        $svc = $this->servico([]);
        $svc->registrarFilas('IMP01', [['fila' => 'LASER-ADM01', 'ip_porta' => '172.20.0.10']]);
        $svc->registrarFilas('IMP02', [['fila' => 'LASER-ADM01', 'ip_porta' => '172.20.1.10']]);

        $this->assertSame(2, PrintQueueDiscovery::count());
    }

    private function usuario(): User
    {
        $u = User::factory()->create(['id' => 9801, 'role' => 'user', 'is_active' => true]);
        app(ModuleAccessService::class)->sync($u, ['impressoras' => ['view']]);

        return $u->fresh();
    }

    public function test_a_tela_exige_o_modulo_e_lista_divergentes(): void
    {
        $this->printer('BRBSSBG03C', '127.0.0.1');
        $svc = $this->servico(['172.19.3.225' => 'BRBSSBG03C']);
        $svc->registrarFilas('IMP02', [['fila' => 'CSP-LASER-ADM01', 'ip_porta' => '172.19.3.225']]);
        $svc->consultarPendentes();

        User::factory()->create(['id' => 9802, 'is_active' => true]);
        $this->actingAs(User::find(9802))->get(route('impressoras.conciliacao'))->assertForbidden();

        $this->actingAs($this->usuario())
            ->get(route('impressoras.conciliacao', ['situacao' => 'divergentes']))
            ->assertOk()
            ->assertSee('CSP-LASER-ADM01')
            ->assertSee('172.19.3.225');
    }
}
