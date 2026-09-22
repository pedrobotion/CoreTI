<?php

namespace App\Services\Backups;

use App\Models\CmdbBackupCheck;
use App\Models\CmdbBackupTarget;
use App\Models\CmdbDatabaseBackup;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;

/**
 * Confere o arquivo de backup no servidor de origem, por SSH.
 *
 * Usa o binário ssh do sistema e as chaves já presentes no servidor, em vez de
 * acrescentar uma dependência de composer — o agendador roda como root, que é
 * quem detém as chaves em /root/.ssh.
 *
 * O comando remoto é somente leitura (stat). Nada é executado no banco.
 */
class BackupSshVerificationService
{
    public function __construct(private readonly DatabaseBackupJiraService $jira)
    {
    }

    public function verificar(CmdbBackupTarget $target): CmdbBackupCheck
    {
        $resultado = $this->lerArquivoRemoto($target);
        $agora = now();

        $status = $resultado['status'];
        $idadeHoras = null;

        if ($resultado['modificado_em'] instanceof Carbon) {
            $idadeHoras = (int) $resultado['modificado_em']->diffInHours($agora);
        }

        // Ordem importa: ausente > velho > pequeno. Só faz sentido conferir
        // tamanho de um arquivo que existe e está atualizado.
        if ($status === CmdbBackupTarget::STATUS_OK) {
            $maxIdade = $target->idadeMaximaHoras();
            $minTamanho = $target->tamanhoMinimoBytes();

            if ($idadeHoras !== null && $idadeHoras > $maxIdade) {
                $status = CmdbBackupTarget::STATUS_VELHO;
                $resultado['detalhe'] = sprintf(
                    'Arquivo tem %dh, acima do limite de %dh.',
                    $idadeHoras,
                    $maxIdade
                );
            } elseif ($minTamanho !== null && $resultado['tamanho'] !== null && $resultado['tamanho'] < $minTamanho) {
                $status = CmdbBackupTarget::STATUS_PEQUENO;
                $resultado['detalhe'] = sprintf(
                    'Arquivo com %s, abaixo do mínimo esperado de %s.',
                    $this->humano($resultado['tamanho']),
                    $this->humano($minTamanho)
                );
            }
        }

        $divergencia = $this->avaliarDivergencia($target, $status, $resultado['modificado_em']);

        $check = CmdbBackupCheck::create([
            'target_id' => $target->id,
            'executado_em' => $agora,
            'status' => $status,
            'detalhe' => $resultado['detalhe'],
            'arquivo_existe' => $resultado['existe'],
            'arquivo_tamanho_bytes' => $resultado['tamanho'],
            'arquivo_modificado_em' => $resultado['modificado_em'],
            'idade_horas' => $idadeHoras,
            'divergente' => $divergencia !== null,
            'divergencia_detalhe' => $divergencia,
        ]);

        $target->update([
            'last_checked_at' => $agora,
            'last_status' => $status,
        ]);

        return $check;
    }

