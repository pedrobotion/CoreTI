<?php

namespace App\Http\Controllers;

use App\Http\Requests\Admissao\AdmissionCaseRequest;
use App\Models\AdmissionCase;
use App\Models\HrCollaborator;
use App\Services\Admissao\AdmissionCaseService;
use App\Services\Admissao\AdmissionProfileResolverService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class AdmissionCaseController extends Controller
{
    public function __construct(
        private readonly AdmissionCaseService $cases,
        private readonly AdmissionProfileResolverService $resolver,
    ) {
    }

    public function index(Request $request)
    {
        $this->authorize('viewAny', AdmissionCase::class);

        $status = (string) $request->query('status', 'abertos');
        $busca = trim((string) $request->query('q', ''));

        $query = AdmissionCase::query()
            ->withCount([
                'tasks',
                'tasks as tasks_pendentes_count' => fn ($q) => $q->pendentes(),
            ])
            ->orderByRaw('data_inicio is null')
            ->orderBy('data_inicio')
            ->orderByDesc('id');

        if ($status === 'abertos') {
            $query->abertos();
        } elseif ($status !== 'todos') {
            $query->where('status', $status);
        }

        if ($busca !== '') {
            $query->where(function ($q) use ($busca): void {
                $q->where('nome', 'like', "%{$busca}%")
                    ->orWhere('matricula', 'like', "%{$busca}%")
                    ->orWhere('codigo', 'like', "%{$busca}%");
            });
        }

        $cases = $query->paginate(25)->withQueryString();

        $resumo = [
            'abertos' => AdmissionCase::abertos()->count(),
            'rascunhos' => AdmissionCase::where('status', AdmissionCase::STATUS_RASCUNHO)->count(),
            'atrasados' => AdmissionCase::abertos()
                ->whereHas('tasks', fn ($q) => $q->pendentes()->whereDate('due_at', '<', now()))
                ->count(),
            'proximos_7_dias' => AdmissionCase::abertos()
                ->whereBetween('data_inicio', [now()->toDateString(), now()->addDays(7)->toDateString()])
                ->count(),
        ];

        return view('admissao.cases.index', compact('cases', 'resumo', 'status', 'busca'));
    }

    public function create()
    {
        $this->authorize('create', AdmissionCase::class);

        $case = new AdmissionCase(['tipo' => AdmissionCase::TIPO_ADMISSAO]);

        return view('admissao.cases.create', compact('case'));
    }

    public function store(AdmissionCaseRequest $request)
    {
        $this->authorize('create', AdmissionCase::class);

        $case = $this->cases->create($request->validated(), $request->user());

        return redirect()
            ->route('admissao.cases.show', $case)
            ->with('success', "Caso {$case->codigo} criado. Revise as tarefas geradas e envie para provisionamento.");
    }

    public function show(AdmissionCase $case)
    {
        $this->authorize('view', $case);

        $case->load(['tasks' => fn ($q) => $q->orderBy('area')->orderBy('due_at'), 'events.user', 'creator', 'submitter']);

        $preview = $this->resolver->preview($case);
        $tasksPorArea = $case->tasks->groupBy('area');

        return view('admissao.cases.show', compact('case', 'preview', 'tasksPorArea'));
    }

    public function edit(AdmissionCase $case)
    {
        $this->authorize('update', $case);

        return view('admissao.cases.edit', compact('case'));
    }

    public function update(AdmissionCaseRequest $request, AdmissionCase $case)
    {
        $this->authorize('update', $case);

        try {
            $this->cases->update($case, $request->validated());
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()
            ->route('admissao.cases.show', $case)
            ->with('success', 'Dados atualizados e tarefas recalculadas.');
    }

    public function submit(Request $request, AdmissionCase $case)
    {
        $this->authorize('submit', $case);

        try {
            $resultados = $this->cases->submit($case, $request->user());
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        $falhas = collect($resultados)->where('status', 'error');

        if ($falhas->isNotEmpty()) {
            return redirect()
                ->route('admissao.cases.show', $case)
                ->with('error', 'Caso enviado, mas houve falha ao abrir chamado: '
                    .$falhas->pluck('message')->filter()->implode(' | ')
                    .' Use "Reenviar chamados" após corrigir a configuração.');
        }

        $simulados = collect($resultados)->where('status', 'simulated')->count();

        return redirect()
            ->route('admissao.cases.show', $case)
            ->with('success', $simulados > 0
                ? 'Caso enviado. Jira desabilitado: os chamados foram simulados para validação do fluxo.'
                : 'Caso enviado e chamados abertos para as áreas responsáveis.');
    }

    public function redispatch(AdmissionCase $case)
    {
        $this->authorize('redispatch', $case);

        $resultados = $this->cases->redispatch($case);

        if ($resultados === []) {
            return back()->with('success', 'Nenhuma tarefa pendente de despacho.');
        }

        $falhas = collect($resultados)->where('status', 'error');

        return back()->with(
            $falhas->isEmpty() ? 'success' : 'error',
            $falhas->isEmpty()
                ? 'Chamados reenviados.'
                : 'Falha ao reenviar: '.$falhas->pluck('message')->filter()->implode(' | ')
        );
    }

    public function cancel(Request $request, AdmissionCase $case)
    {
        $this->authorize('cancel', $case);

        $data = $request->validate([
            'cancel_reason' => ['required', 'string', 'max:500'],
        ]);

        $this->cases->cancel($case, $request->user(), $data['cancel_reason']);

        return redirect()
            ->route('admissao.cases.show', $case)
            ->with('success', 'Caso cancelado. Verifique no histórico os itens já provisionados que precisam ser revertidos.');
    }

    /**
     * Autocomplete do formulário: busca no snapshot de RH para o RH não
     * redigitar matrícula, cargo, unidade e centro de custo.
     */
    public function lookupCollaborator(Request $request): JsonResponse
    {
        $this->authorize('create', AdmissionCase::class);

        $termo = trim((string) $request->query('q', ''));

        if (mb_strlen($termo) < 3) {
            return response()->json(['data' => []]);
        }

        $colaboradores = HrCollaborator::query()
            ->where(function ($q) use ($termo): void {
                $q->where('name', 'like', "%{$termo}%")
                    ->orWhere('employee_number', 'like', "%{$termo}%");
            })
            ->orderBy('name')
            ->limit(15)
            ->get();

        return response()->json([
            'data' => $colaboradores->map(fn (HrCollaborator $c) => [
                'id' => $c->id,
                'nome' => $c->name,
                'matricula' => $c->employee_number,
                'email' => $c->email,
                'cargo_source_id' => $c->position_source_id,
                'area_source_id' => $c->area_source_id,
                'departamento_source_id' => $c->department_source_id,
                'unidade_source_id' => $c->unit_source_id,
                'centro_custo' => $c->cost_center_source_id,
                'gestor_source_id' => $c->manager_source_id,
                'ativo' => $c->active && ! $c->terminated,
            ])->all(),
        ]);
    }
}
