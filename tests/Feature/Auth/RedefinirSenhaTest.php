<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class RedefinirSenhaTest extends TestCase
{
    use RefreshDatabase;

    public function test_link_de_redefinicao_e_enviado(): void
    {
        Notification::fake();

        $user = User::factory()->create(['email' => 'pessoa@cocari.com.br', 'is_active' => true]);

        $this->post('/forgot-password', ['email' => $user->email])
            ->assertSessionHasNoErrors();

        Notification::assertSentTo($user, ResetPassword::class);
    }

    public function test_tela_do_link_abre(): void
    {
        Notification::fake();

        $user = User::factory()->create(['email' => 'pessoa@cocari.com.br', 'is_active' => true]);
        $this->post('/forgot-password', ['email' => $user->email]);

        Notification::assertSentTo($user, ResetPassword::class, function ($notification) {
            $this->get(route('password.reset', $notification->token))->assertOk();

            return true;
        });
    }

    public function test_senha_e_redefinida_com_o_token_do_email(): void
    {
        Notification::fake();

        $user = User::factory()->create(['email' => 'pessoa@cocari.com.br', 'is_active' => true]);
        $this->post('/forgot-password', ['email' => $user->email]);

        Notification::assertSentTo($user, ResetPassword::class, function ($notification) use ($user) {
            $resposta = $this->post('/reset-password', [
                'token' => $notification->token,
                'email' => $user->email,
                'password' => 'nova-senha-forte-123',
                'password_confirmation' => 'nova-senha-forte-123',
            ]);

            $resposta->assertSessionHasNoErrors();
            $resposta->assertRedirect(route('login'));

            return true;
        });
    }
}
