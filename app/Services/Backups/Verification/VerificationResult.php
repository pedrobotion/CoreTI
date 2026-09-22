<?php

namespace App\Services\Backups\Verification;

use App\Support\Backups\BackupStatus;
use Illuminate\Support\Carbon;

/**
 * Resultado bruto de uma estratégia de verificação.
 *
 * A estratégia relata o que observou; quem decide se isso é "atrasado" ou
 * "tamanho anormal" é o serviço de verificação, que conhece os limites do
 * alvo. Assim uma estratégia nova não precisa reimplementar as regras.
 */
class VerificationResult
{
    public function __construct(
        public string $status = BackupStatus::OK,
        public bool $arquivoExiste = false,
        public ?int $tamanhoBytes = null,
        public ?Carbon $modificadoEm = null,
        public ?string $detalhe = null,
        public ?string $erroConexao = null,
        public int $duracaoMs = 0,
        /** @var array<string, mixed> evidências específicas da tecnologia */
        public array $evidencias = [],
    ) {
    }

    public static function falha(string $status, string $detalhe, int $duracaoMs = 0, ?string $erroConexao = null): self
    {
        return new self(
            status: $status,
            detalhe: $detalhe,
            erroConexao: $erroConexao,
            duracaoMs: $duracaoMs,
        );
    }

    public function observouArquivo(): bool
    {
        return $this->arquivoExiste && $this->modificadoEm !== null;
    }
}
