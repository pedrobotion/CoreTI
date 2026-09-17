<?php

namespace App\Services\Telefonia\Faturas\Parsers;

use App\Services\Telefonia\Faturas\FaturaParseResult;
use App\Services\Telefonia\Faturas\FaturaParserInterface;
use App\Services\Telefonia\Faturas\LinhaFaturaDTO;
use RuntimeException;
use Smalot\PdfParser\Parser as SmalotParser;

/**
 * Leitor da fatura VIVO Empresas.
 *
 * Duas formas de extrair o texto: a biblioteca PHP e, se ela devolver pouco
 * (PDF gerado de um jeito que ela não abre bem), o pdftotext do sistema. O
 * segundo preserva o alinhamento em colunas, o que ajuda no detalhamento.
 *
 * O que não for reconhecido vira aviso no resultado — nunca silêncio. Uma
 * fatura lida pela metade que passasse batido viraria conferência errada na
 * mão de quem paga.
 */
class VivoFaturaParser implements FaturaParserInterface
{
    /** Abaixo disso, o texto extraído não é uma fatura inteira. */
    private const TAMANHO_MINIMO_TEXTO = 500;

    public function operadora(): string
    {
        return 'VIVO';
    }

    public function parse(string $caminhoPdf): FaturaParseResult
    {
        if (! is_file($caminhoPdf)) {
            throw new RuntimeException("Arquivo não encontrado: {$caminhoPdf}");
        }

        $resultado = new FaturaParseResult();

        $texto = $this->extrairComBiblioteca($caminhoPdf);
        $resultado->parserUsado = 'smalot/pdfparser';

        if ($texto === null || mb_strlen($texto) < self::TAMANHO_MINIMO_TEXTO) {
            $alternativo = $this->extrairComPdftotext($caminhoPdf);

            if ($alternativo !== null && mb_strlen($alternativo) > mb_strlen($texto ?? '')) {
                $texto = $alternativo;
                $resultado->parserUsado = 'pdftotext -layout';
            }
        }

        if ($texto === null || trim($texto) === '') {
            throw new RuntimeException('Não foi possível extrair texto deste PDF. Ele pode ser uma imagem digitalizada.');
        }

        $this->extrairCabecalho($texto, $resultado);
        $this->extrairLinhas($texto, $resultado);
        $this->validar($resultado);

        return $resultado;
    }

    private function extrairComBiblioteca(string $caminho): ?string
    {
        try {
            return (new SmalotParser())->parseFile($caminho)->getText();
        } catch (\Throwable) {
            return null;
        }
    }

    private function extrairComPdftotext(string $caminho): ?string
    {
        $binario = trim((string) @shell_exec('command -v pdftotext'));

        if ($binario === '') {
            return null;
        }

        $temporario = tempnam(sys_get_temp_dir(), 'fatura_');

        @shell_exec(sprintf(
            '%s -layout -enc UTF-8 %s %s 2>/dev/null',
            escapeshellcmd($binario),
            escapeshellarg($caminho),
            escapeshellarg($temporario)
        ));

        $texto = is_file($temporario) ? @file_get_contents($temporario) : null;
        @unlink($temporario);

        return $texto ?: null;
    }

    private function extrairCabecalho(string $texto, FaturaParseResult $resultado): void
    {
        if (preg_match('/N[ºo°]?\s*da\s*Conta:\s*(\d+)/iu', $texto, $m)) {
            $resultado->numeroConta = $m[1];
        }

        if (preg_match('/M[êe]s\s*de\s*refer[êe]ncia:\s*(\d{2})\/(\d{4})/iu', $texto, $m)) {
            $resultado->competencia = sprintf('%04d-%02d-01', (int) $m[2], (int) $m[1]);
        }

        if (preg_match('/Vencimento\s*[\r\n]+\s*(\d{2})\/(\d{2})\/(\d{4})/u', $texto, $m)) {
            $resultado->vencimento = sprintf('%04d-%02d-%02d', (int) $m[3], (int) $m[2], (int) $m[1]);
        }

        if (preg_match('/Total\s*a\s*Pagar[^\d\-]*[\r\n\s]*-?\s*R?\$?\s*([\d\.]+,\d{2})/iu', $texto, $m)) {
            $resultado->valorTotalDeclarado = $this->valor($m[1]);
        }
    }

    private function extrairLinhas(string $texto, FaturaParseResult $resultado): void
    {
        // Padrão do detalhamento: "DD-NNNNN-NNNN  NOME DO PLANO  1.234,56"
        $regex = '/(\d{2})-(\d{4,5})-(\d{4})\s+([A-Z][A-Z0-9 ÇÃÕÉÊÁÍÓÚÂÔÀÈÌÒÙ\.\/\-]+?)\s+([\d\.]+,\d{2})/u';
        preg_match_all($regex, $texto, $ocorrencias, PREG_SET_ORDER);

        $vistos = [];
        $soma = 0.0;

        foreach ($ocorrencias as $ocorrencia) {
            $ddd = $ocorrencia[1];
            $numero = $ocorrencia[2].$ocorrencia[3];
            $chave = $ddd.'|'.$numero;

            // A fatura repete a mesma linha em outras seções (resumo, detalhe);
            // sem isso o total seria contado várias vezes.
            if (isset($vistos[$chave])) {
                continue;
            }

            $vistos[$chave] = true;

            $item = new LinhaFaturaDTO(
                ddd: $ddd,
                numero: $numero,
                nomePlano: $this->limparPlano($ocorrencia[4]),
                valor: $this->valor($ocorrencia[5]),
            );

            $resultado->linhas[] = $item;
            $soma += $item->valor;
        }

        $resultado->valorTotalCalculado = round($soma, 2);
    }

    private function limparPlano(string $plano): string
    {
        return mb_substr(preg_replace('/\s+/', ' ', trim($plano)) ?? '', 0, 255);
    }

    /** "1.234,56" vira 1234.56. */
    private function valor(string $bruto): float
    {
        return (float) str_replace(',', '.', str_replace('.', '', $bruto));
    }

    private function validar(FaturaParseResult $resultado): void
    {
        if ($resultado->numeroConta === null) {
            $resultado->avisos[] = 'Número da conta não foi identificado no PDF.';
        }

        if ($resultado->competencia === null) {
            $resultado->avisos[] = 'Mês de referência não foi identificado no PDF.';
        }

        if ($resultado->valorTotalDeclarado === null) {
            $resultado->avisos[] = 'Valor "Total a Pagar" não foi identificado no PDF.';
        }

        if ($resultado->linhas === []) {
            $resultado->avisos[] = 'Nenhuma linha foi reconhecida. Confira se o arquivo é mesmo uma fatura VIVO Empresas.';

            return;
        }

        if (! $resultado->totaisBatem()) {
            $resultado->avisos[] = sprintf(
                'A soma das linhas (R$ %s) não bate com o total declarado (R$ %s). Diferença de R$ %s — provavelmente alguma linha não foi reconhecida.',
                number_format($resultado->valorTotalCalculado ?? 0, 2, ',', '.'),
                number_format($resultado->valorTotalDeclarado ?? 0, 2, ',', '.'),
                number_format(abs($resultado->diferencaTotal()), 2, ',', '.')
            );
        }
    }
}
