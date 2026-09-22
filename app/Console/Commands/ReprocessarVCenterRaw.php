<?php

namespace App\Console\Commands;

use App\Services\Cmdb\Discovery\VCenterPayloadExtractor;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Reprocessa o raw_data já gravado das VMs do vCenter.
 *
 * Nenhuma chamada à API: relê o payload que a sincronização guardou e
 * preenche o que a extração antiga deixou em branco (MAC, serial, SO,
 * CPU, memória, disco, datastore).
 *
 * Idempotente e conservador: por padrão só grava em campo vazio, para não
 * sobrescrever ajuste manual. Use --forcar para reescrever tudo.
 */
class ReprocessarVCenterRaw extends Command
{
    protected $signature = 'cmdb:reprocessar-vcenter
        {--forcar : Sobrescreve valores existentes, inclusive ajustes manuais}
        {--dry-run : Apenas relata o que mudaria}';

    protected $description = 'Extrai MAC, serial, SO, CPU, memória e disco do raw_data já sincronizado do vCenter.';

    public function handle(): int
    {
        $forcar = (bool) $this->option('forcar');
        $dryRun = (bool) $this->option('dry-run');

        $descobertas = DB::table('cmdb_discovered_assets')
            ->where('source_type', 'vcenter_virtual_machine')
            ->whereNotNull('raw_data')
            ->get(['id', 'asset_id', 'raw_data', 'mac_address', 'serial_number', 'description']);

        if ($descobertas->isEmpty()) {
            $this->warn('Nenhuma VM do vCenter com raw_data para reprocessar.');

            return self::SUCCESS;
        }

        $this->info("Reprocessando {$descobertas->count()} VM(s)...");

        $stats = ['mac' => 0, 'serial' => 0, 'descricao' => 0, 'so' => 0, 'vm_specs' => 0, 'sem_payload' => 0];

        $temSo = Schema::hasColumn('cmdb_assets', 'operating_system');

        foreach ($descobertas as $d) {
            $source = json_decode((string) $d->raw_data, true)['payload']['source'] ?? null;

            if (! is_array($source)) {
                $stats['sem_payload']++;

                continue;
            }

            $e = VCenterPayloadExtractor::fromVirtualMachine($source);

            // ---------------------------------------------- descoberta
            $mudancas = [];

            if ($e['macs'] !== [] && ($forcar || blank($d->mac_address))) {
                $mudancas['mac_address'] = $e['macs'][0];
                $stats['mac']++;
            }

            if (! empty($e['bios_uuid']) && ($forcar || blank($d->serial_number))) {
                $mudancas['serial_number'] = $e['bios_uuid'];
                $stats['serial']++;
            }

            if (! empty($e['anotacao']) && ($forcar || blank($d->description))) {
                $mudancas['description'] = $e['anotacao'];
                $stats['descricao']++;
            }

            if ($mudancas !== [] && ! $dryRun) {
                DB::table('cmdb_discovered_assets')->where('id', $d->id)->update($mudancas);
            }

            // ------------------------------------- ativo já promovido
            if (! $d->asset_id) {
                continue;
            }

            if ($temSo && ! empty($e['guest_os'])) {
                $atual = DB::table('cmdb_assets')->where('id', $d->asset_id)->value('operating_system');

                if ($forcar || blank($atual)) {
                    if (! $dryRun) {
                        DB::table('cmdb_assets')->where('id', $d->asset_id)
                            ->update(['operating_system' => $e['guest_os']]);
                    }
                    $stats['so']++;
                }
            }

            $specs = array_filter([
                'cpu_cores' => $e['cpu_count'],
                'memory_gb' => $e['memoria_gb'],
                'storage_gb' => $e['disco_gb'],
                'datastore' => $e['datastores'][0] ?? null,
            ], fn ($v) => $v !== null);

            if ($specs === []) {
                continue;
            }

            $linha = DB::table('cmdb_virtual_machines')->where('asset_id', $d->asset_id)->first();

            if ($linha === null) {
                continue;
            }

            $aplicar = [];

            foreach ($specs as $coluna => $valor) {
                if ($forcar || blank($linha->{$coluna} ?? null)) {
                    $aplicar[$coluna] = $valor;
                }
            }

            if ($aplicar !== []) {
                if (! $dryRun) {
                    DB::table('cmdb_virtual_machines')->where('asset_id', $d->asset_id)->update($aplicar);
                }
                $stats['vm_specs']++;
            }
        }

        $this->newLine();
        $this->table(
            ['MAC', 'Serial', 'Descrição', 'Sistema operacional', 'Specs da VM', 'Sem payload'],
            [[$stats['mac'], $stats['serial'], $stats['descricao'], $stats['so'], $stats['vm_specs'], $stats['sem_payload']]]
        );

        if ($dryRun) {
            $this->comment('Execução em --dry-run: nada foi gravado.');
        }

        if (! $forcar) {
            $this->comment('Somente campos vazios foram preenchidos. Use --forcar para sobrescrever.');
        }

        return self::SUCCESS;
    }
}
