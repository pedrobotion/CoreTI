<?php

namespace App\Services\Backups;

use App\Models\CmdbBackupTarget;
use App\Support\Backups\BackupStatus;
use Symfony\Component\Process\Process;

/**
 * Execução remota somente leitura, por SSH.
 *
 * Host, usuário, porta e caminho vêm do cadastro — dados externos indo parar
 * numa linha de comando. Toda a validação fica concentrada aqui, e os
 * argumentos são passados separados ao Process (nunca uma string de shell),
 * de modo que nada do banco é interpretado como comando.
 *
 * O caminho do arquivo é a exceção: ele viaja como argumento de um comando
 * remoto executado pelo shell do outro lado, então recebe escaping explícito.
 */
class SshRunner
{
    /**
     * @return array{ok: bool, saida: string, erro: string, status: string, duracao_ms: int}
     */
    public function executar(CmdbBackupTarget $target, string $comandoRemoto): array
    {
        $inicio = microtime(true);

        $validacao = $this->validar($target);

        if ($validacao !== null) {
            return [
                'ok' => false,
                'saida' => '',
                'erro' => $validacao,
                'status' => BackupStatus::ERRO_VERIFICACAO,
                'duracao_ms' => 0,
            ];
        }

        $processo = new Process($this->argumentos($target, $comandoRemoto));
        $processo->setTimeout((float) config('backups.ssh.timeout', 30));

        try {
            $processo->run();
        } catch (\Throwable $e) {
            return [
                'ok' => false,
                'saida' => '',
                'erro' => 'Falha ao executar ssh: '.$e->getMessage(),
                'status' => BackupStatus::ERRO_VERIFICACAO,
                'duracao_ms' => $this->decorrido($inicio),
            ];
        }

        $saida = trim($processo->getOutput());
        $erro = trim($processo->getErrorOutput());
        $duracao = $this->decorrido($inicio);

        if ($processo->isSuccessful()) {
            return ['ok' => true, 'saida' => $saida, 'erro' => $erro, 'status' => BackupStatus::OK, 'duracao_ms' => $duracao];
        }

        return [
            'ok' => false,
            'saida' => $saida,
            'erro' => $erro !== '' ? $erro : 'Comando remoto falhou sem mensagem.',
            'status' => $this->classificarFalha($erro),
            'duracao_ms' => $duracao,
        ];
    }

    /**
     * Testa apenas a conexão, sem tocar em nada no servidor remoto.
     *
     * @return array{ok: bool, mensagem: string, status: string, duracao_ms: int}
     */
    public function testarConexao(CmdbBackupTarget $target): array
    {
        $resultado = $this->executar($target, $this->comandoDeOperacao('ping'));

        if ($resultado['ok']) {
            return [
                'ok' => true,
                'mensagem' => sprintf(
                    'Conexão SSH realizada com sucesso em %s@%s:%d.',
                    $target->usuarioSsh(),
                    $target->enderecoSsh(),
                    $target->portaSsh()
                ),
                'status' => BackupStatus::OK,
                'duracao_ms' => $resultado['duracao_ms'],
            ];
        }

        return [
            'ok' => false,
            'mensagem' => $this->mensagemAmigavel($resultado['status'], $resultado['erro'], $target),
            'status' => $resultado['status'],
            'duracao_ms' => $resultado['duracao_ms'],
        ];
    }

    /**
     * Traduz a saída crua do ssh em algo acionável para quem lê a tela.
     */
    public function mensagemAmigavel(string $status, string $erro, CmdbBackupTarget $target): string
    {
        return match ($status) {
            BackupStatus::ERRO_AUTENTICACAO => sprintf(
                'Chave SSH não autorizada para %s@%s. Autorize a chave pública do CoreTI no servidor.',
                $target->usuarioSsh(),
                $target->enderecoSsh()
            ),
            BackupStatus::SERVIDOR_INACESSIVEL => sprintf(
                'Servidor %s não respondeu na porta %d.',
                $target->enderecoSsh(),
                $target->portaSsh()
            ),
            default => 'Falha na verificação: '.mb_substr($erro, 0, 300),
        };
    }

