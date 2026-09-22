<?php

namespace App\Http\Controllers\Ad;

use App\Http\Controllers\Controller;
use App\Models\AdPurgeBatch;
use App\Models\AdPurgeBatchItem;
use App\Services\Ad\AdPurgeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Endpoints consumidos pelo servidor do Active Directory.
 *
 * O sentido da conversa é sempre AD → CoreTI. O CoreTI não abre conexão com o
 * domínio e não guarda credencial capaz de apagar contas: ele apenas responde
 * o que foi aprovado por uma pessoa.
 */
class AdPurgeApiController extends Controller
{
    public function __construct(private readonly AdPurgeService $expurgo)
    {
    }

    /** Recebe a prévia mensal montada no servidor do AD. */
    public function previa(Request $request): JsonResponse
    {
        $dados = $request->validate([
            // AAAA-MM, com sufixo opcional para uma rodada extra no mesmo
            // mês (2026-09-2), sem apagar o histórico da anterior.
            'referencia' => ['nullable', 'string', 'max:16', 'regex:/^\d{4}-\d{2}(-[A-Za-z0-9]{1,8})?$/'],
            'dias_criterio' => ['required', 'integer', 'min:1', 'max:3650'],
            'origem_host' => ['nullable', 'string', 'max:255'],
            'search_base' => ['nullable', 'string', 'max:255'],
            'candidatos' => ['present', 'array'],
            'candidatos.*.nome_usuario' => ['required', 'string', 'max:255'],
            'candidatos.*.distinguished_name' => ['required', 'string'],
            'candidatos.*.nome_exibicao' => ['nullable', 'string', 'max:255'],
            'candidatos.*.email' => ['nullable', 'string', 'max:255'],
            'candidatos.*.desativada_em' => ['nullable', 'string', 'max:64'],
            'candidatos.*.dias_desativada' => ['nullable', 'integer'],
            'candidatos.*.ultimo_logon' => ['nullable', 'string', 'max:64'],
            'candidatos.*.descricao' => ['nullable', 'string', 'max:255'],
            'candidatos.*.parece_servico' => ['nullable', 'boolean'],
            'candidatos.*.ja_privilegiada' => ['nullable', 'boolean'],
        ]);

        $referencia = $dados['referencia'] ?? now()->format('Y-m');

        $lote = $this->expurgo->registrarPrevia(
            referencia: $referencia,
            candidatos: $dados['candidatos'],
            diasCriterio: (int) $dados['dias_criterio'],
            origemHost: $dados['origem_host'] ?? null,
            searchBase: $dados['search_base'] ?? null,
        );

        // Prévia enviada para um lote já decidido não substitui a decisão.
        $substituido = $lote->pendente();

        return response()->json([
            'referencia' => $lote->referencia,
            'status' => $lote->status,
            'registrado' => $substituido,
            'total_candidatos' => $lote->total_candidatos,
            'mensagem' => $substituido
                ? 'Prévia registrada. Aguardando aprovação no CoreTI.'
                : 'Lote deste mês já foi decidido; a prévia não foi substituída.',
        ], $substituido ? 201 : 409);
    }

    /**
     * Devolve o que o servidor do AD pode excluir agora.
     *
     * Sem lote aprovado responde 204: o script do AD trata isso como "nada a
     * fazer" e encerra sem erro.
     */
    public function aprovado(): JsonResponse
    {
        $lote = $this->expurgo->loteLiberado();

        if ($lote === null) {
            return response()->json([], 204);
        }

        $max = max(1, (int) config('ad.expurgo.max_por_execucao', 200));

        /*
         | A seleção e a marcação de entrega acontecem na mesma transação, com
         | as linhas travadas.
         |
         | Sem isso, duas execuções simultâneas do executor recebem a MESMA
         | lista: a consulta devolve tudo que está aprovado e nada muda até o
         | resultado voltar. As duas apagariam as mesmas contas, e a segunda
         | reportaria falha para exclusão que deu certo.
         */
        [$itens, $totalAprovados] = DB::transaction(function () use ($lote, $max) {
            $selecionados = $lote->itens()->entregaveis()
                ->orderByDesc('dias_desativada')
                ->limit($max)
                ->lockForUpdate()
                ->get();

            if ($selecionados->isNotEmpty()) {
                $lote->itens()->whereIn('id', $selecionados->pluck('id'))
                    ->update(['entregue_em' => now()]);
            }

            return [$selecionados, $lote->itens()->aprovados()->count()];
        });

        // Tudo aprovado já está entregue e dentro do prazo: nada a fazer agora.
        if ($itens->isEmpty()) {
            return response()->json([], 204);
        }

        return response()->json([
            'referencia' => $lote->referencia,
            'aprovado_em' => optional($lote->aprovado_em)->toIso8601String(),
            'expira_em' => optional($lote->expira_em)->toIso8601String(),
            'total_aprovados' => $totalAprovados,
            'lote_atual' => $itens->count(),
            'contas' => $itens->map(fn ($i) => [
                'nome_usuario' => $i->nome_usuario,
                'distinguished_name' => $i->distinguished_name,
                'dias_desativada' => $i->dias_desativada,
            ])->values(),
        ]);
    }

    /** Recebe o resultado da execução e fecha o ciclo. */
    public function resultado(Request $request): JsonResponse
    {
        $dados = $request->validate([
            'referencia' => ['required', 'string', 'max:16', 'regex:/^\d{4}-\d{2}(-[A-Za-z0-9]{1,8})?$/'],
            'resultados' => ['present', 'array'],
            'resultados.*.nome_usuario' => ['required', 'string', 'max:255'],
            'resultados.*.situacao' => ['required', 'string', 'in:excluido,pulado,falhou'],
            'resultados.*.detalhe' => ['nullable', 'string', 'max:255'],
            'resultados.*.excluido_em' => ['nullable', 'string', 'max:64'],
            'resultados.*.excluido_por' => ['nullable', 'string', 'max:255'],
        ]);

        $lote = AdPurgeBatch::where('referencia', $dados['referencia'])->first();

        if ($lote === null) {
            return response()->json(['message' => 'Lote não encontrado.'], 404);
        }

        /*
         | Resultado só é aceito para lote aprovado ou já em execução. Isso
         | impede que um relatório atrasado reabra um lote rejeitado ou
         | expirado e marque contas como excluídas sem autorização vigente.
         */
        if (! in_array($lote->status, [AdPurgeBatch::STATUS_APROVADO, AdPurgeBatch::STATUS_EXECUTADO], true)) {
            Log::warning('ad.expurgo.resultado_em_lote_invalido', [
                'lote' => $lote->referencia,
                'status' => $lote->status,
            ]);

            return response()->json([
                'message' => 'Lote não está aprovado; resultado recusado.',
                'status' => $lote->status,
            ], 409);
        }

        $resumo = $this->expurgo->aplicarResultado($lote, $dados['resultados']);

        return response()->json([
            'referencia' => $lote->referencia,
            'status' => $lote->fresh()->status,
        ] + $resumo);
    }
}
