<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ResolvesOperationalScope;
use App\Models\AssetAssignment;
use App\Models\CorpDevice;
use App\Models\CorpLine;
use App\Models\OrganizationUnit;
use App\Models\TelefoniaFatura;
use App\Models\TelefoniaFaturaLinha;
use App\Services\Telefonia\RelatorioExportService;
use App\Support\ScopeRegistry;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Painel e relatórios de custo da telefonia.
 *
 * Duas fontes de número, e a diferença importa:
 *
 *  - corp_lines.valor_mensal é o custo corrente — o que se espera pagar hoje.
 *    É atualizado quando uma fatura é importada.
 *  - telefonia_fatura_linhas é o histórico do que foi efetivamente cobrado,
 *    mês a mês. É o que permite ver a evolução e explicar variação.
 *
 * O painel usa o primeiro; a evolução mensal, o segundo.
 */
class TelefoniaRelatorioController extends Controller
{
    use ResolvesOperationalScope;

    /** Meses exibidos na evolução. */
    private const MESES_HISTORICO = 12;

    public function dashboard(Request $request): View
    {
        $user = $request->user();
        $scope = trim((string) $request->string('scope'));

        $linhas = fn () => CorpLine::query()->visibleTo($user)->inScope($scope);
        $aparelhos = fn () => CorpDevice::query()->visibleTo($user)->inScope($scope);
        $vinculos = fn () => AssetAssignment::query()->visibleTo($user)->inScope($scope)
            ->whereHas('items', fn ($q) => $q->whereNotNull('corp_line_id')->orWhereNotNull('corp_device_id'));

        return view('telefonia.relatorios.dashboard', [
            'scope' => $scope,
            'scopeOptions' => $this->scopeOptions($user),
            'numeros' => [
                'linhas_total' => $linhas()->count(),
                'linhas_em_uso' => $linhas()->where('status', CorpLine::STATUS_EM_USO)->count(),
                'linhas_disponiveis' => $linhas()->where('status', CorpLine::STATUS_DISPONIVEL)->count(),
                'linhas_canceladas' => $linhas()->where('status', CorpLine::STATUS_CANCELADA)->count(),
                'custo_mensal' => (float) $linhas()->where('status', '!=', CorpLine::STATUS_CANCELADA)->sum('valor_mensal'),
                'aparelhos_total' => $aparelhos()->count(),
                'aparelhos_disponiveis' => $aparelhos()->where('status', CorpDevice::STATUS_DISPONIVEL)->count(),
                'vinculos_ativos' => $vinculos()->ativos()->count(),
                'sem_termo' => $vinculos()->pendenteTermo()->count(),
                'vencidos' => $vinculos()->vencidos()->count(),
                'colaborador_inativo' => $vinculos()->inconsistentes()->count(),
                'linhas_duplicadas' => $this->linhasEmVinculoDuplicado($user)->count(),
                'linhas_sem_vinculo' => $linhas()
                    ->where('status', CorpLine::STATUS_EM_USO)
                    ->whereDoesntHave('assignmentItems.assignment', fn ($q) => $q->where('ativo', true))
                    ->count(),
            ],
            'porOperadora' => $this->custoPorOperadora($user, $scope),
            'evolucao' => $this->evolucaoMensal($user, $scope),
            'ultimasFaturas' => TelefoniaFatura::query()
                ->visibleTo($user)
                ->inScope($scope)
                ->orderByDesc('competencia')
                ->limit(6)
                ->get(),
        ]);
    }

    public function gastosMensais(Request $request): View
    {
        $user = $request->user();
        $scope = trim((string) $request->string('scope'));

        return view('telefonia.relatorios.gastos-mensais', [
            'scope' => $scope,
            'scopeOptions' => $this->scopeOptions($user),
            'porOperadora' => $this->custoPorOperadora($user, $scope),
            'evolucao' => $this->evolucaoMensal($user, $scope),
            'evolucaoPorOperadora' => $this->evolucaoPorOperadora($user, $scope),
            'maioresLinhas' => $this->maioresCustos($user, $scope),
            'custoTotal' => (float) CorpLine::query()->visibleTo($user)->inScope($scope)
                ->where('status', '!=', CorpLine::STATUS_CANCELADA)->sum('valor_mensal'),
        ]);
    }

