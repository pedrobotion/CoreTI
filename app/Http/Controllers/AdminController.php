<?php

namespace App\Http\Controllers;

use App\Models\UserModulePermission;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

class AdminController extends Controller
{
    public function index()
    {
        $this->authorize('viewAdminDashboard', User::class);

        $users = User::with('modulePermissions')->orderBy('created_at', 'desc')->get();
        $currentUser = $users->firstWhere('id', auth()->id());
        if ($currentUser) {
            $users = $users->reject(fn ($user) => $user->id === $currentUser->id)
                ->prepend($currentUser);
        }

        return view('admin.dashboard', compact('users'));
    }

    public function updateModuleAccess(Request $request, User $user)
    {
        $this->authorize('updateRole', $user);
        if ($user->isMasterAccount()) {
            return redirect()->route('admin.dashboard')
                ->with('error', 'Conta master protegida: operação não permitida.');
        }

        $data = $request->validate([
            'servicedesk' => ['nullable', 'boolean'],
            'unidades' => ['nullable', 'boolean'],
            'aplicativos' => ['nullable', 'boolean'],
            'bancada' => ['nullable', 'boolean'],
            'administrativo' => ['nullable', 'boolean'],
        ]);

        $permissions = UserModulePermission::firstOrCreate(
            ['user_id' => $user->id],
            [
                'servicedesk' => false,
                'unidades' => false,
                'aplicativos' => false,
                'bancada' => false,
                'administrativo' => false,
            ]
        );

        $permissions->update([
            'servicedesk' => (bool) ($data['servicedesk'] ?? false),
            'unidades' => (bool) ($data['unidades'] ?? false),
            'aplicativos' => (bool) ($data['aplicativos'] ?? false),
            'bancada' => (bool) ($data['bancada'] ?? false),
            'administrativo' => (bool) ($data['administrativo'] ?? false),
        ]);

        return redirect()->route('admin.dashboard')
            ->with('success', 'Acessos por módulo atualizados com sucesso.');
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
