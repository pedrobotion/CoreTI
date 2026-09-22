<?php

namespace App\Services\Backups\Verification;

use App\Models\CmdbBackupTarget;
use App\Services\Backups\SshRunner;
use App\Support\Backups\BackupStatus;
use Illuminate\Support\Carbon;

/**
 * Estratégia genérica: confere o artefato de backup no sistema de arquivos.
 *
 * Serve para qualquer tecnologia que produza um arquivo — é o caso do
 * probkup do Progress, de dumps de MySQL/Postgres e de .bak do SQL Server.
 * Um único stat remoto responde existência, tamanho e data.
 */
class SshFileVerificationStrategy implements BackupVerificationStrategy
{
    public function __construct(protected readonly SshRunner $ssh)
    {
    }

    public function chave(): string
    {
        return 'ssh_arquivo';
    }

    public function rotulo(): string
    {
        return 'Arquivo via SSH';
    }

    public function tiposSuportados(): array
    {
        return ['progress', 'mysql', 'postgres', 'sqlserver', 'arquivo', 'outro'];
    }

    public function verificar(CmdbBackupTarget $target): VerificationResult
    {
        $caminho = (string) $target->arquivo;

        if (! $this->ssh->caminhoValido($caminho)) {
            return VerificationResult::falha(
                BackupStatus::ERRO_VERIFICACAO,
                'Caminho do arquivo inválido: deve ser absoluto e sem quebras de linha.'
            );
        }

        // A montagem do comando (com ou sem wrapper) fica no runner, que
        // escapa o caminho. Nada vindo do banco é interpretado como comando.
        $resultado = $this->ssh->executar($target, $this->ssh->comandoDeOperacao('stat', $caminho));

        if (! $resultado['ok']) {
            return $this->interpretarFalha($resultado, $target);
        }

        if (! preg_match('/^(\d+)\|(\d+)$/', $resultado['saida'], $partes)) {
            return VerificationResult::falha(
                BackupStatus::ERRO_VERIFICACAO,
                'Resposta inesperada do stat: '.mb_substr($resultado['saida'], 0, 200),
                $resultado['duracao_ms']
            );
        }

        return new VerificationResult(
            status: BackupStatus::OK,
            arquivoExiste: true,
            tamanhoBytes: (int) $partes[1],
            modificadoEm: Carbon::createFromTimestamp((int) $partes[2]),
            duracaoMs: $resultado['duracao_ms'],
        );
    }

    /**
     * O stat falha tanto por arquivo ausente quanto por SSH recusado; separar
     * os dois é o que evita abrir chamado de backup quando o problema é acesso.
     */
    protected function interpretarFalha(array $resultado, CmdbBackupTarget $target): VerificationResult
    {
        $erro = mb_strtolower($resultado['erro']);

        $arquivoAusente = str_contains($erro, 'no such file')
            || str_contains($erro, 'cannot stat')
            || str_contains($erro, 'não é possível obter estado');

        if ($arquivoAusente) {
            return new VerificationResult(
                status: BackupStatus::ARQUIVO_AUSENTE,
                arquivoExiste: false,
                detalhe: sprintf('Arquivo %s não encontrado em %s.', $target->arquivo, $target->enderecoSsh()),
                duracaoMs: $resultado['duracao_ms'],
            );
        }

        return VerificationResult::falha(
            $resultado['status'],
            $this->ssh->mensagemAmigavel($resultado['status'], $resultado['erro'], $target),
            $resultado['duracao_ms'],
            mb_substr($resultado['erro'], 0, 1000),
        );
    }
}
