<?php

namespace App\Console\Commands;

use App\Models\BancadaEquipment;
use App\Models\BancadaEquipmentEvent;
use App\Services\Bancada\ManutencaoBancadaService;
use App\Services\Bancada\ManutencaoUnidadeMapper;
use App\Services\BancadaStatusFlowService;
use App\Services\Jira\JiraTicketOpener;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Abre no Jira, assim que o equipamento entra na bancada (status "Em bancada",
 * após a entrada), um chamado de MANUTENÇÃO (catálogo "Configuração e Manutenção
 * de Equipamentos de Informática", id 98) com descrição em branco — a equipe
 * liga para a unidade, confirma o defeito, escreve na descrição e finaliza; a
 * descrição volta para a observação do equipamento pelo webhook (parte B).
 *
 * Feito para rodar agendado (dias úteis, de hora em hora). `--seed` registra os
 * equipamentos que já estão na bancada como tratados, sem abrir chamado (para
 * não inundar o Jira com o backlog ao ativar).
 */
class VerificarChamadosManutencaoBancada extends Command
{
    protected $signature = 'bancada:chamados-manutencao
        {--dry-run : Só mostra, sem Jira e sem registrar evento}
        {--sem-jira : Não abre chamados no Jira}
        {--seed : Marca os atuais como tratados, sem abrir chamado (backlog)}';

    protected $description = 'Abre chamado de manutenção no Jira para os equipamentos que entram na bancada.';

    public function handle(
        ManutencaoBancadaService $servico,
        ManutencaoUnidadeMapper $mapper,
        JiraTicketOpener $jira
    ): int {
        $equipamentos = $servico->detectar();

        $this->info('Equipamentos na bancada: ' . $equipamentos->count());

        // Só os que ainda não têm chamado de manutenção no ciclo atual.
        $pendentes = $equipamentos->reject(fn (BancadaEquipment $e) => $this->jaTemChamadoNoCiclo($e))->values();
        $this->line('  sem chamado no ciclo: ' . $pendentes->count());

        if ($pendentes->isNotEmpty()) {
            $this->table(
                ['Equip.', 'Tipo', 'Plaqueta', 'Unidade', 'Sede?'],
                $pendentes->take(25)->map(fn (BancadaEquipment $e) => [
                    '#' . $e->id,
                    $e->tipo_equipamento,
                    $e->plaqueta ?: '—',
                    $e->unidade_setor ?: '—',
                    $servico->ehSede($e) ? 'sim' : 'não',
                ])->all()
            );
        }

        if ($this->option('dry-run')) {
            $this->warn('DRY-RUN: nada aberto, nada registrado.');

            return self::SUCCESS;
        }

        // Semeadura: marca os atuais como tratados sem abrir chamado.
        if ($this->option('seed')) {
            foreach ($pendentes as $equipment) {
                $this->registrarEvento($equipment, null, null, ['seed' => true]);
            }
            $this->info('SEED: ' . $pendentes->count() . ' equipamento(s) marcados como tratados (sem chamado).');

            return self::SUCCESS;
        }

        $inicio = trim((string) config('bancada.manutencao.inicio', ''));
        $aindaNaoComecou = $inicio !== ''
            && \Carbon\CarbonImmutable::today()->lt(\Carbon\CarbonImmutable::parse($inicio)->startOfDay());

        if (! (bool) config('bancada.manutencao.ativo') || $aindaNaoComecou) {
            $motivo = $aindaNaoComecou ? "começa em {$inicio}" : 'BANCADA_MANUT_ATIVO desligado';
            $this->warn("Automação de manutenção inativa ({$motivo}). Nenhum chamado aberto.");

            return self::SUCCESS;
        }

        $abrirJira = ! $this->option('sem-jira') && $jira->configurado();
        if (! $abrirJira) {
            $this->warn('Jira não configurado ou --sem-jira: nenhum chamado será aberto.');

            return self::SUCCESS;
        }

        $teto = (int) config('bancada.manutencao.jira_max_por_execucao');
        $abertos = 0;
        $semUnidade = 0;

        foreach ($pendentes as $equipment) {
            if ($abertos >= $teto) {
                $this->warn("Teto de {$teto} chamados/execução atingido — os demais entram na próxima execução.");
                break;
            }

            $unidade = $mapper->resolver($equipment->unidade_setor, $servico->ehSede($equipment));
            if ($unidade === null) {
                $semUnidade++;
                $this->warn("  ⚠ #{$equipment->id} sem unidade correspondente no Jira (\"{$equipment->unidade_setor}\") — não aberto.");
                continue;
            }

            $chave = $this->abrirChamado($jira, $equipment, $unidade);
            if ($chave !== null) {
                // Grava a TIC no equipamento: a lista de ativos a mostra como
                // link direto para o chamado no Jira (coluna TIC).
                $equipment->update(['tic' => $chave]);
                $this->registrarEvento($equipment, $chave, $jira->urlDoChamado($chave), [
                    'unidade' => $unidade['unidade'],
                    'departamento' => $unidade['departamento'],
                ]);
                $abertos++;
                $this->line("  ✓ {$chave} — #{$equipment->id} {$equipment->tipo_equipamento} {$equipment->plaqueta}");
            }
        }

        $this->info("Chamados abertos: {$abertos} | sem unidade no Jira: {$semUnidade}");

        return self::SUCCESS;
    }

    /**
     * @param  array{value: array<string, mixed>, unidade: string, departamento: ?string}  $unidade
     */
    private function abrirChamado(JiraTicketOpener $jira, BancadaEquipment $equipment, array $unidade): ?string
    {
        $plaqueta = trim((string) $equipment->plaqueta);
        $resumo = 'Manutenção ' . $equipment->tipo_equipamento
            . ($plaqueta !== '' ? ' - PLQ: ' . $plaqueta : '');

        // A observação do equipamento no Core vira a Descrição do chamado.
        $observacao = trim((string) $equipment->observacao);

        // Nobreak tem catálogo próprio (85) e exige Modelo/Quantidade de bateria.
        $ehNobreak = mb_strtolower(trim((string) $equipment->tipo_equipamento)) === 'nobreak';
        $requestType = $ehNobreak
            ? (string) config('bancada.manutencao.nobreak.jira_request_type')
            : (string) config('bancada.manutencao.jira_request_type');

        $extra = [
            // Catálogos 98 e 85 usam "Requisição de Serviço" (10010).
            'issuetype' => ['id' => (string) config('bancada.manutencao.jira_issue_type')],
            (string) config('bancada.manutencao.campo_unidade') => $unidade['value'],
        ];
        // Sem observação: descrição em branco (doc ADF vazio — senão o Jira recusa
        // um nó de texto vazio). Com observação, o JiraTicketOpener monta o ADF.
        if ($observacao === '') {
            $extra['description'] = ['type' => 'doc', 'version' => 1, 'content' => []];
        }
        // customfield_10366 (Patrimônio) é numérico no Jira — enviar como número,
        // não como texto, senão recusa com "O valor da operação deve ser um número".
        if ($plaqueta !== '' && is_numeric($plaqueta)) {
            $extra[(string) config('bancada.manutencao.campo_patrimonio')] = (int) $plaqueta;
        }

        if ($ehNobreak) {
            $modeloMap = (array) config('bancada.manutencao.nobreak.modelos', []);
            $qtdMap = (array) config('bancada.manutencao.nobreak.quantidades', []);
            $modelo = trim((string) $equipment->nobreak_modelo_bateria);
            $qtd = (int) $equipment->nobreak_qtd_baterias;
            // Campos obrigatórios do catálogo 85 — sem o dado de bateria no
            // cadastro, não abre (reabre sozinho quando for preenchido).
            if (! isset($modeloMap[$modelo]) || ! isset($qtdMap[$qtd])) {
                $this->warn("  ⚠ #{$equipment->id} Nobreak sem Modelo/Quantidade de bateria no cadastro — não aberto.");

                return null;
            }
            $extra[(string) config('bancada.manutencao.nobreak.campo_modelo_bateria')] = ['id' => (string) $modeloMap[$modelo]];
            $extra[(string) config('bancada.manutencao.nobreak.campo_qtd_baterias')] = ['id' => (string) $qtdMap[$qtd]];
        }

        try {
            return $jira->abrir(
                $resumo,
                $observacao,
                'bancada-manutencao',
                $extra,
                (string) config('bancada.manutencao.jira_equipe'),
                $requestType,
            );
        } catch (Throwable $ex) {
            $this->warn("Falha ao abrir chamado para #{$equipment->id}: " . $ex->getMessage());

            return null;
        }
    }

    /**
     * Já existe chamado de manutenção para este equipamento no ciclo atual
     * (desde que ele entrou em "Em bancada")? Evita duplicar; o --seed também
     * grava este evento, então semeados não reabrem.
     */
    private function jaTemChamadoNoCiclo(BancadaEquipment $equipment): bool
    {
        $inicioCiclo = DB::table('bancada_equipment_status_histories')
            ->where('bancada_equipment_id', $equipment->id)
            ->where('status', BancadaStatusFlowService::STATUS_EM_BANCADA)
            ->whereNull('end_time')
            ->orderByDesc('start_time')
            ->value('start_time');

        return BancadaEquipmentEvent::query()
            ->where('bancada_equipment_id', $equipment->id)
            ->where('action', 'manutencao_chamado')
            ->where('created_at', '>=', $inicioCiclo ?? now()->subDays(30))
            ->exists();
    }

    /** @param  array<string, mixed>  $metadata */
    private function registrarEvento(BancadaEquipment $equipment, ?string $chave, ?string $url, array $metadata): void
    {
        if ($chave !== null) {
            $metadata['jira_issue_key'] = $chave;
            $metadata['jira_issue_url'] = $url;
        }

        BancadaEquipmentEvent::create([
            'bancada_equipment_id' => $equipment->id,
            'previous_status' => $equipment->status,
            'new_status' => $equipment->status,
            'action' => 'manutencao_chamado',
            'module' => 'Bancada',
            'performed_by' => null,
            'observation' => $chave !== null
                ? "Chamado de manutenção aberto no Jira ({$chave})."
                : 'Equipamento marcado como tratado (seed, sem chamado).',
            'metadata' => $metadata,
        ]);
    }
}
