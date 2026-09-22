<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\ConfirmablePasswordController;
use App\Http\Controllers\Auth\EmailVerificationNotificationController;
use App\Http\Controllers\Auth\EmailVerificationPromptController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\Auth\VerifyEmailController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('register', [RegisteredUserController::class, 'create'])
        ->name('register');

    // Cadastro cria usuário PENDENTE, mas segue sendo escrita pública:
    // sem limite, dá para encher a tabela e sondar e-mails existentes pela
    // mensagem de validação de unicidade.
    Route::post('register', [RegisteredUserController::class, 'store'])
        ->middleware('throttle:5,1,registro:');

    Route::get('login', [AuthenticatedSessionController::class, 'create'])
        ->name('login');

    /*
     | O terceiro argumento do throttle é o PREFIXO da chave, e ele é
     | obrigatório aqui.
     |
     | O Laravel monta a chave com domínio + IP — a URI não entra. Sem prefixo,
     | todas as rotas limitadas dividem UM contador por IP, e quem manda passa
     | a ser o limite mais restrito: quatro tentativas de login gastavam a cota
     | de 5 do reset de senha, e a pessoa recebia 429 na primeira tentativa de
     | trocar a senha.
     */

    /*
     | Duas camadas de propósito.
     |
     | O LoginRequest já limita por e-mail+IP (5 tentativas), o que barra
     | ataque contra uma conta específica. Não barra pulverização: muitos
     | e-mails diferentes do mesmo IP, nenhum estourando o limite individual.
     | O throttle por IP fecha essa brecha sem atrapalhar quem erra a senha.
     */
    Route::post('login', [AuthenticatedSessionController::class, 'store'])
        ->middleware('throttle:20,1,login:');

    Route::get('forgot-password', [PasswordResetLinkController::class, 'create'])
        ->name('password.request');

    // Sem limite, este endpoint enumera e-mails válidos e serve de
    // disparador de e-mail em massa contra terceiros.
    Route::post('forgot-password', [PasswordResetLinkController::class, 'store'])
        ->middleware('throttle:5,1,senha-link:')
        ->name('password.email');

    Route::get('reset-password/{token}', [NewPasswordController::class, 'create'])
        ->name('password.reset');

    // O token é longo e aleatório, mas tentativa ilimitada não deve existir
    // em endpoint que troca senha.
    Route::post('reset-password', [NewPasswordController::class, 'store'])
        ->middleware('throttle:5,1,senha-nova:')
        ->name('password.store');
});


Route::middleware(['auth', 'active'])->group(function () {
    Route::get('verify-email', EmailVerificationPromptController::class)
        ->name('verification.notice');

    Route::get('verify-email/{id}/{hash}', VerifyEmailController::class)
        ->middleware(['signed', 'throttle:6,1,verifica-email:'])
        ->name('verification.verify');

    Route::post('email/verification-notification', [EmailVerificationNotificationController::class, 'store'])
        ->middleware('throttle:6,1,reenvia-verificacao:')
        ->name('verification.send');

    Route::get('confirm-password', [ConfirmablePasswordController::class, 'show'])
        ->name('password.confirm');

    // Confirmação de senha é adivinhação de senha de quem já está logado —
    // relevante em sessão sequestrada.
    Route::post('confirm-password', [ConfirmablePasswordController::class, 'store'])
        ->middleware('throttle:5,1,senha-confirma:');

    Route::put('password', [PasswordController::class, 'update'])->name('password.update');
});

Route::middleware('auth')->group(function () {
    Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])
        ->name('logout');
});
