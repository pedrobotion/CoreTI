<?php

namespace App\Services\Certificados;

use App\Models\CertificateCheck;
use App\Services\Jira\JiraTicketOpener;
use Illuminate\Support\Carbon;

/**
 * Verifica a validade dos certificados TLS e abre chamado de renovação.
 *
 * O chamado sai UMA vez por certificado, dentro da janela de aviso. Enquanto
 * a validade continua curta, não repete — o controle é pela impressão digital:
 * renovou o certificado, muda o fingerprint, e o próximo ciclo pode avisar de
 * novo. Sem isso, o monitor abriria um chamado por dia nos últimos dias.
 */
class CertificateMonitorService
{
    public function __construct(
        private readonly CertificateReader $reader,
        private readonly JiraTicketOpener $jira,
    ) {}

    /**
     * @return array{verificados:int, ok:int, a_vencer:int, chamados_abertos:int, erros:int}
     */
    public function verificarTodos(): array
    {
        $resumo = ['verificados' => 0, 'ok' => 0, 'a_vencer' => 0, 'chamados_abertos' => 0, 'erros' => 0];

        foreach ((array) config('certificados.hosts', []) as $alvo) {
            [$host, $porta] = $this->separarHostPorta($alvo);
            if ($host === '') {
                continue;
            }

            $resumo['verificados']++;
            $this->verificar($host, $porta, $resumo);
        }

        return $resumo;
    }

    /** @param  array<string, int>  $resumo */
    private function verificar(string $host, int $porta, array &$resumo): void
    {
        $leitura = $this->reader->ler($host, $porta);
        $registro = CertificateCheck::firstOrNew(['host' => $host]);
        $registro->verificado_em = now();

        if (! $leitura['ok']) {
            $registro->status = 'erro';
            $registro->detalhe = $leitura['erro'];
            $registro->save();
            $resumo['erros']++;

            return;
        }

        /** @var Carbon $expira */
        $expira = $leitura['expira_em'];
        $diasRestantes = (int) now()->diffInDays($expira, false);
        $janela = (int) config('certificados.avisar_dias_antes', 5);

        // Certificado novo (fingerprint diferente): zera o controle do chamado,
        // para que a renovação do próximo ciclo possa avisar quando chegar a
        // hora.
        if ($registro->fingerprint !== $leitura['fingerprint']) {
            $registro->jira_issue_key = null;
            $registro->jira_aberto_em = null;
        }

        $registro->fill([
            'expira_em' => $expira,
            'fingerprint' => $leitura['fingerprint'],
            'status' => $diasRestantes <= $janela ? 'a_vencer' : 'ok',
            'detalhe' => sprintf('Expira em %s (%d dias).', $expira->toDateString(), $diasRestantes),
        ])->save();

        if ($diasRestantes > $janela) {
            $resumo['ok']++;

            return;
        }

        $resumo['a_vencer']++;

        // Já há chamado aberto para ESTE certificado: não duplica.
        if ($registro->jira_issue_key !== null) {
            return;
        }

        $chave = $this->abrirChamado($host, $leitura['subject'], $expira, $diasRestantes);

        if ($chave !== null) {
            $registro->update(['jira_issue_key' => $chave, 'jira_aberto_em' => now()]);
            $resumo['chamados_abertos']++;
        }
    }

    private function abrirChamado(string $host, ?string $subject, Carbon $expira, int $dias): ?string
    {
        $titulo = sprintf('Renovar certificado SSL — %s (vence em %d dias)', $host, max($dias, 0));

        $descricao = implode(' ', [
            "O certificado TLS de {$host} vence em {$expira->toDateString()}" ,
            "({$dias} dias).",
            $subject ? "Certificado: {$subject}." : '',
            'É um wildcard *.cocari.com.br: renovar um cobre todos os sites que o usam.',
            'Passos: gerar/obter o novo certificado, montar o fullchain (crt + cadeia),',
            'apontar os vhosts do nginx e recarregar (nginx -t && systemctl reload nginx).',
            'Aviso automático do CoreTI — monitoramento de certificados.',
        ]);

        return $this->jira->abrir($titulo, $descricao, 'certificado-ssl');
    }

    /** @return array{0:string, 1:int} */
    private function separarHostPorta(string $alvo): array
    {
        $alvo = trim($alvo);

        if (str_contains($alvo, ':')) {
            [$host, $porta] = explode(':', $alvo, 2);

            return [trim($host), (int) $porta ?: 443];
        }

        return [$alvo, 443];
    }
}
