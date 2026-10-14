<?php

namespace App\Services\Dashboards;

use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Números do painel de Auditoria de Backups.
 *
 * O dado real vive em cmdb_database_backups — o log por execução que o agente
 * envia. As tabelas do fluxo autônomo (alvos, verificações, incidentes) ainda
 * estão vazias; quando o agendador começar a rodar, o bloco de incidentes aqui
 * passa a mostrar número sem precisar mexer na query.
 *
 * "Em silêncio" e "descontinuado" são do config/backups.php, os mesmos limiares
 * que a tela de backups já usa — 36h sem reportar é atraso, 30 dias é inativo.
 */
class BackupAuditStats
{
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

    /** Coluna "quando" — data_backup é a principal, received_at cobre o resto. */
    private const QUANDO = 'COALESCE(data_backup, received_at)';

    private function noPeriodo(): \Illuminate\Database\Query\Builder
    {
        return DB::table('cmdb_database_backups')
            ->whereRaw(self::QUANDO.' BETWEEN ? AND ?', [$this->de, $this->ate]);
    }

    /** @return array<string, int|float> */
    public function resumo(): array
    {
        $base = $this->noPeriodo();

        $execucoes = (clone $base)->count();
        $falhas = (clone $base)->where('sucesso', false)->count();
        $pares = (clone $base)->distinct()->count(DB::raw('CONCAT(host, "|", database_name)'));
        $tamanho = (float) (clone $base)->sum('tamanho');
        $duracao = (float) (clone $base)->avg('duracao_segundos');

        return [
            'execucoes' => $execucoes,
            'falhas' => $falhas,
            'sucesso_pct' => $execucoes > 0 ? round(($execucoes - $falhas) / $execucoes * 100, 1) : 0.0,
            'pares' => $pares,
            'tamanho_gb' => $tamanho,
            'duracao_media' => $duracao,
            'em_silencio' => $this->paresEmSilencio(),
        ];
    }

    /**
     * Pares (host+banco) que já reportaram, ainda são ativos (< 30 dias) e
     * passaram do limiar de atraso (36h) sem um novo backup.
     */
    public function paresEmSilencio(): int
    {
        $staleHoras = (int) config('backups.stale_after_hours', 36);
        $inativoDias = (int) config('backups.inactive_after_days', 30);
        $agora = CarbonImmutable::now();

        return DB::table('cmdb_database_backups')
            ->selectRaw('host, database_name, MAX('.self::QUANDO.') as ultimo')
            ->groupBy('host', 'database_name')
            ->havingRaw('ultimo < ?', [$agora->subHours($staleHoras)])
            ->havingRaw('ultimo >= ?', [$agora->subDays($inativoDias)])
            ->get()
            ->count();
    }

    /** Execuções e falhas por dia, para o gráfico de barras. @return Collection<int, object> */
    public function porDia(): Collection
    {
        return $this->noPeriodo()
            ->selectRaw('DATE('.self::QUANDO.') as dia, COUNT(*) as execucoes, SUM(CASE WHEN sucesso = 0 THEN 1 ELSE 0 END) as falhas, COALESCE(SUM(tamanho), 0) as tamanho')
            ->groupBy('dia')
            ->orderBy('dia')
            ->get()
            ->map(fn ($l) => (object) [
                'dia' => CarbonImmutable::parse($l->dia),
                'execucoes' => (int) $l->execucoes,
                'falhas' => (int) $l->falhas,
                'tamanho' => (float) $l->tamanho,
            ]);
    }

    /** @return Collection<int, object> */
    public function porTipo(): Collection
    {
        $total = max(1, $this->noPeriodo()->count());

        return $this->noPeriodo()
            ->selectRaw('COALESCE(NULLIF(backup_type, ""), "—") as tipo, COUNT(*) as execucoes')
            ->groupBy('tipo')
            ->orderByDesc('execucoes')
            ->get()
            ->map(fn ($l) => (object) [
                'tipo' => $l->tipo,
                'execucoes' => (int) $l->execucoes,
                'percentual' => round((int) $l->execucoes / $total * 100, 1),
            ]);
    }

