<?php

namespace App\Console\Commands;

use App\Models\BancadaCdRequisicao;
use App\Models\BancadaEquipment;
use App\Models\BancadaEquipmentEvent;
use App\Services\BancadaStatusFlowService;
use App\Services\Jira\JiraTicketOpener;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Abre no Jira 1 chamado CONSOLIDADO (catálogo "ADM - Requisição de Estoque",
 * 161) com todas as peças de origem CD que estão "Aguardando peça" e ainda não
 * foram requisitadas. Pensado para rodar segunda e quarta às 08:00: cada corte
 * gera um lote (BancadaCdRequisicao) com o chamado e marca os itens como
 * "requisição aberta". Quem resolve o chamado é a Isabeli; a sincronização
 * (bancada:cd-sincronizar) detecta a resolução e marca as peças como
 * solicitadas ao CD.
 */
class RequisitarPecasCdBancada extends Command
{
    protected $signature = 'bancada:requisitar-pecas-cd
        {--dry-run : Só mostra as peças elegíveis, sem abrir chamado nem gravar}
        {--sem-jira : Não abre chamado no Jira (não marca nada)}
        {--force : Ignora a trava BANCADA_CD_REQ_ATIVO (para abrir o backlog manualmente)}';

    protected $description = 'Abre um chamado consolidado no Jira requisitando as peças de origem CD ao Centro de Distribuição.';

    public function handle(BancadaStatusFlowService $flow, JiraTicketOpener $jira): int
    {
        $itens = $this->elegiveis($flow);

        $this->info('Peças de origem CD elegíveis: ' . $itens->count());
        if ($itens->isNotEmpty()) {
            $this->table(
                ['Equip.', 'Plaqueta', 'Tipo', 'Unidade/Setor', 'Peça', 'Qtd'],
                $itens->take(30)->map(fn (BancadaEquipment $e) => [
                    '#' . $e->id,
                    $this->plaqueta($e),
                    $e->tipo_equipamento ?: '—',
                    $e->unidade_setor ?: '—',
                    $e->peca_nome ?: '—',
                    (int) $e->peca_quantidade ?: 1,
                ])->all()
            );
        }

        if ($this->option('dry-run')) {
            $this->warn('DRY-RUN: nada aberto, nada gravado.');

            return self::SUCCESS;
        }

        if ($itens->isEmpty()) {
            $this->info('Nenhuma peça de CD pendente. Nada a requisitar.');

            return self::SUCCESS;
        }

        if (! (bool) config('bancada.cd_requisicao.ativo') && ! $this->option('force')) {
            $this->warn('Automação de requisição ao CD inativa (BANCADA_CD_REQ_ATIVO desligado). Use --force para abrir manualmente.');

            return self::SUCCESS;
        }

        $abrirJira = ! $this->option('sem-jira') && $jira->configurado();
        if (! $abrirJira) {
            $this->warn('Jira não configurado ou --sem-jira: chamado não aberto e nada marcado.');

            return self::SUCCESS;
        }

        // Abre o chamado ANTES de persistir o lote: sem chave, não cria lote
        // dangling nem marca itens.
        $chave = $this->abrirChamado($jira, $itens);
        if ($chave === null) {
            $this->error('Falha ao abrir o chamado no Jira. Nada foi marcado.');

            return self::FAILURE;
        }

        $url = $jira->urlDoChamado($chave);
        $agora = Carbon::now();
        $coleta = BancadaCdRequisicao::coletaLiberadaPara($agora);

        DB::transaction(function () use ($itens, $chave, $url, $agora, $coleta): void {
            $requisicao = BancadaCdRequisicao::create([
                'jira_issue_key' => $chave,
                'jira_issue_url' => $url,
                'status' => BancadaCdRequisicao::STATUS_ABERTA,
                'aberta_em' => $agora,
                'coleta_liberada_em' => $coleta,
                'total_itens' => $itens->count(),
            ]);

            $ids = $itens->pluck('id')->all();
            $travados = BancadaEquipment::query()->whereIn('id', $ids)->lockForUpdate()->get();

            foreach ($travados as $equipment) {
                $equipment->cd_requisicao_id = $requisicao->id;
                $equipment->peca_fluxo_status = 'requisicao_cd_aberta';
                $equipment->save();

                BancadaEquipmentEvent::create([
                    'bancada_equipment_id' => $equipment->id,
                    'previous_status' => $equipment->status,
                    'new_status' => $equipment->status,
                    'action' => 'requisicao_cd_aberta',
                    'module' => 'Administrativo',
                    'performed_by' => null,
                    'observation' => "Requisição de peça ao CD aberta no Jira ({$chave}).",
                    'metadata' => [
                        'peca_nome' => $equipment->peca_nome,
                        'peca_quantidade' => $equipment->peca_quantidade,
                        'peca_origem' => $equipment->peca_origem,
                        'jira_issue_key' => $chave,
                        'jira_issue_url' => $url,
                        'cd_requisicao_id' => $requisicao->id,
                    ],
                ]);
            }
        });

        $this->info("Chamado {$chave} aberto com {$itens->count()} peça(s). Coleta prevista para " . $coleta->format('d/m/Y') . '.');

        return self::SUCCESS;
    }

