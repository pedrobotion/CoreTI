<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\View\View;
use Throwable;

class PasswordResetLinkController extends Controller
{
    /**
     * Display the password reset link request view.
     */
    public function create(): View
    {
        return view('auth.forgot-password');
    }

    /**
     * Handle an incoming password reset link request.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
        ]);

        if (config('mail.default') === 'log') {
            return $this->createLocalResetLink($request);
        }

        // We will send the password reset link to this user. Once we have attempted
        // to send the link, we will examine the response then see the message we
        // need to show to the user. Finally, we'll send out a proper response.
        try {
            $status = Password::sendResetLink(
                $request->only('email')
            );
        } catch (Throwable) {
            return back()->withInput($request->only('email'))
                ->withErrors([
                    'email' => 'Não foi possível enviar o link de redefinição agora. Verifique a configuração de e-mail do sistema.',
                ]);
        }

        return $status == Password::RESET_LINK_SENT
                    ? back()->with('status', __($status))
                    : back()->withInput($request->only('email'))
                        ->withErrors(['email' => __($status)]);
    }

    private function createLocalResetLink(Request $request): RedirectResponse
    {
        $user = User::where('email', $request->string('email'))->first();

        if (! $user) {
            return back()->withInput($request->only('email'))
                ->withErrors(['email' => __(Password::INVALID_USER)]);
        }

        $token = Password::broker()->createToken($user);
        $resetLink = route('password.reset', [
            'token' => $token,
            'email' => $user->email,
        ]);

        return back()
            ->with('status', 'Link de redefinição gerado com sucesso.')
            ->with('password_reset_link', $resetLink);
    }
}
