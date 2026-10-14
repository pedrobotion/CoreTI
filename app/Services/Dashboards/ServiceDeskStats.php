<?php

namespace App\Services\Dashboards;

use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Números do painel de ServiceDesk, reunindo os submódulos: chamados (jiras),
 * e-mails corporativos (service_desk_emails), Active Directory (ad_users) e
 * licenciamento Office (office_licenses).
 *
 * Os chamados vêm do espelho local do Jira (tabela jiras) — nenhuma chamada
 * externa no read. A base de chamados exclui o "Time Cerrado", o mesmo recorte
 * que a tela de ServiceDesk já aplica, para o número bater com o que a equipe
 * enxerga.
 */
class ServiceDeskStats
{
    private const SQUAD_CERRADO = 'Time Cerrado';

    private CarbonImmutable $de;

    private CarbonImmutable $ate;

    public function __construct(?string $de = null, ?string $ate = null)
    {
        $this->ate = $ate ? CarbonImmutable::parse($ate)->endOfDay() : CarbonImmutable::now()->endOfDay();
        $this->de = $de ? CarbonImmutable::parse($de)->startOfDay() : $this->ate->subDays(29)->startOfDay();
    }

    public function de(): CarbonImmutable
    {
        return $this->de;
    }

    public function ate(): CarbonImmutable
    {
        return $this->ate;
    }

    /** Chamados sem o Time Cerrado — o mesmo recorte da tela de ServiceDesk. */
    private function chamados(): Builder
    {
        return DB::table('jiras')->where(function ($q): void {
            $q->whereNull('squad')->orWhere('squad', '<>', self::SQUAD_CERRADO);
        });
    }

    /** @return array<string, int> */
    public function resumo(): array
    {
        $abertos = $this->chamados()->whereNull('data_hora_resolucao');

        return [
            'chamados_abertos' => (clone $abertos)->count(),
            'aguardando_solicitante' => (clone $abertos)->where(function ($q): void {
                $q->where('status', 'like', '%Aguardando%')->orWhere('currentstatus_status', 'like', '%Aguardando%');
            })->count(),
            'sla_risco' => (clone $abertos)->where(function ($q): void {
                $q->where('tempo_sla_final_remainingTime', '<=', 0)->orWhere('sla_remainingTime', '<=', 0);
            })->count(),
            'criados_periodo' => $this->chamados()->whereBetween('data_hora_criacao', [$this->de, $this->ate])->count(),
            'resolvidos_periodo' => $this->chamados()->whereBetween('data_hora_resolucao', [$this->de, $this->ate])->count(),
            'emails_ativos' => DB::table('service_desk_emails')->where('ativo', true)->count(),
            'ad_ativos' => DB::table('ad_users')->where('e_ativo', true)->where('excluido', false)->count(),
            'office_ativos' => DB::table('office_licenses')->where('ativo', true)->count(),
        ];
    }

    /** @return Collection<int, object> */
    public function chamadosPorStatus(): Collection
    {
        return $this->chamados()
            ->whereNull('data_hora_resolucao')
            ->selectRaw('COALESCE(NULLIF(status, ""), "—") as status, COUNT(*) as quantidade')
            ->groupBy('status')
            ->orderByDesc('quantidade')
            ->get()
            ->map(fn ($l) => (object) ['status' => $l->status, 'quantidade' => (int) $l->quantidade]);
    }

    /** Abertos x resolvidos por dia, no período. @return Collection<int, object> */
    public function movimentoPorDia(): Collection
    {
        $criados = $this->chamados()
            ->whereBetween('data_hora_criacao', [$this->de, $this->ate])
            ->selectRaw('DATE(data_hora_criacao) as dia, COUNT(*) as total')
            ->groupBy('dia')->pluck('total', 'dia');

        $resolvidos = $this->chamados()
            ->whereBetween('data_hora_resolucao', [$this->de, $this->ate])
            ->selectRaw('DATE(data_hora_resolucao) as dia, COUNT(*) as total')
            ->groupBy('dia')->pluck('total', 'dia');

        $dias = collect();
        for ($d = $this->de; $d->lessThanOrEqualTo($this->ate); $d = $d->addDay()) {
            $chave = $d->format('Y-m-d');
            $dias->push((object) [
                'dia' => $d,
                'criados' => (int) ($criados[$chave] ?? 0),
                'resolvidos' => (int) ($resolvidos[$chave] ?? 0),
            ]);
        }

        return $dias;
    }

