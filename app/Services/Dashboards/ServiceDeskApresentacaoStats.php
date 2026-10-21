<?php

namespace App\Services\Dashboards;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Indicadores da apresentação SEMANAL do Service Desk, calculados ao vivo da
 * tabela `jiras` (projeto TIC). Painéis do deck: Chamados Atendidos, Backlog,
 * Incidentes de Internet e Top 5 Catálogos. Pode ser filtrado por EMPRESA
 * (Cocari × afiliadas TratorNew/TratorCase/Pivot), via o prefixo da `unidade`.
 *
 * A janela é a semana (segunda 00:00 → domingo 23:59). As datas em `jiras` já
 * estão em horário de Brasília, então a janela é comparada direto, sem fuso.
 *
 * Chamados Atendidos e Top 5 excluem dois catálogos de automação/robôs que
 * distorceriam o volume de incidentes — mesma regra do deck.
 */
class ServiceDeskApresentacaoStats
{
    private Carbon $ini;

    private Carbon $fim;

    /** Granularidade do período: 'mes' (mês inteiro) ou 'periodo' (intervalo livre). */
    private string $modo;

    /** Squad/time selecionado; null = todos os times. */
    private ?string $squad;

    /** Empresa selecionada (cocari/tratornew/tratorcase/pivot); null = todas. */
    private ?string $empresa;

    /** Identifica a empresa pelo prefixo da `unidade` em `jiras`. */
    private const EMPRESAS_PREFIXO = [
        'pivot' => 'Pivot',
        'tratornew' => 'Tratornew',
        'tratorcase' => 'Tratorcase',
    ];

    private const EXCLUIR_CATALOGOS = [
        'Emissor não Habilitado na Receita',
        'Falha na execução de automação',
    ];

    /** Catálogos de automação/robô NÃO contabilizados (Atendidos e Top 5). */
    public static function catalogosExcluidos(): array
    {
        return self::EXCLUIR_CATALOGOS;
    }

    private const EXCLUIR_TIMES_INCIDENTES = [
        'time ia e automação',
        'time unidade digital',
    ];

    /** Nº de semanas na série do backlog e no comparativo. */
    private const SEMANAS_SERIE = 8;

    /**
     * Período analisado. Com $fim, usa [$inicio, $fim] direto (ex.: mês inteiro);
     * sem $fim, trata $inicio como uma data dentro da semana (compatibilidade).
     */
    public function __construct(?string $inicio = null, ?string $fim = null, ?string $squad = null, string $modo = 'periodo', ?string $empresa = null)
    {
        if ($fim !== null) {
            $this->ini = Carbon::createFromFormat('Y-m-d', $inicio)->startOfDay();
            $this->fim = Carbon::createFromFormat('Y-m-d', $fim)->endOfDay();
        } else {
            $ref = $inicio ? Carbon::createFromFormat('Y-m-d', $inicio) : Carbon::now();
            $this->ini = $ref->copy()->startOfWeek(Carbon::MONDAY)->startOfDay();
            $this->fim = $this->ini->copy()->endOfWeek(Carbon::SUNDAY)->endOfDay();
        }
        $this->modo = $modo === 'mes' ? 'mes' : 'periodo';
        $this->squad = ($squad !== null && trim($squad) !== '') ? $squad : null;
        $empresa = $empresa !== null ? mb_strtolower(trim($empresa)) : null;
        $this->empresa = ($empresa === 'cocari' || isset(self::EMPRESAS_PREFIXO[$empresa])) ? $empresa : null;
    }

    public function empresa(): ?string
    {
        return $this->empresa;
    }

    /** Identificador do período: a segunda-feira da semana, ou o 1º dia do mês. */
    public function semana(): string
    {
        return $this->ini->format('Y-m-d');
    }

    public function rotuloSemana(): string
    {
        return $this->ini->format('d/m').' a '.$this->fim->format('d/m/Y');
    }

