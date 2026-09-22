<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\ModuleAccessService;
use App\Support\LegacyModulePermissions;
use App\Support\ModuleRegistry;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use RuntimeException;

class AdminController extends Controller
{
    public function index()
    {
        $this->authorize('viewAdminDashboard', User::class);

        $users = User::query()
            ->when(LegacyModulePermissions::novaTabelaDisponivel(), fn ($q) => $q->with('moduleAccesses'))
            ->orderBy('created_at', 'desc')
            ->get();
        $currentUser = $users->firstWhere('id', auth()->id());
        if ($currentUser) {
            $users = $users->reject(fn ($user) => $user->id === $currentUser->id)
                ->prepend($currentUser);
        }

        $moduleGroups = ModuleRegistry::grouped();

        // Sem a tabela normalizada, a tela ainda lista os acessos (via modelo
        // legado) mas não pode gravar — o formulário é exibido bloqueado.
        $permissionsReady = LegacyModulePermissions::novaTabelaDisponivel();

        return view('admin.dashboard', compact('users', 'moduleGroups', 'permissionsReady'));
    }

    public function updateModuleAccess(Request $request, User $user, ModuleAccessService $moduleAccess)
    {
        $this->authorize('updateRole', $user);
        if ($user->isMasterAccount()) {
            return redirect()->route('admin.dashboard')
                ->with('error', 'Conta master protegida: operação não permitida.');
        }

        // O formulário envia modules[<modulo>][] = <ability>; o registry decide
        // o que é válido, então nenhum módulo novo exige mudar este método.
        $submitted = (array) $request->input('modules', []);
        $desired = [];

        foreach (ModuleRegistry::keys() as $module) {
            $abilities = array_values(array_filter(
                array_map('strval', (array) ($submitted[$module] ?? [])),
                fn (string $ability) => ModuleRegistry::supportsAbility($module, $ability)
            ));

            if ($abilities !== []) {
                $desired[$module] = $abilities;
            }
        }

        try {
            $moduleAccess->sync($user, $desired, $request->user());
        } catch (RuntimeException $e) {
            return redirect()->route('admin.dashboard')->with('error', $e->getMessage());
        }

        return redirect()->route('admin.dashboard')
            ->with('success', "Acessos de {$user->name} atualizados com sucesso.");
    }

    public function approveUser(User $user)
    {
        $this->authorize('approve', $user);
        if ($user->isMasterAccount()) {
            return redirect()->route('admin.dashboard')
                ->with('error', 'Conta master protegida: operação não permitida.');
        }

        $user->is_active = true;
        $user->save();

        return redirect()->route('admin.dashboard')
            ->with('success', 'Usuário aprovado com sucesso!');
    }

    public function rejectUser(User $user)
    {
        $this->authorize('reject', $user);
        if ($user->isMasterAccount()) {
            return redirect()->route('admin.dashboard')
                ->with('error', 'Conta master protegida: operação não permitida.');
        }

        $user->is_active = false;
        $user->save();

        return redirect()->route('admin.dashboard')
            ->with('success', 'Usuário rejeitado com sucesso!');
    }

    public function resetPassword(User $user)
    {
        $this->authorize('resetPassword', $user);

        $user->forceFill([
            'password' => Hash::make(Str::random(64)),
            'remember_token' => Str::random(60),
            'must_change_password' => true,
        ])->save();

        Password::broker()->deleteToken($user);
        $token = Password::broker()->createToken($user);
        $resetLink = route('password.reset', [
            'token' => $token,
            'email' => $user->email,
        ]);

        return redirect()->route('admin.dashboard')
            ->with('success', 'Reset de senha iniciado com sucesso!')
            ->with('password_reset', [
                'email' => $user->email,
                'link' => $resetLink,
            ]);
    }

    public function updateRole(User $user)
    {
        $this->authorize('updateRole', $user);
        if ($user->isMasterAccount()) {
            return redirect()->route('admin.dashboard')
                ->with('error', 'Conta master protegida: operação não permitida.');
        }

        $user->role = $user->role === 'admin' ? 'user' : 'admin';
        $user->save();

        return redirect()->route('admin.dashboard')
            ->with('success', 'Permissões alteradas com sucesso!');
    }

    public function deleteUser(User $user)
    {
        $this->authorize('delete', $user);
        if ($user->isMasterAccount()) {
            return redirect()->route('admin.dashboard')
                ->with('error', 'Conta master protegida: operação não permitida.');
        }

        $user->delete();

        return redirect()->route('admin.dashboard')
            ->with('success', 'Usuário deletado com sucesso!');
    }
}