    /**
     * Peças de origem CD prontas para requisitar: em "Aguardando peça", sem
     * lote ativo e ainda não requisitadas/abertas.
     *
     * @return Collection<int, BancadaEquipment>
     */
    private function elegiveis(BancadaStatusFlowService $flow): Collection
    {
        return BancadaEquipment::query()
            ->where('peca_origem', 'cd')
            ->whereNull('cd_requisicao_id')
            ->where(function ($q): void {
                $q->whereNull('peca_fluxo_status')
                    ->orWhereNotIn('peca_fluxo_status', ['requisicao_cd_aberta', 'requisicao_realizada']);
            })
            ->orderBy('id')
            ->get()
            ->filter(fn (BancadaEquipment $e) => $flow->normalizeStatus($e->status) === BancadaStatusFlowService::STATUS_AGUARDANDO_PECA)
            ->values();
    }

    /** @param  Collection<int, BancadaEquipment>  $itens */
    private function abrirChamado(JiraTicketOpener $jira, Collection $itens): ?string
    {
        $extra = [
            'issuetype' => ['id' => (string) config('bancada.cd_requisicao.jira_issue_type', '10010')],
            // Tabela estilo "planilha" na descrição (ADF).
            'description' => $this->descricaoAdf($itens),
        ];

        try {
            return $jira->abrir(
                (string) config('bancada.cd_requisicao.resumo', 'CoreTI - Requisição de peças CD'),
                'Requisição de peças ao Centro de Distribuição (CoreTI).',
                'bancada-cd-requisicao',
                $extra,
                (string) config('bancada.cd_requisicao.jira_equipe'),
                (string) config('bancada.cd_requisicao.jira_request_type'),
                semEquipe: false,
                assigneeEmail: (string) config('bancada.cd_requisicao.responsavel'),
            );
        } catch (Throwable $e) {
            $this->warn('Falha ao abrir chamado: ' . $e->getMessage());

            return null;
        }
    }

    /**
     * Monta a descrição do chamado como documento ADF com uma tabela
     * (Plaqueta | Equipamento | Unidade/Setor | Peça | Qtd).
     *
     * @param  Collection<int, BancadaEquipment>  $itens
     * @return array<string, mixed>
     */
    private function descricaoAdf(Collection $itens): array
    {
        $linhas = [$this->linhaTabela(['Plaqueta', 'Equipamento', 'Unidade/Setor', 'Peça', 'Qtd'], true)];

        foreach ($itens as $e) {
            $linhas[] = $this->linhaTabela([
                $this->plaqueta($e),
                (string) ($e->tipo_equipamento ?: '—'),
                (string) ($e->unidade_setor ?: '—'),
                (string) ($e->peca_nome ?: '—'),
                (string) ((int) $e->peca_quantidade ?: 1),
            ], false);
        }

        return [
            'type' => 'doc',
            'version' => 1,
            'content' => [
                [
                    'type' => 'paragraph',
                    'content' => [[
                        'type' => 'text',
                        'text' => 'Requisição automática de peças ao Centro de Distribuição (CoreTI).',
                    ]],
                ],
                [
                    'type' => 'table',
                    'attrs' => ['isNumberColumnEnabled' => false, 'layout' => 'default'],
                    'content' => $linhas,
                ],
            ],
        ];
    }

    /**
     * @param  array<int, string>  $celulas
     * @return array<string, mixed>
     */
    private function linhaTabela(array $celulas, bool $cabecalho): array
    {
        $tipoCelula = $cabecalho ? 'tableHeader' : 'tableCell';

        $content = array_map(function (string $texto) use ($tipoCelula): array {
            return [
                'type' => $tipoCelula,
                'content' => [[
                    'type' => 'paragraph',
                    'content' => $texto === '' ? [] : [['type' => 'text', 'text' => $texto]],
                ]],
            ];
        }, $celulas);

        return ['type' => 'tableRow', 'content' => $content];
    }

    /** Plaqueta do equipamento, ou "S/PLQ" quando não houver. */
    private function plaqueta(BancadaEquipment $e): string
    {
        $plaqueta = trim((string) $e->plaqueta);

        return $plaqueta !== '' ? $plaqueta : 'S/PLQ';
    }
}