    /** Rótulo do período: "Outubro 2026" no modo mês; "d/m a d/m/Y" na semana. */
    public function rotuloPeriodo(): string
    {
        return $this->modo === 'mes'
            ? ucfirst($this->ini->copy()->locale('pt_BR')->translatedFormat('F Y'))
            : $this->rotuloSemana();
    }

    public function modo(): string
    {
        return $this->modo;
    }

    /** Janela anterior (para comparativos): n meses atrás, ou n períodos de mesmo tamanho atrás. */
    private function janelaAnterior(int $n): array
    {
        if ($this->modo === 'mes') {
            $mi = $this->ini->copy()->startOfMonth()->subMonthsNoOverflow($n);

            return [$mi, $mi->copy()->endOfMonth()];
        }

        // Período livre: desloca pela própria duração (em dias).
        $dias = $this->ini->copy()->startOfDay()->diffInDays($this->fim->copy()->startOfDay()) + 1;
        $shift = $dias * $n;

        return [$this->ini->copy()->subDays($shift), $this->fim->copy()->subDays($shift)];
    }

    /** Rótulo curto de uma janela: "Out/26" no modo mês; "d/m" no período livre. */
    private function rotuloJanela(Carbon $d): string
    {
        if ($this->modo === 'mes') {
            $abrev = [1 => 'Jan', 'Fev', 'Mar', 'Abr', 'Mai', 'Jun', 'Jul', 'Ago', 'Set', 'Out', 'Nov', 'Dez'];

            return $abrev[(int) $d->format('n')].'/'.$d->format('y');
        }

        return $d->format('d/m');
    }

    private function base()
    {
        $q = DB::table('jiras');

        if ($this->squad !== null) {
            $q->where('squad', $this->squad);
        }

        if ($this->squad === null) {
            $q->where(function ($w) {
                $w->where('tipo_requisicao', 'not like', '%Incid%')
                    ->orWhereNull('tipo_requisicao')
                    ->orWhereNull('squad')
                    ->orWhereRaw('LOWER(TRIM(squad)) NOT IN (?, ?)', self::EXCLUIR_TIMES_INCIDENTES);
            });
        }

        $this->aplicarEmpresa($q);

        return $q;
    }

    /** Filtra a empresa pela `unidade`: prefixo p/ afiliadas, resto = Cocari. */
    private function aplicarEmpresa($q): void
    {
        if ($this->empresa === null) {
            return; // todas as empresas
        }

        if (isset(self::EMPRESAS_PREFIXO[$this->empresa])) {
            $q->where('unidade', 'like', self::EMPRESAS_PREFIXO[$this->empresa].'%');

            return;
        }

        // Cocari = tudo que NÃO é afiliada (inclui unidade vazia/nula).
        $q->where(function ($w) {
            $w->whereNull('unidade')->orWhere(function ($x) {
                foreach (self::EMPRESAS_PREFIXO as $prefixo) {
                    $x->where('unidade', 'not like', $prefixo.'%');
                }
            });
        });
    }

    private function semRobos($q)
    {
        return $q->where(function ($w) {
            $w->whereNotIn('catalogo', self::EXCLUIR_CATALOGOS)->orWhereNull('catalogo');
        });
    }

    private function criadosEntre($q, Carbon $ini, Carbon $fim)
    {
        return $q->where('data_hora_criacao', '>=', $ini)->where('data_hora_criacao', '<=', $fim);
    }

    private function criadosNaSemana($q)
    {
        return $this->criadosEntre($q, $this->ini, $this->fim);
    }

    // ---------------------------------------------------------- 1) ATENDIDOS