    /**
     * Separa "recusou minha credencial" de "não cheguei no servidor".
     * São problemas diferentes, resolvidos por pessoas diferentes.
     */
    private function classificarFalha(string $erro): string
    {
        $e = mb_strtolower($erro);

        foreach (['permission denied', 'authentication failed', 'publickey', 'too many authentication'] as $marca) {
            if (str_contains($e, $marca)) {
                return BackupStatus::ERRO_AUTENTICACAO;
            }
        }

        foreach (['connection refused', 'connection timed out', 'no route to host',
                  'could not resolve', 'name or service not known', 'network is unreachable',
                  'operation timed out', 'connection closed'] as $marca) {
            if (str_contains($e, $marca)) {
                return BackupStatus::SERVIDOR_INACESSIVEL;
            }
        }

        return BackupStatus::ERRO_VERIFICACAO;
    }

    /**
     * @return array<int, string>
     */
    private function argumentos(CmdbBackupTarget $target, string $comandoRemoto): array
    {
        $argumentos = [
            'ssh',
            '-o', 'BatchMode=yes',
            '-o', 'StrictHostKeyChecking=accept-new',
            '-o', 'ConnectTimeout='.(int) config('backups.ssh.connect_timeout', 10),
            '-o', 'PasswordAuthentication=no',
            '-p', (string) $target->portaSsh(),
        ];

        if ($chave = config('backups.ssh.identity_file')) {
            $argumentos[] = '-i';
            $argumentos[] = (string) $chave;
        }

        $argumentos[] = $target->usuarioSsh().'@'.$target->enderecoSsh();
        $argumentos[] = '--';
        $argumentos[] = $comandoRemoto;

        return $argumentos;
    }

    /**
     * Barreira contra injeção. Falha fechada: qualquer formato inesperado
     * impede a execução em vez de tentar sanear.
     */
    private function validar(CmdbBackupTarget $target): ?string
    {
        $host = $target->enderecoSsh();
        $usuario = $target->usuarioSsh();
        $porta = $target->portaSsh();

        if (! preg_match('/^[A-Za-z0-9]([A-Za-z0-9._-]{0,253}[A-Za-z0-9])?$/', $host)) {
            return 'Host SSH com formato inválido: '.mb_substr($host, 0, 80);
        }

        if (! preg_match('/^[a-z_][a-z0-9_-]{0,31}$/i', $usuario)) {
            return 'Usuário SSH com formato inválido.';
        }

        if ($porta < 1 || $porta > 65535) {
            return 'Porta SSH fora da faixa válida.';
        }

        return null;
    }

    /**
     * Caminho de arquivo aceita espaço e acento, mas nunca quebra de linha
     * nem byte nulo — e vai escapado para o shell remoto.
     */
    public function caminhoValido(string $caminho): bool
    {
        if ($caminho === '' || ! str_starts_with($caminho, '/')) {
            return false;
        }

        foreach (["\n", "\r", "\0"] as $proibido) {
            if (str_contains($caminho, $proibido)) {
                return false;
            }
        }

        // Travessia é recusada em vez de normalizada: um caminho auditado deve
        // ser literal, e '..' não tem uso legítimo aqui.
        foreach (explode('/', $caminho) as $segmento) {
            if ($segmento === '..') {
                return false;
            }
        }

        return true;
    }

    /**
     * Monta a operação remota.
     *
     * Com wrapper configurado (config backups.ssh.wrapper), o CoreTI chama
     * apenas '<wrapper> <operação> <caminho>' — o servidor auditado decide o
     * que é permitido. É o que possibilita auditar vários backups com uma
     * única chave SSH, sem forced-command por arquivo.
     *
     * Sem wrapper, cai no comando direto, com o caminho escapado.
     */
    public function comandoDeOperacao(string $operacao, ?string $caminho = null): string
    {
        $wrapper = trim((string) config('backups.ssh.wrapper', ''));

        if ($wrapper !== '') {
            return escapeshellarg($wrapper).' '.escapeshellarg($operacao)
                .($caminho !== null ? ' '.escapeshellarg($caminho) : '');
        }

        return match ($operacao) {
            'ping' => 'true',
            'stat' => 'stat -c %s\|%Y '.escapeshellarg((string) $caminho),
            default => 'true',
        };
    }

    private function decorrido(float $inicio): int
    {
        return (int) round((microtime(true) - $inicio) * 1000);
    }
}
