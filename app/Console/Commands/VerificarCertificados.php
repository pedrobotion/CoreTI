<?php

namespace App\Console\Commands;

use App\Services\Certificados\CertificateMonitorService;
use Illuminate\Console\Command;

/**
 * Verifica a validade dos certificados TLS e abre chamado de renovação quando
 * algum entra na janela de aviso.
 */
class VerificarCertificados extends Command
{
    protected $signature = 'certificados:verificar';

    protected $description = 'Verifica validade dos certificados TLS e abre chamado no Jira quando perto do vencimento.';

    public function handle(CertificateMonitorService $monitor): int
    {
        $this->info('Verificando certificados...');
        $r = $monitor->verificarTodos();

        $this->table(
            ['Verificados', 'OK', 'A vencer', 'Chamados abertos', 'Erros'],
            [[$r['verificados'], $r['ok'], $r['a_vencer'], $r['chamados_abertos'], $r['erros']]]
        );

        if ($r['chamados_abertos'] > 0) {
            $this->warn($r['chamados_abertos'].' chamado(s) de renovação aberto(s) no Jira.');
        }

        return self::SUCCESS;
    }
}