    public function chamadosAtendidos(): array
    {
        $criados = $this->semRobos($this->criadosNaSemana($this->base()))->count();
        $requisicoes = $this->semRobos($this->criadosNaSemana($this->base()))->where('tipo_requisicao', 'like', '%Requis%')->count();
        $incidentes = $this->semRobos($this->criadosNaSemana($this->base()))->where('tipo_requisicao', 'like', '%Incid%')->count();
        $atendidos = $this->semRobos($this->criadosNaSemana($this->base()))
            ->whereNotNull('data_hora_resolucao')
            ->where('data_hora_resolucao', '<=', $this->fim)
            ->count();

        $perf = $criados > 0 ? round($atendidos / $criados * 100, 2) : 0;

        return [
            'criados' => $criados,
            'atendidos' => $atendidos,
            'requisicoes' => $requisicoes,
            'incidentes' => $incidentes,
            'perf' => $perf,
            'pctReq' => $criados > 0 ? round($requisicoes / $criados * 100, 2) : 0,
            'pctInc' => $criados > 0 ? round($incidentes / $criados * 100, 2) : 0,
        ];
    }

    /**
     * Acumulado do ANO: SEMPRE de 1º de janeiro do ano CORRENTE até o mês atual
     * (hoje), independente do mês selecionado no topo — muda só com o filtro de
     * empresa/time (via base()). Exclui robôs.
     *
     * "Atendido" usa a MESMA régua do BI (e dos cartões mensais): resolvido até o
     * fim do MÊS em que foi criado (resolução dentro do mês), não depois. Assim o
     * acumulado do ano = soma do que cada mês resolveu no próprio mês.
     *
     * @return array{criados:int,atendidos:int,ano:int,periodo:string}
     */
    public function acumuladoAno(): array
    {
        $hoje = Carbon::now();
        $ini = $hoje->copy()->startOfYear();
        $fim = $hoje->copy()->endOfMonth();

        $criados = $this->semRobos($this->criadosEntre($this->base(), $ini, $fim))->count();
        $atendidos = $this->semRobos($this->criadosEntre($this->base(), $ini, $fim))
            ->whereNotNull('data_hora_resolucao')
            ->whereRaw('data_hora_resolucao < LAST_DAY(data_hora_criacao) + INTERVAL 1 DAY')
            ->where('data_hora_resolucao', '<=', $fim)
            ->count();

        $mesFim = ucfirst($hoje->copy()->locale('pt_BR')->translatedFormat('F'));

        return [
            'criados' => $criados,
            'atendidos' => $atendidos,
            'perf' => $criados > 0 ? round($atendidos / $criados * 100, 2) : 0,
            'ano' => (int) $hoje->format('Y'),
            'periodo' => 'Janeiro a '.$mesFim.' '.$hoje->format('Y'),
        ];
    }

    /**
     * Comparativo dos últimos 3 meses (Criados × Atendidos), ancorado no mês da
     * semana selecionada. Respeita o filtro de time e as exclusões de robô.
     *
     * @return array<int,array{rotulo:string,criados:int,atendidos:int,perf:float}>
     */
    public function comparativoMensal(): array
    {
        $abrev = [1 => 'Jan', 'Fev', 'Mar', 'Abr', 'Mai', 'Jun', 'Jul', 'Ago', 'Set', 'Out', 'Nov', 'Dez'];
        $mesRef = $this->ini->copy()->startOfMonth();
        $res = [];

        for ($i = 2; $i >= 0; $i--) {
            $mi = $mesRef->copy()->subMonthsNoOverflow($i)->startOfMonth();
            $mf = $mi->copy()->endOfMonth();
            $criados = $this->semRobos($this->criadosEntre($this->base(), $mi, $mf))->count();
            $atendidos = $this->semRobos($this->criadosEntre($this->base(), $mi, $mf))
                ->whereNotNull('data_hora_resolucao')->where('data_hora_resolucao', '<=', $mf)->count();
            $requisicoes = $this->semRobos($this->criadosEntre($this->base(), $mi, $mf))->where('tipo_requisicao', 'like', '%Requis%')->count();
            $incidentes = $this->semRobos($this->criadosEntre($this->base(), $mi, $mf))->where('tipo_requisicao', 'like', '%Incid%')->count();
            $incResolvidos = $this->semRobos($this->criadosEntre($this->base(), $mi, $mf))
                ->where('tipo_requisicao', 'like', '%Incid%')
                ->whereNotNull('data_hora_resolucao')->where('data_hora_resolucao', '<=', $mf)->count();
            $res[] = [
                'rotulo' => $abrev[(int) $mi->format('n')].'/'.$mi->format('y'),
                'criados' => $criados,
                'atendidos' => $atendidos,
                'requisicoes' => $requisicoes,
                'incidentes' => $incidentes,
                'incResolvidos' => $incResolvidos,
                'perf' => $criados > 0 ? round($atendidos / $criados * 100, 1) : 0,
                'incPerf' => $incidentes > 0 ? round($incResolvidos / $incidentes * 100, 1) : 0,
            ];
        }

        return $res;
    }

