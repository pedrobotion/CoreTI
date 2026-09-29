<?php

namespace App\Http\Controllers\Impressao;

use App\Http\Controllers\Controller;
use App\Services\Impressao\PrintStatsService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PrintDashboardController extends Controller
{
    public function impressoes(Request $request): View
    {
        $filtros = $request->validate([
            'de' => ['nullable', 'date'],
            'ate' => ['nullable', 'date'],
        ]);

        $stats = PrintStatsService::periodo($filtros['de'] ?? null, $filtros['ate'] ?? null);

        return view('dashboards.impressoes', [
            'de' => $stats->de(),
            'ate' => $stats->ate(),
            'resumo' => $stats->resumo(),
            'porUsuario' => $stats->porUsuario(),
            'porLotacao' => $stats->porLotacao(),
            'porImpressora' => $stats->porImpressora(),
            'porDia' => $stats->porDia(),
            'porHorario' => $stats->porHorario(),
            'maiores' => $stats->maioresTrabalhos(),
            'perfil' => $stats->porPerfil(),
            'maisPesados' => $stats->maisPesadosPorPagina(),
            'servidores' => $stats->porServidor(),
        ]);
    }
}
