<?php

namespace App\Services\Bancada;

use Illuminate\Support\Facades\DB;

/**
 * Detecta pendências administrativas de equipamentos da bancada: etapas que
 * dependem de uma ação administrativa (dar entrada na nota, emitir nota de
 * saída, cobrar o terceiro, cobrar a peça) e que passaram do prazo.
 *
 * O "tempo parado" é medido pelo período aberto do status atual
 * (bancada_equipment_status_histories com end_time nulo), que existe para 100%
 * dos equipamentos ativos — mais confiável que os timestamps avulsos, que
 * vieram em branco em parte da base.
 */
class PendenciaAdministrativaService
{
    /** @var array<string,array{rotulo:string,status:string[],dias_cfg:string,extra:?string}> */
    private const REGRAS = [
        'entrada_fiscal' => [
            'rotulo' => 'Entrada fiscal pendente',
            'status' => ['Aguardando Entrada Fiscal', 'Aguardando Entrada'],
            'dias_cfg' => 'entrada_fiscal_dias',
            'extra' => null,
        ],
        // A nota de saída NÃO é tratada aqui: o comando bancada:chamados-nota-saida
        // já abre 1 chamado por ROTA (consolidado) como Requisição, no dia da
        // separação. Tratar como pendência aqui abria incidentes separados por
        // equipamento, duplicando aquilo — por isso foi removido.
        'terceiros' => [
            'rotulo' => 'Parado no terceiro',
            'status' => ['Terceiros'],
            'dias_cfg' => 'terceiros_dias',
            'extra' => null,
        ],
        'aguardando_peca' => [
            'rotulo' => 'Aguardando peça',
            'status' => ['Aguardando peça'],
            'dias_cfg' => 'aguardando_peca_dias',
            'extra' => null,
        ],
    ];

    /**
     * Lista plana de pendências, da mais antiga para a mais recente.
     *
     * @return array<int,array{equipment_id:int,plaqueta:?string,tic:?string,tipo_equipamento:?string,unidade:?string,status:string,pendencia:string,rotulo:string,dias:int,limiar:int}>
     */
    public function detectar(): array
    {
        $pendencias = [];

        foreach (self::REGRAS as $chave => $regra) {
            $limiar = (int) config("bancada.pendencias.{$regra['dias_cfg']}");
            $placeholders = implode(',', array_fill(0, count($regra['status']), '?'));
            $extra = $regra['extra'] ? " AND {$regra['extra']}" : '';

            $sql = "SELECT e.id AS equipment_id, e.plaqueta, e.tic, e.tipo_equipamento,
                       e.unidade_setor AS unidade, e.status,
                       TIMESTAMPDIFF(DAY, h.start_time, NOW()) AS dias
                    FROM bancada_equipments e
                    JOIN bancada_equipment_status_histories h
                      ON h.bancada_equipment_id = e.id AND h.end_time IS NULL
                    WHERE e.status IN ({$placeholders}){$extra}
                      AND TIMESTAMPDIFF(DAY, h.start_time, NOW()) > ?
                    ORDER BY dias DESC";

            $linhas = DB::select($sql, [...$regra['status'], $limiar]);

            foreach ($linhas as $l) {
                $pendencias[] = [
                    'equipment_id' => (int) $l->equipment_id,
                    'plaqueta' => $l->plaqueta ?: null,
                    'tic' => $l->tic ?: null,
                    'tipo_equipamento' => $l->tipo_equipamento,
                    'unidade' => trim((string) $l->unidade) ?: null,
                    'status' => $l->status,
                    'pendencia' => $chave,
                    'rotulo' => $regra['rotulo'],
                    'dias' => (int) $l->dias,
                    'limiar' => $limiar,
                ];
            }
        }

        usort($pendencias, static fn ($a, $b) => $b['dias'] <=> $a['dias']);

        return $pendencias;
    }

    /**
     * Contagem por tipo de pendência.
     *
     * @return array<string,int>
     */
    public function resumo(): array
    {
        $resumo = array_fill_keys(array_keys(self::REGRAS), 0);

        foreach ($this->detectar() as $p) {
            $resumo[$p['pendencia']]++;
        }

        return $resumo;
    }

    public function identificador(array $pendencia): string
    {
        return $pendencia['plaqueta'] ?: ($pendencia['tic'] ?: '#'.$pendencia['equipment_id']);
    }
}
