<?php

namespace App\Http\Middleware;

use App\Support\ModuleRegistry;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Libera a rota se o usuário atender a QUALQUER uma das expressões informadas.
 *
 * Aceita tanto o formato antigo quanto o novo, por nível:
 *   module_access:cmdb,network_datacenter   -> leitura em qualquer um dos dois
 *   module_access:admissao.request          -> precisa da permissão de solicitar
 */
class EnsureModuleAccess
{
    public function handle(Request $request, Closure $next, string ...$modules)
    {
        if (! Auth::check()) {
            abort(403);
        }

        $user = Auth::user();

        $expressions = collect($modules)
            ->flatMap(fn (string $value) => explode(',', $value))
            ->map(fn (string $value) => trim($value))
            ->filter()
            ->values();

        if ($expressions->isEmpty()) {
            abort(403, 'Acesso não autorizado para este módulo.');
        }

        foreach ($expressions as $expression) {
            if (ModuleRegistry::parse($expression) === null) {
                // Expressão inválida na rota: falha fechada e sinaliza no log.
                report(new \RuntimeException("Módulo/permissão desconhecido em rota: {$expression}"));

                continue;
            }

            if ($user->canModule($expression)) {
                return $next($request);
            }
        }

        abort(403, 'Acesso não autorizado para este módulo.');
    }
}
