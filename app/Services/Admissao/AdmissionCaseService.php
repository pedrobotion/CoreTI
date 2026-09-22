<?php

namespace App\Services\Admissao;

use App\Models\AdmissionCase;
use App\Models\AdmissionTask;
use App\Models\HrCollaborator;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * Orquestra o ciclo de vida do caso de admissão.
 *
 * Controllers não mudam status nem geram tarefas direto: tudo passa por aqui
 * para que auditoria, recálculo de tarefas e despacho fiquem consistentes.
 */
class AdmissionCaseService
{
    public function __construct(
        private readonly AdmissionTaskGeneratorService $generator,
        private readonly AdmissionJiraDispatchService $dispatcher,
        private readonly AdmissionProfileResolverService $resolver,
        private readonly AdmissionEventLogger $events,
    ) {
    }

    public function create(array $data, User $user): AdmissionCase
    {
        return DB::transaction(function () use ($data, $user): AdmissionCase {
            $case = new AdmissionCase($data);
            $case->codigo = $this->gerarCodigo();
            $case->status = AdmissionCase::STATUS_RASCUNHO;
            $case->created_by = $user->id;
            $case->save();

            $this->events->log($case, 'caso.criado', 'Caso de admissão criado.');
            $this->generator->sync($case);

            return $case->refresh();
        });
    }

    public function update(AdmissionCase $case, array $data): AdmissionCase
    {
        if (! $case->isEditavel()) {
            throw new RuntimeException('Este caso não pode mais ser editado.');
        }

        $case->fill($data)->save();

        $this->events->log($case, 'caso.atualizado', 'Dados do colaborador atualizados.');
        $this->generator->sync($case);

        return $case->refresh();
    }

    /**
     * Envia o caso: congela a edição, gera as tarefas e abre os chamados.
     *
     * @return array<int, array<string, mixed>> resultado do despacho por área
     */
    public function submit(AdmissionCase $case, User $user): array
    {
        if ($case->status !== AdmissionCase::STATUS_RASCUNHO) {
            throw new RuntimeException('Somente casos em rascunho podem ser enviados.');
        }

        $this->garantirDadosMinimos($case);

        $this->generator->sync($case);
        $case->refresh();

        if ($case->tasks()->count() === 0) {
            throw ValidationException::withMessages([
                'perfil' => 'Nenhum perfil de acesso se aplica a este colaborador. '
                    .'Cadastre um perfil para o cargo/área ou um perfil padrão antes de enviar.',
            ]);
        }

        $case->update([
            'status' => AdmissionCase::STATUS_ENVIADO,
            'submitted_by' => $user->id,
            'submitted_at' => now(),
        ]);

        $this->events->log($case, 'caso.enviado', 'Caso enviado para provisionamento.');

        $resultados = $this->dispatcher->dispatch($case);

        $this->recalcularStatus($case);

        return $resultados;
    }

    /**
     * Reabre o despacho para tarefas que falharam ou foram criadas depois.
     */
    public function redispatch(AdmissionCase $case): array
    {
        if ($case->isCancelado()) {
            throw new RuntimeException('Caso cancelado não gera novos chamados.');
        }

        $resultados = $this->dispatcher->dispatch($case);
        $this->recalcularStatus($case);

        return $resultados;
    }

    public function completeTask(AdmissionTask $task, User $user, array $resultado = [], ?string $observacao = null): AdmissionTask
    {
        $task->update([
            'status' => AdmissionTask::STATUS_CONCLUIDO,
            'resultado' => $resultado === [] ? null : $resultado,
            'observacao' => $observacao ?? $task->observacao,
            'concluded_by' => $user->id,
            'concluded_at' => now(),
            'bloqueio_motivo' => null,
        ]);

        $this->events->log(
            $task->admissionCase,
            'tarefa.concluida',
            $task->label().' concluída.',
            $resultado,
            $task
        );

        $this->recalcularStatus($task->admissionCase);

        return $task->refresh();
    }

    public function blockTask(AdmissionTask $task, User $user, string $motivo): AdmissionTask
    {
        $task->update([
            'status' => AdmissionTask::STATUS_BLOQUEADO,
            'bloqueio_motivo' => $motivo,
        ]);

        $this->events->log(
            $task->admissionCase,
            'tarefa.bloqueada',
            $task->label().' bloqueada: '.$motivo,
            [],
            $task
        );

        $this->recalcularStatus($task->admissionCase);

        return $task->refresh();
    }

