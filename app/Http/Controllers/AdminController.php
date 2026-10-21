<?php

namespace App\Http\Controllers;

use App\Mail\CoreTiNotificationMail;
use App\Models\User;
use App\Services\ModuleAccessService;
use App\Support\LegacyModulePermissions;
use App\Support\ModuleRegistry;
use App\Support\ScopeRegistry;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
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
            ->when(ScopeRegistry::tabelasDisponiveis(), fn ($q) => $q->with('scopes'))
            ->orderBy('created_at', 'desc')
            ->get();
        $currentUser = $users->firstWhere('id', auth()->id());
        if ($currentUser) {
            $users = $users->reject(fn ($user) => $user->id === $currentUser->id)
                ->prepend($currentUser);
        }

        $moduleGroups = ModuleRegistry::grouped();

        // Escopo operacional (Sede/Cerrado): controla QUAIS dados o usuário vê
        // (dashboards, telefonia, etc.), separado do módulo que abre a tela.
        $scopeOptions = ScopeRegistry::tabelasDisponiveis() ? ScopeRegistry::options() : [];

        // Sem a tabela normalizada, a tela ainda lista os acessos (via modelo
        // legado) mas não pode gravar — o formulário é exibido bloqueado.
        $permissionsReady = LegacyModulePermissions::novaTabelaDisponivel();

        return view('admin.dashboard', compact('users', 'moduleGroups', 'permissionsReady', 'scopeOptions'));
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

            // Escopo operacional (Sede/Cerrado): quais dados o usuário enxerga.
            if (ScopeRegistry::tabelasDisponiveis()) {
                $this->syncScopes($user, (array) $request->input('scopes', []), $request->user());
            }
        } catch (RuntimeException $e) {
            return redirect()->route('admin.dashboard')->with('error', $e->getMessage());
        }

        return redirect()->route('admin.dashboard')
            ->with('success', "Acessos de {$user->name} atualizados com sucesso.");
    }

    /**
     * Sincroniza o escopo operacional (user_scopes) do usuário com o que veio
     * do formulário, validando contra o ScopeRegistry.
     *
     * @param  array<int, mixed>  $submitted
     */
    private function syncScopes(User $user, array $submitted, ?User $actor): void
    {
        $desired = array_values(array_unique(array_filter(
            array_map('strval', $submitted),
            fn (string $scope) => ScopeRegistry::exists($scope)
        )));

        $current = $user->scopes()->pluck('scope')->all();

        $remover = array_diff($current, $desired);
        if ($remover !== []) {
            $user->scopes()->whereIn('scope', $remover)->delete();
        }

        foreach (array_diff($desired, $current) as $scope) {
            $user->scopes()->create(['scope' => $scope, 'granted_by' => $actor?->id]);
        }

        $user->load('scopes');
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

        // Envia o link direto ao usuário por e-mail. Se falhar, o modal com o
        // link (copiar) segue como reserva para o admin enviar manualmente.
        $emailEnviado = false;
        try {
            Mail::to($user->email)->send(new CoreTiNotificationMail([
                'tipo_mensagem' => 'INFORMAÇÃO',
                'titulo' => 'Redefinição de senha do CoreTI',
                'destinatario' => $user->name ?: $user->email,
                'mensagem_principal' => 'Foi solicitada a redefinição da sua senha de acesso ao CoreTI. Clique no botão abaixo para criar uma nova senha.',
                'servico' => 'Acesso ao CoreTI',
                'status' => 'AÇÃO NECESSÁRIA',
                'orientacao' => 'Se você não reconhece esta solicitação, avise a equipe de Tecnologia da Informação.',
                'url_acao' => $resetLink,
                'texto_botao' => 'Redefinir minha senha',
            ]));
            $emailEnviado = true;
        } catch (\Throwable $e) {
            Log::warning('admin.reset-password.email_falhou', [
                'user_id' => $user->id,
                'erro' => $e->getMessage(),
            ]);
        }

        return redirect()->route('admin.dashboard')
            ->with('success', $emailEnviado
                ? "Reset de senha iniciado — link enviado por e-mail para {$user->email}."
                : 'Reset de senha iniciado. Não foi possível enviar o e-mail agora; copie o link abaixo e envie manualmente.')
            ->with('password_reset', [
                'email' => $user->email,
                'link' => $resetLink,
                'email_enviado' => $emailEnviado,
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