    public function comparativoUnidades(Request $request): View
    {
        $user = $request->user();

        return view('telefonia.relatorios.comparativo-unidades', [
            'porOperacao' => $this->custoPorOperacao($user),
            'porUnidade' => $this->custoPorUnidade($user),
            'semVinculo' => $this->custoSemVinculo($user),
            'duplicadas' => $this->linhasEmVinculoDuplicado($user),
        ]);
    }

    public function exportGastos(Request $request, RelatorioExportService $export, string $formato): StreamedResponse|Response
    {
        $user = $request->user();
        $scope = trim((string) $request->string('scope'));

        $cabecalhos = ['Operadora', 'Linhas', 'Custo mensal', 'Custo médio por linha', '% do total'];
        $porOperadora = $this->custoPorOperadora($user, $scope);
        $total = $porOperadora->sum('custo');

        $registros = $porOperadora->map(fn (object $item) => [
            $item->operadora,
            $item->quantidade,
            round($item->custo, 2),
            round($item->quantidade > 0 ? $item->custo / $item->quantidade : 0, 2),
            $total > 0 ? round($item->custo / $total * 100, 1).'%' : '—',
        ])->all();

        $rodape = ['TOTAL', $porOperadora->sum('quantidade'), round($total, 2), null, '100%'];
        $subtitulo = $scope !== '' ? 'Operação: '.ScopeRegistry::label($scope) : 'Todas as operações';

        return $formato === 'excel'
            ? $export->excel('Gastos mensais por operadora', $cabecalhos, $registros, $rodape, subtitulo: $subtitulo)
            : $export->pdf('Gastos mensais por operadora', $cabecalhos, $registros, $rodape, 'portrait', $subtitulo);
    }

    public function exportComparativo(Request $request, RelatorioExportService $export, string $formato): StreamedResponse|Response
    {
        $user = $request->user();

        $cabecalhos = ['Unidade', 'Operação', 'Linhas vinculadas', 'Custo mensal', 'Custo médio por linha'];
        $porUnidade = $this->custoPorUnidade($user);
        $total = $porUnidade->sum('custo');

        $registros = $porUnidade->map(fn (object $item) => [
            $item->unidade,
            $item->operacao,
            $item->quantidade,
            round($item->custo, 2),
            round($item->quantidade > 0 ? $item->custo / $item->quantidade : 0, 2),
        ])->all();

        $rodape = ['TOTAL', null, $porUnidade->sum('quantidade'), round($total, 2), null];

        return $formato === 'excel'
            ? $export->excel('Comparativo de gastos por unidade', $cabecalhos, $registros, $rodape)
            : $export->pdf('Comparativo de gastos por unidade', $cabecalhos, $registros, $rodape);
    }

    // ------------------------------------------------------------------
    // Consultas
    // ------------------------------------------------------------------

    /** @return Collection<int, object> */
    private function custoPorOperadora(?object $user, string $scope): Collection
    {
        return CorpLine::query()
            ->visibleTo($user)
            ->inScope($scope)
            ->where('status', '!=', CorpLine::STATUS_CANCELADA)
            ->selectRaw('operadora, COUNT(*) as quantidade, COALESCE(SUM(valor_mensal), 0) as custo')
            ->groupBy('operadora')
            ->orderByDesc('custo')
            ->get()
            ->map(fn ($linha) => (object) [
                'operadora' => $linha->operadora,
                'quantidade' => (int) $linha->quantidade,
                'custo' => (float) $linha->custo,
            ]);
    }

