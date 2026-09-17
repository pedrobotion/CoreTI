<?php

namespace App\Console\Commands;

use App\Services\Insight\InsightApiClient;
use App\Services\Insight\InsightApiException;
use Illuminate\Console\Command;

/**
 * Confere a credencial da Insight API e mostra o que ela enxerga.
 *
 * Só leitura: nada é criado nem alterado no portal. Serve para validar a
 * chave assim que ela chegar e para descobrir os customerId da COCARI, que
 * quase toda consulta exige.
 */
class TestarInsightApi extends Command
{
    protected $signature = 'insight:testar {--devices : Também lista os equipamentos de cada cliente visível}';

    protected $description = 'Testa a conexão com a EKM Insight API (somente leitura)';

    public function handle(InsightApiClient $insight): int
    {
        $this->components->twoColumnDetail('Endereço', (string) config('insight.base_url'));
        $this->components->twoColumnDetail('Credencial', $insight->configurado()
            ? '<fg=green>configurada</>'
            : '<fg=red>ausente — defina INSIGHT_API_KEY e INSIGHT_API_SECRET no .env</>');
        $this->newLine();

        $resultado = $insight->testarConexao();

        if (! $resultado['ok']) {
            $this->components->error($resultado['mensagem']);

            return self::FAILURE;
        }

        $this->components->info($resultado['mensagem']);

        $clientes = $resultado['clientes'] ?? [];

        if ($clientes === []) {
            $this->components->warn('A credencial autenticou, mas não enxerga nenhum cliente. Confira o escopo do usuário de API no portal.');

            return self::SUCCESS;
        }

        $this->table(['customerId', 'Cliente', 'Status'], array_map(
            fn (array $c) => [$c['id'], $c['nome'], $c['status']],
            $clientes
        ));

        $this->newLine();
        $this->components->info('Coloque os ids acima em INSIGHT_CUSTOMER_IDS no .env (separados por vírgula).');

        if (! $this->option('devices')) {
            return self::SUCCESS;
        }

        foreach ($clientes as $cliente) {
            $this->newLine();
            $this->components->twoColumnDetail("<fg=cyan>{$cliente['nome']}</> (id {$cliente['id']})", '');

            try {
                $equipamentos = $insight->devices($cliente['id']);
            } catch (InsightApiException $e) {
                $this->components->error($e->getMessage());

                continue;
            }

            if ($equipamentos === []) {
                $this->line('    nenhum equipamento');

                continue;
            }

            $this->components->twoColumnDetail('  equipamentos', (string) count($equipamentos));

            $amostra = array_slice($equipamentos, 0, 10);
            $this->table(
                ['deviceId', 'Série', 'Fabricante / modelo', 'IP', 'Patrimônio', 'Último contato'],
                array_map(fn (array $d) => [
                    $d['deviceId'] ?? '—',
                    $d['serialNumber'] ?? '—',
                    trim(($d['extendedFields']['manufacturer'] ?? '').' '.($d['extendedFields']['model'] ?? '')) ?: '—',
                    $d['ipAddress'] ?? '—',
                    $d['assetNumber'] ?? '—',
                    isset($d['lastContact']) ? substr((string) $d['lastContact'], 0, 10) : '—',
                ], $amostra)
            );

            if (count($equipamentos) > 10) {
                $this->line('    … e mais '.(count($equipamentos) - 10).'.');
            }
        }

        return self::SUCCESS;
    }
}
