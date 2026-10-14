<?php

namespace App\Console\Commands;

use App\Services\Impressao\PrinterFleetReconciliationService;
use Illuminate\Console\Command;

/**
 * Lê por SNMP o serial das filas ainda não consultadas e casa com a Simpress.
 *
 * Separado do recebimento de propósito: a consulta de rede é lenta (um timeout
 * por impressora que não responde) e roda em segundo plano, sem segurar o
 * servidor que só quis reportar suas filas.
 */
class ConsultarSeriaisImpressoras extends Command
{
    protected $signature = 'impressoras:consultar-seriais
        {--limite= : Máximo de filas a consultar nesta execução}
        {--tudo : Relê também as que já casaram, para pegar troca de IP na impressora}';

    protected $description = 'Lê o serial das impressoras por SNMP e concilia com a base da Simpress.';

    public function handle(PrinterFleetReconciliationService $servico): int
    {
        $limite = $this->option('limite') !== null ? (int) $this->option('limite') : null;

        $this->info('Consultando filas por SNMP...');
        $r = $servico->consultarPendentes($limite, (bool) $this->option('tudo'));

        $this->table(
            ['Consultadas', 'Com serial', 'Casadas', 'Divergência de IP', 'Sem resposta'],
            [[$r['consultadas'], $r['com_serial'], $r['casadas'], $r['divergentes'], $r['sem_resposta']]]
        );

        if ($r['divergentes'] > 0) {
            $this->warn($r['divergentes'].' impressora(s) com IP divergente da Simpress — veja em /impressoras/conciliacao.');
        }

        return self::SUCCESS;
    }
}
