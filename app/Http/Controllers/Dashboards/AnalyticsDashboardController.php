<?php

namespace App\Http\Controllers\Dashboards;

use App\Http\Controllers\Controller;
use App\Services\Dashboards\BackupAuditStats;
use App\Services\Dashboards\ServiceDeskApresentacaoStats;
use App\Services\Dashboards\ServiceDeskStats;
use App\Services\Dashboards\TelefoniaStats;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * Painéis analíticos registrados em config/dashboards.php. Cada rota é
 * protegida pelo módulo do assunto; o controller só colhe os números do
 * service e entrega à view.
 */
class AnalyticsDashboardController extends Controller
{
    public function auditoriaBackups(Request $request): View
    {
        $filtros = $request->validate(['de' => ['nullable', 'date'], 'ate' => ['nullable', 'date']]);
        $stats = new BackupAuditStats($filtros['de'] ?? null, $filtros['ate'] ?? null);

        $saude = $stats->saudeDosPares();

        return view('dashboards.auditoria-backups', [
            'de' => $stats->de(),
            'ate' => $stats->ate(),
            'resumo' => $stats->resumo(),
            'porDia' => $stats->porDia(),
            'porTipo' => $stats->porTipo(),
            'porHost' => $stats->porHost(),
            'saude' => $saude,
            'comProblema' => $saude->whereIn('situacao', ['falha', 'silencio'])->values(),
            'falhasRecentes' => $stats->falhasRecentes(),
            'incidentes' => $stats->incidentes(),
        ]);
    }

    public function telefonia(): View
    {
        $stats = new TelefoniaStats(Auth::user());

        return view('dashboards.telefonia', [
            'resumo' => $stats->resumo(),
            'custoPorOperadora' => $stats->custoPorOperadora(),
            'linhasPorStatus' => $stats->linhasPorStatus(),
            'custoPorOperacao' => $stats->custoPorOperacao(),
            'custoPorUnidade' => $stats->custoPorUnidade(),
            'aparelhosPorMarca' => $stats->aparelhosPorMarca(),
            'aparelhosPorStatus' => $stats->aparelhosPorStatus(),
            'qualidade' => $stats->qualidade(),
        ]);
    }

    public function servicedesk(Request $request): View
    {
        $filtros = $request->validate(['de' => ['nullable', 'date'], 'ate' => ['nullable', 'date']]);
        $stats = new ServiceDeskStats($filtros['de'] ?? null, $filtros['ate'] ?? null);

        return view('dashboards.servicedesk', [
            'de' => $stats->de(),
            'ate' => $stats->ate(),
            'resumo' => $stats->resumo(),
            'chamadosPorStatus' => $stats->chamadosPorStatus(),
            'movimentoPorDia' => $stats->movimentoPorDia(),
            'chamadosPorSquad' => $stats->chamadosPorSquad(),
            'porCatalogo' => $stats->rankingPor('catalogo'),
            'porTipo' => $stats->rankingPor('tipo_requisicao'),
            'porUnidade' => $stats->rankingPor('unidade'),
            'emailsPorScope' => $stats->emailsPorScope(),
            'adResumo' => $stats->adResumo(),
            'adPorLotacao' => $stats->adPorLotacao(),
            'officePorTipo' => $stats->officePorTipo(),
        ]);
    }

    /**
     * Apresentação mensal do Service Desk (versão viva do deck): 5 indicadores
     * em abas, calculados ao vivo da tabela `jiras`, com seletor de mês.
     */
    public function servicedeskApresentacao(Request $request): View
    {
        $filtros = $request->validate([
            'inicio' => ['nullable', 'date_format:Y-m-d'],
            'fim' => ['nullable', 'date_format:Y-m-d'],
            'mes' => ['nullable', 'regex:/^\d{4}-\d{2}$/'],
            'time' => ['nullable', 'string', 'max:120'],
        ]);

        // Times (squads) disponíveis, do mais ao menos movimentado.
        $times = \Illuminate\Support\Facades\DB::table('jiras')
            ->selectRaw('TRIM(squad) s, COUNT(*) n')
            ->whereNotNull('squad')->where('squad', '<>', '')
            ->groupBy('s')->orderByDesc('n')->pluck('s')->values();

        $timeSelecionado = ($filtros['time'] ?? '') !== '' && $times->contains($filtros['time'])
            ? $filtros['time'] : null;

        // Período analisado. Duas formas:
        //  - Mês: escolher um mês mostra o MÊS INTEIRO (modo 'mes').
        //  - Período livre: Início + Fim definem o intervalo (modo 'periodo').
        // Sem nada, o padrão é o mês atual inteiro.
        $inicioInput = $filtros['inicio'] ?? null;
        $fimInput = $filtros['fim'] ?? null;
        $mesInput = $filtros['mes'] ?? null;

        if ($inicioInput && $fimInput) {
            $ini = Carbon::createFromFormat('Y-m-d', $inicioInput)->startOfDay();
            $fim = Carbon::createFromFormat('Y-m-d', $fimInput)->endOfDay();
            if ($fim->lt($ini)) {
                [$ini, $fim] = [$fim->copy()->startOfDay(), $ini->copy()->endOfDay()];
            }
            $modo = 'periodo';
            $mesSelecionado = $ini->format('Y-m');
            $inicioSel = $ini->format('Y-m-d');
            $fimSel = $fim->format('Y-m-d');
        } elseif ($mesInput) {
            $modo = 'mes';
            $mesSelecionado = $mesInput;
            $ini = Carbon::createFromFormat('Y-m-d', $mesInput.'-01')->startOfMonth();
            $fim = $ini->copy()->endOfMonth();
            $inicioSel = $fimSel = '';
        } else {
            $modo = 'mes';
            $ini = Carbon::now()->startOfMonth();
            $fim = $ini->copy()->endOfMonth();
            $mesSelecionado = $ini->format('Y-m');
            $inicioSel = $fimSel = '';
        }

        $stats = new ServiceDeskApresentacaoStats($ini->format('Y-m-d'), $fim->format('Y-m-d'), $timeSelecionado, $modo);

        // Seletor de mês: últimos 12 meses (mês/ano). O período livre vem dos calendários.
        $meses = collect(range(0, 11))
            ->map(function ($i) {
                $d = Carbon::now()->startOfMonth()->subMonths($i);

                return [
                    'valor' => $d->format('Y-m'),
                    'rotulo' => ucfirst($d->locale('pt_BR')->translatedFormat('F Y')),
                ];
            })
            ->values();

        return view('dashboards.service-desk-apresentacao', [
            'modo' => $modo,
            'inicioSel' => $inicioSel,
            'fimSel' => $fimSel,
            'rotuloSemana' => $stats->rotuloPeriodo(),
            'meses' => $meses,
            'mesSelecionado' => $mesSelecionado,
            'times' => $times,
            'timeSelecionado' => $timeSelecionado,
            'atendidos' => $stats->chamadosAtendidos(),
            'comparativoMensal' => $stats->comparativoMensal(),
            'backlog' => $stats->backlog(),
            'internet' => $stats->incidentesInternet(),
            'top5' => $stats->top5Catalogos(),
            'pivot' => $stats->unidadePivot(),
        ]);
    }

    /**
     * Mês (Y-m) a que uma semana pertence: o da sua quinta-feira (regra ISO),
     * para que cada semana caia em um único mês mesmo quando cruza a virada.
     */
}
