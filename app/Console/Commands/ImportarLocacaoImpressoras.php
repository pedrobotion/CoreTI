<?php

namespace App\Console\Commands;

use App\Models\Printer;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use PhpOffice\PhpSpreadsheet\Reader\IReadFilter;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx;

/**
 * Importa o valor de locação mensal das impressoras a partir do pré-faturamento
 * da Simpress (.xlsx).
 *
 * A locação é fixa por equipamento e vive na coluna "Valor Mensal Item" (S) do
 * relatório, casada pelo número de série (coluna A). Só interessam as linhas de
 * hardware: as linhas NDDIGITAL / SOFT / SOS são software por dispositivo (não
 * locação) e a linha "TOTAIS:" é um rodapé — todas descartadas.
 *
 * Algumas séries no CoreTI vêm truncadas pela API Insight (terminam em "......");
 * para essas a casação é por prefixo, aceita só quando houver um único candidato.
 */
class ImportarLocacaoImpressoras extends Command
{
    protected $signature = 'impressoras:importar-locacao
        {arquivo : Caminho do .xlsx de pré-faturamento}
        {--dry-run : Mostra o que seria feito sem gravar}';

    protected $description = 'Importa o valor de locação mensal das impressoras do pré-faturamento Simpress (.xlsx).';

    /** Modelos que não são locação de hardware. */
    private const NAO_HARDWARE = ['NDDIGITAL', 'SOFT SAMSUNG', 'SOS'];

    public function handle(): int
    {
        $arquivo = $this->argument('arquivo');

        if (! is_file($arquivo)) {
            $this->error("Arquivo não encontrado: {$arquivo}");

            return self::FAILURE;
        }

        $this->info('Lendo a planilha (colunas Série / Modelo / Valor Mensal Item)...');
        $locacaoPorSerie = $this->lerPlanilha($arquivo);
        $this->line('  '.count($locacaoPorSerie).' série(s) de hardware na fatura.');

        $seriesHardware = array_keys($locacaoPorSerie);
        $dryRun = (bool) $this->option('dry-run');
        $agora = Carbon::now();

        $casadasExato = 0;
        $casadasPrefixo = 0;
        $somaLocacao = 0.0;
        $semMatch = [];
        $ambiguas = [];

        foreach (Printer::query()->get(['id', 'serial_number']) as $printer) {
            $serie = trim((string) $printer->serial_number);
            $valor = null;
            $via = null;

            if ($serie !== '' && array_key_exists($serie, $locacaoPorSerie)) {
                $valor = $locacaoPorSerie[$serie];
                $via = 'exato';
            } elseif (str_contains($serie, '......')) {
                $prefixo = rtrim(str_replace('......', '', $serie), '.');
                $candidatos = $prefixo === '' ? [] : array_values(array_filter(
                    $seriesHardware,
                    static fn (string $h): bool => str_starts_with($h, $prefixo)
                ));

                if (count($candidatos) === 1) {
                    $valor = $locacaoPorSerie[$candidatos[0]];
                    $via = 'prefixo';
                } elseif (count($candidatos) > 1) {
                    $ambiguas[] = $serie;
                }
            }

            if ($valor === null) {
                if (! str_contains($serie, '......') || ! in_array($serie, $ambiguas, true)) {
                    $semMatch[] = $serie !== '' ? $serie : "#{$printer->id}";
                }

                continue;
            }

            $via === 'exato' ? $casadasExato++ : $casadasPrefixo++;
            $somaLocacao += (float) $valor;

            if (! $dryRun) {
                $printer->forceFill([
                    'rental_value' => $valor,
                    'rental_value_updated_at' => $agora,
                ])->save();
            }
        }

        $totalCasadas = $casadasExato + $casadasPrefixo;

        $this->table(
            ['Casadas (exato)', 'Casadas (prefixo)', 'Total casadas', 'Sem match', 'Ambíguas'],
            [[$casadasExato, $casadasPrefixo, $totalCasadas, count($semMatch), count($ambiguas)]]
        );
        $this->info('Soma da locação das casadas: R$ '.number_format($somaLocacao, 2, ',', '.'));

        if ($ambiguas !== []) {
            $this->warn('Prefixos ambíguos (não gravados): '.implode(', ', array_slice($ambiguas, 0, 20)));
        }

        if ($dryRun) {
            $this->warn('DRY-RUN: nada foi gravado.');
        } else {
            $this->info('Valores de locação gravados em printers.rental_value.');
        }

        return self::SUCCESS;
    }

    /**
     * @return array<string,float> série => valor mensal de locação (só hardware)
     */
    private function lerPlanilha(string $arquivo): array
    {
        $reader = new Xlsx;
        $reader->setReadDataOnly(true);
        $reader->setReadFilter(new class implements IReadFilter
        {
            public function readCell(string $column, int $row, string $worksheetName = ''): bool
            {
                return $row >= 11 && in_array($column, ['A', 'D', 'S'], true);
            }
        });

        $sheet = $reader->load($arquivo)->getActiveSheet();
        $mapa = [];

        foreach ($sheet->getRowIterator(11) as $row) {
            $serie = trim((string) ($sheet->getCell('A'.$row->getRowIndex())->getValue() ?? ''));
            $modelo = trim((string) ($sheet->getCell('D'.$row->getRowIndex())->getValue() ?? ''));
            $valor = $sheet->getCell('S'.$row->getRowIndex())->getValue();

            if ($serie === '' || str_starts_with($serie, 'TOTAIS')) {
                continue;
            }
            if ($modelo === '' || in_array($modelo, self::NAO_HARDWARE, true)) {
                continue;
            }
            if (! is_numeric($valor)) {
                continue;
            }

            // Se a mesma série aparecer em mais de uma linha de hardware, soma.
            $mapa[$serie] = ($mapa[$serie] ?? 0.0) + (float) $valor;
        }

        return $mapa;
    }
}
