<?php

namespace App\Services\Admissao;

use App\Models\AdmissionCase;
use App\Models\AdmissionCaseEvent;
use App\Models\AdmissionTask;
use Illuminate\Support\Facades\Auth;

/**
 * Trilha de auditoria do caso. Toda mudança relevante passa por aqui para que
 * o "quem pediu / quem executou / quando" fique registrado sem depender de log
 * de aplicação.
 */
class AdmissionEventLogger
{
    public function log(
        AdmissionCase $case,
        string $event,
        ?string $descricao = null,
        array $contexto = [],
        ?AdmissionTask $task = null,
    ): AdmissionCaseEvent {
        return AdmissionCaseEvent::create([
            'admission_case_id' => $case->id,
            'admission_task_id' => $task?->id,
            'event' => $event,
            'descricao' => $descricao,
            'contexto' => $contexto === [] ? null : $contexto,
            'user_id' => Auth::id(),
        ]);
    }
}