    // ------------------------------------------------------------ 2) BACKLOG

    private function backlogEm(Carbon $t): int
    {
        return $this->base()
            ->where('data_hora_criacao', '<=', $t)
            ->where(function ($w) use ($t) {
                $w->whereNull('data_hora_resolucao')->orWhere('data_hora_resolucao', '>', $t);
            })
            ->count();
    }

    public function backlog(): array
    {
        $backlogInicial = $this->backlogEm($this->ini->copy()->subSecond());
        $backlogFinal = $this->backlogEm($this->fim);
        $criados = $this->criadosNaSemana($this->base())->count();
        $resolvidos = $this->base()
            ->whereNotNull('data_hora_resolucao')
            ->where('data_hora_resolucao', '>=', $this->ini)
            ->where('data_hora_resolucao', '<=', $this->fim)
            ->count();
        $variacao = $backlogFinal - $backlogInicial;
        $variacaoPct = $backlogInicial > 0 ? round($variacao / $backlogInicial * 100, 2) : 0;

        // Série: evolução do backlog DENTRO do período selecionado, com a
        // granularidade adaptada ao tamanho — diária para um mês/período curto,
        // semanal para alguns meses, mensal para períodos longos.
        $dias = $this->ini->copy()->startOfDay()->diffInDays($this->fim->copy()->startOfDay()) + 1;
        $serie = [];

        if ($dias <= 45) {
            $granularidade = 'diaria';
            $cursor = $this->ini->copy()->startOfDay();
            while ($cursor->lte($this->fim)) {
                $serie[] = ['rotulo' => $cursor->format('d/m'), 'valor' => $this->backlogEm($cursor->copy()->endOfDay())];
                $cursor->addDay();
            }
        } elseif ($dias <= 7 * 26) {
            $granularidade = 'semanal';
            $cursor = $this->ini->copy()->startOfWeek(Carbon::MONDAY);
            while ($cursor->lte($this->fim)) {
                $fimP = $cursor->copy()->endOfWeek(Carbon::SUNDAY)->endOfDay();
                $serie[] = ['rotulo' => $cursor->format('d/m'), 'valor' => $this->backlogEm($fimP->gt($this->fim) ? $this->fim : $fimP)];
                $cursor->addWeek();
            }
        } else {
            $granularidade = 'mensal';
            $cursor = $this->ini->copy()->startOfMonth();
            while ($cursor->lte($this->fim)) {
                $fimP = $cursor->copy()->endOfMonth()->endOfDay();
                $serie[] = ['rotulo' => $this->rotuloJanela($cursor), 'valor' => $this->backlogEm($fimP->gt($this->fim) ? $this->fim : $fimP)];
                $cursor->addMonthNoOverflow();
            }
        }

        return compact('backlogInicial', 'backlogFinal', 'criados', 'resolvidos', 'variacao', 'variacaoPct', 'serie', 'granularidade');
    }

    // -------------------------------------------------- 3) INCIDENTES INTERNET

