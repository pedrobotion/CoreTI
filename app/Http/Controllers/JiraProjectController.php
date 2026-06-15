<?php

namespace App\Http\Controllers;

use App\Models\JiraProject;
use App\Models\ServiceDeskEmailCostCenter;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Contracts\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class JiraProjectController extends Controller
{
    public function index(Request $request): View|StreamedResponse
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'string', Rule::in(['todos', 'ativo', 'desativado'])],
            'tipo_unidade' => ['nullable', 'string', Rule::in(['', 'Sede', 'Unidades'])],
            'projeto_grupo' => ['nullable', 'string', 'max:255'],
            'per_page' => ['nullable', 'integer', 'in:10,25,50'],
            'export' => ['nullable', 'string', Rule::in(['csv'])],
        ]);

        $query = JiraProject::query()->where('excluido', false);
        $search = trim((string) ($filters['q'] ?? ''));
        $status = $filters['status'] ?? 'todos';
        $tipoUnidade = $filters['tipo_unidade'] ?? '';
        $grupo = trim((string) ($filters['projeto_grupo'] ?? ''));
        $perPage = (int) ($filters['per_page'] ?? 10);

        if ($search !== '') {
            $query->where(function ($q) use ($search): void {
                $q->where('email', 'like', "%{$search}%")
                    ->orWhere('unidade_nome', 'like', "%{$search}%")
                    ->orWhere('projeto_grupo', 'like', "%{$search}%")
                    ->orWhere('centro_custo', 'like', "%{$search}%")
                    ->orWhere('obs', 'like', "%{$search}%");
            });
        }

        if ($status === 'ativo') {
            $query->where('status', 'Ativo');
        } elseif ($status === 'desativado') {
            $query->where('status', 'Desativado');
        }

        if ($tipoUnidade !== '') {
            $query->where('tipo_unidade', $tipoUnidade);
        }

        if ($grupo !== '') {
            $query->where('projeto_grupo', $grupo);
        }

        if (($filters['export'] ?? null) === 'csv') {
            return $this->exportCsv($query->orderBy('email')->get());
        }

        $projects = $query->orderBy('email')->paginate($perPage)->withQueryString();

        return view('jira-projects.index', [
            'projects' => $projects,
            'search' => $search,
            'status' => $status,
            'tipoUnidade' => $tipoUnidade,
            'grupo' => $grupo,
            'perPage' => $perPage,
            'grupos' => JiraProject::query()
                ->where('excluido', false)
                ->whereNotNull('projeto_grupo')
                ->where('projeto_grupo', '<>', '')
                ->distinct()
                ->orderBy('projeto_grupo')
                ->pluck('projeto_grupo'),
            'projetoGrupos' => $this->defaultGroups(),
            'centroCustoOptionsUnidades' => $this->costCenterOptions('Unidades'),
            'centroCustoOptionsSede' => $this->costCenterOptions('Sede'),
            'stats' => [
                'total' => JiraProject::where('excluido', false)->count(),
                'ativos' => JiraProject::where('excluido', false)->where('status', 'Ativo')->count(),
                'desativados' => JiraProject::where('excluido', false)->where('status', 'Desativado')->count(),
                'sede' => JiraProject::where('excluido', false)->where('tipo_unidade', 'Sede')->count(),
                'unidades' => JiraProject::where('excluido', false)->where('tipo_unidade', 'Unidades')->count(),
            ],
        ]);
    }

    public function create(): View
    {
        return view('jira-projects.form', [
            'record' => new JiraProject([
                'tipo_unidade' => 'Unidades',
                'status' => 'Ativo',
                'data_inclusao' => today(),
            ]),
            'mode' => 'create',
            'centroCustoOptions' => $this->costCenterOptions('Unidades'),
            'projetoGrupos' => $this->defaultGroups(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validatePayload($request, null);
        JiraProject::create($data);

        return redirect()->route('jira-projects.index')->with('success', 'Registro Jira adicionado.');
    }

    public function edit(JiraProject $jiraProject): View
    {
        return view('jira-projects.form', [
            'record' => $jiraProject,
            'mode' => 'edit',
            'centroCustoOptions' => $this->costCenterOptions($jiraProject->tipo_unidade),
            'projetoGrupos' => $this->defaultGroups(),
        ]);
    }

    public function update(Request $request, JiraProject $jiraProject)
    {
        $data = $this->validatePayload($request, $jiraProject->id);
        $jiraProject->update($data);

        return redirect()->route('jira-projects.index')->with('success', 'Registro Jira atualizado.');
    }

    public function destroy(JiraProject $jiraProject)
    {
        $jiraProject->update(['excluido' => true]);

        return back()->with('success', 'Registro Jira removido.');
    }

    private function validatePayload(Request $request, ?int $id): array
    {
        $data = $request->validate([
            'email' => ['required', 'email', 'max:255', Rule::unique('jira_projects', 'email')->where('excluido', false)->ignore($id)],
            'tipo_unidade' => ['required', Rule::in(['Sede', 'Unidades'])],
            'unidade_nome' => ['required', 'string', 'max:255'],
            'projeto_grupo' => ['required', 'string', 'max:255'],
            'status' => ['required', Rule::in(['Ativo', 'Desativado'])],
            'obs' => ['nullable', 'string', 'max:2000'],
            'data_inclusao' => ['nullable', 'date'],
            'data_desativacao' => ['nullable', 'date'],
            'centro_custo' => ['nullable', 'string', 'max:255'],
        ]);

        if ($data['status'] === 'Desativado' && empty($data['data_desativacao'])) {
            $data['data_desativacao'] = today()->toDateString();
        }

        $parts = explode('.', (string) ($data['centro_custo'] ?? ''), 2);
        $data['unicoop'] = $parts[0] ?? null;
        $data['area'] = $parts[1] ?? null;

        return $data;
    }

    private function costCenterOptions(string $tipoUnidade)
    {
        $scope = $tipoUnidade === 'Sede' ? 'sede' : 'unidades';

        return ServiceDeskEmailCostCenter::query()
            ->where('scope', $scope)
            ->orderBy('name')
            ->limit(600)
            ->get()
            ->map(fn ($row) => [
                'name' => $row->name,
                'centro_custo' => trim(($row->unicoop ?? '') . '.' . ($row->area ?? ''), '.'),
            ]);
    }

    private function defaultGroups(): array
    {
        return [
            'Team Segurança Patrimonial',
            'Team Infra',
            'Team MKT',
            'Team Suprimentos',
            'Team CSC',
            'Team RH',
        ];
    }

    private function exportCsv($rows)
    {
        $filename = 'jira_projetos_' . now()->format('Ymd_His') . '.csv';
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename={$filename}",
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
        ];

        $callback = static function () use ($rows): void {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['E-mail', 'Tipo Unidade', 'Unidade/Setor', 'Centro Custo', 'Projeto/Grupo', 'Status', 'Observacao', 'Data Inclusao', 'Data Desativacao']);
            foreach ($rows as $row) {
                fputcsv($out, [
                    $row->email,
                    $row->tipo_unidade,
                    $row->unidade_nome,
                    $row->centro_custo,
                    $row->projeto_grupo,
                    $row->status,
                    $row->obs,
                    optional($row->data_inclusao)->format('Y-m-d'),
                    optional($row->data_desativacao)->format('Y-m-d'),
                ]);
            }
            fclose($out);
        };

        return response()->stream($callback, 200, $headers);
    }
}
