<?php

namespace App\Services\Insight;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Cliente da EKM Insight API (portal HP Smart Device Services / Simpress).
 *
 * Autenticação em dois passos, como o manual descreve:
 *   1. POST /login com Basic base64(chave:segredo) → devolve um JWT
 *   2. as demais chamadas vão com Authorization: Bearer <jwt>
 *
 * O JWT vale 24h. Guardá-lo em cache não é otimização: o manual recomenda
 * reaproveitar até expirar, e uma sincronização de inventário faz dezenas de
 * chamadas — logar a cada uma seria gerar dezenas de sessões por minuto.
 */
class InsightApiClient
{
    private const CHAVE_CACHE = 'insight:jwt';

    public function configurado(): bool
    {
        return filled(config('insight.key')) && filled(config('insight.secret'));
    }

    // ------------------------------------------------------------------
    // Recursos
    // ------------------------------------------------------------------

    /** @return array<int, array<string, mixed>> */
    public function customers(): array
    {
        return $this->get('/api/customers');
    }

    /** @return array<int, array<string, mixed>> */
    public function devices(int $customerId, bool $camposEstendidos = true): array
    {
        return $this->get('/api/devices', [
            'customerId' => $customerId,
            'includeExtendedFields' => $camposEstendidos ? 'true' : 'false',
        ]);
    }

    /**
     * Busca avançada de dispositivos.
     *
     * @param  array<int, string>  $termos  ex.: ['serial:~BCF24', 'monitorStatus:Y']
     * @return array<int, array<string, mixed>>
     */
    public function buscarDevices(array $termos, int $maxResultados = 500): array
    {
        return $this->get('/api/devices/search', [
            'q' => $termos,
            'maxResults' => $maxResultados,
            'includeExtendedFields' => 'true',
        ]);
    }

    /**
     * Consumíveis detalhados de todos os equipamentos do cliente.
     *
     * Preferido sobre niveisDeSuprimento(): traz o consumableId, que é o que
     * o POST de pedido de toner exige. O outro devolve só preto/ciano/
     * magenta/amarelo/resíduo, sem identificador.
     *
     * @return array<int, array{deviceId: int, consumables: array}>
     */
    public function consumiveisDoCliente(int $customerId): array
    {
        return $this->get('/api/devices/consumables', ['customerId' => $customerId]);
    }

    /** Níveis de suprimento resumidos (sem consumableId). */
    public function niveisDeSuprimento(int $customerId): array
    {
        return $this->get('/api/devices/supplylevels', ['customerId' => $customerId]);
    }

    /**
     * Contadores de faturamento do cliente.
     *
     * Sem billingDate a API devolve o conjunto mais recente; com data, o
     * fechamento daquela competência — que é o que se confere contra a nota.
     */
    public function contadores(int $customerId, ?string $billingDate = null): array
    {
        return $this->get("/api/devices/meters/{$customerId}", array_filter([
            'billingDate' => $billingDate,
        ]));
    }

    /** Volume impresso por um equipamento entre duas datas (Y-m-d). */
    public function consumoDoPeriodo(int $deviceId, string $inicio, string $fim): array
    {
        return $this->get("/api/devices/{$deviceId}/meters/usage", [
            'startDate' => $inicio,
            'endDate' => $fim,
            'includeStartAndEndMeters' => 'true',
        ]);
    }

    public function alertasAtuais(int $deviceId): array
    {
        return $this->get("/api/devices/{$deviceId}/alerts/current");
    }

    public function consumiveisDoDevice(int $deviceId): array
    {
        return $this->get("/api/devices/{$deviceId}/consumables");
    }

    /** @param  array<string, mixed>  $filtros */
    public function pedidosDeConsumivel(array $filtros = []): array
    {
        return $this->get('/api/consumable-requests', $filtros);
    }

    /**
     * Abre um pedido de consumível.
     *
     * Exige o papel DATA_WRITER no usuário de API — por isso fica isolado dos
     * métodos de leitura, que funcionam só com DATA_READER.
     */
    public function abrirPedidoDeConsumivel(int $deviceId, int $consumableId, ?string $comentario = null, ?string $refExterna = null): array
    {
        return $this->post("/api/consumable-requests/{$deviceId}/{$consumableId}", array_filter([
            'comment' => $comentario,
            'externalRef' => $refExterna,
        ]));
    }

