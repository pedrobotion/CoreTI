<?php

namespace App\Console\Commands;

use App\Models\CmdbConnector;
use App\Services\Cmdb\Discovery\Connectors\VCenterApiClient;
use App\Services\Cmdb\Discovery\VCenterGuestNetworkCollector;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Busca IP, máscara, gateway e DNS das VMs no vCenter.
 *
 * Faz o que o detalhe da VM não entrega: consulta a rede vista de dentro do
 * guest. Preenche a descoberta e, quando a VM já virou ativo, registra o
 * endereço em cmdb_ip_addresses.
 *
 * Conservador por padrão: NÃO sobrescreve main_ip preenchido à mão. Use
 * --forcar-ip-principal para reescrever.
 */
class EnriquecerRedeVCenter extends Command
{
    protected $signature = 'cmdb:enriquecer-rede
        {--connector=2 : Conector vCenter a consultar}
        {--limite=0 : Processa no máximo N VMs (0 = todas)}
        {--incluir-desligadas : Tenta também VMs desligadas (normalmente inútil)}
        {--forcar-ip-principal : Sobrescreve main_ip já preenchido no ativo}
        {--dry-run : Apenas relata}';

    protected $description = 'Coleta IP/máscara/gateway/DNS das VMs do vCenter via guest networking.';

    public function handle(VCenterGuestNetworkCollector $coletor): int
    {
        $conector = CmdbConnector::find((int) $this->option('connector'));

        if (! $conector) {
            $this->error('Conector não encontrado.');

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');
        $client = new VCenterApiClient($conector);

        try {
            $vms = $client->list('vm');
        } catch (\Throwable $e) {
            $this->error('Falha ao listar VMs: '.$e->getMessage());

            return self::FAILURE;
        }

        if (! $this->option('incluir-desligadas')) {
            $vms = array_values(array_filter($vms, fn ($v) => ($v['power_state'] ?? '') === 'POWERED_ON'));
        }

        if ($limite = (int) $this->option('limite')) {
            $vms = array_slice($vms, 0, $limite);
        }

        $this->info(count($vms).' VM(s) a consultar...');

        $stats = ['com_ip' => 0, 'sem_tools' => 0, 'descoberta' => 0, 'ip_registrado' => 0, 'main_ip' => 0];
        $barra = $this->output->createProgressBar(count($vms));
        $barra->start();

        foreach ($vms as $vm) {
            $barra->advance();
            $rede = $coletor->coletar($client, (string) $vm['vm']);

            if (! $rede['disponivel'] || $rede['ips'] === []) {
                $stats['sem_tools']++;

                continue;
            }

            $stats['com_ip']++;
            $ipPrincipal = $rede['ips'][0];
            $macPrincipal = $rede['macs'][0] ?? null;

            $descoberta = DB::table('cmdb_discovered_assets')
                ->where('source_type', 'vcenter_virtual_machine')
                ->where('object_id', $vm['vm'])
                ->first(['id', 'asset_id', 'ip_address']);

            if (! $descoberta) {
                continue;
            }

            if (! $dryRun) {
                DB::table('cmdb_discovered_assets')->where('id', $descoberta->id)->update(array_filter([
                    'ip_address' => $ipPrincipal,
                    'mac_address' => $macPrincipal,
                ]));
            }
            $stats['descoberta']++;

            if (! $descoberta->asset_id) {
                continue;
            }

            $this->registrarEnderecos($descoberta->asset_id, $rede, $dryRun, $stats);
        }

        $barra->finish();
        $client->close();
        $this->newLine(2);

        $this->table(
            ['VMs com IP', 'Sem Tools/IP', 'Descobertas atualizadas', 'IPs registrados', 'main_ip preenchido'],
            [[$stats['com_ip'], $stats['sem_tools'], $stats['descoberta'], $stats['ip_registrado'], $stats['main_ip']]]
        );

        if ($dryRun) {
            $this->comment('Execução em --dry-run: nada foi gravado.');
        }

        return self::SUCCESS;
    }

    /**
     * Grava cada endereço do guest em cmdb_ip_addresses e, se o ativo ainda
     * não tem IP principal, define o primeiro.
     */
    private function registrarEnderecos(int $assetId, array $rede, bool $dryRun, array &$stats): void
    {
        $gateway = $rede['gateways'][0] ?? null;

        foreach ($rede['interfaces'] as $indice => $iface) {
            $existente = DB::table('cmdb_ip_addresses')
                ->where('asset_id', $assetId)
                ->where('ip_address', $iface['ip'])
                ->first(['id']);

            $dados = array_filter([
                'asset_id' => $assetId,
                'ip_address' => $iface['ip'],
                'subnet_mask' => $iface['mascara'],
                'gateway' => $gateway,
                'dns' => $iface['dns'] ? implode(', ', $iface['dns']) : null,
                'mac_address' => $iface['mac'],
                'is_primary' => $indice === 0,
                'notes' => 'Coletado do vCenter (guest networking)',
            ], fn ($v) => $v !== null);

            if ($dryRun) {
                $stats['ip_registrado']++;

                continue;
            }

            if ($existente) {
                DB::table('cmdb_ip_addresses')->where('id', $existente->id)
                    ->update($dados + ['updated_at' => now()]);
            } else {
                DB::table('cmdb_ip_addresses')->insert($dados + ['created_at' => now(), 'updated_at' => now()]);
            }

            $stats['ip_registrado']++;
        }

        $atual = DB::table('cmdb_assets')->where('id', $assetId)->value('main_ip');

        // Só preenche o que está vazio: há 127 valores cadastrados à mão.
        if (blank($atual) || $this->option('forcar-ip-principal')) {
            if (! $dryRun) {
                DB::table('cmdb_assets')->where('id', $assetId)->update(['main_ip' => $rede['ips'][0]]);
            }
            $stats['main_ip']++;
        }
    }
}
