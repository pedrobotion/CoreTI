<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class EnsureUserIsActive
{
    public function handle(Request $request, Closure $next)
    {
        if (Auth::check() && ! Auth::user()->is_active) {
            Auth::logout();

            if ($request->expectsJson()) {
                return response()->json(['message' => 'Conta ainda não aprovada.'], 403);
            }

            return redirect()->route('login')->withErrors([
                'email' => 'Sua conta ainda não foi aprovada.'
            ]);
        }

        return $next($request);
    }
}
