<?php

namespace App\Services\Cmdb\Discovery\Connectors;

use App\Models\CmdbConnector;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;

class VCenterApiClient
{
    private ?string $sessionId = null;

    public function __construct(private readonly CmdbConnector $connector)
    {
    }

    public function about(): array
    {
        try {
            return $this->get('/api/vcenter/about');
        } catch (VCenterApiException $exception) {
            if ($exception->kind !== 'api' || $exception->getCode() !== 404) {
                throw $exception;
            }

            // Alguns vCenter 8.x não expõem /api/vcenter/about, mas mantêm
            // a versão/build disponíveis no endpoint do appliance.
            return $this->get('/api/appliance/system/version');
        }
    }

    public function list(string $resource, array $query = []): array
    {
        $response = $this->get('/api/vcenter/' . ltrim($resource, '/'), $query);

        return array_values((array) ($response['value'] ?? $response));
    }

    public function detail(string $resource, string $id): array
    {
        return $this->get('/api/vcenter/' . trim($resource, '/') . '/' . rawurlencode($id));
    }

    public function close(): void
    {
        if ($this->sessionId === null) {
            return;
        }

        try {
            $this->request()->delete('/api/session');
        } catch (\Throwable) {
            // The inventory result is more important than a failed logout call.
        } finally {
            $this->sessionId = null;
        }
    }

    public function baseUrl(): string
    {
        $host = trim((string) $this->connector->host);
        if ($host === '') {
            throw new VCenterApiException('URL do vCenter não configurada.', 'configuration');
        }

        if (! str_starts_with($host, 'https://') || str_contains($host, '@')) {
            throw new VCenterApiException('A URL do vCenter deve usar HTTPS e não pode conter credenciais.', 'configuration');
        }

        $parts = parse_url(rtrim($host, '/'));
        if (! is_array($parts) || empty($parts['host']) || ! empty($parts['user']) || ! empty($parts['pass'])) {
            throw new VCenterApiException('URL do vCenter inválida.', 'configuration');
        }

        return rtrim($host, '/');
    }

    private function get(string $path, array $query = []): array
    {
        try {
            $response = $this->request()->get($path, $query);
        } catch (\Throwable $exception) {
            throw new VCenterApiException('Não foi possível conectar ao vCenter.', 'connection', 0, $exception);
        }

        if ($response->status() === 401) {
            $this->sessionId = null;
            try {
                $response = $this->request()->get($path, $query);
            } catch (\Throwable $exception) {
                throw new VCenterApiException('Não foi possível conectar ao vCenter.', 'connection', 0, $exception);
            }
        }

        if ($response->status() === 403) {
            throw new VCenterApiException('O usuário do vCenter não possui permissão de leitura do inventário.', 'authorization', 403);
        }

        if ($response->failed()) {
            throw new VCenterApiException('O vCenter recusou a consulta do inventário.', 'api', $response->status());
        }

        $json = $response->json();
        if (! is_array($json)) {
            throw new VCenterApiException('Resposta inesperada recebida do vCenter.', 'api');
        }

        return $json;
    }

    private function request(): PendingRequest
    {
        $request = Http::baseUrl($this->baseUrl())
            ->acceptJson()
            ->timeout(max(1, (int) ($this->connector->timeout_seconds ?: Arr::get($this->connector->settings ?? [], 'timeout_seconds', 30))))
            ->withOptions(['verify' => $this->connector->verify_ssl ?? true]);

        if ($this->sessionId === null) {
            $username = trim((string) $this->connector->username);
            $encryptedPassword = $this->connector->password_encrypted;
            if ($username === '' || ! $encryptedPassword) {
                throw new VCenterApiException('Usuário e senha do vCenter não configurados.', 'configuration');
            }

            try {
                $password = Crypt::decryptString((string) $encryptedPassword);
            } catch (\Throwable $exception) {
                throw new VCenterApiException('A senha do vCenter não pôde ser descriptografada.', 'configuration', 0, $exception);
            }

            try {
                $session = $request->withBasicAuth($username, $password)->post('/api/session');
            } catch (\Throwable $exception) {
                throw new VCenterApiException('Não foi possível conectar ao vCenter.', 'connection', 0, $exception);
            }

            if ($session->status() === 401) {
                throw new VCenterApiException('Credenciais do vCenter inválidas.', 'authentication', 401);
            }

            if ($session->status() === 403) {
                throw new VCenterApiException('O usuário do vCenter não possui permissão para autenticar.', 'authorization', 403);
            }

            if ($session->failed()) {
                throw new VCenterApiException('Falha na autenticação com o vCenter.', 'authentication', $session->status());
            }

            $this->sessionId = is_string($session->json()) ? $session->json() : (string) $session->body();
            if ($this->sessionId === '') {
                throw new VCenterApiException('O vCenter não retornou uma sessão válida.', 'authentication');
            }
        }

        return $request->withHeaders(['vmware-api-session-id' => $this->sessionId]);
    }
}
