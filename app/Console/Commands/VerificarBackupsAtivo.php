<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

/**
 * @deprecated Substituído por backups:verificar.
 *
 * Mantido apenas para não quebrar cron, script ou runbook que ainda chame o
 * nome antigo. Repassa a execução e avisa. Pode ser removido depois que o
 * novo nome estiver consolidado.
 */
class VerificarBackupsAtivo extends Command
{
    protected $signature = 'backups:verificar-ativo
        {--host=}
        {--target=}
        {--sem-descoberta : Ignorado; a auditoria não descobre mais alvos por evento}
        {--notificar : Ignorado; o registro em log é sempre feito}';

    protected $description = '[Descontinuado] Use backups:verificar.';

    public function handle(): int
    {
        $this->warn('backups:verificar-ativo foi substituído por backups:verificar. Repassando...');

        return $this->call('backups:verificar', array_filter([
            '--host' => $this->option('host'),
            '--target' => $this->option('target'),
        ]));
    }
}