    public function incidentesInternet(): array
    {
        $q = fn () => $this->criadosNaSemana($this->base())
            ->where('catalogo', 'Indisponibilidade de Internet')
            ->where('tipo_requisicao', 'like', '%Incid%');

        $total = $q()->count();
        $unidadesDistintas = (int) $q()->distinct()->count('unidade');

        $porUnidade = $q()
            ->selectRaw('COALESCE(NULLIF(TRIM(unidade),""),"(sem unidade)") u, COUNT(*) n')
            ->groupBy('u')->orderByDesc('n')->limit(8)->get();

        $topUnidade = $porUnidade->first();
        $pctTop = ($total > 0 && $topUnidade) ? round($topUnidade->n / $total * 100, 2) : 0;

        $recorrencia = $topUnidade
            ? $this->criadosNaSemana($this->base())
                ->where('catalogo', 'Indisponibilidade de Internet')
                ->where('tipo_requisicao', 'like', '%Incid%')
                ->where('unidade', $topUnidade->u)
                ->orderBy('data_hora_criacao')
                ->get(['chave', 'data_hora_criacao'])
            : collect();

        return [
            'total' => $total,
            'unidadesDistintas' => $unidadesDistintas,
            'porUnidade' => $porUnidade,
            'unidadeMaisAfetada' => $topUnidade->u ?? '—',
            'ocorrenciasTop' => $topUnidade->n ?? 0,
            'pctTop' => $pctTop,
            'recorrencia' => $recorrencia,
        ];
    }

    // ------------------------------------------------------- 4) TOP 5 CATÁLOGOS

    private function incidentesPorCatalogo(Carbon $ini, Carbon $fim)
    {
        return $this->semRobos($this->base())
            ->where('tipo_requisicao', 'like', '%Incid%')
            ->whereBetween('data_hora_criacao', [$ini, $fim])
            ->selectRaw('catalogo, COUNT(*) n')
            ->groupBy('catalogo')->pluck('n', 'catalogo');
    }

    public function top5Catalogos(): array
    {
        [$i1, $f1] = $this->janelaAnterior(1);
        [$i2, $f2] = $this->janelaAnterior(2);
        $atual = $this->incidentesPorCatalogo($this->ini, $this->fim);
        $s1 = $this->incidentesPorCatalogo($i1, $f1);
        $s2 = $this->incidentesPorCatalogo($i2, $f2);

        $totalAtual = (int) $atual->sum();
        $top = $atual->sortDesc()->take(5);
        $concentracao = $totalAtual > 0 ? round($top->sum() / $totalAtual * 100, 2) : 0;

        $linhas = [];
        foreach ($top as $cat => $n) {
            $n1 = (int) ($s1[$cat] ?? 0);
            $n2 = (int) ($s2[$cat] ?? 0);
            $var = $n1 > 0 ? round(($n - $n1) / $n1 * 100) : ($n > 0 ? 100 : 0);
            $linhas[] = ['catalogo' => $cat, 's2' => $n2, 's1' => $n1, 'atual' => (int) $n, 'variacao' => $var];
        }

        $totalS1 = (int) $s1->sum();
        $variacaoTotal = $totalAtual - $totalS1;
        $variacaoTotalPct = $totalS1 > 0 ? round($variacaoTotal / $totalS1 * 100, 2) : 0;
        $maiorAlta = collect($linhas)->sortByDesc('variacao')->first();
        $lider = $linhas[0] ?? null;

        return [
            'total' => $totalAtual,
            'lider' => $lider,
            'pctLider' => ($lider && $totalAtual > 0) ? round($lider['atual'] / $totalAtual * 100, 2) : 0,
            'concentracao' => $concentracao,
            'concentracaoAbs' => $top->sum(),
            'variacaoTotal' => $variacaoTotal,
            'variacaoTotalPct' => $variacaoTotalPct,
            'maiorAlta' => $maiorAlta,
            'linhas' => $linhas,
            'rotuloS2' => $this->rotuloJanela($i2),
            'rotuloS1' => $this->rotuloJanela($i1),
            'rotuloAtual' => $this->rotuloJanela($this->ini),
        ];
    }

}
