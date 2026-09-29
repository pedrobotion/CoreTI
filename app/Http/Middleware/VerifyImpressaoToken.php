<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\IpUtils;

/**
 * Autentica os envios dos servidores de impressão.
 *
 * Sem token configurado responde 503 em vez de liberar: um endpoint que
 * aceita escrita no banco precisa falhar fechado.
 */
class VerifyImpressaoToken
{
    public function handle(Request $request, Closure $next)
    {
        $esperado = (string) config('impressao.token', '');

        if ($esperado === '') {
            Log::error('impressao.sem_token_configurado');

            return response()->json([
                'message' => 'Coleta de impressão não configurada no servidor.',
            ], 503);
        }

        if (! hash_equals($esperado, $this->tokenDaRequisicao($request))) {
            Log::warning('impressao.token_invalido', ['ip' => $request->ip()]);

            return response()->json(['message' => 'Token inválido.'], 401);
        }

        if (! $this->origemPermitida($request)) {
            Log::warning('impressao.ip_nao_autorizado', ['ip' => $request->ip()]);

            return response()->json(['message' => 'Origem não autorizada.'], 403);
        }

        return $next($request);
    }

    private function tokenDaRequisicao(Request $request): string
    {
        $header = (string) $request->header('X-Coreti-Impressao-Token', '');

        return $header !== '' ? $header : (string) $request->bearerToken();
    }

    private function origemPermitida(Request $request): bool
    {
        $permitidos = (array) config('impressao.allowed_ips', []);

        if ($permitidos === []) {
            return true;
        }

        return IpUtils::checkIp((string) $request->ip(), $permitidos);
    }
}
