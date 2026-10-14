<?php

namespace App\Services\Dashboards;

use App\Models\CorpDevice;
use App\Models\CorpLine;
use App\Models\User;
use App\Support\ScopeRegistry;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Números do painel de Telefonia.
 *
 * É uma foto do agora, não uma série temporal: o custo vem de
 * corp_lines.valor_mensal (o aparelho não tem custo próprio), e as faturas
 * mês a mês ainda não foram importadas. Respeita o escopo visível do usuário
 * (Sede x Cerrado), como o resto do módulo.
 */
class TelefoniaStats
{
    public function __construct(private readonly ?User $user) {}

    /** @return array<string, int|float> */
    public function resumo(): array
    {
        $linhas = CorpLine::query()->visibleTo($this->user);
        $aparelhos = CorpDevice::query()->visibleTo($this->user);

        $vinculosAtivos = DB::table('asset_assignments')
            ->where('ativo', true)
            ->whereIn('scope', $this->user?->visibleScopes() ?? [])
            ->count();

        return [
            'linhas_total' => (clone $linhas)->count(),
            'linhas_em_uso' => (clone $linhas)->where('status', CorpLine::STATUS_EM_USO)->count(),
            'linhas_disponiveis' => (clone $linhas)->where('status', 'Disponível')->count(),
            'linhas_canceladas' => (clone $linhas)->where('status', CorpLine::STATUS_CANCELADA)->count(),
            'custo_mensal' => (float) (clone $linhas)->where('status', '!=', CorpLine::STATUS_CANCELADA)->sum('valor_mensal'),
            'aparelhos_total' => (clone $aparelhos)->count(),
            'aparelhos_em_uso' => (clone $aparelhos)->where('status', CorpDevice::STATUS_EM_USO)->count(),
            'vinculos_ativos' => $vinculosAtivos,
        ];
    }

    /** @return Collection<int, object> */
    public function custoPorOperadora(): Collection
    {
        return CorpLine::query()
            ->visibleTo($this->user)
            ->where('status', '!=', CorpLine::STATUS_CANCELADA)
            ->selectRaw('COALESCE(NULLIF(operadora, ""), "Sem operadora") as operadora, COUNT(*) as quantidade, COALESCE(SUM(valor_mensal), 0) as custo')
            ->groupBy('operadora')
            ->orderByDesc('custo')
            ->get()
            ->map(fn ($l) => (object) [
                'operadora' => $l->operadora,
                'quantidade' => (int) $l->quantidade,
                'custo' => (float) $l->custo,
            ]);
    }

    /** @return Collection<int, object> */
    public function linhasPorStatus(): Collection
    {
        return CorpLine::query()
            ->visibleTo($this->user)
            ->selectRaw('status, COUNT(*) as quantidade')
            ->groupBy('status')
            ->orderByDesc('quantidade')
            ->get()
            ->map(fn ($l) => (object) ['status' => $l->status, 'quantidade' => (int) $l->quantidade]);
    }

    /** @return Collection<int, object> */
    public function custoPorOperacao(): Collection
    {
        return CorpLine::query()
            ->visibleTo($this->user)
            ->where('status', '!=', CorpLine::STATUS_CANCELADA)
            ->selectRaw('scope, COUNT(*) as quantidade, COALESCE(SUM(valor_mensal), 0) as custo')
            ->groupBy('scope')
            ->get()
            ->map(fn ($l) => (object) [
                'operacao' => ScopeRegistry::label($l->scope),
                'quantidade' => (int) $l->quantidade,
                'custo' => (float) $l->custo,
            ])
            ->sortByDesc('custo')
            ->values();
    }

