<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\IpUtils;

/**
 * Autentica as chamadas do servidor do Active Directory.
 *
 * Estes endpoints decidem quais contas podem ser apagadas do domínio. Sem
 * token configurado o serviço responde 503 em vez de liberar: falhar fechado
 * é o único comportamento aceitável aqui.
 */
class VerifyAdPurgeToken
{
    public function handle(Request $request, Closure $next)
    {
        $esperado = (string) config('ad.expurgo.token', '');

        if ($esperado === '') {
            Log::error('ad.expurgo.sem_token_configurado');

            return response()->json([
                'message' => 'Expurgo do AD não configurado no servidor.',
            ], 503);
        }

        if (! hash_equals($esperado, $this->tokenDaRequisicao($request))) {
            Log::warning('ad.expurgo.token_invalido', ['ip' => $request->ip()]);

            return response()->json(['message' => 'Token inválido.'], 401);
        }

        if (! $this->origemPermitida($request)) {
            Log::warning('ad.expurgo.ip_nao_autorizado', ['ip' => $request->ip()]);

            return response()->json(['message' => 'Origem não autorizada.'], 403);
        }

        return $next($request);
    }

    private function tokenDaRequisicao(Request $request): string
    {
        $header = (string) $request->header('X-Coreti-Ad-Token', '');

        return $header !== '' ? $header : (string) $request->bearerToken();
    }

    private function origemPermitida(Request $request): bool
    {
        $permitidos = (array) config('ad.expurgo.allowed_ips', []);

        if ($permitidos === []) {
            return true;
        }

        return IpUtils::checkIp((string) $request->ip(), $permitidos);
    }
}
