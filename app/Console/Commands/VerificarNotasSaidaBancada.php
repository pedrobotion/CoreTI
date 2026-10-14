<?php

namespace App\Console\Commands;

use App\Models\BancadaEquipment;
use App\Models\BancadaEquipmentEvent;
use App\Services\Bancada\NotaSaidaSeparacaoService;
use App\Services\BancadaStatusFlowService;
use App\Services\Jira\JiraTicketOpener;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Abre no Jira, na manhã do dia de separação de cada rota, um chamado de
 * emissão de NOTA FISCAL DE SAÍDA para cada equipamento NÃO-Sede que está em
 * "Pronto para entrega" e ainda sem nota — para que a nota saia a tempo de o
 * equipamento embarcar no mesmo dia (até ~15:00).
 *
 * Feito para rodar agendado (dias úteis, 08:00). Em --dry-run só mostra o que
 * faria, sem abrir chamado e sem registrar evento.
 */
class VerificarNotasSaidaBancada extends Command
{
    protected $signature = 'bancada:chamados-nota-saida
        {--dry-run : Só mostra os equipamentos, sem Jira e sem registrar evento}
        {--sem-jira : Não abre chamados no Jira (mas registra o que faria)}';

    protected $description = 'Abre chamado no Jira para emitir a nota de saída dos equipamentos não-Sede no dia da separação.';

    public function handle(NotaSaidaSeparacaoService $servico, JiraTicketOpener $jira): int
    {
        $itens = $servico->detectarParaData();

        $this->info('Equipamentos para emitir nota de saída hoje (separação): ' . count($itens));
        if ($itens !== []) {
            $this->table(
                ['Equip.', 'Tipo', 'Plaqueta', 'Unidade', 'Rota', 'Separação'],
                array_map(fn (array $i) => [
                    '#' . $i['equipment']->id,
                    $i['equipment']->tipo_equipamento,
                    $i['equipment']->plaqueta ?: '—',
                    $i['equipment']->unidade_setor ?: '—',
                    $i['route']->nome,
                    $i['separation']->format('d/m/Y'),
                ], $itens)
            );
        }

        if ($this->option('dry-run')) {
            $this->warn('DRY-RUN: nenhum chamado aberto, nenhum evento registrado.');

            return self::SUCCESS;
        }

        $abrirJira = ! $this->option('sem-jira') && $jira->configurado();
        if (! $abrirJira) {
            $this->warn('Jira não configurado ou --sem-jira: nenhum chamado será aberto.');

            return self::SUCCESS;
        }

        // Tira os equipamentos que já têm chamado neste ciclo e agrupa o resto
        // por rota: 1 chamado por rota, listando todos os equipamentos da
        // separação do dia.
        $grupos = [];
        $jaTinham = 0;
        foreach ($itens as $item) {
            if ($this->jaTemChamadoNoCiclo($item['equipment'])) {
                $jaTinham++;
                continue;
            }
            $rid = $item['route']->id;
            $grupos[$rid] ??= [
                'route' => $item['route'],
                'separation' => $item['separation'],
                'equipamentos' => [],
            ];
            $grupos[$rid]['equipamentos'][] = $item['equipment'];
        }

        $teto = (int) config('bancada.notas_saida.jira_max_por_execucao');
        $abertos = 0;

        foreach ($grupos as $grupo) {
            if ($abertos >= $teto) {
                $this->warn("Teto de {$teto} chamados/execução atingido — os demais entram na próxima execução.");
                break;
            }

            $chave = $this->abrirChamadoRota($jira, $grupo);
            if ($chave !== null) {
                $url = $jira->urlDoChamado($chave);
                foreach ($grupo['equipamentos'] as $equipment) {
                    $this->registrarEvento($equipment, $chave, $url, $grupo);
                }
                $abertos++;
                $this->line("  ✓ {$chave} — rota {$grupo['route']->nome} (" . count($grupo['equipamentos']) . ' equip.)');
            }
        }

        $this->info("Chamados (por rota) abertos: {$abertos} | equipamentos já com chamado no ciclo: {$jaTinham}");

        return self::SUCCESS;
    }

