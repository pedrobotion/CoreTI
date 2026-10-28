<?php

namespace App\Console\Commands;

use App\Models\BancadaCdRequisicao;
use App\Models\BancadaEquipmentEvent;
use App\Models\JiraIssue;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Detecta quando a Isabeli resolve o chamado de requisição ao CD e fecha o
 * ciclo no CoreTI: lê a tabela `jira_issues` (sincronizada do Jira) pela chave
 * do lote; quando o chamado está resolvido (data_hora_resolucao preenchida),
 * marca o lote como "resolvida" e as peças como solicitadas ao CD
 * (peca_fluxo_status = requisicao_realizada). A partir daí o lote aparece no
 * sino dos usuários da bancada até ser marcado como "Produtos Coletados".
 */
class SincronizarRequisicoesCdBancada extends Command
{
    protected $signature = 'bancada:cd-sincronizar {--dry-run : Só mostra o que faria}';

    protected $description = 'Marca as peças como solicitadas ao CD quando o chamado de requisição é resolvido no Jira.';

    public function handle(): int
    {
        $abertas = BancadaCdRequisicao::query()
            ->where('status', BancadaCdRequisicao::STATUS_ABERTA)
            ->whereNotNull('jira_issue_key')
            ->get();

        if ($abertas->isEmpty()) {
            $this->info('Nenhuma requisição aberta aguardando resolução.');

            return self::SUCCESS;
        }

        $resolvidas = 0;

        foreach ($abertas as $requisicao) {
            $issue = JiraIssue::query()->where('chave', $requisicao->jira_issue_key)->first();
            if ($issue === null || $issue->data_hora_resolucao === null) {
                continue;
            }

            $this->line("  ✓ {$requisicao->jira_issue_key} resolvido — marcando peças como solicitadas ao CD.");

            if ($this->option('dry-run')) {
                $resolvidas++;
                continue;
            }

            $agora = Carbon::now();
            DB::transaction(function () use ($requisicao, $agora): void {
                $requisicao->update([
                    'status' => BancadaCdRequisicao::STATUS_RESOLVIDA,
                    'resolvida_em' => $agora,
                ]);

                foreach ($requisicao->equipments()->lockForUpdate()->get() as $equipment) {
                    if ($equipment->peca_fluxo_status !== 'requisicao_cd_aberta') {
                        continue;
                    }
                    $equipment->peca_fluxo_status = 'requisicao_realizada';
                    $equipment->peca_admin_realizado_em = $agora;
                    $equipment->save();

                    BancadaEquipmentEvent::create([
                        'bancada_equipment_id' => $equipment->id,
                        'previous_status' => $equipment->status,
                        'new_status' => $equipment->status,
                        'action' => 'requisicao_cd_realizada',
                        'module' => 'Administrativo',
                        'performed_by' => null,
                        'observation' => "Peça solicitada ao CD (chamado {$requisicao->jira_issue_key} resolvido).",
                        'metadata' => [
                            'peca_nome' => $equipment->peca_nome,
                            'peca_quantidade' => $equipment->peca_quantidade,
                            'peca_origem' => $equipment->peca_origem,
                            'cd_requisicao_id' => $requisicao->id,
                            'coleta_liberada_em' => optional($requisicao->coleta_liberada_em)->format('Y-m-d'),
                        ],
                    ]);
                }
            });

            $resolvidas++;
        }

        $this->info(($this->option('dry-run') ? 'DRY-RUN: ' : '') . "Requisições resolvidas nesta execução: {$resolvidas}.");

        return self::SUCCESS;
    }
}
