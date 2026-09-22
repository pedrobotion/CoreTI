<?php

namespace Tests\Feature\Cmdb;

use App\Models\CmdbConnector;
use App\Models\CmdbSyncLog;
use App\Services\Cmdb\Discovery\DiscoveryEngine;
use App\Services\Cmdb\Discovery\DiscoveryResult;
use App\Services\CmdbSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * O vCenter terminava toda sincronização "Com avisos".
 *
 * A regra antiga comparava o total encontrado com a lista de ativos casados.
 * Para vCenter e SNMP essa lista é sempre vazia — eles mandam todo item para a
 * fila de revisão por desenho —, então o aviso era estrutural, não sintoma de
 * problema. Um alerta que nunca desliga ensina a ignorar o painel.
 */
class SyncLogStatusTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['coreti.sync_notifications.enabled' => false]);
    }

    private function conector(string $tipo): CmdbConnector
    {
        return CmdbConnector::create([
            'name' => 'Conector '.$tipo,
            'connector_type' => $tipo,
            'status' => 'active',
            'is_active' => true,
        ]);
    }

    private function comItens(array $itens, string $status = 'success'): void
    {
        $this->instance(DiscoveryEngine::class, new class($itens, $status) extends DiscoveryEngine
        {
            public function __construct(private array $itens, private string $status)
            {
            }

            public function sync(CmdbConnector $connector): DiscoveryResult
            {
                return new DiscoveryResult(
                    success: true,
                    status: $this->status,
                    source: 'teste',
                    message: 'ok',
                    items: $this->itens,
                );
            }
        });
    }

    private function vm(string $nome): array
    {
        return [
            'name' => $nome,
            'asset_type' => 'virtual_machine',
            'source_external_id' => 'vm-'.$nome,
            'hostname' => $nome.'.cocari.com.br',
        ];
    }

    public function test_vcenter_sem_falha_conclui_como_sucesso(): void
    {
        $this->comItens([$this->vm('web01'), $this->vm('db01')]);

        app(CmdbSyncService::class)->syncConnector($this->conector('vcenter'));

        $log = CmdbSyncLog::latest('id')->firstOrFail();

        $this->assertSame('success', $log->status, 'Fila de revisão é operação normal, não aviso.');
        $this->assertSame(2, $log->assets_found);
        $this->assertSame(2, $log->assets_review);
        $this->assertSame(0, $log->assets_unmatched);
        $this->assertSame(0, $log->assets_failed);
    }

    public function test_contadores_deixam_de_ser_somados_em_sem_alteracao(): void
    {
        // "332 sem alteração" parecia sync perfeito e significava
        // "332 foram para a fila". Os desfechos agora são distintos.
        $this->comItens([$this->vm('web01'), $this->vm('db01'), $this->vm('app01')]);

        app(CmdbSyncService::class)->syncConnector($this->conector('vcenter'));

        $log = CmdbSyncLog::latest('id')->firstOrFail();

        $this->assertSame(3, $log->assets_review);
        $this->assertSame(0, $log->assets_unchanged);
    }

    public function test_aviso_do_proprio_conector_continua_virando_aviso(): void
    {
        $this->comItens([$this->vm('web01')], status: 'warning');

        app(CmdbSyncService::class)->syncConnector($this->conector('vcenter'));

        $this->assertSame('warning', CmdbSyncLog::latest('id')->firstOrFail()->status);
    }

    public function test_tipo_de_referencia_nao_conta_como_revisao(): void
    {
        // Rede, datastore, cluster e datacenter não viram ativo. Ficavam
        // pendentes para sempre, escondendo o que realmente espera decisão.
        \App\Models\CmdbDiscoveredAsset::create([
            'connector_id' => $this->conector('vcenter')->id,
            'source_type' => 'vcenter_network',
            'object_type' => 'network',
            'source_external_id' => 'network-1',
            'hostname' => 'VLAN 10',
            'status' => 'pending',
            'discovered_at' => now(),
            'source_hash' => hash('sha256', uniqid('', true)),
        ]);
        \App\Models\CmdbDiscoveredAsset::create([
            'connector_id' => $this->conector('vcenter')->id,
            'source_type' => 'vcenter_virtual_machine',
            'object_type' => 'virtual_machine',
            'source_external_id' => 'vm-999',
            'hostname' => 'novo.cocari.com.br',
            'status' => 'pending',
            'discovered_at' => now(),
            'source_hash' => hash('sha256', uniqid('', true)),
        ]);

        $query = app(\App\Services\CmdbDiscoveryQueryService::class);

        $this->assertSame(1, $query->pending()->count(), 'Só a VM espera decisão.');
        $this->assertSame(2, $query->pendingTodosOsTipos()->count());
    }

    public function test_tipo_desconhecido_continua_visivel_na_fila(): void
    {
        // A regra é lista de exclusão, não de permissão: um tipo novo do
        // vSphere não pode sumir da fila em silêncio.
        \App\Models\CmdbDiscoveredAsset::create([
            'connector_id' => $this->conector('vcenter')->id,
            'source_type' => 'vcenter_tipo_que_ainda_nao_existe',
            'object_type' => 'novidade',
            'source_external_id' => 'novo-1',
            'hostname' => 'objeto novo',
            'status' => 'pending',
            'discovered_at' => now(),
            'source_hash' => hash('sha256', uniqid('', true)),
        ]);

        $this->assertSame(1, app(\App\Services\CmdbDiscoveryQueryService::class)->pending()->count());
    }

    public function test_item_sem_correspondencia_gera_aviso(): void
    {
        // Conector que não usa fila de revisão: item sem ativo correspondente
        // não seria visto por ninguém se não fosse sinalizado.
        $this->comItens([$this->vm('equipamento-orfao')]);

        app(CmdbSyncService::class)->syncConnector($this->conector('generic'));

        $log = CmdbSyncLog::latest('id')->firstOrFail();

        $this->assertSame('warning', $log->status);
        $this->assertSame(1, $log->assets_unmatched);
        $this->assertSame(0, $log->assets_review);
    }
}
