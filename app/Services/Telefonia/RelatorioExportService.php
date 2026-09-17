<?php

namespace App\Services\Telefonia;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Exportação em planilha e PDF para as telas de telefonia e equipamentos.
 *
 * Um único lugar para os dois formatos porque a alternativa é repetir 60
 * linhas de formatação em cada tela — foi o que aconteceu no gestaoTI, onde
 * linhas, celulares e vínculos têm três cópias quase iguais do mesmo código,
 * e a coluna de total só existe em uma delas.
 *
 * O visual segue o do gestaoTI (cabeçalho azul, linhas alternadas, rodapé de
 * total) para quem recebe o relatório não estranhar a troca de sistema.
 */
class RelatorioExportService
{
    private const AZUL_CABECALHO = '4472C4';

    private const CINZA_RODAPE = 'E7E6E6';

    private const CINZA_ZEBRA = 'F2F2F2';

    /**
     * @param  array<int, string>  $cabecalhos
     * @param  array<int, array<int, string|int|float|null>>  $linhas
     * @param  array<int, string|null>|null  $rodape  Linha de totais, alinhada às colunas.
     * @param  array<int, string>  $textoLiteral  Índices de coluna (0-based) que não podem virar número — IMEI, patrimônio, DDD.
     */
    public function excel(
        string $titulo,
        array $cabecalhos,
        array $linhas,
        ?array $rodape = null,
        array $textoLiteral = [],
        ?string $subtitulo = null,
    ): StreamedResponse {
        $planilha = new Spreadsheet();
        $aba = $planilha->getActiveSheet();
        $aba->setTitle(mb_substr($titulo, 0, 31));

        $ultimaColuna = $this->colunaExcel(count($cabecalhos) - 1);

        $aba->setCellValue('A1', mb_strtoupper($titulo));
        $aba->mergeCells("A1:{$ultimaColuna}1");
        $aba->getStyle('A1')->applyFromArray([
            'font' => ['bold' => true, 'size' => 14],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);

        $aba->setCellValue('A2', trim(($subtitulo ? $subtitulo.' · ' : '').'Gerado em '.now()->format('d/m/Y H:i')));
        $aba->mergeCells("A2:{$ultimaColuna}2");
        $aba->getStyle('A2')->applyFromArray([
            'font' => ['size' => 9, 'color' => ['rgb' => '666666']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);

        foreach ($cabecalhos as $indice => $cabecalho) {
            $aba->setCellValue($this->colunaExcel($indice).'4', $cabecalho);
        }

        $aba->getStyle("A4:{$ultimaColuna}4")->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => self::AZUL_CABECALHO]],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
        ]);

        $linhaAtual = 5;

        foreach ($linhas as $registro) {
            foreach (array_values($registro) as $indice => $valor) {
                $celula = $this->colunaExcel($indice).$linhaAtual;

                // IMEI e patrimônio viram notação científica ou perdem o zero à
                // esquerda se o Excel os tratar como número.
                if (in_array($indice, $textoLiteral, true)) {
                    $aba->setCellValueExplicit($celula, (string) $valor, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                } else {
                    $aba->setCellValue($celula, $valor);
                }
            }

            if ($linhaAtual % 2 === 1) {
                $aba->getStyle("A{$linhaAtual}:{$ultimaColuna}{$linhaAtual}")->applyFromArray([
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => self::CINZA_ZEBRA]],
                ]);
            }

            $linhaAtual++;
        }

        $ultimaLinhaDados = $linhaAtual - 1;

        if ($ultimaLinhaDados >= 5) {
            $aba->getStyle("A5:{$ultimaColuna}{$ultimaLinhaDados}")->applyFromArray([
                'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'DDDDDD']]],
            ]);
        }

        if ($rodape !== null) {
            foreach (array_values($rodape) as $indice => $valor) {
                if ($valor !== null && $valor !== '') {
                    $aba->setCellValue($this->colunaExcel($indice).$linhaAtual, $valor);
                }
            }

            $aba->getStyle("A{$linhaAtual}:{$ultimaColuna}{$linhaAtual}")->applyFromArray([
                'font' => ['bold' => true],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => self::CINZA_RODAPE]],
                'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
            ]);
        }

        foreach (range(0, count($cabecalhos) - 1) as $indice) {
            $aba->getColumnDimension($this->colunaExcel($indice))->setAutoSize(true);
        }

        $aba->freezePane('A5');

        $nome = $this->nomeArquivo($titulo, 'xlsx');

        return response()->streamDownload(function () use ($planilha): void {
            (new Xlsx($planilha))->save('php://output');
        }, $nome, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /**
     * @param  array<int, string>  $cabecalhos
     * @param  array<int, array<int, string|int|float|null>>  $linhas
     * @param  array<int, string|null>|null  $rodape
     */
    public function pdf(
        string $titulo,
        array $cabecalhos,
        array $linhas,
        ?array $rodape = null,
        string $orientacao = 'landscape',
        ?string $subtitulo = null,
    ): Response {
        $pdf = Pdf::loadView('telefonia.exports.tabela-pdf', [
            'titulo' => $titulo,
            'subtitulo' => $subtitulo,
            'cabecalhos' => $cabecalhos,
            'linhas' => $linhas,
            'rodape' => $rodape,
        ])->setPaper('a4', $orientacao);

        return $pdf->download($this->nomeArquivo($titulo, 'pdf'));
    }

    /** 0 => A, 25 => Z, 26 => AA. */
    private function colunaExcel(int $indice): string
    {
        $coluna = '';

        for ($n = $indice; $n >= 0; $n = intdiv($n, 26) - 1) {
            $coluna = chr($n % 26 + 65).$coluna;
        }

        return $coluna;
    }

    private function nomeArquivo(string $titulo, string $extensao): string
    {
        $base = preg_replace('/[^a-z0-9]+/', '-', mb_strtolower(\Illuminate\Support\Str::ascii($titulo))) ?? 'relatorio';

        return trim($base, '-').'-'.now()->format('Y-m-d-His').'.'.$extensao;
    }
}