    /**
     * Executa o stat remoto.
     *
     * @return array{status: string, existe: bool, tamanho: ?int, modificado_em: ?Carbon, detalhe: ?string}
     */
    private function lerArquivoRemoto(CmdbBackupTarget $target): array
    {
        $vazio = ['existe' => false, 'tamanho' => null, 'modificado_em' => null];

        if (! $this->destinoValido($target)) {
            return $vazio + [
                'status' => CmdbBackupTarget::STATUS_INACESSIVEL,
                'detalhe' => 'Host ou caminho do arquivo com formato inválido.',
            ];
        }

        // %s = tamanho em bytes, %Y = mtime em epoch.
        $comandoRemoto = 'stat -c %s\|%Y '.escapeshellarg($target->arquivo);

        $argumentos = [
            'ssh',
            '-o', 'BatchMode=yes',
            '-o', 'StrictHostKeyChecking=accept-new',
            '-o', 'ConnectTimeout='.(int) config('backups.ssh.connect_timeout', 10),
            '-p', (string) $target->portaSsh(),
        ];

        if ($chave = config('backups.ssh.identity_file')) {
            $argumentos[] = '-i';
            $argumentos[] = (string) $chave;
        }

        $argumentos[] = $target->usuarioSsh().'@'.$target->enderecoSsh();
        $argumentos[] = '--';
        $argumentos[] = $comandoRemoto;

        $processo = new Process($argumentos);
        $processo->setTimeout((float) config('backups.ssh.timeout', 30));

        try {
            $processo->run();
        } catch (\Throwable $e) {
            Log::error('backup.ssh.excecao', ['target' => $target->id, 'erro' => $e->getMessage()]);

            return $vazio + [
                'status' => CmdbBackupTarget::STATUS_INACESSIVEL,
                'detalhe' => 'Falha ao executar ssh: '.$e->getMessage(),
            ];
        }

        $saida = trim($processo->getOutput());
        $erro = trim($processo->getErrorOutput());

        if (! $processo->isSuccessful()) {
            // stat devolve erro tanto para arquivo ausente quanto para falha de
            // conexão; separamos pela mensagem para não alarmar errado.
            $ausente = str_contains(mb_strtolower($erro), 'no such file')
                || str_contains(mb_strtolower($erro), 'cannot stat');

            return $vazio + [
                'status' => $ausente ? CmdbBackupTarget::STATUS_AUSENTE : CmdbBackupTarget::STATUS_INACESSIVEL,
                'detalhe' => mb_substr($erro !== '' ? $erro : 'Comando remoto falhou.', 0, 500),
            ];
        }

        if (! preg_match('/^(\d+)\|(\d+)$/', $saida, $partes)) {
            return $vazio + [
                'status' => CmdbBackupTarget::STATUS_INACESSIVEL,
                'detalhe' => 'Resposta inesperada do stat: '.mb_substr($saida, 0, 200),
            ];
        }

        return [
            'status' => CmdbBackupTarget::STATUS_OK,
            'existe' => true,
            'tamanho' => (int) $partes[1],
            'modificado_em' => Carbon::createFromTimestamp((int) $partes[2]),
            'detalhe' => null,
        ];
    }

    /**
     * O achado que justifica a verificação ativa: o agente reportou sucesso
     * recente, mas o arquivo no disco não sustenta a afirmação.
     */
    private function avaliarDivergencia(CmdbBackupTarget $target, string $status, ?Carbon $modificadoEm): ?string
    {
        $ultimoSucesso = CmdbDatabaseBackup::doPar($target->host, $target->database_name)
            ->sucessos()
            ->latest('data_backup')
            ->first();

        if ($ultimoSucesso === null) {
            return null;
        }

        $reportadoEm = $ultimoSucesso->data_backup ?? $ultimoSucesso->received_at;

        if ($reportadoEm === null || $reportadoEm->lessThan(now()->subHours($target->idadeMaximaHoras()))) {
            // O próprio agente já está em silêncio; isso é outro alerta, não
            // divergência entre as duas fontes.
            return null;
        }

        if ($status === CmdbBackupTarget::STATUS_AUSENTE) {
            return sprintf(
                'Agente reportou sucesso em %s, mas o arquivo não existe no servidor.',
                $reportadoEm->format('d/m/Y H:i')
            );
        }

        if ($status === CmdbBackupTarget::STATUS_PEQUENO) {
            return sprintf(
                'Agente reportou sucesso em %s, mas o arquivo está menor que o esperado.',
                $reportadoEm->format('d/m/Y H:i')
            );
        }

        if ($modificadoEm !== null && $modificadoEm->lessThan($reportadoEm->copy()->subHours(6))) {
            return sprintf(
                'Agente reportou sucesso em %s, mas o arquivo é de %s.',
                $reportadoEm->format('d/m/Y H:i'),
                $modificadoEm->format('d/m/Y H:i')
            );
        }

        return null;
    }

    /**
     * Barreira contra injeção: host e caminho vêm de payload externo e vão
     * parar numa linha de comando.
     */
    private function destinoValido(CmdbBackupTarget $target): bool
    {
        $host = $target->enderecoSsh();
        $usuario = $target->usuarioSsh();

        return (bool) preg_match('/^[A-Za-z0-9._-]+$/', $host)
            && (bool) preg_match('/^[A-Za-z0-9._-]+$/', $usuario)
            && $target->arquivo !== ''
            && ! str_contains($target->arquivo, "\n");
    }

    private function humano(int $bytes): string
    {
        foreach ([['GB', 1024 ** 3], ['MB', 1024 ** 2], ['KB', 1024]] as [$unidade, $divisor]) {
            if ($bytes >= $divisor) {
                return number_format($bytes / $divisor, 2, ',', '.').' '.$unidade;
            }
        }

        return $bytes.' B';
    }
}
