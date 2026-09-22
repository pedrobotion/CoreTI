<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

/**
 * Endpoints públicos que aceitam POST precisam de limite.
 *
 * Sem limite, /forgot-password enumera e-mails válidos e dispara e-mail em
 * massa contra terceiros; /register enche a tabela; /login permite
 * pulverização — muitos e-mails diferentes do mesmo IP, nenhum estourando o
 * limite por conta que o LoginRequest já aplica.
 */
class RateLimitTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        RateLimiter::clear('');
        $this->app['cache']->flush();
    }

    public function test_login_barra_pulverizacao_de_varios_emails_do_mesmo_ip(): void
    {
        // Cada e-mail é diferente, então o limite por conta nunca dispara.
        for ($i = 0; $i < 20; $i++) {
            $this->post('/login', [
                'email' => "alvo{$i}@cocari.com.br",
                'password' => 'tentativa',
            ]);
        }

        $this->post('/login', [
            'email' => 'alvo999@cocari.com.br',
            'password' => 'tentativa',
        ])->assertStatus(429);
    }

    public function test_forgot_password_tem_limite(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->post('/forgot-password', ['email' => "pessoa{$i}@cocari.com.br"]);
        }

        $this->post('/forgot-password', ['email' => 'outra@cocari.com.br'])
            ->assertStatus(429);
    }

    public function test_registro_tem_limite(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->post('/register', [
                'name' => "Teste {$i}",
                'email' => "novo{$i}@cocari.com.br",
                'password' => 'senha-de-teste-123',
                'password_confirmation' => 'senha-de-teste-123',
            ]);
        }

        $this->post('/register', [
            'name' => 'Excedente',
            'email' => 'excedente@cocari.com.br',
            'password' => 'senha-de-teste-123',
            'password_confirmation' => 'senha-de-teste-123',
        ])->assertStatus(429);
    }

    public function test_limite_por_conta_continua_valendo(): void
    {
        // A camada do LoginRequest não foi substituída pela do IP.
        $user = User::factory()->create([
            'email' => 'alvo@cocari.com.br',
            'is_active' => true,
        ]);

        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', ['email' => $user->email, 'password' => 'errada']);
        }

        $resposta = $this->post('/login', ['email' => $user->email, 'password' => 'errada']);

        // O LoginRequest devolve erro de validação com a mensagem de bloqueio.
        $resposta->assertSessionHasErrors('email');
    }
}
