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
        $hasAnyDashboardAccess = $isAdmin || (
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
            'stats' => $stats,
            'syncStatus' => $syncStatus->fontes($user),
            'pendingUsers' => $pendingUsers,
            'isAdmin' => $isAdmin,
            'hasAnyDashboardAccess' => $hasAnyDashboardAccess,
        ]);
    }
}
