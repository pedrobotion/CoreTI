<?php

namespace App\Http\Controllers;

use App\Models\AdmissionCase;
use App\Models\AdmissionTask;
use App\Services\Admissao\AdmissionCaseService;
use Illuminate\Http\Request;

/**
 * Fila de execução das áreas.
 *
 * O board é o que permite "centralizar as criações em lote": a TI vê todas as
 * contas de e-mail a criar na semana em uma tela, em vez de caçar chamado a
 * chamado.
 */
class AdmissionTaskController extends Controller
{
    public function __construct(private readonly AdmissionCaseService $cases)
    {
    }

    public function board(Request $request)
    {
        $this->authorize('viewAny', AdmissionCase::class);

        $area = (string) $request->query('area', '');
        $tipo = (string) $request->query('tipo', '');
        $janela = (int) $request->query('janela', 15);

        $query = AdmissionTask::query()
            ->with('admissionCase')
            ->pendentes()
            ->whereHas('admissionCase', fn ($q) => $q->abertos())
            ->orderByRaw('due_at is null')
            ->orderBy('due_at');

        if ($area !== '' && array_key_exists($area, (array) config('admissao.areas'))) {
            $query->daArea($area);
        }

        if ($tipo !== '' && config("admissao.task_types.{$tipo}") !== null) {
            $query->where('task_type', $tipo);
        }

        if ($janela > 0) {
            $query->where(function ($q) use ($janela): void {
                $q->whereNull('due_at')
                    ->orWhereDate('due_at', '<=', now()->addDays($janela)->toDateString());
            });
        }

        $tasks = $query->paginate(50)->withQueryString();

        // Agrupar por tipo é o que viabiliza a criação em lote.
        $porTipo = $tasks->getCollection()->groupBy('task_type');

        return view('admissao.tasks.board', compact('tasks', 'porTipo', 'area', 'tipo', 'janela'));
    }

    public function complete(Request $request, AdmissionTask $task)
    {
        $this->authorize('executeTasks', $task->admissionCase);

        $data = $request->validate([
            'resultado' => ['nullable', 'array'],
            'resultado.*' => ['nullable', 'string', 'max:500'],
            'observacao' => ['nullable', 'string', 'max:1000'],
        ]);

        $resultado = array_filter($data['resultado'] ?? [], fn ($v) => filled($v));

        $this->cases->completeTask($task, $request->user(), $resultado, $data['observacao'] ?? null);

        return back()->with('success', $task->label().' concluída.');
    }

    public function block(Request $request, AdmissionTask $task)
    {
        $this->authorize('executeTasks', $task->admissionCase);

        $data = $request->validate([
            'bloqueio_motivo' => ['required', 'string', 'max:500'],
        ]);

        $this->cases->blockTask($task, $request->user(), $data['bloqueio_motivo']);

        return back()->with('success', 'Tarefa marcada como bloqueada.');
    }

    public function skip(Request $request, AdmissionTask $task)
    {
        $this->authorize('executeTasks', $task->admissionCase);

        $data = $request->validate([
            'motivo' => ['nullable', 'string', 'max:500'],
        ]);

        $this->cases->skipTask($task, $request->user(), $data['motivo'] ?? null);

        return back()->with('success', 'Tarefa marcada como não aplicável.');
    }
}
