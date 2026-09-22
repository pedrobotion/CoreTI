<?php

namespace Tests\Feature\Backups;

use Symfony\Component\Process\Process;
use Tests\TestCase;

/**
 * Fronteira de segurança do wrapper remoto (scripts/coreti-backup-audit).
 *
 * O wrapper roda no servidor auditado sob forced-command, e é ele que decide
 * o que a chave do CoreTI pode fazer. Os casos abaixo executam o script real
 * com uma allowlist apontada para um diretório temporário.
 */
class WrapperAuditoriaTest extends TestCase
{
    private string $raiz;
    private string $wrapper;

    protected function setUp(): void
    {
        parent::setUp();

        $this->raiz = sys_get_temp_dir().'/coreti-wrapper-'.getmypid();
        @mkdir($this->raiz.'/dbmon', 0755, true);
        file_put_contents($this->raiz.'/dbmon/dbmon.onl', str_repeat('x', 1234));

        // Cópia do script real, com a allowlist apontada para o diretório de teste.
        $original = base_path('scripts/coreti-backup-audit');
        $this->wrapper = $this->raiz.'/coreti-backup-audit';

        file_put_contents($this->wrapper, str_replace(
            '"/backup/dbmon"',
            '"'.$this->raiz.'/dbmon"',
            file_get_contents($original)
        ));
        chmod($this->wrapper, 0755);
    }

    protected function tearDown(): void
    {
        exec('rm -rf '.escapeshellarg($this->raiz));
        parent::tearDown();
    }

    /**
     * Executa como o sshd faria: comando inteiro em SSH_ORIGINAL_COMMAND.
     *
     * @return array{saida: string, erro: string, codigo: int}
     */
    private function comoSsh(string $comandoDoCliente): array
    {
        $processo = new Process(['bash', $this->wrapper]);
        $processo->setEnv(['SSH_ORIGINAL_COMMAND' => $comandoDoCliente]);
        $processo->run();

        return [
            'saida' => trim($processo->getOutput()),
            'erro' => trim($processo->getErrorOutput()),
            'codigo' => $processo->getExitCode(),
        ];
    }

    private function pedido(string ...$partes): string
    {
        // Reproduz o escaping que o SshRunner aplica.
        return implode(' ', array_map(
            fn (string $p) => escapeshellarg($p),
            array_merge(['/usr/local/bin/coreti-backup-audit'], $partes)
        ));
    }

    public function test_ping_responde(): void
    {
        $r = $this->comoSsh($this->pedido('ping'));

        $this->assertSame(0, $r['codigo']);
        $this->assertSame('ok', $r['saida']);
    }

    public function test_stat_devolve_tamanho_e_mtime(): void
    {
        $r = $this->comoSsh($this->pedido('stat', $this->raiz.'/dbmon/dbmon.onl'));

        $this->assertSame(0, $r['codigo']);
        $this->assertMatchesRegularExpression('/^\d+\|\d+$/', $r['saida']);
        $this->assertSame(1234, (int) explode('|', $r['saida'])[0]);
    }

    public function test_caminho_com_espacos_e_acentos_funciona(): void
    {
        $arquivo = $this->raiz.'/dbmon/backup diário da base.onl';
        file_put_contents($arquivo, 'abc');

        $r = $this->comoSsh($this->pedido('stat', $arquivo));

        $this->assertSame(0, $r['codigo'], 'saída: '.$r['erro']);
        $this->assertSame(3, (int) explode('|', $r['saida'])[0]);
    }

    /** Ausência de arquivo não pode virar "caminho não permitido". */
    public function test_arquivo_ausente_reporta_no_such_file(): void
    {
        $r = $this->comoSsh($this->pedido('stat', $this->raiz.'/dbmon/nao-existe.onl'));

        $this->assertNotSame(0, $r['codigo']);
        $this->assertStringContainsString('No such file', $r['erro']);
        $this->assertStringNotContainsString('não permitido', $r['erro']);
    }

    public function test_recusa_caminho_fora_da_allowlist(): void
    {
        $r = $this->comoSsh($this->pedido('stat', '/etc/passwd'));

        $this->assertNotSame(0, $r['codigo']);
        $this->assertStringContainsString('não permitido', $r['erro']);
    }

    public function test_recusa_travessia_de_diretorio(): void
    {
        $r = $this->comoSsh($this->pedido('stat', $this->raiz.'/dbmon/../../etc/passwd'));

        $this->assertNotSame(0, $r['codigo']);
        $this->assertStringContainsString('não permitido', $r['erro']);
    }

    public function test_recusa_symlink_apontando_para_fora(): void
    {
        symlink('/etc/passwd', $this->raiz.'/dbmon/link.onl');

        $r = $this->comoSsh($this->pedido('stat', $this->raiz.'/dbmon/link.onl'));

        $this->assertNotSame(0, $r['codigo']);
        $this->assertStringContainsString('não permitido', $r['erro']);
    }

    public function test_recusa_operacao_nao_prevista(): void
    {
        foreach (['rm', 'cat', 'bash', 'sudo'] as $operacao) {
            $r = $this->comoSsh($this->pedido($operacao, '/etc/passwd'));

            $this->assertNotSame(0, $r['codigo']);
            $this->assertStringContainsString('operação não permitida', $r['erro']);
        }
    }

    public function test_recusa_metacaracteres_de_shell_no_caminho(): void
    {
        foreach ([
            $this->raiz.'/dbmon/x; cat /etc/passwd',
            $this->raiz.'/dbmon/x | cat',
            $this->raiz.'/dbmon/$(id)',
            $this->raiz.'/dbmon/`id`',
            $this->raiz.'/dbmon/x && id',
        ] as $malicioso) {
            $r = $this->comoSsh($this->pedido('stat', $malicioso));

            $this->assertNotSame(0, $r['codigo'], "deveria recusar: {$malicioso}");
            $this->assertStringNotContainsString('uid=', $r['saida'], 'nada pode ser executado');
        }
    }

    public function test_comando_vazio_ou_malformado_falha_fechado(): void
    {
        foreach (['', "'stat", 'stat'] as $entrada) {
            $r = $this->comoSsh($entrada);

            $this->assertNotSame(0, $r['codigo'], "deveria recusar: [{$entrada}]");
        }
    }

    /** O wrapper nunca escreve: o arquivo auditado sai intacto. */
    public function test_auditoria_nao_altera_o_arquivo(): void
    {
        $arquivo = $this->raiz.'/dbmon/dbmon.onl';
        $antes = [filesize($arquivo), filemtime($arquivo), md5_file($arquivo)];

        $this->comoSsh($this->pedido('stat', $arquivo));

        $this->assertSame($antes, [filesize($arquivo), filemtime($arquivo), md5_file($arquivo)]);
    }
}
