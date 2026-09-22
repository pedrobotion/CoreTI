<?php

namespace App\Console\Commands;

use App\Models\AdOfficeRule;
use App\Services\Ad\AdOfficeRuleService;
use Illuminate\Console\Command;

/**
 * Monta as regras de Escritório a partir do que o AD tem hoje.
 *
 * A sugestão é o código dominante da OU. Não é decisão: a tela mostra como
 * "a confirmar" até alguém olhar, porque o dominante pode estar errado —
 * numa OU com 9 contas em 01.222 e 1 em 66.154, o dominante é o certo, mas
 * numa OU com metade vazia ele pode ser só o que alguém digitou primeiro.
 */
class SemearRegrasEscritorioAd extends Command
{
    protected $signature = 'ad:semear-regras-escritorio {--dry-run : Apenas relata}';

    protected $description = 'Cria/atualiza as regras de centro de custo por OU a partir do AD atual.';

    public function handle(AdOfficeRuleService $servico): int
    {
        $levantamento = $servico->levantamento();

        $this->info(sprintf('%d OUs encontradas no AD.', count($levantamento)));

        $semCodigo = array_filter($levantamento, fn ($d) => $d['dominante'] === null);
        $comDivergencia = array_filter($levantamento, fn ($d) => $d['divergentes'] > 0);

        $this->line(sprintf('  sem nenhum código hoje ...... %d  (você precisa definir)', count($semCodigo)));
        $this->line(sprintf('  com códigos divergentes ..... %d  (confira antes de confirmar)', count($comDivergencia)));

        if ($this->option('dry-run')) {
            $this->newLine();
            $this->comment('Execução em --dry-run: nada foi gravado.');

            $this->table(
                ['OU', 'Contas', 'Sugestão', 'Divergentes'],
                collect($levantamento)->take(20)->map(fn ($d, $ou) => [
                    $ou, $d['contas'], $d['dominante'] ?? '—', $d['divergentes'],
                ])->values()->all()
            );

            return self::SUCCESS;
        }

        $resumo = $servico->semear();

        $this->newLine();
        $this->table(
            ['Criadas', 'Atualizadas', 'Preservadas (já decididas)'],
            [[$resumo['criadas'], $resumo['atualizadas'], $resumo['intocadas']]]
        );

        $faltando = AdOfficeRule::whereNull('centro_custo_code')->where('ignorar', false)->count();

        if ($faltando > 0) {
            $this->warn("{$faltando} OU(s) sem centro de custo definido. Nenhuma conta delas será alterada.");
        }

        return self::SUCCESS;
    }
}
