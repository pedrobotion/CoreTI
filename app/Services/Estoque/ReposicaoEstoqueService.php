<?php

namespace App\Services\Estoque;

use App\Models\CoretiCostCenter;
use App\Models\InternalStockProduct;
use App\Services\Jira\JiraTicketOpener;
use Illuminate\Support\Facades\DB;

/**
 * Abre o chamado de reposição do estoque interno da TI quando o consumo da
 * bancada faz o saldo de um produto bater no mínimo.
 *
 * O mesmo chamado serve a dois propósitos (como o usuário pediu): pede a
 * reposição da quantidade consumida E informa ao Administrativo cada uso ainda
 * não debitado (peça, quantidade, plaqueta, unidade e centro de custo) para
 * cobrar a unidade. Os usos informados ficam marcados como "reposicao_solicitada"
 * para não serem cobrados de novo, e o produto ganha uma marca de pedido em
 * aberto (reposicao_pendente_em) para não abrir outro chamado a cada consumo —
 * a próxima entrada de estoque limpa a marca.
 */
class ReposicaoEstoqueService
{
    public function __construct(private readonly JiraTicketOpener $jira) {}

    /**
     * Abre o chamado se o produto estiver no/abaixo do mínimo e ainda não houver
     * um pedido de reposição em aberto. Devolve a chave do chamado ou null.
     */
    public function abrirSeNecessario(InternalStockProduct $produto, ?int $userId = null): ?string
    {
        $produto->refresh();

        if (! $produto->abaixo_do_minimo || $produto->reposicao_pendente_em !== null) {
            return null;
        }

        $usos = DB::table('bancada_stock_usages as u')
            ->leftJoin('bancada_equipments as e', 'e.id', '=', 'u.bancada_equipment_id')
            ->where('u.origem', 'estoque_ti')
            ->where('u.status', 'pendente_debito')
            ->whereRaw('LOWER(u.peca_nome) = ?', [mb_strtolower(trim($produto->nome))])
            ->orderBy('u.used_at')
            ->get(['u.id', 'u.plaqueta', 'u.unidade_setor', 'u.quantidade', 'e.cost_center_code', 'e.tipo_equipamento']);

        if ($usos->isEmpty()) {
            return null;
        }

        $total = (int) $usos->sum('quantidade');

        $linhas = $usos->map(function ($u): string {
            $cc = $u->cost_center_code ?: $this->centroDeCustoPorNome($u->unidade_setor);
            $tipo = trim((string) $u->tipo_equipamento);
            $plaqueta = trim((string) $u->plaqueta) ?: 's/ plaqueta';

            return '- '.trim($tipo.' '.$plaqueta)
                .' — '.(trim((string) $u->unidade_setor) ?: 'unidade não informada')
                .' — '.(int) $u->quantidade.' un'
                .' — Centro de custo: '.($cc ?: 'não encontrado');
        })->implode("\n");

        $titulo = "[Estoque TI] Reposição — {$produto->nome} ({$total} un)";
        $descricao = "Reposição automática do estoque interno da TI (CoreTI).\n\n"
            ."Produto: {$produto->nome} ({$produto->categoria})\n"
            ."Saldo atual: {$produto->saldo} un | Mínimo: {$produto->quantidade_minima} un\n"
            ."Quantidade a repor (consumida desde a última reposição): {$total} un\n\n"
            ."Peças usadas a cobrar das unidades:\n{$linhas}\n";

        $chave = $this->jira->abrir(
            $titulo,
            $descricao,
            'estoque-reposicao',
            ['issuetype' => ['id' => (string) config('bancada.estoque_reposicao.jira_issue_type')]],
            (string) config('bancada.estoque_reposicao.jira_equipe'),
            (string) config('bancada.estoque_reposicao.jira_request_type'),
            semEquipe: false,
        );

        if ($chave === null) {
            return null;
        }

        DB::table('bancada_stock_usages')
            ->whereIn('id', $usos->pluck('id')->all())
            ->update([
                'status' => 'reposicao_solicitada',
                'observacao' => 'Reposição/cobrança solicitada no chamado '.$chave,
                'updated_at' => now(),
            ]);

        $produto->forceFill([
            'reposicao_pendente_em' => now(),
            'reposicao_jira_key' => $chave,
            'reposicao_jira_url' => $this->jira->urlDoChamado($chave),
        ])->save();

        return $chave;
    }

    /** Centro de custo pelo nome da unidade (fallback quando o equipamento não o tem gravado). */
    private function centroDeCustoPorNome(?string $nome): ?string
    {
        $nome = trim((string) $nome);

        if ($nome === '') {
            return null;
        }

        return CoretiCostCenter::query()
            ->active()
            ->whereRaw('LOWER(TRIM(name)) = ?', [mb_strtolower($nome)])
            ->orderBy('code')
            ->value('code');
    }
}