    // ------------------------------------------------------------------
    // Diagnóstico
    // ------------------------------------------------------------------

    /**
     * Confere credencial e acesso, sem alterar nada do outro lado.
     *
     * @return array{ok: bool, mensagem: string, clientes?: array<int, array{id: int, nome: string, status: string}>}
     */
    public function testarConexao(): array
    {
        if (! $this->configurado()) {
            return ['ok' => false, 'mensagem' => 'Chave e segredo não configurados no .env.'];
        }

        try {
            $this->esquecerToken();
            $this->token();

            $clientes = collect($this->customers())
                ->map(fn (array $c) => [
                    'id' => (int) ($c['customerId'] ?? 0),
                    'nome' => (string) ($c['name'] ?? '—'),
                    'status' => (string) ($c['status'] ?? '—'),
                ])
                ->values()
                ->all();

            return [
                'ok' => true,
                'mensagem' => sprintf('Autenticado em %s. %d cliente(s) visível(is) para esta credencial.',
                    config('insight.base_url'), count($clientes)),
                'clientes' => $clientes,
            ];
        } catch (InsightApiException $e) {
            return ['ok' => false, 'mensagem' => $e->getMessage()];
        } catch (\Throwable $e) {
            return ['ok' => false, 'mensagem' => 'Falha ao falar com a Insight API: '.$e->getMessage()];
        }
    }

    // ------------------------------------------------------------------
    // Transporte
    // ------------------------------------------------------------------

    /** @param  array<string, mixed>  $parametros */
    private function get(string $caminho, array $parametros = []): array
    {
        return $this->chamar('get', $caminho, $parametros);
    }

    /** @param  array<string, mixed>  $corpo */
    private function post(string $caminho, array $corpo = []): array
    {
        return $this->chamar('post', $caminho, $corpo);
    }

    /**
     * Executa a chamada já autenticada.
     *
     * Um 401 aqui significa token revogado ou expirado antes da hora — nesse
     * caso vale uma segunda tentativa com token novo. Repetir mais de uma vez
     * seria insistir contra credencial inválida.
     *
     * @param  array<string, mixed>  $dados
     */
    private function chamar(string $metodo, string $caminho, array $dados, bool $jaRepetiu = false): array
    {
        // O token tem de sair antes do contador: autenticar também é chamada.
        $token = $this->token();

        $this->respeitarLimites($caminho);

        $resposta = $this->requisicao()
            ->withToken($token)
            ->{$metodo}($this->url($caminho), $dados);

        if ($resposta->status() === 401 && ! $jaRepetiu) {
            $this->esquecerToken();

            return $this->chamar($metodo, $caminho, $dados, true);
        }

        if ($resposta->failed()) {
            throw InsightApiException::doHttp($resposta->status(), $resposta->body(), $caminho);
        }

        $json = $resposta->json();

        return is_array($json) ? $json : [];
    }

    private function token(): string
    {
        $cacheado = Cache::get(self::CHAVE_CACHE);

        if (is_string($cacheado) && $cacheado !== '') {
            return $cacheado;
        }

        return $this->autenticar();
    }

    private function autenticar(): string
    {
        if (! $this->configurado()) {
            throw InsightApiException::semCredencial();
        }

        // O login conta no teto como qualquer outra chamada. Credencial
        // recusada devolve 401 e o cliente pede token de novo: sem isso, um
        // segredo errado viraria martelada em /login.
        $this->respeitarLimites('/login');

        $basic = base64_encode(config('insight.key').':'.config('insight.secret'));

        $resposta = $this->requisicao()
            ->withHeaders(['Authorization' => 'Basic '.$basic])
            ->post($this->url('/login'));

        if ($resposta->failed()) {
            // Nunca registrar a credencial no log; só o resultado.
            Log::warning('insight.login_recusado', ['status' => $resposta->status()]);

            throw InsightApiException::doHttp($resposta->status(), $resposta->body(), '/login');
        }

        $token = (string) ($resposta->json('access_token') ?? '');
        $validade = (int) ($resposta->json('expires_in') ?? 86400);

        if ($token === '') {
            throw new InsightApiException('A Insight API respondeu ao login sem access_token.');
        }

        $margem = (int) config('insight.token_margem_segundos', 600);
        Cache::put(self::CHAVE_CACHE, $token, max($validade - $margem, 60));

        return $token;
    }