    /**
     * Abre UM chamado para a rota, listando todos os equipamentos da separação.
     *
     * @param  array{route: \App\Models\BancadaMaloteRoute, separation: \Carbon\CarbonImmutable, equipamentos: list<BancadaEquipment>}  $grupo
     */
    private function abrirChamadoRota(JiraTicketOpener $jira, array $grupo): ?string
    {
        $route = $grupo['route'];
        $data = $grupo['separation'];
        $equipamentos = $grupo['equipamentos'];
        $total = count($equipamentos);

        $titulo = "[Nota de saída] Rota {$route->nome} — " . $data->format('d/m/Y')
            . " ({$total} " . ($total > 1 ? 'equipamentos' : 'equipamento') . ')';

        $linhas = '';
        $i = 1;
        foreach ($equipamentos as $e) {
            $linhas .= "{$i}. {$e->tipo_equipamento}"
                . ' — plaqueta ' . ($e->plaqueta ?: '—')
                . ' | série/Service Tag ' . ($e->service_tag ?: '—')
                . ' | TIC ' . ($e->tic ?: '—')
                . ' | destino ' . ($e->unidade_setor ?: '—')
                . ' | centro de custo ' . ($e->cost_center_code ?: '—')
                . "\n";
            $i++;
        }

        $descricao = "Solicitação de emissão de NOTA FISCAL DE SAÍDA dos equipamentos abaixo (transferência da bancada de TI para as unidades), que embarcam na rota {$route->nome}.\n\n"
            . 'Dia de embarque: ' . $data->format('d/m/Y') . " — levar ao local de embarque até ~15:00.\n"
            . "Total de equipamentos: {$total}\n\n"
            . "Equipamentos:\n" . $linhas . "\n"
            . 'Chamado aberto automaticamente pelo CoreTI na manhã do dia de separação.';

        try {
            return $jira->abrir(
                $titulo,
                $descricao,
                'bancada-nota-saida',
                [],
                (string) config('bancada.notas_saida.jira_equipe'),
                (string) config('bancada.notas_saida.jira_request_type'),
            );
        } catch (Throwable $ex) {
            $this->warn("Falha ao abrir chamado da rota {$route->nome}: " . $ex->getMessage());

            return null;
        }
    }

    /**
     * Já existe chamado de nota de saída para este equipamento no ciclo atual
     * (desde que ele entrou em "Pronto para entrega")? Evita duplicar.
     */
    private function jaTemChamadoNoCiclo(BancadaEquipment $equipment): bool
    {
        $inicioCiclo = DB::table('bancada_equipment_status_histories')
            ->where('bancada_equipment_id', $equipment->id)
            ->where('status', BancadaStatusFlowService::STATUS_PRONTO_ENTREGA)
            ->whereNull('end_time')
            ->orderByDesc('start_time')
            ->value('start_time');

        $query = BancadaEquipmentEvent::query()
            ->where('bancada_equipment_id', $equipment->id)
            ->where('action', 'nota_saida_chamado');

        // Sem histórico aberto (lacuna de dados): janela de segurança de 14 dias.
        $query->where('created_at', '>=', $inicioCiclo ?? now()->subDays(14));

        return $query->exists();
    }

    /** @param  array{route: \App\Models\BancadaMaloteRoute, separation: \Carbon\CarbonImmutable}  $item */
    private function registrarEvento(BancadaEquipment $equipment, string $chave, string $url, array $item): void
    {
        BancadaEquipmentEvent::create([
            'bancada_equipment_id' => $equipment->id,
            'previous_status' => $equipment->status,
            'new_status' => $equipment->status,
            'action' => 'nota_saida_chamado',
            'module' => 'Bancada',
            'performed_by' => null,
            'observation' => "Chamado de nota de saída aberto no Jira ({$chave}).",
            'metadata' => [
                'jira_issue_key' => $chave,
                'jira_issue_url' => $url,
                'rota' => $item['route']->nome,
                'data_separacao' => $item['separation']->toDateString(),
            ],
        ]);
    }
}
