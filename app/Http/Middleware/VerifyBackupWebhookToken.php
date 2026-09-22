<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\IpUtils;

/**
 * Autentica o webhook de auditoria de backup.
 *
 * O webhook do n8n era aberto — qualquer um que descobrisse a URL podia gravar
 * registros falsos e abrir chamados no Jira. Como o endpoint agora vive no
 * CoreTI, exige token compartilhado e, opcionalmente, origem conhecida.
 */
class VerifyBackupWebhookToken
{
    public function handle(Request $request, Closure $next)
    {
        $esperado = (string) config('backups.webhook.token', '');

        if ($esperado === '') {
            Log::error('backup.webhook.sem_token_configurado');

            return response()->json([
                'message' => 'Webhook de backup não configurado no servidor.',
            ], 503);
        }

        if (! hash_equals($esperado, $this->tokenDaRequisicao($request))) {
            Log::warning('backup.webhook.token_invalido', [
                'ip' => $request->ip(),
                'host' => $request->input('host'),
            ]);

            return response()->json(['message' => 'Token inválido.'], 401);
        }

        if (! $this->origemPermitida($request)) {
            Log::warning('backup.webhook.ip_nao_autorizado', ['ip' => $request->ip()]);

            return response()->json(['message' => 'Origem não autorizada.'], 403);
        }

        return $next($request);
    }

    /**
     * Aceita tanto o header dedicado quanto Bearer, para o agente poder usar
     * o que for mais simples no curl.
     */
    private function tokenDaRequisicao(Request $request): string
    {
        $header = (string) $request->header('X-Coreti-Token', '');

        if ($header !== '') {
            return $header;
        }

        return (string) $request->bearerToken();
    }

    private function origemPermitida(Request $request): bool
    {
        $permitidos = (array) config('backups.webhook.allowed_ips', []);

        if ($permitidos === []) {
            return true;
        }

        return IpUtils::checkIp((string) $request->ip(), $permitidos);
    }
}
