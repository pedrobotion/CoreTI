<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use App\Http\Middleware\EnsureUserIsActive;
use App\Http\Middleware\AdminMiddleware;
use App\Http\Middleware\EnsureModuleAccess;
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
