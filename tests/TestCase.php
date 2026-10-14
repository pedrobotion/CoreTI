<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Http;

abstract class TestCase extends BaseTestCase
{
    /**
     * Nenhum teste fala com a internet.
     *
     * Em 29/09/2026 a suíte abriu quatro chamados REAIS no Jira da Cocari.
     * A causa: o .env de produção ganhou credencial de Jira naquele dia, os
     * testes herdaram essa configuração, e as classes que exercitam abertura
     * de chamado não falsificavam o HTTP em todos os métodos. A chamada saiu
     * de verdade — e chamado aberto não pode ser excluído, porque afeta os
     * indicadores da equipe.
     *
     * preventStrayRequests transforma esse acidente em falha de teste: uma
     * requisição não falsificada estoura na hora, com a URL no erro, em vez
     * de atingir um sistema de produção em silêncio.
     *
     * Quem precisa de HTTP declara o próprio Http::fake() — ele continua
     * valendo e tem precedência sobre este bloqueio.
     */
    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();
    }
}