    /**
     * Custo por unidade de quem está com a linha. A linha não tem unidade
     * própria — quem tem é a pessoa. Cada linha entra uma vez, pelo vínculo
     * ativo mais recente, para o rateio não somar mais que o custo total.
     *
     * @return Collection<int, object>
     */
    public function custoPorUnidade(int $limite = 15): Collection
    {
        $escoposVisiveis = $this->user?->visibleScopes() ?? [];
        $unidades = DB::table('organization_units')->pluck('name', 'source_id');

        $vinculoPorLinha = DB::table('asset_assignment_items')
            ->join('asset_assignments', function ($join): void {
                $join->on('asset_assignments.id', '=', 'asset_assignment_items.asset_assignment_id')
                    ->where('asset_assignments.ativo', '=', true);
            })
            ->whereNotNull('asset_assignment_items.corp_line_id')
            ->selectRaw('asset_assignment_items.corp_line_id as corp_line_id, MAX(asset_assignments.id) as assignment_id')
            ->groupBy('asset_assignment_items.corp_line_id');

        return DB::table('corp_lines')
            ->joinSub($vinculoPorLinha, 'vinculo', 'vinculo.corp_line_id', '=', 'corp_lines.id')
            ->join('asset_assignments', 'asset_assignments.id', '=', 'vinculo.assignment_id')
            ->join('hr_collaborators', 'hr_collaborators.id', '=', 'asset_assignments.hr_collaborator_id')
            ->whereIn('corp_lines.scope', $escoposVisiveis)
            ->where('corp_lines.status', '!=', CorpLine::STATUS_CANCELADA)
            ->selectRaw('hr_collaborators.unit_source_id as unidade_id, COUNT(*) as quantidade, COALESCE(SUM(corp_lines.valor_mensal), 0) as custo')
            ->groupBy('hr_collaborators.unit_source_id')
            ->get()
            ->map(fn ($l) => (object) [
                'unidade' => $unidades[$l->unidade_id] ?? 'Sem unidade informada',
                'quantidade' => (int) $l->quantidade,
                'custo' => (float) $l->custo,
            ])
            ->sortByDesc('custo')
            ->take($limite)
            ->values();
    }

    /**
     * Aparelhos por marca, com o nome normalizado: a base importada tem
     * "SAMSUMG" (erro de digitação) e "Samsung" convivendo — sem juntar, a
     * mesma marca aparece em três fatias.
     *
     * @return Collection<int, object>
     */
    public function aparelhosPorMarca(): Collection
    {
        return CorpDevice::query()
            ->visibleTo($this->user)
            ->selectRaw('marca, COUNT(*) as quantidade')
            ->groupBy('marca')
            ->get()
            ->groupBy(fn ($l) => $this->normalizarMarca($l->marca))
            ->map(fn (Collection $grupo, string $marca) => (object) [
                'marca' => $marca,
                'quantidade' => (int) $grupo->sum('quantidade'),
            ])
            ->sortByDesc('quantidade')
            ->values();
    }

    private function normalizarMarca(?string $marca): string
    {
        $limpo = strtoupper(trim((string) $marca));

        return match ($limpo) {
            '' => 'SEM MARCA',
            'SAMSUMG' => 'SAMSUNG',
            default => $limpo,
        };
    }

    /** @return Collection<int, object> */
    public function aparelhosPorStatus(): Collection
    {
        return CorpDevice::query()
            ->visibleTo($this->user)
            ->selectRaw('status, COUNT(*) as quantidade')
            ->groupBy('status')
            ->orderByDesc('quantidade')
            ->get()
            ->map(fn ($l) => (object) ['status' => $l->status, 'quantidade' => (int) $l->quantidade]);
    }

    /**
     * Pendências que distorcem o rateio ou indicam cadastro a corrigir.
     *
     * @return array<string, int>
     */
    public function qualidade(): array
    {
        $escoposVisiveis = $this->user?->visibleScopes() ?? [];

        $linhasComVinculoAtivo = DB::table('asset_assignment_items')
            ->join('asset_assignments', function ($join): void {
                $join->on('asset_assignments.id', '=', 'asset_assignment_items.asset_assignment_id')
                    ->where('asset_assignments.ativo', '=', true);
            })
            ->whereNotNull('asset_assignment_items.corp_line_id')
            ->distinct()
            ->pluck('asset_assignment_items.corp_line_id');

        $emUsoSemVinculo = CorpLine::query()
            ->visibleTo($this->user)
            ->where('status', CorpLine::STATUS_EM_USO)
            ->whereNotIn('id', $linhasComVinculoAtivo)
            ->count();

        $vencidos = DB::table('asset_assignments')
            ->where('ativo', true)
            ->whereIn('scope', $escoposVisiveis)
            ->whereNotNull('data_vencimento')
            ->whereDate('data_vencimento', '<', now())
            ->count();

        $colaboradorInativo = DB::table('asset_assignments')
            ->join('hr_collaborators', 'hr_collaborators.id', '=', 'asset_assignments.hr_collaborator_id')
            ->where('asset_assignments.ativo', true)
            ->whereIn('asset_assignments.scope', $escoposVisiveis)
            ->where(function ($q): void {
                $q->where('hr_collaborators.active', false)->orWhere('hr_collaborators.terminated', true);
            })
            ->count();

        return [
            'linhas_sem_vinculo' => $emUsoSemVinculo,
            'vinculos_vencidos' => $vencidos,
            'colaborador_inativo' => $colaboradorInativo,
        ];
    }
}
