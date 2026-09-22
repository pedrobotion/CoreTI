<?php

namespace App\Services;

use App\Models\NetworkServicoConectividade;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class NetworkFichaIaExtractionService
{
    private const KNOWN_LABELS = [
        'CONTRATO',
        'DADOS DO SERVICO',
        'DADOS DO SERVIÇO',
        'RAZAO SOCIAL',
        'RAZÃO SOCIAL',
        'VELOCIDADE NOMINAL DO SERVICO',
        'VELOCIDADE NOMINAL DO SERVIÇO',
        'VELOCIDADE NOMINAL',
        'PRODUTO',
        'REDE',
        'MASCARA',
        'MÁSCARA',
        'GATEWAY',
        'BROADCAST',
        'FAIXA',
        'FAIXA (GATEWAY ESTA DENTRO DA FAIXA)',
        'FAIXA (GATEWAY ESTÁ DENTRO DA FAIXA)',
        'QTD',
        'ENDERECO DE INSTALACAO',
        'ENDEREÇO DE INSTALAÇÃO',
        'ESTACAO A',
        'ESTAÇÃO A',
        'ENDERECO',
        'ENDEREÇO',
        'NUMERO',
        'NÚMERO',
        'BAIRRO',
        'CONTATO',
        'MUNICIPIO',
        'MUNICÍPIO',
        'CONTRATANTE',
        'OUTROS CONTATOS',
        'CONEXOES',
        'CONEXÕES',
        'NOME TELEFONE E-MAIL',
        '1. DADOS DO SERVICO',
        '1. DADOS DO SERVIÇO',
        '2. ENDERECO DE INSTALACAO',
        '2. ENDEREÇO DE INSTALAÇÃO',
        '3. OUTROS CONTATOS',
        '4. CONEXOES',
        '4. CONEXÕES',
    ];

    public function extract(NetworkServicoConectividade $servico): array
    {
        if (! $servico->ficha_storage_path || ! Storage::disk('local')->exists($servico->ficha_storage_path)) {
            return [
                'success' => false,
                'message' => 'Anexe a ficha PDF antes de solicitar a extração.',
                'source' => 'fallback',
                'data' => [],
                'raw_text' => '',
                'warnings' => [],
            ];
        }

        return $this->extractFromPdf(Storage::disk('local')->path($servico->ficha_storage_path));
    }

    public function extractFromPdf(string $path): array
    {
        if (! is_file($path) || ! is_readable($path)) {
            return [
                'success' => false,
                'message' => 'Não foi possível ler o arquivo PDF informado.',
                'source' => 'fallback',
                'data' => [],
                'raw_text' => '',
                'warnings' => [],
            ];
        }

        $rawText = $this->readTextFromPdf($path);
        $normalizedText = $this->normalizePdfText($rawText);

        if (app()->environment('local')) {
            Log::debug('Texto extraído da ficha Network', [
                'text_preview' => mb_substr($rawText, 0, 3000),
            ]);
        }

        if (trim($normalizedText) === '') {
            return [
                'success' => false,
                'message' => 'Não foi possível extrair texto do PDF. Verifique se a ficha é textual.',
                'source' => 'fallback',
                'data' => [],
                'raw_text' => '',
                'warnings' => ['pdftotext retornou vazio.'],
            ];
        }

        $data = $this->extractWithRegex($normalizedText);

        if ($this->shouldFallbackContratanteToRazaoSocial($data['contratante_nome'] ?? null, $data['razao_social'] ?? null)) {
            $data['contratante_nome'] = $data['razao_social'] ?? $data['contratante_nome'];
        }

        if (blank($data['contratante_nome'] ?? null)) {
            $data['contratante_nome'] = $data['razao_social'] ?? null;
        }

        $warnings = [];
        foreach (array_keys($data) as $field) {
            if (blank(Arr::get($data, $field))) {
                $warnings[] = sprintf('Campo %s não identificado automaticamente.', $field);
            }
        }

        return [
            'success' => true,
            'message' => 'Dados extraídos com sucesso. Revise antes de salvar.',
            'source' => 'regex',
            'data' => $data,
            'raw_text' => $rawText,
            'warnings' => $warnings,
        ];
    }

    private function extractWithRegex(string $rawText): array
    {
        $normalizedText = $this->normalizePdfText($rawText);
        $lines = $this->splitLines($normalizedText);

        $data = [
            'contrato' => null,
            'razao_social' => null,
            'velocidade_nominal' => null,
            'produto_nome' => null,
            'rede' => null,
            'mascara' => null,
            'gateway' => null,
            'broadcast' => null,
            'faixa' => null,
            'qtd' => null,
            'estacao_a' => null,
            'endereco' => null,
            'numero' => null,
            'bairro' => null,
            'municipio' => null,
            'uf' => null,
            'contato' => null,
            'outros_contatos' => null,
            'contratante_nome' => null,
        ];

        $serviceRow = $this->nextLineAfterGroup($lines, ['CONTRATO', 'CONTRATANTE', 'RAZAO SOCIAL', 'RAZÃO SOCIAL']);
        $speedRow = $this->nextLineAfterGroup($lines, ['VELOCIDADE NOMINAL DO SERVICO', 'VELOCIDADE NOMINAL DO SERVIÇO', 'PRODUTO']);
        $networkRow = $this->nextLineAfterGroup($lines, ['REDE', 'MASCARA', 'MÁSCARA', 'GATEWAY', 'BROADCAST', 'FAIXA', 'QTD']);
        $stationRow = $this->nextLineAfterGroup($lines, ['ESTACAO A', 'ESTAÇÃO A']);
        $addressRow = $this->nextLineAfterGroup($lines, ['ENDERECO', 'ENDEREÇO', 'NUMERO', 'NÚMERO']);
        $contactRow = $this->nextLineAfterGroup($lines, ['BAIRRO', 'MUNICIPIO', 'MUNICÍPIO', 'CONTATO']);
        $contactsRow = $this->nextLineAfterGroup($lines, ['NOME TELEFONE E-MAIL']);

        if ($serviceRow !== null) {
            $cols = $this->splitColumnsRow($serviceRow);
            $data['contrato'] = $this->cleanValue($cols[0] ?? null);
            $data['contratante_nome'] = $this->cleanValue($cols[1] ?? null);
            $data['razao_social'] = $this->cleanValue($cols[2] ?? $cols[1] ?? null);
        }

        if ($speedRow !== null) {
            $cols = $this->splitColumnsRow($speedRow);
            $data['velocidade_nominal'] = $this->extractFirstSpeed($cols[0] ?? null) ?? $this->extractFirstSpeed($speedRow);
            $data['produto_nome'] = $this->cleanValue($cols[1] ?? null) ?? $this->extractProductFromText($speedRow);
        }

        if ($networkRow !== null) {
            $cols = $this->splitColumnsRow($networkRow);
            $data['rede'] = $this->extractFirstCidr($cols[0] ?? null) ?? $this->extractFirstCidr($networkRow);
            $data['mascara'] = $this->extractFirstSubnetMask($cols[1] ?? null) ?? $this->extractFirstSubnetMask($networkRow);
            $data['gateway'] = $this->extractFirstIpv4($cols[2] ?? null) ?? $this->extractFirstIpv4($networkRow);
            $data['broadcast'] = $this->extractFirstIpv4($cols[3] ?? null) ?? $this->extractFirstIpv4($networkRow);
            $data['faixa'] = $this->extractFirstIpRange($cols[4] ?? null) ?? $this->extractFirstIpRange($networkRow);
            $data['qtd'] = $this->extractFirstQtd($cols[5] ?? null) ?? $this->extractFirstQtd($networkRow);
        }

        if ($stationRow !== null) {
            $data['estacao_a'] = $this->cleanValue($stationRow);
        }

        if ($addressRow !== null) {
            $cols = $this->splitColumnsRow($addressRow);
            $data['endereco'] = $this->cleanValue($cols[0] ?? null);
            $data['numero'] = $this->extractFirstNumber($cols[1] ?? null) ?? $this->extractFirstNumber($addressRow);
        }

        if ($contactRow !== null) {
            $cols = $this->splitColumnsRow($contactRow);
            $data['bairro'] = $this->cleanValue($cols[0] ?? null);
            $municipioUfRaw = $this->cleanValue($cols[1] ?? null);
            if ($municipioUfRaw !== null && preg_match('/^(.*?)\s*-\s*([A-Z]{2})$/u', $municipioUfRaw, $matches)) {
                $data['municipio'] = $this->cleanValue($matches[1]);
                $data['uf'] = $this->cleanValue($matches[2]);
            } else {
                $data['municipio'] = $this->cleanValue($municipioUfRaw);
            }
            $data['contato'] = $this->cleanValue($cols[2] ?? null);
        }

        if ($contactsRow !== null) {
            $cols = $this->splitColumnsRow($contactsRow);
            $data['outros_contatos'] = $this->cleanValue(implode(' ', array_filter(array_map([$this, 'cleanValue'], $cols))));
        }

        if (blank($data['numero']) && filled($data['endereco'])) {
            $data['numero'] = $this->extractFirstNumber($data['endereco']);
        }

        if (blank($data['contratante_nome'])) {
            $data['contratante_nome'] = $data['razao_social'];
        }

        if (blank($data['razao_social']) && filled($data['contratante_nome'])) {
            $data['razao_social'] = $data['contratante_nome'];
        }

        if (blank($data['municipio']) && filled($data['contato'])) {
            $maybeMunicipio = $this->extractFirstMunicipio($contactRow ?? '');
            if ($maybeMunicipio !== null && preg_match('/^(.*?)\s*-\s*([A-Z]{2})$/u', $maybeMunicipio, $matches)) {
                $data['municipio'] = $this->cleanValue($matches[1]);
                $data['uf'] = $this->cleanValue($matches[2]);
            }
        }

        return $data;
    }

    private function extractContract(string $text): ?string
    {
        $value = $this->extractBetweenLabels($text, ['CONTRATO'], self::KNOWN_LABELS);
        if (! $this->isValidContractValue($value)) {
            $value = $this->extractPattern($text, '/\b([0-9]{6,10})\b/');
        }

        return $this->cleanValue($value);
    }

    private function extractRazaoSocial(string $serviceText, string $allText): ?string
    {
        $value = $this->extractBetweenLabels($serviceText, ['RAZAO SOCIAL', 'RAZÃO SOCIAL'], self::KNOWN_LABELS);
        if (! $this->isValidCompanyName($value)) {
            $value = $this->extractCompanyFromText($serviceText)
                ?? $this->extractCompanyFromText($allText);
        }

        return $this->cleanValue($value);
    }

    private function extractProduto(string $serviceText, string $allText): ?string
    {
        $value = $this->extractBetweenLabels($serviceText, ['PRODUTO'], self::KNOWN_LABELS);
        if ($this->isInvalidExtractedValue($value)) {
            $value = $this->extractProductFromText($serviceText)
                ?? $this->extractProductFromText($allText);
        }

        return $this->cleanValue($value);
    }

    private function extractSpeed(string $serviceText): ?string
    {
        $value = $this->extractBetweenLabels($serviceText, ['VELOCIDADE NOMINAL DO SERVICO', 'VELOCIDADE NOMINAL DO SERVIÇO', 'VELOCIDADE NOMINAL'], self::KNOWN_LABELS);
        $value = $this->extractFirstSpeed($value) ?? $this->extractFirstSpeed($serviceText);

        return $this->cleanValue($value);
    }

    private function extractRede(string $serviceText): ?string
    {
        $value = $this->extractBetweenLabels($serviceText, ['REDE'], self::KNOWN_LABELS);
        $value = $this->extractFirstCidr($value) ?? $this->extractFirstCidr($serviceText);

        return $this->cleanValue($value);
    }

    private function extractMascara(string $serviceText, string $allText): ?string
    {
        $value = $this->extractBetweenLabels($serviceText, ['MASCARA', 'MÁSCARA'], self::KNOWN_LABELS);
        $value = $this->extractFirstSubnetMask($value) ?? $this->extractFirstSubnetMask($serviceText) ?? $this->extractFirstSubnetMask($allText);

        return $this->cleanValue($value);
    }

    private function extractGateway(string $serviceText): ?string
    {
        $value = $this->extractBetweenLabels($serviceText, ['GATEWAY'], self::KNOWN_LABELS);
        $value = $this->extractFirstIpv4($value) ?? $this->extractFirstIpv4($serviceText);

        return $this->cleanValue($value);
    }

    private function extractBroadcast(string $serviceText): ?string
    {
        $value = $this->extractBetweenLabels($serviceText, ['BROADCAST'], self::KNOWN_LABELS);
        $value = $this->extractFirstIpv4($value) ?? $this->extractFirstIpv4($serviceText);

        return $this->cleanValue($value);
    }

    private function extractFaixa(string $serviceText): ?string
    {
        $value = $this->extractBetweenLabels($serviceText, ['FAIXA (GATEWAY ESTA DENTRO DA FAIXA)', 'FAIXA (GATEWAY ESTÁ DENTRO DA FAIXA)', 'FAIXA'], self::KNOWN_LABELS);
        $value = $this->extractFirstIpRange($value) ?? $this->extractFirstIpRange($serviceText);

        return $this->cleanValue($value);
    }

    private function extractQtd(string $serviceText): ?string
    {
        $value = $this->extractBetweenLabels($serviceText, ['QTD'], self::KNOWN_LABELS);
        $value = $this->extractFirstQtd($value) ?? $this->extractFirstQtd($serviceText);

        return $this->cleanValue($value);
    }

    private function extractEstacaoA(string $enderecoText, string $allText): ?string
    {
        $value = $this->extractBetweenLabels($enderecoText, ['ESTACAO A', 'ESTAÇÃO A'], self::KNOWN_LABELS);
        if ($this->isInvalidExtractedValue($value)) {
            $value = $this->extractPattern($allText, '/\(\d+-A\).*?AMAZONAS,\s*\d+/u');
        }

        return $this->cleanValue($value);
    }

    private function extractEndereco(string $enderecoText, string $allText): ?string
    {
        $value = $this->extractBetweenLabels($enderecoText, ['ENDERECO', 'ENDEREÇO'], self::KNOWN_LABELS);
        $value = $this->extractFirstAddress($value) ?? $this->extractFirstAddress($enderecoText) ?? $this->extractFirstAddress($allText);

        if ($this->isInvalidExtractedValue($value)) {
            $value = null;
        }

        return $this->cleanValue($value);
    }

    private function extractNumero(string $enderecoText, string $allText): ?string
    {
        $value = $this->extractBetweenLabels($enderecoText, ['NUMERO', 'NÚMERO'], self::KNOWN_LABELS);
        $value = $this->extractFirstNumber($value) ?? $this->extractFirstNumber($enderecoText) ?? $this->extractFirstNumber($allText);

        if ($this->isInvalidExtractedValue($value)) {
            $endereco = $this->extractEndereco($enderecoText, $allText);
            if (is_string($endereco) && preg_match('/,\s*([0-9A-Za-z\/-]+)\s*$/u', $endereco, $matches)) {
                $value = $matches[1];
            }
        }

        return $this->cleanValue($value);
    }

    private function extractBairro(string $enderecoText, string $allText): ?string
    {
        $value = $this->extractBetweenLabels($enderecoText, ['BAIRRO'], self::KNOWN_LABELS);
        if ($this->isInvalidExtractedValue($value)) {
            $value = $this->extractFirstBairro($enderecoText) ?? $this->extractFirstBairro($allText);
        }

        return $this->cleanValue($value);
    }

    private function extractContato(string $enderecoText, string $allText): ?string
    {
        $value = $this->extractBetweenLabels($enderecoText, ['CONTATO'], self::KNOWN_LABELS);
        if ($this->isInvalidExtractedValue($value)) {
            $value = $this->extractFirstContact($enderecoText) ?? $this->extractFirstContact($allText);
        }

        return $this->cleanValue($value);
    }

    private function extractMunicipioUf(string $enderecoText, string $allText): array
    {
        $value = $this->extractBetweenLabels($enderecoText, ['MUNICIPIO', 'MUNICÍPIO'], self::KNOWN_LABELS);
        if ($this->isInvalidExtractedValue($value)) {
            $value = $this->extractFirstMunicipio($enderecoText) ?? $this->extractFirstMunicipio($allText);
        }

        $value = $this->cleanValue($value);
        if (preg_match('/^(.*?)\s*-\s*([A-Z]{2})$/u', (string) $value, $matches)) {
            return [
                'municipio' => $this->cleanValue($matches[1]),
                'uf' => $this->cleanValue($matches[2]),
            ];
        }

        return [
            'municipio' => $value,
            'uf' => null,
        ];
    }

    private function extractOutrosContatos(string $outrosText): ?string
    {
        $value = $this->cleanValue($outrosText);
        if ($this->isInvalidExtractedValue($value)) {
            $value = $this->extractCompanyFromText($outrosText)
                ?? $this->extractFirstContact($outrosText)
                ?? $value;
        }

        return $this->cleanValue($value);
    }

    private function extractContratanteNome(string $serviceText, string $allText): ?string
    {
        $value = $this->extractBetweenLabels($serviceText, ['CONTRATANTE'], self::KNOWN_LABELS);
        if (! $this->isValidCompanyName($value)) {
            $value = $this->extractCompanyFromText($serviceText)
                ?? $this->extractCompanyFromText($allText);
        }

        return $this->cleanValue($value);
    }

    private function nextLineAfterGroup(array $lines, array $labels): ?string
    {
        $normalizedLines = $this->normalizeLines($lines);
        $normalizedLabels = array_map([$this, 'normalizeLabel'], $labels);

        foreach ($normalizedLines as $index => $line) {
            $matchesAll = true;
            foreach ($normalizedLabels as $label) {
                if ($label === '') {
                    continue;
                }

                if (! str_contains($line, $label)) {
                    $matchesAll = false;
                    break;
                }
            }

            if (! $matchesAll) {
                continue;
            }

            for ($cursor = $index + 1; $cursor < count($lines); $cursor++) {
                $candidate = trim((string) $lines[$cursor]);
                if ($candidate === '') {
                    continue;
                }

                if ($this->isKnownLabel($this->normalizeLabel($candidate))) {
                    continue;
                }

                return $candidate;
            }
        }

        return null;
    }

    private function splitColumnsRow(string $row): array
    {
        $row = trim(str_replace("\t", ' ', $row));
        if ($row === '') {
            return [];
        }

        $parts = preg_split('/\s{2,}/', $row) ?: [];
        $parts = array_values(array_filter(array_map(fn ($part) => trim((string) $part), $parts), fn ($part) => $part !== ''));

        if (count($parts) <= 1 && preg_match('/\s{2,}/', $row) === 0) {
            return [$row];
        }

        return $parts;
    }

    private function extractBetweenLabels(string $text, array $startLabels, array $stopLabels): ?string
    {
        $normalized = $this->normalizePdfText($text);
        $startPattern = $this->buildLabelPattern($startLabels);
        $stopPattern = $this->buildLabelPattern($stopLabels);

        if ($startPattern === '') {
            return null;
        }

        $pattern = '/(?:^|\n)\s*(?:' . $startPattern . ')\s*[:\-]?\s*(.*?)(?=(?:\n\s*(?:' . $stopPattern . ')\b)|$)/isu';

        if (preg_match($pattern, $normalized, $matches)) {
            return $this->cleanValue($matches[1]);
        }

        return null;
    }

    private function extractSection(string $text, array $startLabels, array $stopLabels): ?string
    {
        $normalized = $this->normalizePdfText($text);
        $startPattern = $this->buildLabelPattern($startLabels);
        $stopPattern = $this->buildLabelPattern($stopLabels);

        if ($startPattern === '') {
            return null;
        }

        $pattern = '/(?:^|\n)\s*(?:' . $startPattern . ')\s*(.*?)(?=(?:\n\s*(?:' . $stopPattern . ')\b)|$)/isu';

        if (preg_match($pattern, $normalized, $matches)) {
            return $this->cleanValue($matches[1]);
        }

        return null;
    }

    private function buildLabelPattern(array $labels): string
    {
        $patterns = [];
        foreach ($labels as $label) {
            $label = trim((string) $label);
            if ($label === '') {
                continue;
            }
            $parts = preg_split('/\s+/', $label) ?: [];
            $parts = array_map(fn ($part) => preg_quote($part, '/'), $parts);
            $patterns[] = implode('\s+', $parts);
        }

        return implode('|', array_unique($patterns));
    }

    private function extractPattern(string $text, string $pattern): ?string
    {
        if (preg_match($pattern, $text, $matches)) {
            return $this->cleanValue($matches[1] ?? $matches[0]);
        }

        return null;
    }

    private function extractFirstSpeed(?string $text): ?string
    {
        if ($text === null) {
            return null;
        }

        if (preg_match('/\b\d+\s*(Mbps|Gbps)\b/iu', $text, $matches)) {
            return $this->cleanValue($matches[0]);
        }

        return null;
    }

    private function extractFirstCidr(?string $text): ?string
    {
        if ($text === null) {
            return null;
        }

        if (preg_match('/\b(?:\d{1,3}\.){3}\d{1,3}\s*\/\s*\d{1,2}\b/', $text, $matches)) {
            return $this->cleanValue($matches[0]);
        }

        return null;
    }

    private function extractFirstSubnetMask(?string $text): ?string
    {
        if ($text === null) {
            return null;
        }

        if (preg_match('/\b255(?:\.255){2}\.\d{1,3}\b/', $text, $matches)) {
            return $this->cleanValue($matches[0]);
        }

        if (preg_match('/\b(?:\d{1,3}\.){3}\d{1,3}\b/', $text, $matches)) {
            return $this->cleanValue($matches[0]);
        }

        return null;
    }

    private function extractFirstIpv4(?string $text): ?string
    {
        if ($text === null) {
            return null;
        }

        if (preg_match('/\b(?:\d{1,3}\.){3}\d{1,3}\b/', $text, $matches)) {
            return $this->cleanValue($matches[0]);
        }

        return null;
    }

    private function extractFirstIpRange(?string $text): ?string
    {
        if ($text === null) {
            return null;
        }

        if (preg_match('/\b(?:\d{1,3}\.){3}\d{1,3}\s*-\s*(?:\d{1,3}\.){3}\d{1,3}\b/', $text, $matches)) {
            return $this->cleanValue($matches[0]);
        }

        return null;
    }

    private function extractFirstQtd(?string $text): ?string
    {
        if ($text === null) {
            return null;
        }

        if (preg_match('/\b\d+\s*\+\s*\d+\b/', $text, $matches)) {
            $normalized = preg_replace('/\s*\+\s*/', ' + ', $matches[0]) ?? $matches[0];
            return $this->cleanValue($normalized);
        }

        if (preg_match('/\b\d+\b/', $text, $matches)) {
            return $this->cleanValue($matches[0]);
        }

        return null;
    }

    private function extractFirstAddress(?string $text): ?string
    {
        if ($text === null) {
            return null;
        }

        if (preg_match('/\b([A-ZÀ-Ú0-9\s.-]+?,\s*[0-9A-Za-z\/-]+)\b/u', $text, $matches)) {
            return $this->cleanValue($matches[1]);
        }

        return null;
    }

    private function extractFirstNumber(?string $text): ?string
    {
        if ($text === null) {
            return null;
        }

        if (preg_match('/,\s*([0-9A-Za-z\/-]+)\s*$/u', $text, $matches)) {
            $value = $this->cleanValue($matches[1]);
            if ($value !== '' && preg_match('/\d/u', $value) === 1) {
                return $value;
            }
        }

        return null;
    }

    private function extractFirstBairro(?string $text): ?string
    {
        if ($text === null) {
            return null;
        }

        if (preg_match('/^\s*([A-ZÀ-Ú][A-ZÀ-Ú\s.-]{1,30}?)(?=\s+[A-ZÀ-Ú].*-\s*[A-Z]{2}|\s+\d{2}\s*\d{4,5}-\d{4}|$)/u', $text, $matches)) {
            return $this->cleanValue($matches[1]);
        }

        if (preg_match('/^\s*([A-ZÀ-Ú][A-ZÀ-Ú\s.-]{1,30})/u', $text, $matches)) {
            return $this->cleanValue($matches[1]);
        }

        return null;
    }

    private function extractFirstContact(?string $text): ?string
    {
        if ($text === null) {
            return null;
        }

        if (preg_match('/\d{2}\s*\d{4,5}-\d{4}.*?(?:@|\[mailto:)/iu', $text, $matches)) {
            return $this->cleanValue($matches[0]);
        }

        if (preg_match('/\d{2}\s*\d{4,5}-\d{4}.*$/u', $text, $matches)) {
            return $this->cleanValue($matches[0]);
        }

        return null;
    }

    private function extractFirstMunicipio(?string $text): ?string
    {
        if ($text === null) {
            return null;
        }

        if (preg_match('/^(.+?)\s*-\s*([A-Z]{2})$/u', trim($text), $matches)) {
            return $this->cleanValue($matches[1] . ' - ' . $matches[2]);
        }

        return null;
    }

    private function extractCompanyFromText(?string $text): ?string
    {
        if ($text === null) {
            return null;
        }

        if (preg_match('/\b([A-ZÀ-Ú0-9][A-ZÀ-Ú0-9\s.&\/-]{2,}\b(?:LTDA|S\/A|SA|EIRELI|ME|EPP))\b/iu', $text, $matches)) {
            return $this->cleanValue($matches[1]);
        }

        return null;
    }

    private function extractProductFromText(?string $text): ?string
    {
        if ($text === null) {
            return null;
        }

        if (preg_match('/\b(IP DIRETO[^\\n]*|SERVI[ÇC]O INTERNET[^\\n]*|MPLS[^\\n]*|LINK[^\\n]*|INTERNET[^\\n]*)\b/iu', $text, $matches)) {
            return $this->cleanValue($matches[1]);
        }

        return null;
    }

    private function isValidContractValue(?string $value): bool
    {
        $value = $this->cleanValue($value);

        return $value !== '' && preg_match('/^\d{6,10}$/', $value) === 1;
    }

    private function isValidCompanyName(?string $value): bool
    {
        $value = $this->cleanValue($value);
        if ($value === '' || $this->isKnownLabelValue($value)) {
            return false;
        }

        return (bool) preg_match('/\b(LTDA|S\/A|SA|EIRELI|ME|EPP)\b/u', $value);
    }

    private function shouldFallbackContratanteToRazaoSocial(?string $contratante, ?string $razaoSocial): bool
    {
        $contratante = $this->cleanValue($contratante);
        $razaoSocial = $this->cleanValue($razaoSocial);

        if ($contratante === '' || $razaoSocial === '') {
            return false;
        }

        if ($this->isValidCompanyName($contratante)) {
            return false;
        }

        $normalized = $this->normalizeLabel($contratante);
        if ($normalized === '') {
            return false;
        }

        if (mb_strlen($normalized) <= 10 && ! str_contains($normalized, ' ')) {
            return true;
        }

        return false;
    }

    private function isValidIpv4(?string $value): bool
    {
        $value = $this->cleanValue($value);

        return $value !== '' && preg_match('/^(25[0-5]|2[0-4][0-9]|1?[0-9]{1,2})(\.(25[0-5]|2[0-4][0-9]|1?[0-9]{1,2})){3}$/', $value) === 1;
    }

    private function isInvalidExtractedValue(?string $value): bool
    {
        $value = $this->cleanValue($value);
        if ($value === '' || $value === '-') {
            return true;
        }

        if ($this->isKnownLabelValue($value)) {
            return true;
        }

        $normalized = $this->normalizeLabel($value);
        if ($normalized === '' || $this->isKnownLabel($normalized)) {
            return true;
        }

        if (preg_match('/^\d{1,2}\s*\+\s*\d{1,2}$/', $value) === 1) {
            return false;
        }

        return false;
    }

    private function isKnownLabelValue(?string $value): bool
    {
        $value = $this->cleanValue($value);
        if ($value === '' || $value === '-') {
            return true;
        }

        $normalized = $this->normalizeLabel($value);
        if ($this->isKnownLabel($normalized)) {
            return true;
        }

        $stripped = $normalized;
        foreach (self::KNOWN_LABELS as $label) {
            $stripped = trim(str_ireplace($this->normalizeLabel($label), ' ', $stripped));
        }
        $stripped = preg_replace('/\s+/', ' ', (string) $stripped);
        $stripped = trim((string) $stripped);

        if ($stripped === '') {
            return true;
        }

        $labelHits = 0;
        foreach (self::KNOWN_LABELS as $label) {
            if (str_contains($normalized, $this->normalizeLabel($label))) {
                $labelHits++;
            }
        }

        if ($labelHits >= 2) {
            return true;
        }

        if (preg_match('/^[A-Z0-9\s\/().,\-]+$/u', $normalized) === 1 && $labelHits >= 1 && mb_strlen($stripped) <= 5) {
            return true;
        }

        return false;
    }

    private function isKnownLabel(string $text): bool
    {
        $normalized = $this->normalizeLabel($text);

        foreach (self::KNOWN_LABELS as $label) {
            if ($normalized === $this->normalizeLabel($label)) {
                return true;
            }
        }

        return false;
    }

    private function normalizePdfText(string $text): string
    {
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $lines = preg_split('/\n+/', $text) ?: [];
        $normalized = [];

        foreach ($lines as $line) {
            $line = trim((string) $line);
            if ($line === '') {
                continue;
            }

            $normalized[] = trim($line);
        }

        return implode("\n", $normalized);
    }

    private function splitLines(string $text): array
    {
        return array_values(array_filter(array_map('trim', preg_split('/\n+/', $text) ?: []), fn ($line) => $line !== ''));
    }

    private function normalizeLines(array $lines): array
    {
        return array_map(fn ($line) => $this->normalizeLabel((string) $line), $lines);
    }

    private function normalizeLabel(?string $text): string
    {
        $text = Str::ascii(mb_strtoupper(trim((string) $text)));
        $text = preg_replace('/\s+/', ' ', $text) ?? $text;
        $text = preg_replace('/\s*[:\-]\s*$/', '', $text) ?? $text;

        return trim($text);
    }

    private function cleanValue(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim(preg_replace('/\s+/', ' ', $value) ?? '');
        $value = preg_replace('/\s+\|\s+/u', ' | ', $value) ?? $value;
        $value = trim($value);

        return $value !== '' ? $value : null;
    }

    private function readTextFromPdf(string $path): string
    {
        $command = 'pdftotext -layout -nopgbrk ' . escapeshellarg($path) . ' - 2>/dev/null';
        $output = shell_exec($command);

        return is_string($output) ? $output : '';
    }
}
