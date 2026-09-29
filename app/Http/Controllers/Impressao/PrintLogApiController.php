<?php

namespace App\Http\Controllers\Impressao;

use App\Http\Controllers\Controller;
use App\Models\PrintCollectorStatus;
use App\Models\PrintJob;
use App\Services\Impressao\PrintJobIngestService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Recebe o log de impressão dos servidores Windows.
 *
 * Só escrita de evento: nada aqui devolve dado que sirva para outro fim, e
 * nenhuma rota daqui apaga registro. O pior que um token vazado consegue
 * fazer é sujar a estatística — não é pouco, mas é reversível.
 */
class PrintLogApiController extends Controller
{
    public function lote(Request $request, PrintJobIngestService $ingest): JsonResponse
    {
        $max = (int) config('impressao.max_por_lote', 1000);

        $dados = $request->validate([
            'servidor' => ['required', 'string', 'max:40'],
            // present e nao required: array vazio e um sinal legitimo de
            // vida em dia sem impressao.
            'eventos' => ['present', 'array', 'max:'.$max],
            'eventos.*.impresso_em' => ['required', 'string', 'max:40'],
            'eventos.*.usuario' => ['required', 'string', 'max:120'],
            'eventos.*.impressora' => ['required', 'string', 'max:160'],
            'eventos.*.estacao' => ['nullable', 'string', 'max:120'],
            'eventos.*.porta' => ['nullable', 'string', 'max:120'],
            'eventos.*.doc_numero' => ['nullable', 'string', 'max:40'],
            'eventos.*.paginas' => ['nullable', 'integer', 'min:0'],
            'eventos.*.tamanho_bytes' => ['nullable', 'integer', 'min:0'],
        ]);

        $resumo = $ingest->registrar($dados['servidor'], $dados['eventos']);

        /*
         | Marca o contato mesmo quando nada foi gravado.
         |
         | Lote vazio ou só de repetidos continua sendo prova de que o coletor
         | está vivo — e é isso que o painel precisa saber. Fim de semana sem
         | impressão nenhuma não pode parecer coletor parado.
         */
        PrintCollectorStatus::registrarContato(
            $dados['servidor'],
            $resumo['recebidos'],
            $resumo['gravados']
        );

        Log::info('impressao.lote_recebido', $resumo + ['servidor' => $dados['servidor'], 'ip' => $request->ip()]);

        return response()->json($resumo);
    }

    /**
     * Até quando este servidor já enviou.
     *
     * O script pergunta antes de coletar e busca só o que falta. Sem isso ele
     * reenviaria a semana inteira toda vez — o CoreTI descartaria as
     * repetições, mas a rede e o log do Windows pagariam o preço à toa.
     */
    public function ultimoEvento(Request $request): JsonResponse
    {
        $servidor = mb_strtoupper(trim((string) $request->query('servidor', '')));

        if ($servidor === '') {
            return response()->json(['message' => 'Informe o servidor.'], 422);
        }

        $ultimo = PrintJob::query()->where('servidor', $servidor)->max('impresso_em');

        return response()->json([
            'servidor' => $servidor,
            'ultimo_evento' => $ultimo,
            'total' => PrintJob::query()->where('servidor', $servidor)->count(),
            'dias_padrao' => (int) config('impressao.dias_padrao', 7),
        ]);
    }
}
