<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\IpUtils;

/**
 * Autentica o webhook do Jira que avisa o fechamento dos chamados de manutenção
 * da bancada. Exige token compartilhado (header X-Coreti-Token ou Bearer) e,
 * opcionalmente, IP de origem conhecido.
 */
class VerifyJiraManutencaoWebhookToken
{
    public function handle(Request $request, Closure $next)
    {
        $esperado = (string) config('bancada.manutencao.webhook.token', '');

        if ($esperado === '') {
            Log::error('manutencao.webhook.sem_token_configurado');

            return response()->json(['message' => 'Webhook de manutenção não configurado no servidor.'], 503);
        }

        $token = (string) $request->header('X-Coreti-Token', '') ?: (string) $request->bearerToken();
        if (! hash_equals($esperado, $token)) {
            Log::warning('manutencao.webhook.token_invalido', ['ip' => $request->ip()]);

            return response()->json(['message' => 'Token inválido.'], 401);
        }

        $permitidos = (array) config('bancada.manutencao.webhook.allowed_ips', []);
        if ($permitidos !== [] && ! IpUtils::checkIp((string) $request->ip(), $permitidos)) {
            Log::warning('manutencao.webhook.ip_nao_autorizado', ['ip' => $request->ip()]);

            return response()->json(['message' => 'Origem não autorizada.'], 403);
        }

        return $next($request);
    }
}
