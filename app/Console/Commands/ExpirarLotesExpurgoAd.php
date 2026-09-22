<?php

namespace App\Console\Commands;

use App\Models\AdPurgeBatch;
use App\Services\Ad\AdPurgeService;
use Illuminate\Console\Command;

/**
 * Vence aprovações antigas de exclusão de contas do Active Directory.
 *
 * Uma aprovação vale para a lista conferida naquele dia. Se o servidor do AD
 * ficar fora do ar por semanas, executar depois apagaria contas com base em
 * informação vencida — inclusive gente que voltou e teve a conta reativada.
 */
class ExpirarLotesExpurgoAd extends Command
{
    protected $signature = 'ad:expirar-lotes {--dry-run : Apenas relata}';

    protected $description = 'Expira lotes de exclusão de contas do AD cuja aprovação venceu.';

    public function handle(AdPurgeService $expurgo): int
    {
        $vencidos = AdPurgeBatch::where('status', AdPurgeBatch::STATUS_APROVADO)
            ->whereNotNull('expira_em')
            ->where('expira_em', '<', now())
            ->get();

        if ($vencidos->isEmpty()) {
            $this->info('Nenhum lote vencido.');

            return self::SUCCESS;
        }

        foreach ($vencidos as $lote) {
            $pendentes = $lote->itens()->aprovados()->count();

            $this->warn("Lote {$lote->referencia}: aprovação venceu em ".$lote->expira_em->format('d/m/Y H:i').", {$pendentes} contas não executadas.");

            if (! $this->option('dry-run')) {
                $expurgo->expirar($lote);
            }
        }

        if ($this->option('dry-run')) {
            $this->comment('Execução em --dry-run: nada foi alterado.');
        }

        return self::SUCCESS;
    }
}