    /**
     * Evolução do que foi efetivamente cobrado, mês a mês.
     *
     * @return Collection<int, object>
     */
    private function evolucaoMensal(?object $user, string $scope): Collection
    {
        return TelefoniaFaturaLinha::query()
            ->join('telefonia_faturas', 'telefonia_faturas.id', '=', 'telefonia_fatura_linhas.telefonia_fatura_id')
            ->when($scope !== '', fn ($q) => $q->where('telefonia_faturas.scope', $scope))
            ->whereIn('telefonia_faturas.scope', $user?->visibleScopes() ?? [])
            ->selectRaw('telefonia_fatura_linhas.competencia as competencia, COUNT(*) as quantidade, SUM(telefonia_fatura_linhas.valor) as custo')
            ->groupBy('telefonia_fatura_linhas.competencia')
            ->orderByDesc('telefonia_fatura_linhas.competencia')
            ->limit(self::MESES_HISTORICO)
            ->get()
            ->map(fn ($linha) => (object) [
                'competencia' => $linha->competencia,
                'quantidade' => (int) $linha->quantidade,
                'custo' => (float) $linha->custo,
            ])
            ->reverse()
            ->values();
    }

    /** @return Collection<int, object> */
    private function evolucaoPorOperadora(?object $user, string $scope): Collection
    {
        return TelefoniaFaturaLinha::query()
            ->join('telefonia_faturas', 'telefonia_faturas.id', '=', 'telefonia_fatura_linhas.telefonia_fatura_id')
            ->when($scope !== '', fn ($q) => $q->where('telefonia_faturas.scope', $scope))
            ->whereIn('telefonia_faturas.scope', $user?->visibleScopes() ?? [])
            ->selectRaw('telefonia_faturas.operadora as operadora, telefonia_fatura_linhas.competencia as competencia, SUM(telefonia_fatura_linhas.valor) as custo')
            ->groupBy('telefonia_faturas.operadora', 'telefonia_fatura_linhas.competencia')
            ->orderBy('telefonia_fatura_linhas.competencia')
            ->get()
            ->map(fn ($linha) => (object) [
                'operadora' => $linha->operadora,
                'competencia' => $linha->competencia,
                'custo' => (float) $linha->custo,
            ]);
    }

    /** As linhas que mais pesam na conta. @return Collection<int, CorpLine> */
    private function maioresCustos(?object $user, string $scope): Collection
    {
        return CorpLine::query()
            ->visibleTo($user)
            ->inScope($scope)
            ->where('status', '!=', CorpLine::STATUS_CANCELADA)
            ->whereNotNull('valor_mensal')
            ->orderByDesc('valor_mensal')
            ->limit(20)
            ->get();
    }

    /** @return Collection<int, object> */
    private function custoPorOperacao(?object $user): Collection
    {
        return CorpLine::query()
            ->visibleTo($user)
            ->where('status', '!=', CorpLine::STATUS_CANCELADA)
            ->selectRaw('scope, COUNT(*) as quantidade, COALESCE(SUM(valor_mensal), 0) as custo')
            ->groupBy('scope')
            ->get()
            ->map(fn ($linha) => (object) [
                'operacao' => ScopeRegistry::label($linha->scope),
                'quantidade' => (int) $linha->quantidade,
                'custo' => (float) $linha->custo,
            ])
            ->sortByDesc('custo')
            ->values();
    }

