<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Cada rota limitada precisa do próprio contador.
 *
 * O Laravel monta a chave do throttle com domínio + IP — a URI não entra. Sem
 * prefixo, todas as rotas limitadas dividem UM contador, e quem manda passa a
 * ser o limite mais restrito: quatro tentativas de login gastavam a cota de 5
 * do reset de senha, e a pessoa recebia 429 na primeira tentativa de trocar a
 * senha pelo link do e-mail.
 */
class ThrottleIsoladoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->app['cache']->flush();
    }

    public function test_tentativas_de_login_nao_gastam_a_cota_do_reset_de_senha(): void
    {
        // Exatamente o cenário que apareceu no log do nginx.
        for ($i = 0; $i < 8; $i++) {
            $this->post('/login', ['email' => 'alguem@cocari.com.br', 'password' => 'errada']);
        }

        // O reset ainda deve aceitar: o contador dele não foi tocado.
        $resposta = $this->post('/reset-password', [
            'token' => 'token-invalido',
            'email' => 'alguem@cocari.com.br',
            'password' => 'nova-senha-123',
            'password_confirmation' => 'nova-senha-123',
        ]);

        $this->assertNotSame(429, $resposta->getStatusCode(), 'O login não pode consumir a cota do reset.');
    }

    public function test_cada_rota_mantem_o_proprio_limite(): void
    {
        // 5 por minuto no reset: a sexta é barrada, e só ela.
        for ($i = 0; $i < 5; $i++) {
            $this->post('/reset-password', [
                'token' => 'x', 'email' => 'a@cocari.com.br',
                'password' => 'senha-de-teste-123', 'password_confirmation' => 'senha-de-teste-123',
            ]);
        }

        $this->post('/reset-password', [
            'token' => 'x', 'email' => 'a@cocari.com.br',
            'password' => 'senha-de-teste-123', 'password_confirmation' => 'senha-de-teste-123',
        ])->assertStatus(429);

        // O registro continua livre: contador separado.
        $this->post('/register', [
            'name' => 'Teste', 'email' => 'novo@cocari.com.br',
            'password' => 'senha-de-teste-123', 'password_confirmation' => 'senha-de-teste-123',
        ])->assertStatus(302);
    }
}
