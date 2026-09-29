<?php

namespace Tests\Feature\Impressoras;

use App\Models\OrganizationUnit;
use App\Models\Printer;
use App\Models\PrinterSupply;
use App\Models\User;
use App\Services\ModuleAccessService;
use App\Support\LegacyModulePermissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ParqueTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        LegacyModulePermissions::esquecerCache();
    }

    private function usuario(array $habilidades = ['view']): User
    {
        $user = User::factory()->create(['role' => 'user', 'is_active' => true]);
        app(ModuleAccessService::class)->sync($user, ['impressoras' => $habilidades]);

        return $user->fresh();
    }

    private function impressora(array $dados = []): Printer
    {
        static $n = 0;
        $n++;

        return Printer::create(array_merge([
            'insight_device_id' => 900000 + $n,
            'insight_customer_id' => 1163,
            'serial_number' => 'SER'.str_pad((string) $n, 5, '0', STR_PAD_LEFT),
            'model' => 'Laser 408dn',
            'manufacturer' => 'HP',
            'monitor_status' => 'Y',
            'last_contact' => now(),
        ], $dados));
    }

    private function suprimento(Printer $printer, ?int $nivel, array $dados = []): PrinterSupply
    {
        static $n = 0;
        $n++;

        return PrinterSupply::create(array_merge([
            'printer_id' => $printer->id,
            'insight_consumable_id' => 700000 + $n,
            'indice' => 1,
            'tipo' => 'TONER',
            'cor' => 'BLACK',
            'descricao' => 'Black Toner',
            'percent_left' => $nivel,
        ], $dados));
    }

    public function test_a_lista_exige_o_modulo(): void
    {
        $semAcesso = User::factory()->create(['role' => 'user', 'is_active' => true]);

        $this->actingAs($semAcesso->fresh())->get(route('impressoras.index'))->assertForbidden();
        $this->actingAs($this->usuario())->get(route('impressoras.index'))->assertOk();
    }

    public function test_lista_mostra_as_impressoras(): void
    {
        $this->impressora(['serial_number' => 'ABC12345']);

        $this->actingAs($this->usuario())
            ->get(route('impressoras.index'))
            ->assertOk()
            ->assertSee('ABC12345')
            ->assertSee('Laser 408dn');
    }

    public function test_filtro_de_suprimento_critico_usa_a_mesma_faixa_do_cartao(): void
    {
        $critica = $this->impressora(['serial_number' => 'CRITICA1']);
        $this->suprimento($critica, 8);

        $tranquila = $this->impressora(['serial_number' => 'TRANQUI1']);
        $this->suprimento($tranquila, 80);

        $this->actingAs($this->usuario())
            ->get(route('impressoras.index', ['situacao' => 'suprimento_critico']))
            ->assertOk()
            ->assertSee('CRITICA1')
            ->assertDontSee('TRANQUI1');
    }

    public function test_filtro_sem_contato_pega_tambem_quem_nunca_reportou(): void
    {
        $this->impressora(['serial_number' => 'NUNCA001', 'last_contact' => null]);
        $this->impressora(['serial_number' => 'ANTIGA01', 'last_contact' => now()->subDays(30)]);
        $this->impressora(['serial_number' => 'RECENTE1', 'last_contact' => now()]);

        $this->actingAs($this->usuario())
            ->get(route('impressoras.index', ['situacao' => 'sem_contato']))
            ->assertOk()
            ->assertSee('NUNCA001')
            ->assertSee('ANTIGA01')
            ->assertDontSee('RECENTE1');
    }

    public function test_filtro_sem_unidade(): void
    {
        $unidade = OrganizationUnit::create([
            'source_system' => 'intranet', 'source_id' => '1', 'code' => '01',
            'name' => 'Sede', 'active' => true,
        ]);
        $this->impressora(['serial_number' => 'COMUNID1', 'organization_unit_id' => $unidade->id]);
        $this->impressora(['serial_number' => 'SEMUNID1', 'organization_unit_id' => null]);

        $this->actingAs($this->usuario())
            ->get(route('impressoras.index', ['situacao' => 'sem_unidade']))
            ->assertOk()
            ->assertSee('SEMUNID1')
            ->assertDontSee('COMUNID1');
    }

    public function test_busca_alcanca_serial_ip_e_hostname(): void
    {
        $this->impressora(['serial_number' => 'BUSCA001', 'ip_address' => '172.17.64.9', 'hostname' => 'SED-LASER-01']);
        $this->impressora(['serial_number' => 'OUTRA001', 'ip_address' => '10.0.0.1', 'hostname' => 'OUTRO-HOST']);

        foreach (['BUSCA001', '172.17.64.9', 'SED-LASER-01'] as $termo) {
            $this->actingAs($this->usuario())
                ->get(route('impressoras.index', ['q' => $termo]))
                ->assertOk()
                ->assertSee('BUSCA001')
                ->assertDontSee('OUTRA001');
        }
    }

    public function test_a_coluna_de_toner_mostra_o_nivel_mais_baixo(): void
    {
        // A lista precisa alertar pelo pior consumível, não por um qualquer.
        $printer = $this->impressora(['serial_number' => 'MULTI001']);
        $this->suprimento($printer, 90, ['cor' => 'CYAN']);
        $this->suprimento($printer, 4, ['cor' => 'BLACK']);

        $this->actingAs($this->usuario())
            ->get(route('impressoras.index'))
            ->assertOk()
            ->assertSee('4%')
            ->assertDontSee('90%');
    }

    /*
     | Toner não é o único consumível que acaba.
     |
     | O parque tem 330 fusores, 1.195 roletes e 226 cilindros monitorados,
     | todos com percentual de vida. O tipo é filtro à parte da faixa: assim
     | "fusor crítico" sai da combinação dos dois, sem precisar de uma
     | situação nova para cada tipo.
     */

    public function test_filtra_por_tipo_de_consumivel(): void
    {
        $comFusor = $this->impressora(['serial_number' => 'FUSOR001']);
        $this->suprimento($comFusor, 60, ['tipo' => 'FUSER', 'cor' => null]);

        $soToner = $this->impressora(['serial_number' => 'TONER001']);
        $this->suprimento($soToner, 60, ['tipo' => 'TONER']);

        $this->actingAs($this->usuario())
            ->get(route('impressoras.index', ['consumivel' => 'FUSER']))
            ->assertOk()
            ->assertSee('FUSOR001')
            ->assertDontSee('TONER001');
    }

    public function test_tipo_e_faixa_se_combinam(): void
    {
        // Fusor acabando, toner cheio: precisa aparecer em "fusor crítico".
        $fusorCritico = $this->impressora(['serial_number' => 'FCRIT001']);
        $this->suprimento($fusorCritico, 5, ['tipo' => 'FUSER', 'cor' => null]);
        $this->suprimento($fusorCritico, 95, ['tipo' => 'TONER']);

        // Toner acabando, fusor cheio: NÃO é problema de fusor.
        $tonerCritico = $this->impressora(['serial_number' => 'TCRIT001']);
        $this->suprimento($tonerCritico, 5, ['tipo' => 'TONER']);
        $this->suprimento($tonerCritico, 95, ['tipo' => 'FUSER', 'cor' => null]);

        $this->actingAs($this->usuario())
            ->get(route('impressoras.index', ['consumivel' => 'FUSER', 'situacao' => 'suprimento_critico']))
            ->assertOk()
            ->assertSee('FCRIT001')
            ->assertDontSee('TCRIT001');
    }

    public function test_a_coluna_mostra_o_nivel_do_tipo_escolhido(): void
    {
        // Filtrar por fusor e ver o nível do toner responderia outra pergunta.
        $printer = $this->impressora(['serial_number' => 'COLUNA01']);
        $this->suprimento($printer, 3, ['tipo' => 'TONER']);
        $this->suprimento($printer, 47, ['tipo' => 'FUSER', 'cor' => null]);

        $this->actingAs($this->usuario())
            ->get(route('impressoras.index', ['consumivel' => 'FUSER']))
            ->assertOk()
            ->assertSee('47%')
            ->assertDontSee('3%');
    }

    public function test_tipo_desconhecido_e_recusado(): void
    {
        $this->actingAs($this->usuario())
            ->get(route('impressoras.index', ['consumivel' => 'INVENTADO']))
            ->assertSessionHasErrors('consumivel');
    }

    public function test_o_seletor_so_oferece_tipos_que_existem_no_parque(): void
    {
        $printer = $this->impressora();
        $this->suprimento($printer, 50, ['tipo' => 'FUSER', 'cor' => null]);

        $this->actingAs($this->usuario())
            ->get(route('impressoras.index'))
            ->assertOk()
            ->assertSee('Fusor')
            ->assertDontSee('Reservatório de resíduo');
    }

    public function test_ficha_ordena_suprimentos_do_mais_baixo_para_o_mais_alto(): void
    {
        $printer = $this->impressora(['serial_number' => 'FICHA001']);
        $this->suprimento($printer, 70, ['descricao' => 'Toner com folga']);
        $this->suprimento($printer, 5, ['descricao' => 'Toner acabando']);

        $corpo = $this->actingAs($this->usuario())
            ->get(route('impressoras.show', $printer))
            ->assertOk()
            ->assertSee('FICHA001')
            ->getContent();

        $this->assertLessThan(
            strpos($corpo, 'Toner com folga'),
            strpos($corpo, 'Toner acabando'),
            'o que está acabando precisa vir primeiro'
        );
    }

    public function test_ficha_avisa_quando_nao_ha_contato_recente(): void
    {
        $parada = $this->impressora(['last_contact' => now()->subDays(30)]);
        $ativa = $this->impressora(['last_contact' => now()]);

        $this->actingAs($this->usuario())
            ->get(route('impressoras.show', $parada))
            ->assertOk()
            ->assertSee('Sem contato há', false);

        $this->actingAs($this->usuario())
            ->get(route('impressoras.show', $ativa))
            ->assertOk()
            ->assertDontSee('Sem contato há', false);
    }

    public function test_ficha_explica_que_o_status_do_portal_nao_e_fila(): void
    {
        /*
         | Todos os 705 pedidos do parque estão em NEW, inclusive os já
         | atendidos. A tela precisa dizer isso, senão quem lê conclui que há
         | centenas de pedidos em aberto.
         */
        $printer = $this->impressora();

        $this->actingAs($this->usuario())
            ->get(route('impressoras.show', $printer))
            ->assertOk()
            ->assertSee('data de substituição', false);
    }
}
