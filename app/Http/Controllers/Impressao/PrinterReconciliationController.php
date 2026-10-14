<?php

namespace App\Http\Controllers\Impressao;

use App\Http\Controllers\Controller;
use App\Models\PrintQueueDiscovery;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Conciliação entre o que os servidores veem e a base da Simpress.
 *
 * Existe uma tela em vez de abertura de chamado de propósito: são mais de cem
 * divergências de IP acumuladas, e uma enxurrada de chamados de dívida antiga
 * não ajuda ninguém. A tela deixa corrigir em lote na Simpress; o chamado fica
 * para a divergência NOVA, que aparecer depois da base já limpa.
 */
class PrinterReconciliationController extends Controller
{
    private const SITUACOES = [
        'divergentes' => 'IP divergente da Simpress',
        'sem_simpress' => 'No servidor, fora da Simpress',
        'sem_resposta' => 'Sem resposta SNMP',
        'casadas' => 'Casadas e conferindo',
    ];

    public function index(Request $request): View
    {
        $filtros = $request->validate([
            'servidor' => ['nullable', 'string', 'max:40'],
            'situacao' => ['nullable', 'string', 'in:'.implode(',', array_keys(self::SITUACOES))],
        ]);

        $consulta = PrintQueueDiscovery::query()->with('printer');

        if (($servidor = trim((string) ($filtros['servidor'] ?? ''))) !== '') {
            $consulta->where('servidor', $servidor);
        }

        match ($filtros['situacao'] ?? '') {
            'divergentes' => $consulta->comDivergenciaDeIp(),
            'sem_simpress' => $consulta->semCorrespondencia(),
            'sem_resposta' => $consulta->where('snmp_status', PrintQueueDiscovery::SNMP_SEM_RESPOSTA),
            'casadas' => $consulta->casadas()->where('ip_divergente', false),
            default => null,
        };

        return view('impressoras.conciliacao', [
            'registros' => $consulta->orderByDesc('ip_divergente')->orderBy('servidor')->orderBy('fila')->paginate(50)->withQueryString(),
            'situacoes' => self::SITUACOES,
            'filtros' => $filtros,
            'servidores' => PrintQueueDiscovery::query()->distinct()->orderBy('servidor')->pluck('servidor'),
            'resumo' => [
                'total' => PrintQueueDiscovery::query()->count(),
                'divergentes' => PrintQueueDiscovery::comDivergenciaDeIp()->count(),
                'sem_simpress' => PrintQueueDiscovery::semCorrespondencia()->count(),
                'sem_resposta' => PrintQueueDiscovery::query()->where('snmp_status', PrintQueueDiscovery::SNMP_SEM_RESPOSTA)->count(),
                'casadas' => PrintQueueDiscovery::casadas()->count(),
            ],
        ]);
    }
}
