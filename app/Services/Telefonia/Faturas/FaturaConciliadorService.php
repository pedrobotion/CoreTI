<?php

namespace App\Services\Telefonia\Faturas;

use App\Models\CorpLine;
use App\Models\TelefoniaFatura;
use App\Models\TelefoniaFaturaLinha;
use Illuminate\Support\Facades\DB;

/**
 * Cruza o que veio no PDF com as linhas cadastradas.
 *
 * A conferência responde três perguntas, e é por isso que a importação tem
 * uma etapa de revisão antes de gravar:
 *
 *  - o que bate      → linha cadastrada que veio na fatura (com a variação)
 *  - o que é novo    → número cobrado que ninguém cadastrou ainda
 *  - o que sumiu     → linha cadastrada e ativa que não veio na fatura,
 *                      candidata a cancelamento que ninguém registrou
 */
class FaturaConciliadorService
{
    /**
     * Monta a revisão, sem gravar nada.
     *
     * @return array<string, mixed>
     */
    public function revisar(FaturaParseResult $resultado, string $scope, string $operadora): array
    {
        $cadastradas = $this->linhasCadastradas($scope, $operadora);

        $conferidas = [];
        $novas = [];
        $vistas = [];

        foreach ($resultado->linhas as $item) {
            $chave = $item->chave();
            $vistas[$chave] = true;
            $linha = $cadastradas[$chave] ?? null;

            if ($linha === null) {
                $novas[] = [
                    'ddd' => $item->ddd,
                    'numero' => $item->numero,
                    'numero_formatado' => $this->formatar($item->ddd, $item->numero),
                    'nome_plano' => $item->nomePlano,
                    'valor' => $item->valor,
                ];

                continue;
            }

            $valorAnterior = $linha->valor_mensal !== null ? (float) $linha->valor_mensal : null;

            $conferidas[] = [
                'linha_id' => $linha->id,
                'numero_formatado' => $linha->numero_formatado,
                'nome_plano' => $item->nomePlano,
                'plano_anterior' => $linha->nome_plano,
                'valor' => $item->valor,
                'valor_anterior' => $valorAnterior,
                'variacao' => $this->variacao($item->valor, $valorAnterior),
                'status_atual' => $linha->status,
            ];
        }

        $ausentes = [];

        foreach ($cadastradas as $chave => $linha) {
            if (isset($vistas[$chave]) || $linha->status === CorpLine::STATUS_CANCELADA) {
                continue;
            }

            $ausentes[] = [
                'linha_id' => $linha->id,
                'numero_formatado' => $linha->numero_formatado,
                'nome_plano' => $linha->nome_plano,
                'valor_mensal' => $linha->valor_mensal !== null ? (float) $linha->valor_mensal : null,
                'status' => $linha->status,
            ];
        }

        return [
            'conferidas' => $conferidas,
            'novas' => $novas,
            'ausentes' => $ausentes,
            'quantidade' => $resultado->quantidade(),
            'total_declarado' => $resultado->valorTotalDeclarado,
            'total_calculado' => $resultado->valorTotalCalculado,
            'totais_batem' => $resultado->totaisBatem(),
            'diferenca' => $resultado->diferencaTotal(),
            'numero_conta' => $resultado->numeroConta,
            'competencia' => $resultado->competencia,
            'vencimento' => $resultado->vencimento,
            'avisos' => $resultado->avisos,
            'parser' => $resultado->parserUsado,
        ];
    }

    /**
     * Grava a fatura, os itens e atualiza plano e valor das linhas conferidas.
     *
     * @param  array<int, int>  $linhasParaCancelar  Ausentes que a revisão confirmou como canceladas.
     */
    public function salvar(
        FaturaParseResult $resultado,
        string $scope,
        string $operadora,
        ?int $usuarioId,
        string $caminhoArquivo,
        string $nomeOriginal,
        string $hash,
        array $linhasParaCancelar = [],
    ): TelefoniaFatura {
        return DB::transaction(function () use (
            $resultado, $scope, $operadora, $usuarioId,
            $caminhoArquivo, $nomeOriginal, $hash, $linhasParaCancelar
        ): TelefoniaFatura {
            $fatura = TelefoniaFatura::query()->create([
                'scope' => $scope,
                'operadora' => $operadora,
                'numero_conta' => $resultado->numeroConta,
                'competencia' => $resultado->competencia,
                'vencimento' => $resultado->vencimento,
                'valor_total_declarado' => $resultado->valorTotalDeclarado,
                'valor_total_calculado' => $resultado->valorTotalCalculado,
                'quantidade_linhas' => $resultado->quantidade(),
                'arquivo_pdf' => $caminhoArquivo,
                'arquivo_nome_original' => $nomeOriginal,
                'hash_arquivo' => $hash,
                'status_processamento' => 'processado',
                'observacao_parser' => $resultado->avisos === [] ? null : implode("\n", $resultado->avisos),
                'importado_por' => $usuarioId,
            ]);

            $cadastradas = $this->linhasCadastradas($scope, $operadora);

            foreach ($resultado->linhas as $item) {
                $linha = $cadastradas[$item->chave()] ?? null;
                $valorAnterior = $linha?->valor_mensal !== null ? (float) $linha->valor_mensal : null;

                TelefoniaFaturaLinha::query()->create([
                    'telefonia_fatura_id' => $fatura->id,
                    'corp_line_id' => $linha?->id,
                    'competencia' => $resultado->competencia,
                    'ddd' => $item->ddd,
                    'numero' => $item->numero,
                    'nome_plano' => $item->nomePlano,
                    'valor' => $item->valor,
                    'valor_anterior' => $valorAnterior,
                    'variacao_percentual' => $this->variacao($item->valor, $valorAnterior),
                    'linha_nova' => $linha === null,
                ]);

                // A fatura é a fonte de verdade do que está sendo cobrado:
                // depois de importada, o cadastro passa a refletir a conta.
                $linha?->update([
                    'valor_mensal' => $item->valor,
                    'nome_plano' => $item->nomePlano,
                ]);
            }

            if ($linhasParaCancelar !== []) {
                CorpLine::query()
                    ->whereIn('id', $linhasParaCancelar)
                    ->where('scope', $scope)
                    ->update([
                        'status' => CorpLine::STATUS_CANCELADA,
                        'data_cancelamento' => $resultado->competencia,
                    ]);
            }

            return $fatura;
        });
    }

    /**
     * @return \Illuminate\Support\Collection<string, CorpLine>
     */
    private function linhasCadastradas(string $scope, string $operadora): \Illuminate\Support\Collection
    {
        return CorpLine::query()
            ->where('scope', $scope)
            ->where('operadora', $operadora)
            ->get()
            ->keyBy(fn (CorpLine $linha) => $linha->ddd.'|'.$linha->numero);
    }

    private function variacao(float $valor, ?float $anterior): ?float
    {
        if ($anterior === null || $anterior <= 0.0) {
            return null;
        }

        return round((($valor - $anterior) / $anterior) * 100, 2);
    }

    private function formatar(string $ddd, string $numero): string
    {
        return match (strlen($numero)) {
            9 => sprintf('(%s) %s %s-%s', $ddd, substr($numero, 0, 1), substr($numero, 1, 4), substr($numero, 5)),
            8 => sprintf('(%s) %s-%s', $ddd, substr($numero, 0, 4), substr($numero, 4)),
            default => sprintf('(%s) %s', $ddd, $numero),
        };
    }
}
