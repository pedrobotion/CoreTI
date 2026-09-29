<?php

namespace Tests\Feature\Insight;

use App\Services\Insight\InsightApiClient;
use App\Services\Insight\InsightApiException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

/**
 * A Insight API é de terceiro e compartilhada com a operação da Simpress.
 *
 * Estes testes existem para garantir que nenhum caminho do código consiga
 * disparar requisição sem passar pelo teto — inclusive o /login.
 */
class InsightRateLimitTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        RateLimiter::clear('insight:por-minuto');

        config([
            'insight.key' => 'chave-de-teste',
            'insight.secret' => 'segredo-de-teste',
            'insight.base_url' => 'https://insight.invalido/PortalAPI',
            // Sem pausa: o teste não precisa esperar de verdade.
            'insight.limites.intervalo_ms' => 0,
            'insight.limites.por_minuto' => 100,
            'insight.limites.por_dia' => 5,
            'insight.limites.espera_maxima_segundos' => 0,
        ]);

        Http::preventStrayRequests();
        Http::fake([
            '*/login' => Http::response(['access_token' => 'jwt-de-teste', 'expires_in' => 86400]),
            '*' => Http::response([]),
        ]);
    }

    private function cliente(): InsightApiClient
    {
        return new InsightApiClient();
    }

    public function test_login_conta_no_teto_como_qualquer_chamada(): void
    {
        // Segredo errado devolvendo 401 já martelou /login no passado; o
        // login precisa ser contado igual às demais.
        $this->cliente()->customers();

        // 1 login + 1 customers.
        $this->assertSame(2, $this->cliente()->consumoDeHoje()['usadas']);
    }

    public function test_teto_diario_interrompe_em_vez_de_esperar(): void
    {
        $cliente = $this->cliente();

        // 5 chamadas de teto: login + 4 consultas.
        $cliente->customers();
        $cliente->customers();
        $cliente->customers();
        $cliente->customers();

        $this->assertSame(5, $cliente->consumoDeHoje()['usadas']);
        $this->assertSame(0, $cliente->consumoDeHoje()['restantes']);

        $this->expectException(InsightApiException::class);
        $this->expectExceptionMessageMatches('/Teto diário de 5 chamadas/');
        $cliente->customers();
    }

    public function test_contador_do_dia_expira_e_nao_fica_preso(): void
    {
        /*
         | increment() numa chave inexistente cria entrada SEM validade e o
         | contador nunca zeraria — a API ficaria bloqueada para sempre.
         */
        $this->cliente()->customers();

        $chave = 'insight:chamadas-do-dia:'.now()->toDateString();
        $this->assertNotNull(Cache::get($chave));

        $this->travel(2)->days();
        $this->assertSame(0, $this->cliente()->consumoDeHoje()['usadas'], 'o dia novo começa zerado');
    }

    public function test_janela_do_minuto_cheia_falha_quando_a_espera_e_longa(): void
    {
        config(['insight.limites.por_minuto' => 2, 'insight.limites.espera_maxima_segundos' => 0]);

        $cliente = $this->cliente();
        $cliente->customers(); // login + customers = 2 batidas

        $this->expectException(InsightApiException::class);
        $this->expectExceptionMessageMatches('/por minuto/');
        $cliente->customers();
    }

    public function test_intervalo_minimo_separa_chamadas_coladas(): void
    {
        config(['insight.limites.intervalo_ms' => 150, 'insight.limites.por_dia' => 50]);

        $cliente = $this->cliente();
        $cliente->customers(); // aquece o marcador

        $inicio = microtime(true);
        $cliente->customers();
        $decorridoMs = (microtime(true) - $inicio) * 1000;

        $this->assertGreaterThanOrEqual(140, $decorridoMs, 'a chamada seguinte precisa respeitar o intervalo');
    }

    public function test_sem_credencial_nao_chega_a_sair_requisicao(): void
    {
        config(['insight.key' => null, 'insight.secret' => null]);

        $this->expectException(InsightApiException::class);

        try {
            $this->cliente()->customers();
        } finally {
            Http::assertNothingSent();
        }
    }
}