    public function skipTask(AdmissionTask $task, User $user, ?string $motivo = null): AdmissionTask
    {
        $task->update([
            'status' => AdmissionTask::STATUS_NAO_APLICAVEL,
            'observacao' => $motivo ?? $task->observacao,
            'concluded_by' => $user->id,
            'concluded_at' => now(),
        ]);

        $this->events->log(
            $task->admissionCase,
            'tarefa.dispensada',
            $task->label().' marcada como não aplicável.',
            [],
            $task
        );

        $this->recalcularStatus($task->admissionCase);

        return $task->refresh();
    }

    /**
     * Cancelamento (no-show, desistência).
     *
     * Na Fase 1 nada é revertido automaticamente — as tarefas já concluídas
     * são listadas no evento para que a reversão seja tratada manualmente.
     * A automação da reversão é a Fase 4.
     */
    public function cancel(AdmissionCase $case, User $user, string $motivo): AdmissionCase
    {
        if ($case->isCancelado()) {
            return $case;
        }

        $concluidas = $case->tasks()
            ->where('status', AdmissionTask::STATUS_CONCLUIDO)
            ->get(['task_type', 'resultado']);

        $case->tasks()->pendentes()->update([
            'status' => AdmissionTask::STATUS_NAO_APLICAVEL,
            'bloqueio_motivo' => 'Admissão cancelada.',
        ]);

        $case->update([
            'status' => AdmissionCase::STATUS_CANCELADO,
            'cancelled_at' => now(),
            'cancel_reason' => $motivo,
        ]);

        $this->events->log(
            $case,
            'caso.cancelado',
            'Admissão cancelada: '.$motivo,
            ['reverter' => $concluidas->toArray()],
        );

        return $case->refresh();
    }

    /**
     * Marca o caso como concluído no primeiro dia do colaborador.
     */
    public function conclude(AdmissionCase $case): AdmissionCase
    {
        $case->update([
            'status' => AdmissionCase::STATUS_CONCLUIDO,
            'concluded_at' => now(),
        ]);

        $this->events->log($case, 'caso.concluido', 'Admissão concluída.');

        return $case->refresh();
    }

    /**
     * Deriva o status do caso a partir das tarefas.
     */
    public function recalcularStatus(AdmissionCase $case): void
    {
        if (in_array($case->status, [AdmissionCase::STATUS_CANCELADO, AdmissionCase::STATUS_CONCLUIDO], true)) {
            return;
        }

        if ($case->status === AdmissionCase::STATUS_RASCUNHO) {
            return;
        }

        $pendentes = $case->tasks()->pendentes()->count();
        $novo = $pendentes === 0
            ? AdmissionCase::STATUS_PRONTO
            : AdmissionCase::STATUS_PROVISIONANDO;

        if ($case->status !== $novo) {
            $case->update(['status' => $novo]);
            $this->events->log($case, 'caso.status', 'Status alterado para '.$novo.'.');
        }
    }

    /**
     * Preenche o caso a partir do snapshot de RH, para o RH não redigitar.
     */
    public function preencherPorColaborador(AdmissionCase $case, HrCollaborator $collaborator): AdmissionCase
    {
        $case->fill([
            'hr_collaborator_id' => $collaborator->id,
            'nome' => $case->nome ?: $collaborator->name,
            'matricula' => $case->matricula ?: $collaborator->employee_number,
            'cargo_source_id' => $case->cargo_source_id ?: $collaborator->position_source_id,
            'area_source_id' => $case->area_source_id ?: $collaborator->area_source_id,
            'departamento_source_id' => $case->departamento_source_id ?: $collaborator->department_source_id,
            'unidade_source_id' => $case->unidade_source_id ?: $collaborator->unit_source_id,
            'centro_custo' => $case->centro_custo ?: $collaborator->cost_center_source_id,
            'gestor_source_id' => $case->gestor_source_id ?: $collaborator->manager_source_id,
        ])->save();

        return $case;
    }

    private function garantirDadosMinimos(AdmissionCase $case): void
    {
        $erros = [];

        if (blank($case->nome)) {
            $erros['nome'] = 'Informe o nome do colaborador.';
        }

        if (blank($case->data_inicio)) {
            $erros['data_inicio'] = 'Informe a data de início para que os prazos sejam calculados.';
        }

        if (blank($case->unidade_source_id) && blank($case->unidade_nome)) {
            $erros['unidade_source_id'] = 'Informe a unidade de lotação.';
        }

        if ($erros !== []) {
            throw ValidationException::withMessages($erros);
        }
    }

    /**
     * Código legível usado nos chamados: ADM-2026-0007.
     */
    private function gerarCodigo(): string
    {
        $ano = now()->year;

        $ultimo = AdmissionCase::where('codigo', 'like', "ADM-{$ano}-%")
            ->orderByDesc('id')
            ->value('codigo');

        $sequencial = $ultimo ? ((int) substr($ultimo, -4)) + 1 : 1;

        return sprintf('ADM-%d-%04d', $ano, $sequencial);
    }
}
