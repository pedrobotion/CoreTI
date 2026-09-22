<?php

namespace App\Console\Commands;

use App\Models\CmdbBackupTarget;
use App\Services\Backups\BackupTargetDiscoveryService;
use Illuminate\Console\Command;

/**
 * Semeia o cadastro de alvos a partir do histórico recebido do agente/n8n.
 *
 * Ferramenta de migração, executada à mão uma vez. A auditoria não depende
 * dela: alvos podem (e devem) ser cadastrados pela tela.
 */
class ImportarAlvosLegado extends Command
{
    protected $signature = 'backups:importar-alvos-legado {--ativar : Já cria os alvos ativos}';

    protected $description = '[Migração] Cria alvos de backup a partir do histórico legado de eventos do agente.';

    public function handle(BackupTargetDiscoveryService $importador): int
    {
        $this->warn('Este comando é uma ferramenta de migração e não faz parte da auditoria automática.');

        $criados = $importador->importarDoHistoricoLegado();

        if ($criados->isEmpty()) {
            $this->info('Nenhum alvo novo a importar.');
            $this->line('Cadastre os alvos manualmente em CMDB → Backups → Alvos.');

            return self::SUCCESS;
        }

        if ($this->option('ativar')) {
            CmdbBackupTarget::whereIn('id', $criados->pluck('id'))->update(['ativo' => true]);
        }

        $this->table(
            ['Servidor', 'Banco', 'Arquivo', 'Ativo'],
            $criados->map(fn (CmdbBackupTarget $t) => [
                $t->host, $t->database_name, $t->arquivo,
                $this->option('ativar') ? 'sim' : 'não — revise antes de ativar',
            ])->all()
        );

        $this->newLine();
        $this->info($criados->count().' alvo(s) importado(s).');

        if (! $this->option('ativar')) {
            $this->comment('Confira host SSH, usuário e limites de cada alvo na tela antes de ativar.');
        }

        return self::SUCCESS;
    }
}
