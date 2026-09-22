<?php

namespace App\Policies;

use App\Models\AdmissionCase;
use App\Models\User;

/**
 * Regras de quem faz o quê no caso.
 *
 * Complementa o middleware de módulo: o middleware garante que o usuário
 * pertence ao módulo, a policy decide o que ele pode fazer com aquele caso.
 */
class AdmissionCasePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->canModule('admissao', 'view');
    }

    public function view(User $user, AdmissionCase $case): bool
    {
        return $user->canModule('admissao', 'view');
    }

    public function create(User $user): bool
    {
        return $user->canModule('admissao', 'request');
    }

    public function update(User $user, AdmissionCase $case): bool
    {
        return $case->isEditavel() && $user->canModule('admissao', 'request');
    }

    public function submit(User $user, AdmissionCase $case): bool
    {
        return $case->status === AdmissionCase::STATUS_RASCUNHO
            && $user->canModule('admissao', 'request');
    }

    /**
     * Cancelar é do RH (quem sabe que o candidato desistiu) ou de quem
     * administra o módulo.
     */
    public function cancel(User $user, AdmissionCase $case): bool
    {
        return ! $case->isCancelado()
            && $user->canAnyModule(['admissao.request', 'admissao.manage']);
    }

    public function delete(User $user, AdmissionCase $case): bool
    {
        return $case->status === AdmissionCase::STATUS_RASCUNHO
            && $user->canModule('admissao', 'manage');
    }

    /**
     * Executar tarefas é da TI e das áreas de apoio.
     */
    public function executeTasks(User $user, AdmissionCase $case): bool
    {
        return ! $case->isCancelado()
            && $user->canModule('admissao', 'execute');
    }

    public function redispatch(User $user, AdmissionCase $case): bool
    {
        return ! $case->isCancelado()
            && $user->canAnyModule(['admissao.execute', 'admissao.manage']);
    }

    public function manageProfiles(User $user): bool
    {
        return $user->canModule('admissao', 'manage');
    }
}
