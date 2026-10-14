<?php

namespace App\Services\Certificados;

use Illuminate\Support\Carbon;

/**
 * Lê o certificado TLS que um host está realmente servindo.
 *
 * Pela conexão, não pelo arquivo: é o que o navegador do usuário vê. Assim
 * pega certificado trocado por fora, vhost apontando para o fullchain antigo e
 * qualquer host externo — não só os arquivos deste servidor.
 */
class CertificateReader
{
    public function __construct(private readonly int $timeout = 10) {}

    /**
     * @return array{ok:bool, expira_em:?Carbon, fingerprint:?string, subject:?string, erro:?string}
     */
    public function ler(string $host, int $porta = 443): array
    {
        $contexto = stream_context_create(['ssl' => [
            'capture_peer_cert' => true,
            // Queremos ler o certificado mesmo que a cadeia esteja incompleta
            // ou o nome não bata: o objetivo é inspecionar, não confiar.
            'verify_peer' => false,
            'verify_peer_name' => false,
            'SNI_enabled' => true,
            'peer_name' => $host,
        ]]);

        $cliente = @stream_socket_client(
            "ssl://{$host}:{$porta}",
            $errno,
            $errstr,
            $this->timeout,
            STREAM_CLIENT_CONNECT,
            $contexto
        );

        if ($cliente === false) {
            return $this->falha("Não foi possível conectar em {$host}:{$porta} — {$errstr}");
        }

        $params = stream_context_get_params($cliente);
        fclose($cliente);

        $cert = $params['options']['ssl']['peer_certificate'] ?? null;

        if ($cert === null) {
            return $this->falha("Conectou em {$host}:{$porta} mas não recebeu certificado.");
        }

        $dados = openssl_x509_parse($cert);

        if ($dados === false || ! isset($dados['validTo_time_t'])) {
            return $this->falha("Certificado de {$host} não pôde ser interpretado.");
        }

        openssl_x509_export($cert, $pem);

        return [
            'ok' => true,
            'expira_em' => Carbon::createFromTimestampUTC($dados['validTo_time_t']),
            'fingerprint' => openssl_x509_fingerprint($cert, 'sha256') ?: null,
            'subject' => $dados['subject']['CN'] ?? $host,
            'erro' => null,
        ];
    }

    /** @return array{ok:bool, expira_em:null, fingerprint:null, subject:null, erro:string} */
    private function falha(string $mensagem): array
    {
        return ['ok' => false, 'expira_em' => null, 'fingerprint' => null, 'subject' => null, 'erro' => $mensagem];
    }
}