    /** @return Collection<int, object> */
    public function chamadosPorSquad(): Collection
    {
        return $this->chamados()
            ->whereNull('data_hora_resolucao')
            ->selectRaw('COALESCE(NULLIF(squad, ""), "Sem time") as squad, COUNT(*) as quantidade')
            ->groupBy('squad')
            ->orderByDesc('quantidade')
            ->get()
            ->map(fn ($l) => (object) ['squad' => $l->squad, 'quantidade' => (int) $l->quantidade]);
    }

    /**
     * Ranking por uma coluna de dimensão (catálogo, tipo de requisição ou
     * unidade), considerando os chamados criados no período.
     *
     * @return Collection<int, object>
     */
    public function rankingPor(string $coluna, int $limite = 10): Collection
    {
        $permitidas = ['catalogo', 'tipo_requisicao', 'unidade', 'departamento'];
        $coluna = in_array($coluna, $permitidas, true) ? $coluna : 'catalogo';

        return $this->chamados()
            ->whereBetween('data_hora_criacao', [$this->de, $this->ate])
            ->selectRaw("COALESCE(NULLIF({$coluna}, ''), '—') as rotulo, COUNT(*) as quantidade")
            ->groupBy('rotulo')
            ->orderByDesc('quantidade')
            ->limit($limite)
            ->get()
            ->map(fn ($l) => (object) ['rotulo' => $l->rotulo, 'quantidade' => (int) $l->quantidade]);
    }

    /** @return Collection<int, object> */
    public function emailsPorScope(): Collection
    {
        $rotulos = ['sede' => 'Sede', 'unidades' => 'Unidades', 'cerrado' => 'Cerrado', 'genericos' => 'Genéricos'];

        return DB::table('service_desk_emails')
            ->selectRaw('scope, SUM(CASE WHEN ativo = 1 THEN 1 ELSE 0 END) as ativos, COUNT(*) as total')
            ->groupBy('scope')
            ->orderByDesc('ativos')
            ->get()
            ->map(fn ($l) => (object) [
                'scope' => $rotulos[$l->scope] ?? ucfirst((string) $l->scope),
                'ativos' => (int) $l->ativos,
                'total' => (int) $l->total,
            ]);
    }

    /** @return array<string, int> */
    public function adResumo(): array
    {
        $base = DB::table('ad_users');

        return [
            'ativos' => (clone $base)->where('e_ativo', true)->where('excluido', false)->count(),
            'inativos' => (clone $base)->where('e_ativo', false)->where('excluido', false)->count(),
            'excluidos' => (clone $base)->where('excluido', true)->count(),
            'nao_colaboradores' => DB::table('ad_non_collaborator_accounts')->count(),
        ];
    }

    /** Contas AD ativas por unidade/setor (lotação). @return Collection<int, object> */
    public function adPorLotacao(int $limite = 12): Collection
    {
        return DB::table('ad_users')
            ->where('e_ativo', true)
            ->where('excluido', false)
            ->selectRaw('COALESCE(NULLIF(unidade_setor, ""), "Sem lotação") as lotacao, COUNT(*) as quantidade')
            ->groupBy('lotacao')
            ->orderByDesc('quantidade')
            ->limit($limite)
            ->get()
            ->map(fn ($l) => (object) ['lotacao' => $l->lotacao, 'quantidade' => (int) $l->quantidade]);
    }

    /** Licenças Office ativas por tipo. @return Collection<int, object> */
    public function officePorTipo(): Collection
    {
        $base = DB::table('office_licenses')->where('ativo', true);

        $tipos = [
            'office_apps' => 'Office Apps',
            'office_business' => 'Office Business',
            'powerbi_pro' => 'Power BI Pro',
            'powerbi_premium' => 'Power BI Premium',
            'visio_plan' => 'Visio',
        ];

        return collect($tipos)
            ->map(fn (string $rotulo, string $coluna) => (object) [
                'tipo' => $rotulo,
                'quantidade' => (clone $base)->where($coluna, true)->count(),
            ])
            ->sortByDesc('quantidade')
            ->values();
    }
}
