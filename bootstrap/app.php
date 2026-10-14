<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use App\Http\Middleware\EnsureUserIsActive;
use App\Http\Middleware\AdminMiddleware;
use App\Http\Middleware\EnsureModuleAccess;
use App\Http\Middleware\VerifyBackupWebhookToken;
use App\Http\Middleware\VerifyJiraManutencaoWebhookToken;
use App\Http\Middleware\VerifyAdPurgeToken;
use App\Http\Middleware\VerifyImpressaoToken;
use Illuminate\Session\TokenMismatchException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        //
        $middleware->alias([
            'active' => EnsureUserIsActive::class,
            'admin' => AdminMiddleware::class,
            'module_access' => EnsureModuleAccess::class,
            'backup_webhook' => VerifyBackupWebhookToken::class,
            'jira_manutencao_webhook' => VerifyJiraManutencaoWebhookToken::class,
            'ad_purge_token' => VerifyAdPurgeToken::class,
            'impressao_token' => VerifyImpressaoToken::class,
        ]);

        // Webhook chamado por curl a partir dos servidores: não há sessão nem
        // token CSRF. A autenticação é feita pelo middleware backup_webhook.
        $middleware->validateCsrfTokens(except: [
            'webhooks/backup',
            // Webhook do Jira (fechamento de chamado de manutenção da bancada):
            // sem sessão/CSRF; autenticado pelo jira_manutencao_webhook.
            'webhooks/jira-manutencao',
            // Chamado pelo servidor do AD via Invoke-RestMethod: não há sessão
            // nem token CSRF. A autenticação é feita pelo ad_purge_token.
            'api/ad/exclusao/*',
            // Chamado pelas tarefas agendadas dos servidores de impressão,
            // também sem sessão. Autenticado pelo impressao_token.
            'api/impressao/*',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (TokenMismatchException $e, \Illuminate\Http\Request $request) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Sua sessão expirou. Atualize a página e tente novamente.',
                ], 419);
            }

            if ($request->is('login') || $request->routeIs('login')) {
                return redirect()
                    ->route('login')
                    ->with('error', 'Sua sessão expirou. Faça login novamente.');
            }

            return back()->with('error', 'Sua sessão expirou. Atualize a página e tente novamente.');
        });
    })->create();
