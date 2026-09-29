<?php

namespace App\Console\Commands;

use App\Services\Impressao\PrintJobIngestService;
use Illuminate\Console\Command;
use PhpOffice\PhpSpreadsheet\IOFactory;

/**
 * Importa um relatório de impressões já exportado dos servidores.
 *
 * Serve para o histórico que existe em planilha mas não está mais no log do
 * Windows — o canal do PrintService gira e apaga os eventos antigos. Sem esta
 * porta, esse período se perderia para sempre.
 *
 * Passa pelo mesmo serviço do envio automático, então a regra de duplicidade
 * é a mesma: importar duas vezes não infla a estatística.
 */
class ImportarPlanilhaImpressoes extends Command
{
    protected $signature = 'impressao:importar-planilha
        {arquivo : Caminho do .xlsx com a aba Detalhe}
        {--servidor=PLANILHA : Origem a registrar nos eventos}
        {--aba=Detalhe : Nome da aba}
        {--simular : Lê e relata sem gravar}';

    protected $description = 'Importa o histórico de impressões de uma planilha exportada dos servidores.';

    public function handle(PrintJobIngestService $ingest): int
    {
        $arquivo = (string) $this->argument('arquivo');

        if (! is_file($arquivo)) {
            $this->error("Arquivo não encontrado: {$arquivo}");

            return self::FAILURE;
        }

        $aba = IOFactory::load($arquivo)->getSheetByName((string) $this->option('aba'));

        if ($aba === null) {
            $this->error('Aba "'.$this->option('aba').'" não encontrada na planilha.');

            return self::FAILURE;
        }

        $eventos = [];
        $ultima = $aba->getHighestRow();

        for ($i = 2; $i <= $ultima; $i++) {
            $usuario = trim((string) $aba->getCell('D'.$i)->getValue());
            $impressora = trim((string) $aba->getCell('F'.$i)->getValue());

            if ($usuario === '' || $impressora === '') {
                continue;
            }

            $eventos[] = [
                'impresso_em' => $this->instante($aba->getCell('A'.$i)),
                'usuario' => $usuario,
                'estacao' => trim((string) $aba->getCell('E'.$i)->getValue()),
                'impressora' => $impressora,
                'porta' => trim((string) $aba->getCell('G'.$i)->getValue()),
                'doc_numero' => trim((string) $aba->getCell('H'.$i)->getValue()),
                'paginas' => (int) $aba->getCell('I'.$i)->getValue(),
                // A planilha traz KB; o banco guarda bytes.
                'tamanho_bytes' => (int) round(((float) $aba->getCell('J'.$i)->getValue()) * 1024),
            ];
        }

        $this->components->twoColumnDetail('Linhas lidas', (string) count($eventos));

        if ($eventos === []) {
            $this->warn('Nada a importar.');

            return self::SUCCESS;
        }

        if ($this->option('simular')) {
            $this->table(
                ['Quando', 'Usuário', 'Impressora', 'Páginas'],
                array_map(
                    fn (array $e): array => [$e['impresso_em'], $e['usuario'], $e['impressora'], $e['paginas']],
                    array_slice($eventos, 0, 5)
                )
            );
            $this->comment('Simulação: nada foi gravado.');

            return self::SUCCESS;
        }

        $totais = ['gravados' => 0, 'repetidos' => 0, 'ignorados' => 0];

        // Em pedaços para o serviço não montar um array gigante de uma vez.
        foreach (array_chunk($eventos, 1000) as $pedaco) {
            $r = $ingest->registrar((string) $this->option('servidor'), $pedaco);

            foreach (array_keys($totais) as $k) {
                $totais[$k] += $r[$k];
            }
        }

        $this->newLine();
        $this->table(['Gravados', 'Repetidos', 'Ignorados'], [array_values($totais)]);

        return self::SUCCESS;
    }

    /**
     * Instante do evento, com o fuso de quem gerou a planilha.
     *
     * A célula é data do Excel (número serial) ou texto. Em ambos os casos o
     * horário é o local de Brasília — o relatório saiu dos servidores daqui —
     * e o offset precisa ir explícito, senão o serviço trataria como UTC e o
     * gráfico por horário sairia três horas deslocado.
     */
    private function instante(\PhpOffice\PhpSpreadsheet\Cell\Cell $celula): string
    {
        $valor = $celula->getValue();

        if (is_numeric($valor)) {
            /*
             | excelToTimestamp arredondado, não excelToDateTimeObject.
             |
             | O serial do Excel é fração de dia em ponto flutuante: o segundo
             | 58 chega como 57,9999 e o objeto sai com :57. O arredondamento
             | recupera o valor que o Excel mostra — e isso importa porque o
             | horário entra na chave que evita duplicata: um segundo de
             | diferença faria o mesmo evento entrar duas vezes quando o
             | script do servidor reenviasse a mesma janela.
             */
            $relogio = gmdate('Y-m-d H:i:s', (int) round(
                \PhpOffice\PhpSpreadsheet\Shared\Date::excelToTimestamp((float) $valor)
            ));

            /*
             | E é hora de parede, não UTC: o relatório saiu dos servidores
             | daqui. Interpretar como Brasília, e não converter para ela.
             */
            return \Illuminate\Support\Carbon::parse(
                $relogio,
                config('app.display_timezone', 'America/Sao_Paulo')
            )->toIso8601String();
        }

        return trim((string) $celula->getFormattedValue());
    }
}
