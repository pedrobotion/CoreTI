<?php

namespace App\Console\Commands;

use App\Mail\PrinterAlertsMail;
use App\Models\PrinterAlert;
use App\Services\Insight\PrinterAlertSyncService;
use App\Support\Impressao\CatalogoErrosImpressora;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Varre os alertas atuais das impressoras monitoradas (EKM/Insight) e envia por
 * e-mail os que são Críticos ou Avisos — com série, nome, erro, descrição e
 * criticidade. Pensado para rodar 1x/dia, de madrugada.
 */
class VerificarAlertasImpressoras extends Command
{
    protected $signature = 'impressoras:alertas
        {--dry-run : Varre (consome API) mas não grava nem envia e-mail}
        {--limite= : Máximo de impressoras a consultar (para teste)}
        {--sem-email : Não envia o e-mail de resumo}
        {--so-email : Só reenvia o e-mail com o que já está no banco (não varre a API)}';

    protected $description = 'Varre alertas das impressoras (Insight) e envia por e-mail os críticos e avisos.';

    public function handle(PrinterAlertSyncService $sync): int
    {
        $limite = $this->option('limite') !== null ? (int) $this->option('limite') : null;
        $dryRun = (bool) $this->option('dry-run');

        // Só reenviar o e-mail com o que já está no banco (sem bater na API).
        if ($this->option('so-email')) {
            $this->enviarEmail();

            return self::SUCCESS;
        }

        $this->info('Varrendo alertas das impressoras monitoradas online...');
        $r = $sync->sincronizar($limite, $dryRun);

        $this->table(
            ['Impressoras', 'Consultadas', 'Abertos', 'Novos', 'Resolvidos', 'Erros', 'Interrompido'],
            [[$r['impressoras'], $r['consultadas'], $r['alertas_abertos'], $r['novos'], $r['resolvidos'], $r['erros'], $r['interrompido'] ? 'SIM (teto)' : 'não']]
        );

        if ($r['interrompido']) {
            $this->warn('Varredura interrompida pelo teto diário da API — nem todas as impressoras foram consultadas hoje.');
        }

        if ($dryRun) {
            $this->warn('DRY-RUN: nada gravado, nenhum e-mail enviado.');

            return self::SUCCESS;
        }

        if (! $this->option('sem-email')) {
            $this->enviarEmail();
        }

        return self::SUCCESS;
    }

    /** Regiões conhecidas e o rótulo que aparece no e-mail. */
    private const REGIOES = [
        'sede' => 'Paraná',
        'cerrado' => 'Cerrado',
    ];

    private function enviarEmail(): void
    {
        // Alertas abertos das classes de hardware/mecânico (fora suprimentos/papel).
        $classes = (array) config('insight.alertas_classes', ['SYSTEM_FAIL', 'SYSTEM_WARNING', 'OPEN']);

        $alertas = PrinterAlert::query()
            ->with(['printer:id,serial_number,manufacturer,model,hostname,ip_address,scope,organization_unit_id,insight_location,insight_zone', 'printer.unit:id,name'])
            ->whereNull('resolvido_em')
            ->whereIn('alert_class', $classes)
            ->orderByDesc('severity_level')
            ->orderBy('printer_id')
            ->get();

        if ($alertas->isEmpty()) {
            $this->info('Nenhum alerta de sistema/mecânico aberto — e-mail não enviado.');

            return;
        }

        // Separa os itens por região (sede/cerrado), guardando à parte os sem
        // região definida — estes entram no e-mail de TODAS as regiões.
        $porRegiao = ['sede' => [], 'cerrado' => []];
        $semRegiao = [];
        foreach ($alertas as $a) {
            $item = $this->montarItem($a);
            $regiao = $this->regiaoDaImpressora($a->printer);
            if ($regiao !== null && isset($porRegiao[$regiao])) {
                $porRegiao[$regiao][] = $item;
            } else {
                $semRegiao[] = $item;
            }
        }

        $enviouAlgo = false;
        foreach (self::REGIOES as $regiao => $rotulo) {
            // Cada região recebe seus alertas + os sem região (decisão do usuário).
            $itens = array_merge($porRegiao[$regiao], $semRegiao);
            if ($itens === []) {
                continue;
            }

            $destinatarios = $this->destinatariosDaRegiao($regiao);
            if ($destinatarios === []) {
                $this->warn("Região {$rotulo}: {$this->contar($itens)} sem destinatário configurado (fallback INSIGHT_ALERTAS_EMAIL).");
                $destinatarios = array_values(array_filter(array_map('trim', explode(',', (string) config('insight.alertas_email')))));
            }
            if ($destinatarios === []) {
                $this->warn("Região {$rotulo}: nenhum destinatário — e-mail não enviado.");

                continue;
            }

            $criticos = $this->contarCriticos($itens);
            try {
                Mail::to($destinatarios)->send(new PrinterAlertsMail(
                    alertas: $itens,
                    total: count($itens),
                    criticos: $criticos,
                    urlAcao: rtrim((string) config('app.url'), '/').'/impressoras',
                    regiao: $rotulo,
                ));
                $this->info("Região {$rotulo}: e-mail para ".implode(', ', $destinatarios)." (".count($itens)." alertas).");
                $enviouAlgo = true;
            } catch (Throwable $e) {
                $this->warn("Região {$rotulo}: falha ao enviar e-mail — ".$e->getMessage());
            }
        }

        if (! $enviouAlgo) {
            $this->warn('Nenhum e-mail enviado — verifique os destinatários por região (INSIGHT_ALERTAS_EMAIL_SEDE / _CERRADO).');
        }
    }

