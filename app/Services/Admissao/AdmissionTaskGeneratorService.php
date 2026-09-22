<?php

namespace App\Services\Admissao;

use App\Models\AdmissionCase;
use App\Models\AdmissionProfileItem;
use App\Models\AdmissionTask;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Traduz o caso + perfis resolvidos em tarefas concretas.
 *
 * É idempotente: rodar de novo depois que o RH corrigiu o cargo ou a data de
 * início atualiza prazos e cria/remove tarefas sem duplicar nada e sem
 * derrubar o que a TI já concluiu.
 */
class AdmissionTaskGeneratorService
{
    public function __construct(
        private readonly AdmissionProfileResolverService $resolver,
        private readonly AdmissionEventLogger $events,
    ) {
    }

    /**
     * @return array{criadas: int, atualizadas: int, removidas: int}
     */
    public function sync(AdmissionCase $case): array
    {
        $items = $this->resolver->resolveItems($case);
        $resumo = ['criadas' => 0, 'atualizadas' => 0, 'removidas' => 0];

        DB::transaction(function () use ($case, $items, &$resumo): void {
            $existentes = $case->tasks()->get()->keyBy('task_type');

            foreach ($items as $taskType => $item) {
                $task = $existentes->get($taskType);
                $dueAt = $this->calcularPrazo($case, $item);

                if ($task === null) {
                    $case->tasks()->create([
                        'task_type' => $taskType,
                        'area' => $item->area(),
                        'status' => AdmissionTask::STATUS_PENDENTE,
                        'due_at' => $dueAt,
                        'obrigatorio' => $item->obrigatorio,
                        'observacao' => $item->observacao,
                        'config' => $item->config,
                    ]);
                    $resumo['criadas']++;

                    continue;
                }

                // Tarefa já resolvida não é mexida — apenas o prazo, que é informativo.
                if ($task->isFinalizado()) {
                    if (! $task->due_at?->equalTo($dueAt)) {
                        $task->update(['due_at' => $dueAt]);
                        $resumo['atualizadas']++;
                    }

                    continue;
                }

                $task->update([
                    'area' => $item->area(),
                    'due_at' => $dueAt,
                    'obrigatorio' => $item->obrigatorio,
                    'observacao' => $item->observacao,
                    'config' => $item->config,
                ]);
                $resumo['atualizadas']++;
            }

            // Tarefas que deixaram de ser aplicáveis (cargo mudou, por exemplo).
            // Só some o que ninguém tocou; o resto vira "não aplicável" para
            // preservar o histórico e o chamado já aberto.
            $obsoletas = $existentes->keys()->diff($items->keys());

            foreach ($obsoletas as $taskType) {
                $task = $existentes->get($taskType);

                if ($task->isFinalizado()) {
                    continue;
                }

                if ($task->jira_issue_key !== null) {
                    $task->update([
                        'status' => AdmissionTask::STATUS_NAO_APLICAVEL,
                        'observacao' => trim(($task->observacao ?? '')."\nDeixou de ser aplicável após alteração do cadastro."),
                    ]);
                    $resumo['atualizadas']++;

                    continue;
                }

                $task->delete();
                $resumo['removidas']++;
            }
        });

        $this->events->log($case, 'tarefas.sincronizadas', 'Tarefas recalculadas a partir dos perfis de acesso.', $resumo);

        return $resumo;
    }

    /**
     * Prazo da tarefa: N dias úteis antes do início do colaborador.
     * Sem data de início não há prazo — o caso ainda está incompleto.
     */
    private function calcularPrazo(AdmissionCase $case, AdmissionProfileItem $item): ?Carbon
    {
        if (! $case->data_inicio) {
            return null;
        }

        return $case->data_inicio->copy()->subWeekdays($item->slaDiasAntes());
    }
}
