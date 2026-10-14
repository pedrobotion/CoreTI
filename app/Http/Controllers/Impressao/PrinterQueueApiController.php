<?php

namespace App\Http\Controllers\Impressao;

use App\Http\Controllers\Controller;
use App\Services\Impressao\PrinterFleetReconciliationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Recebe a lista de filas de impressão de cada servidor Windows.
 *
 * O servidor manda só o que enxerga sem esforço — nome, IP da porta e driver.
 * A leitura SNMP do serial acontece depois, no CoreTI, que alcança as
 * impressoras diretamente. O servidor não precisa de SNMP funcional.
 */
class PrinterQueueApiController extends Controller
{
    public function lote(Request $request, PrinterFleetReconciliationService $servico): JsonResponse
    {
        $dados = $request->validate([
            'servidor' => ['required', 'string', 'max:40'],
            'filas' => ['present', 'array', 'max:2000'],
            'filas.*.fila' => ['required', 'string', 'max:160'],
            'filas.*.ip_porta' => ['nullable', 'string', 'max:45'],
            'filas.*.driver' => ['nullable', 'string', 'max:200'],
        ]);

        $resumo = $servico->registrarFilas($dados['servidor'], $dados['filas']);

        Log::info('impressao.filas_recebidas', $resumo + ['servidor' => $dados['servidor'], 'ip' => $request->ip()]);

        return response()->json($resumo);
    }
}