    /** @return array<string,mixed> */
    private function montarItem(PrinterAlert $a): array
    {
        $p = $a->printer;
        $explicacao = CatalogoErrosImpressora::explicar(
            (string) $a->alert_class,
            (int) $a->mib_code,
            $a->descricao,
        );

        return [
            'severidade' => $a->severity_level === 3 ? 'critico' : 'aviso',
            'nome' => $p?->nome ?: '—',
            'serie' => $p?->serial_number ?: '—',
            'local' => $this->localDaImpressora($p),
            'ip' => $p?->ip_address ?: '—',
            'codigo' => $explicacao['codigo'],
            'titulo' => $explicacao['titulo'],
            'o_que_e' => $explicacao['o_que_e'],
            'acao' => $explicacao['acao'],
        ];
    }

    /**
     * Região da impressora. Ordem: scope próprio → unidade vinculada →
     * sinais do Cerrado (prefixo do hostname / sub-rede). Sem nenhum, assume
     * Sede (Paraná) — ver nota em config/insight.php.
     */
    private function regiaoDaImpressora(?\App\Models\Printer $p): string
    {
        if (! $p) {
            return 'sede';
        }

        $regiao = $p->scope ?: \App\Support\ScopeRegistry::forUnitId($p->organization_unit_id);
        if (isset(self::REGIOES[(string) $regiao])) {
            return (string) $regiao;
        }

        return $this->pareceCerrado($p) ? 'cerrado' : 'sede';
    }

    /** Impressora do Cerrado pelo prefixo do hostname ou pela sub-rede. */
    private function pareceCerrado(\App\Models\Printer $p): bool
    {
        return \App\Support\Impressao\ClassificadorCerrado::eCerrado($p->hostname, $p->ip_address);
    }

    /** @return array<int,string> */
    private function destinatariosDaRegiao(string $regiao): array
    {
        return array_values(array_filter((array) config("insight.alertas_emails.{$regiao}", [])));
    }

    /** @param  array<int,array<string,mixed>>  $itens */
    private function contarCriticos(array $itens): int
    {
        return count(array_filter($itens, fn ($i) => ($i['severidade'] ?? '') === 'critico'));
    }

    /** @param  array<int,array<string,mixed>>  $itens */
    private function contar(array $itens): string
    {
        $n = count($itens);

        return $n.' '.($n === 1 ? 'alerta' : 'alertas');
    }

    /** Melhor nome de onde a impressora está: unidade → cidade → local → zona → hostname. */
    private function localDaImpressora(?\App\Models\Printer $p): string
    {
        if (! $p) {
            return '—';
        }

        // A "zona" às vezes vem só como o nome da empresa (COCARI) — inútil como
        // local; nesse caso o hostname (ex.: PIR-LASER-ADM-1) localiza melhor.
        $zona = trim((string) $p->insight_zone);
        if (strcasecmp($zona, 'COCARI') === 0) {
            $zona = '';
        }

        return $p->unit?->name
            ?: ($this->cidadePeloPrefixo($p)
            ?: (trim((string) $p->insight_location)
            ?: ($zona
            ?: (trim((string) $p->hostname) ?: '—'))));
    }

    /** Cidade do Cerrado pela sigla/sub-rede (ex.: PIR-LASER-ADM-1 → Piracanjuba). */
    private function cidadePeloPrefixo(\App\Models\Printer $p): ?string
    {
        return \App\Support\Impressao\ClassificadorCerrado::cidade($p->hostname, $p->ip_address);
    }
}