    /**
     * Custo por unidade do colaborador que está com a linha.
     *
     * A linha não tem unidade própria — quem tem é a pessoa que a usa. Linha
     * sem vínculo ativo não entra aqui; ela aparece separada, justamente para
     * o total continuar batendo com o custo mensal.
     *
     * @return Collection<int, object>
     */
    private function custoPorUnidade(?object $user): Collection
    {
        $unidades = OrganizationUnit::query()->pluck('name', 'source_id');
        $escoposVisiveis = $user?->visibleScopes() ?? [];

        /*
         | Um vínculo por linha, sempre o mais recente.
         |
         | Sem isso, linha que aparece em dois vínculos ativos entra duas vezes
         | e o rateio passa a somar mais que o custo total — a base importada
         | do gestaoTI tem um caso assim. A duplicidade não é escondida: ela é
         | contada em linhasEmVinculoDuplicado() e aparece como pendência.
         */
        $vinculoPorLinha = DB::table('asset_assignment_items')
            ->join('asset_assignments', function ($join): void {
                $join->on('asset_assignments.id', '=', 'asset_assignment_items.asset_assignment_id')
                    ->where('asset_assignments.ativo', '=', true);
            })
            ->whereNotNull('asset_assignment_items.corp_line_id')
            ->selectRaw('asset_assignment_items.corp_line_id as corp_line_id, MAX(asset_assignments.id) as assignment_id')
            ->groupBy('asset_assignment_items.corp_line_id');

        return DB::table('corp_lines')
            ->joinSub($vinculoPorLinha, 'vinculo_da_linha', 'vinculo_da_linha.corp_line_id', '=', 'corp_lines.id')
            ->join('asset_assignments', 'asset_assignments.id', '=', 'vinculo_da_linha.assignment_id')
            ->join('hr_collaborators', 'hr_collaborators.id', '=', 'asset_assignments.hr_collaborator_id')
            ->whereIn('corp_lines.scope', $escoposVisiveis)
            ->where('corp_lines.status', '!=', CorpLine::STATUS_CANCELADA)
            ->selectRaw('hr_collaborators.unit_source_id as unidade_id, corp_lines.scope as scope, COUNT(*) as quantidade, COALESCE(SUM(corp_lines.valor_mensal), 0) as custo')
            ->groupBy('hr_collaborators.unit_source_id', 'corp_lines.scope')
            ->get()
            ->map(fn ($linha) => (object) [
                'unidade' => $unidades[$linha->unidade_id] ?? 'Sem unidade informada',
                'operacao' => ScopeRegistry::label($linha->scope),
                'quantidade' => (int) $linha->quantidade,
                'custo' => (float) $linha->custo,
            ])
            ->sortByDesc('custo')
            ->values();
    }

    /**
     * Linhas presas em mais de um vínculo ativo ao mesmo tempo.
     *
     * O cadastro impede criar assim, mas a carga do gestaoTI trouxe casos que
     * lá já estavam errados. Fica à vista para alguém encerrar o vínculo
     * duplicado — enquanto existir, o rateio por unidade atribui a linha só ao
     * vínculo mais recente.
     *
     * @return \Illuminate\Support\Collection<int, object>
     */
    private function linhasEmVinculoDuplicado(?object $user): Collection
    {
        $escoposVisiveis = $user?->visibleScopes() ?? [];

        return DB::table('asset_assignment_items')
            ->join('asset_assignments', function ($join): void {
                $join->on('asset_assignments.id', '=', 'asset_assignment_items.asset_assignment_id')
                    ->where('asset_assignments.ativo', '=', true);
            })
            ->join('corp_lines', 'corp_lines.id', '=', 'asset_assignment_items.corp_line_id')
            ->whereIn('corp_lines.scope', $escoposVisiveis)
            ->selectRaw('corp_lines.id as linha_id, corp_lines.ddd, corp_lines.numero, COUNT(*) as vinculos')
            ->groupBy('corp_lines.id', 'corp_lines.ddd', 'corp_lines.numero')
            ->havingRaw('COUNT(*) > 1')
            ->get();
    }

    /** @return array{quantidade: int, custo: float} */
    private function custoSemVinculo(?object $user): array
    {
        $query = CorpLine::query()
            ->visibleTo($user)
            ->where('status', '!=', CorpLine::STATUS_CANCELADA)
            ->whereDoesntHave('assignmentItems.assignment', fn ($q) => $q->where('ativo', true));

        return [
            'quantidade' => (clone $query)->count(),
            'custo' => (float) (clone $query)->sum('valor_mensal'),
        ];
    }
}
