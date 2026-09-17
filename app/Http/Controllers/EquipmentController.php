<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ResolvesOperationalScope;
use App\Models\CoretiCostCenter;
use App\Models\Equipment;
use App\Models\OrganizationDepartment;
use App\Models\OrganizationUnit;
use App\Support\ScopeRegistry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EquipmentController extends Controller
{
    use ResolvesOperationalScope;

    public function index(Request $request): View
    {
        $user = $request->user();
        $filters = $this->filters($request);

        $equipments = Equipment::query()
            ->with(['unit:id,name', 'department:id,name'])
            ->visibleTo($user)
            ->inScope($filters['scope'])
            ->when($filters['tipo'] !== '', fn ($q) => $q->where('tipo', $filters['tipo']))
            ->when($filters['status'] !== '', fn ($q) => $q->where('status', $filters['status']))
            ->when($filters['unidade'] !== '', fn ($q) => $q->where('organization_unit_id', $filters['unidade']))
            ->when($filters['busca'] !== '', fn ($q) => $q->where(fn ($inner) => $inner
                ->where('patrimonio', 'like', "%{$filters['busca']}%")
                ->orWhere('nome', 'like', "%{$filters['busca']}%")
                ->orWhere('numero_serie', 'like', "%{$filters['busca']}%")
                ->orWhere('endereco_ip', 'like', "%{$filters['busca']}%")))
            ->withCount(['assignmentItems as vinculos_ativos_count' => fn ($q) => $q
                ->whereHas('assignment', fn ($a) => $a->where('ativo', true))])
            ->orderBy('tipo')
            ->orderBy('nome')
            ->paginate(50)
            ->withQueryString();

        $base = Equipment::query()->visibleTo($user)->inScope($filters['scope']);

        $resumo = [
            'total' => (clone $base)->count(),
            'disponiveis' => (clone $base)->where('status', Equipment::STATUS_DISPONIVEL)->count(),
            'em_uso' => (clone $base)->where('status', Equipment::STATUS_EM_USO)->count(),
            'manutencao' => (clone $base)->whereIn('status', [Equipment::STATUS_EM_MANUTENCAO, Equipment::STATUS_EM_CONSERTO])->count(),
            /*
             | Só conta os tipos que são entregues a pessoa.
             |
             | Um switch ou nobreak instalado numa unidade fica "Em Uso" sem
             | vínculo nenhum, e isso é o estado correto dele. Contar esses
             | como pendência transformaria o alerta em ruído: na carga inicial
             | do gestaoTI seriam 176 "presos", dos quais a esmagadora maioria
             | é equipamento de infraestrutura funcionando normalmente.
             */
            'em_uso_sem_entrega' => (clone $base)
                ->where('status', Equipment::STATUS_EM_USO)
                ->whereIn('tipo', Equipment::TIPOS_VINCULAVEIS)
                ->whereDoesntHave('assignmentItems.assignment', fn ($q) => $q->where('ativo', true))
                ->count(),
        ];

        return view('equipamentos.index', [
            'equipments' => $equipments,
            'filters' => $filters,
            'resumo' => $resumo,
            'scopeOptions' => $this->scopeOptions($user),
            'tipos' => Equipment::TIPOS,
            'statuses' => Equipment::STATUSES,
            'unidades' => $this->unitOptions($user),
        ]);
    }

    public function create(Request $request): View
    {
        return view('equipamentos.create', $this->formData($request, new Equipment([
            'status' => Equipment::STATUS_DISPONIVEL,
        ])));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $data['scope'] = $this->resolveEquipmentScope($request, null);
        $data = $this->fillCostCenterName($data);
        $data['created_by'] = $request->user()?->id;

        $equipment = Equipment::query()->create($data);

        return redirect()
            ->route('service-desk.equipamentos.index')
            ->with('success', "Equipamento {$equipment->patrimonio} cadastrado.");
    }

    public function edit(Request $request, Equipment $equipment): View
    {
        $this->ensureScopeIsVisible($request->user(), $equipment->scope);

        return view('equipamentos.edit', $this->formData($request, $equipment) + [
            'temVinculoAtivo' => $equipment->hasActiveAssignment(),
            'temHistorico' => $equipment->assignmentItems()->exists(),
        ]);
    }

    public function update(Request $request, Equipment $equipment): RedirectResponse
    {
        $this->ensureScopeIsVisible($request->user(), $equipment->scope);

        $data = $this->validated($request, $equipment);
        $data['scope'] = $this->resolveEquipmentScope($request, $equipment);
        $data = $this->fillCostCenterName($data);
        $data['updated_by'] = $request->user()?->id;

        $temVinculoAtivo = $equipment->hasActiveAssignment();

        // Equipamento entregue a alguém não pode ser declarado disponível nem
        // baixado sem devolução: o vínculo continuaria apontando para ele.
        if ($temVinculoAtivo && $data['status'] !== Equipment::STATUS_EM_USO) {
            throw ValidationException::withMessages([
                'status' => 'Este equipamento está com um colaborador. Registre a devolução antes de mudar a situação dele.',
            ]);
        }

        if ($temVinculoAtivo && $data['scope'] !== $equipment->scope) {
            throw ValidationException::withMessages([
                'organization_unit_id' => 'Este equipamento tem vínculo ativo e não pode mudar de operação. Registre a devolução antes.',
            ]);
        }

        $equipment->update($data);

        return redirect()
            ->route('service-desk.equipamentos.index')
            ->with('success', "Equipamento {$equipment->patrimonio} atualizado.");
    }

    public function destroy(Request $request, Equipment $equipment): RedirectResponse
    {
        $this->ensureScopeIsVisible($request->user(), $equipment->scope);

        if ($equipment->assignmentItems()->exists()) {
            return back()->with('error', 'Este equipamento tem histórico de entrega e não pode ser excluído. Se ele saiu do parque, marque a situação como Baixado.');
        }

        $patrimonio = $equipment->patrimonio;
        $equipment->delete();

        return redirect()
            ->route('service-desk.equipamentos.index')
            ->with('success', "Equipamento {$patrimonio} excluído.");
    }

    public function export(Request $request): StreamedResponse
    {
        $user = $request->user();
        $filters = $this->filters($request);

        $equipments = Equipment::query()
            ->with(['unit:id,name', 'department:id,name'])
            ->visibleTo($user)
            ->inScope($filters['scope'])
            ->when($filters['tipo'] !== '', fn ($q) => $q->where('tipo', $filters['tipo']))
            ->when($filters['status'] !== '', fn ($q) => $q->where('status', $filters['status']))
            ->when($filters['unidade'] !== '', fn ($q) => $q->where('organization_unit_id', $filters['unidade']))
            ->orderBy('tipo')
            ->orderBy('nome')
            ->get();

        $nome = 'equipamentos-'.now()->format('Y-m-d-His').'.csv';

        return response()->streamDownload(function () use ($equipments): void {
            $saida = fopen('php://output', 'wb');
            fwrite($saida, "\xEF\xBB\xBF");

            fputcsv($saida, ['Operação', 'Tipo', 'Equipamento', 'Patrimônio', 'Nº de série', 'IP', 'Unidade', 'Departamento', 'Centro de custo', 'Situação'], ';');

            foreach ($equipments as $equipment) {
                fputcsv($saida, [
                    ScopeRegistry::label($equipment->scope),
                    $equipment->tipo,
                    $equipment->nome,
                    // Patrimônio costuma ter zero à esquerda ("023731"); sem a
                    // tabulação o Excel come o zero e o número deixa de bater.
                    "\t".$equipment->patrimonio,
                    $equipment->numero_serie,
                    $equipment->endereco_ip,
                    $equipment->unit?->name ?: $equipment->localizacao,
                    $equipment->department?->name,
                    trim($equipment->centro_custo_codigo.' '.$equipment->centro_custo_nome),
                    $equipment->status,
                ], ';');
            }

            fclose($saida);
        }, $nome, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * Escopo do equipamento: quando há unidade, ela manda.
     *
     * Deixar o usuário escolher a operação com a unidade preenchida abriria a
     * porta para um equipamento em Cristalina II marcado como Sede. A unidade
     * já sabe a resposta — o unit_scopes é exatamente esse de/para.
     */
    private function resolveEquipmentScope(Request $request, ?Equipment $equipment): string
    {
        $unitId = $request->input('organization_unit_id');

        if ($unitId === null || $unitId === '') {
            return $this->resolveScope($request->user(), $request->input('scope'));
        }

        $scope = ScopeRegistry::forUnitId($unitId);

        if ($scope === null) {
            $unidade = OrganizationUnit::query()->find($unitId);

            throw ValidationException::withMessages([
                'organization_unit_id' => sprintf(
                    'A unidade "%s" ainda não foi classificada como Sede ou Cerrado. Classifique-a antes de cadastrar equipamento nela.',
                    $unidade?->name ?? $unitId
                ),
            ]);
        }

        if (! $request->user()?->canScope($scope)) {
            throw ValidationException::withMessages([
                'organization_unit_id' => sprintf(
                    'Essa unidade pertence à operação %s, fora do seu acesso.',
                    ScopeRegistry::label($scope)
                ),
            ]);
        }

        return $scope;
    }

    /**
     * O código é a chave; o nome vem junto para o histórico e a exportação
     * continuarem legíveis se o catálogo mudar depois.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function fillCostCenterName(array $data): array
    {
        $codigo = $data['centro_custo_codigo'] ?? null;

        $data['centro_custo_nome'] = $codigo
            ? CoretiCostCenter::query()->where('code', $codigo)->value('name')
            : null;

        return $data;
    }

    /**
     * @return array<string, mixed>
     */
    private function formData(Request $request, Equipment $equipment): array
    {
        $user = $request->user();

        return [
            'equipment' => $equipment,
            'scopeOptions' => $this->scopeOptions($user),
            'tipos' => Equipment::TIPOS,
            'statuses' => Equipment::STATUSES,
            'unidades' => $this->unitOptions($user),
            'departamentos' => OrganizationDepartment::query()->where('active', true)->orderBy('name')->get(['id', 'name']),
            'centrosCusto' => CoretiCostCenter::query()->active()->orderBy('code')->get(['code', 'name']),
            'temVinculoAtivo' => false,
            'temHistorico' => false,
        ];
    }

    /**
     * Só as unidades da(s) operação(ões) do usuário. Oferecer as outras num
     * <select> seria oferecer o que a gravação vai recusar depois.
     */
    private function unitOptions(?object $user): \Illuminate\Support\Collection
    {
        $visiveis = $user?->visibleScopes() ?? [];

        return OrganizationUnit::query()
            ->where('active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'source_id'])
            ->filter(fn (OrganizationUnit $unit) => in_array(ScopeRegistry::forUnit((string) $unit->source_id), $visiveis, true))
            ->values();
    }

    /**
     * @return array<string, string>
     */
    private function filters(Request $request): array
    {
        return [
            'scope' => trim((string) $request->string('scope')),
            'tipo' => trim((string) $request->string('tipo')),
            'status' => trim((string) $request->string('status')),
            'unidade' => trim((string) $request->string('unidade')),
            'busca' => trim((string) $request->string('busca')),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?Equipment $equipment = null): array
    {
        return $request->validate([
            'scope' => ['nullable', 'string', Rule::in(ScopeRegistry::keys())],
            'tipo' => ['required', 'string', Rule::in(Equipment::TIPOS)],
            'nome' => ['required', 'string', 'max:200'],
            // Identificador do equipamento no patrimônio da cooperativa: único
            // em todo o parque, não por operação.
            'patrimonio' => [
                'required', 'string', 'max:100',
                Rule::unique('equipments', 'patrimonio')->ignore($equipment?->id),
            ],
            'numero_serie' => ['nullable', 'string', 'max:100'],
            'endereco_ip' => ['nullable', 'ip'],
            'organization_unit_id' => ['nullable', 'integer', 'exists:organization_units,id'],
            'organization_department_id' => ['nullable', 'integer', 'exists:organization_departments,id'],
            'centro_custo_codigo' => ['nullable', 'string', 'max:20', 'exists:coreti_cost_centers,code'],
            'localizacao' => ['nullable', 'string', 'max:255'],
            'status' => ['required', 'string', Rule::in(Equipment::STATUSES)],
            'observacoes' => ['nullable', 'string', 'max:2000'],
        ], [
            'patrimonio.unique' => 'Já existe um equipamento com este número de patrimônio.',
            'endereco_ip.ip' => 'Informe um endereço IP válido (ex.: 172.17.3.10).',
            'centro_custo_codigo.exists' => 'Centro de custo não encontrado no catálogo.',
        ], [
            'scope' => 'operação',
            'nome' => 'equipamento',
            'patrimonio' => 'patrimônio',
            'numero_serie' => 'número de série',
            'endereco_ip' => 'endereço IP',
            'organization_unit_id' => 'unidade',
            'organization_department_id' => 'departamento',
            'centro_custo_codigo' => 'centro de custo',
            'localizacao' => 'localização',
        ]);
    }
}
