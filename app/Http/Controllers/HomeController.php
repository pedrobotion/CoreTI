<?php

namespace App\Http\Controllers;

use App\Models\Application;
use App\Models\CircuitUnit;
use App\Models\OrganizationUnit;
use App\Models\User;
use App\Services\SyncStatusService;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class HomeController extends Controller
{
    public function __invoke(SyncStatusService $syncStatus): View
    {
        $user = Auth::user();
        $isAdmin = $user?->role === 'admin';
        $dashboards = $this->dashboardsVisiveis($user);

        /*
         | Ter QUALQUER painel já é motivo para ver a tela.
         |
         | Esta condição era uma lista fixa de módulos, e o registro de
         | dashboards nasceu fora dela: quem tinha só o módulo de impressoras
         | caía em "Nenhum dashboard encontrado" — justamente quem acabara de
         | ganhar um. Somar o registro evita que a lista envelheça de novo a
         | cada painel novo.
         */
        $hasAnyDashboardAccess = $isAdmin || $dashboards !== [] || (
            $user?->hasModuleAccess('servicedesk')
            || $user?->hasModuleAccess('unidades')
            || $user?->hasModuleAccess('aplicativos')
            || $user?->hasModuleAccess('bancada')
            || $user?->hasModuleAccess('administrativo')
            || $user?->hasModuleAccess('coordenacao')
        );

        $stats = [
            'users_total' => User::count(),
            'users_active' => User::where('is_active', true)->count(),
            'users_pending' => User::where('is_active', false)->count(),
            'admins' => User::where('role', 'admin')->count(),
            'applications_total' => Application::where('is_active', true)->count(),
            'circuits_total' => CircuitUnit::count(),
            'units_total' => OrganizationUnit::query()->active()->count(),
            'operators_total' => CircuitUnit::query()
                ->whereNotNull('operadora')
                ->distinct()
                ->count('operadora'),
        ];

        $pendingUsers = $isAdmin
            ? User::where('is_active', false)->orderByDesc('created_at')->limit(5)->get()
            : collect();

        return view('home', [
            'dashboards' => $dashboards,
            'stats' => $stats,
            'syncStatus' => $syncStatus->fontes($user),
            'pendingUsers' => $pendingUsers,
            'isAdmin' => $isAdmin,
            'hasAnyDashboardAccess' => $hasAnyDashboardAccess,
        ]);
    }

    /**
     * Painéis que este usuário pode abrir.
     *
     * A lista vem de config/dashboards.php, e não da view: painel novo é uma
     * entrada no registro, sem mexer em três arquivos. A permissão é a mesma
     * que protege a rota, então o atalho nunca leva a um 403.
     *
     * @return array<string, array<string, mixed>>
     */
    private function dashboardsVisiveis(?User $user): array
    {
        if ($user === null) {
            return [];
        }

        return array_filter(
            (array) config('dashboards', []),
            fn (array $painel): bool => \Illuminate\Support\Facades\Route::has($painel['rota'] ?? '')
                && $user->hasModuleAccess((string) ($painel['modulo'] ?? ''))
        );
    }

}
