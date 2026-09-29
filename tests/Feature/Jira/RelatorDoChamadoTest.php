<?php

namespace Tests\Feature\Jira;

use App\Services\Backups\DatabaseBackupJiraService;
use ReflectionMethod;
use Tests\TestCase;

/**
 * Chamado aberto pelo CoreTI assina como conta de sistema.
 *
 * A autenticação usa um token pessoal. Sem definir o relator, o chamado
 * apareceria na fila em nome dessa pessoa — o que confunde quem lê e quebra a
 * automação no dia em que ela sair da empresa.
 */
class RelatorDoChamadoTest extends TestCase
{
    /** @param  array<string, mixed>  $campos */
    private function comRelator(array $campos = []): array
    {
        $m = new ReflectionMethod(DatabaseBackupJiraService::class, 'comRelator');
        $m->setAccessible(true);

        return $m->invoke(app(DatabaseBackupJiraService::class), $campos);
    }

    public function test_define_o_relator_configurado(): void
    {
        config(['services.jira.reporter_account_id' => '712020:abc-123']);

        $campos = $this->comRelator(['summary' => 'Backup falhou']);

        $this->assertSame(['id' => '712020:abc-123'], $campos['reporter']);
        $this->assertSame('Backup falhou', $campos['summary'], 'não mexe no resto');
    }

    public function test_sem_relator_configurado_nao_envia_o_campo(): void
    {
        /*
         | Mandar reporter vazio faria o Jira recusar a criação inteira.
         | Ausente, o relator vira a conta que autentica — comportamento
         | anterior, que continua servindo de retaguarda.
         */
        config(['services.jira.reporter_account_id' => null]);

        $this->assertArrayNotHasKey('reporter', $this->comRelator(['summary' => 'x']));
    }

    public function test_espaco_em_branco_vale_como_ausencia(): void
    {
        config(['services.jira.reporter_account_id' => '   ']);

        $this->assertArrayNotHasKey('reporter', $this->comRelator());
    }

    public function test_os_dois_caminhos_de_criacao_passam_pelo_relator(): void
    {
        /*
         | O serviço abre chamado em dois lugares: falha de backup de banco e
         | incidente de alvo. Cobri um e esqueci o outro na primeira versão —
         | metade dos chamados sairia assinada pela pessoa errada.
         */
        $fonte = file_get_contents(
            base_path('app/Services/Backups/DatabaseBackupJiraService.php')
        );

        $criacoes = substr_count($fonte, "post(\$this->url('/rest/api/3/issue'), ['fields' => \$campos])");
        $relatores = substr_count($fonte, '$campos = $this->comRelator($campos);');

        $this->assertSame($criacoes, $relatores, 'toda criação de chamado precisa passar pelo relator');
    }
}
