<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class EnsureModuleAccess
{
    public function handle(Request $request, Closure $next, string $module)
    {
        if (! Auth::check()) {
            abort(403);
        }

        $user = Auth::user();
        if (! $user || ! method_exists($user, 'hasModuleAccess') || ! $user->hasModuleAccess($module)) {
            abort(403, 'Acesso não autorizado para este módulo.');
        }

        return $next($request);
    }
}