    /** Hosts com mais movimento no período. @return Collection<int, object> */
    public function porHost(int $limite = 12): Collection
    {
        return $this->noPeriodo()
            ->selectRaw('host, COUNT(*) as execucoes, SUM(CASE WHEN sucesso = 0 THEN 1 ELSE 0 END) as falhas, COUNT(DISTINCT database_name) as bancos, COALESCE(SUM(tamanho), 0) as tamanho')
            ->groupBy('host')
            ->orderByDesc('execucoes')
            ->limit($limite)
            ->get()
            ->map(fn ($l) => (object) [
                'host' => $l->host,
                'execucoes' => (int) $l->execucoes,
                'falhas' => (int) $l->falhas,
                'bancos' => (int) $l->bancos,
                'tamanho' => (float) $l->tamanho,
            ]);
    }

    /**
     * Situação atual de cada par host+banco (último backup conhecido), para
     * destacar quem está com falha ou em silêncio. Independe do período.
     *
     * @return Collection<int, object>
     */
    public function saudeDosPares(): Collection
    {
        $staleHoras = (int) config('backups.stale_after_hours', 36);
        $inativoDias = (int) config('backups.inactive_after_days', 30);
        $agora = CarbonImmutable::now();

        $ultimoPorPar = DB::table('cmdb_database_backups')
            ->selectRaw('host, database_name, MAX('.self::QUANDO.') as ultimo')
            ->groupBy('host', 'database_name');

        return DB::table('cmdb_database_backups as b')
            ->joinSub($ultimoPorPar, 'u', function ($join): void {
                $join->on('b.host', '=', 'u.host')
                    ->on('b.database_name', '=', 'u.database_name')
                    ->whereRaw(self::QUANDO.' = u.ultimo');
            })
            ->selectRaw('b.host, b.database_name, b.sucesso, b.status, b.tamanho, b.jira_issue_key, b.jira_issue_url, u.ultimo')
            ->orderBy('u.ultimo')
            ->get()
            ->unique(fn ($l) => $l->host.'|'.$l->database_name)
            ->map(function ($l) use ($agora, $staleHoras, $inativoDias) {
                $ultimo = CarbonImmutable::parse($l->ultimo);
                $horas = $ultimo->diffInHours($agora);

                $situacao = match (true) {
                    ! (bool) $l->sucesso => 'falha',
                    $horas >= $inativoDias * 24 => 'inativo',
                    $horas >= $staleHoras => 'silencio',
                    default => 'ok',
                };

                return (object) [
                    'host' => $l->host,
                    'banco' => $l->database_name,
                    'sucesso' => (bool) $l->sucesso,
                    'status' => $l->status,
                    'tamanho' => (float) $l->tamanho,
                    'ultimo' => $ultimo,
                    'horas' => (int) $horas,
                    'situacao' => $situacao,
                    'jira_issue_key' => $l->jira_issue_key,
                    'jira_issue_url' => $l->jira_issue_url,
                ];
            })
            ->values();
    }

    /** Falhas mais recentes no período, para a tabela final. @return Collection<int, object> */
    public function falhasRecentes(int $limite = 20): Collection
    {
        return $this->noPeriodo()
            ->where('sucesso', false)
            ->selectRaw('host, database_name, status, observacao, '.self::QUANDO.' as quando, jira_issue_key, jira_issue_url')
            ->orderByRaw(self::QUANDO.' DESC')
            ->limit($limite)
            ->get()
            ->map(fn ($l) => (object) [
                'host' => $l->host,
                'banco' => $l->database_name,
                'status' => $l->status,
                'observacao' => $l->observacao,
                'quando' => CarbonImmutable::parse($l->quando),
                'jira_issue_key' => $l->jira_issue_key,
                'jira_issue_url' => $l->jira_issue_url,
            ]);
    }

    /**
     * Incidentes do fluxo autônomo. Hoje a tabela está vazia; quando o
     * agendador rodar, estes números passam a valer sem alterar a query.
     *
     * @return array<string, int|float|null>
     */
    public function incidentes(): array
    {
        $base = DB::table('cmdb_backup_incidents');

        $recuperados = (clone $base)->where('status', 'recuperado')->get(['aberto_em', 'recuperado_em']);
        $mttr = $recuperados
            ->map(fn ($i) => $i->aberto_em && $i->recuperado_em
                ? CarbonImmutable::parse($i->aberto_em)->diffInHours(CarbonImmutable::parse($i->recuperado_em))
                : null)
            ->filter()
            ->avg();

        return [
            'abertos' => (clone $base)->where('status', 'aberto')->count(),
            'recuperados' => $recuperados->count(),
            'com_chamado' => (clone $base)->whereNotNull('jira_issue_key')->count(),
            'mttr_horas' => $mttr !== null ? round((float) $mttr, 1) : null,
            'total' => (clone $base)->count(),
        ];
    }
}