    public function esquecerToken(): void
    {
        Cache::forget(self::CHAVE_CACHE);
    }

    // ------------------------------------------------------------------
    // Teto de requisições
    // ------------------------------------------------------------------

    private const CHAVE_ULTIMA = 'insight:ultima-chamada';

    private const CHAVE_DIA = 'insight:chamadas-do-dia';

    /**
     * Segura a chamada antes de ela sair.
     *
     * Três camadas, da mais branda para a mais dura: um intervalo mínimo que
     * desmancha rajada, um teto por minuto que espera a janela abrir, e um
     * teto diário que simplesmente interrompe.
     *
     * Os contadores vivem no cache de propósito. Em memória do processo, dois
     * comandos simultâneos teriam cada um o seu teto e o volume dobraria —
     * que é exatamente o que não pode acontecer com API de terceiro.
     */
    private function respeitarLimites(string $caminho): void
    {
        $limites = (array) config('insight.limites', []);

        $this->cobrarTetoDiario((int) ($limites['por_dia'] ?? 300), $caminho);
        $this->esperarJanelaDoMinuto(
            (int) ($limites['por_minuto'] ?? 20),
            (int) ($limites['espera_maxima_segundos'] ?? 90)
        );
        $this->aguardarIntervalo((int) ($limites['intervalo_ms'] ?? 400));

        Cache::put(self::CHAVE_ULTIMA, microtime(true), now()->addMinutes(10));
    }

    /** Disjuntor: estourou o dia, não sai mais nada. */
    private function cobrarTetoDiario(int $teto, string $caminho): void
    {
        if ($teto <= 0) {
            return;
        }

        $chave = self::CHAVE_DIA.':'.now()->toDateString();
        $usadas = (int) Cache::get($chave, 0);

        if ($usadas >= $teto) {
            Log::warning('Insight API: teto diário atingido, chamada recusada.', [
                'caminho' => $caminho,
                'usadas' => $usadas,
                'teto' => $teto,
            ]);

            throw InsightApiException::tetoDiario($teto);
        }

        // add() cria a chave com validade; increment() sozinho criaria uma
        // chave sem expiração e o contador nunca zeraria.
        Cache::add($chave, 0, now()->endOfDay()->addMinutes(5));
        Cache::increment($chave);
    }

    private function esperarJanelaDoMinuto(int $teto, int $esperaMaxima): void
    {
        if ($teto <= 0) {
            return;
        }

        $chave = 'insight:por-minuto';

        if (RateLimiter::remaining($chave, $teto) > 0) {
            RateLimiter::hit($chave, 60);

            return;
        }

        $faltam = RateLimiter::availableIn($chave);

        if ($faltam > $esperaMaxima) {
            throw InsightApiException::esperaLonga($faltam, $esperaMaxima);
        }

        Log::info('Insight API: janela do minuto cheia, aguardando.', ['segundos' => $faltam]);
        sleep(max($faltam, 1));
        RateLimiter::hit($chave, 60);
    }

    /** Desmancha rajada: nunca duas chamadas coladas. */
    private function aguardarIntervalo(int $intervaloMs): void
    {
        if ($intervaloMs <= 0) {
            return;
        }

        $ultima = Cache::get(self::CHAVE_ULTIMA);

        if (! is_float($ultima) && ! is_int($ultima)) {
            return;
        }

        $decorridoMs = (microtime(true) - (float) $ultima) * 1000;

        if ($decorridoMs < $intervaloMs) {
            usleep((int) (($intervaloMs - $decorridoMs) * 1000));
        }
    }

    /**
     * Quantas chamadas já saíram hoje e quanto resta do teto.
     *
     * @return array{usadas:int, teto:int, restantes:int}
     */
    public function consumoDeHoje(): array
    {
        $teto = (int) config('insight.limites.por_dia', 300);
        $usadas = (int) Cache::get(self::CHAVE_DIA.':'.now()->toDateString(), 0);

        return [
            'usadas' => $usadas,
            'teto' => $teto,
            'restantes' => max($teto - $usadas, 0),
        ];
    }

    private function requisicao(): PendingRequest
    {
        return Http::timeout((int) config('insight.timeout', 30))
            ->acceptJson()
            ->asJson();
    }

    private function url(string $caminho): string
    {
        return config('insight.base_url').'/'.ltrim($caminho, '/');
    }
}
