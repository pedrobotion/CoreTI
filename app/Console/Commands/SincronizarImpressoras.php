<?php

namespace App\Console\Commands;

use App\Services\Insight\InsightApiException;
use App\Services\Insight\PrinterSyncService;
use Illuminate\Console\Command;

/**
 * Traz o parque de impressoras da Insight para o CoreTI.
 *
 * Só leitura no portal: nenhum equipamento é criado ou alterado lá.
 */
class SincronizarImpressoras extends Command
{
    protected $signature = 'impressoras:sincronizar
                            {--customer=* : Limita a estes customerId (padrão: os do .env)}
                            {--simular : Lê e relata sem gravar}';

    protected $description = 'Sincroniza impressoras, suprimentos, contadores e pedidos da EKM Insight API';

    public function handle(PrinterSyncService $sync): int
    {
        $simular = (bool) $this->option('simular');
        $clientes = array_map('intval', (array) $this->option('customer'));

        $this->components->twoColumnDetail('Origem', (string) config('insight.base_url'));
        $this->components->twoColumnDetail('Modo', $simular
            ? '<fg=green>simulação — nada é gravado</>'
            : '<fg=yellow>gravando no CoreTI</>');
        $this->newLine();

        try {
            $inicio = microtime(true);
            $resumo = $sync->sincronizar($clientes, $simular);
            $segundos = round(microtime(true) - $inicio, 1);
        } catch (InsightApiException $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }

        $linhas = [];

        foreach ($resumo['clientes'] as $customerId => $c) {
            $linhas[] = [
                $customerId,
                $c['equipamentos'],
                $c['novos'],
                $c['sem_unidade'],
                $c['suprimentos'],
                $c['contadores'],
                $c['pedidos'],
            ];
        }

        $this->table(
            ['customerId', 'Equipamentos', 'Novos', 'Sem unidade', 'Suprimentos', 'Contadores', 'Pedidos'],
            $linhas
        );

        $semUnidade = array_sum(array_column($resumo['clientes'], 'sem_unidade'));

        if ($semUnidade > 0) {
            $this->components->warn(
                "{$semUnidade} impressora(s) sem unidade correspondente — ficam sem escopo até alguém classificar. "
                .'A localização que vem do portal é texto livre e nem sempre casa com o nome da unidade.'
            );
        }

        $this->components->twoColumnDetail('Tempo', "{$segundos}s");

        if ($simular) {
            $this->components->warn('Simulação: nada foi gravado.');
        }

        return self::SUCCESS;
    }
}
