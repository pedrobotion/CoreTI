<?php

namespace App\Console\Commands;

use App\Mail\CoreTiNotificationMail;
use App\Models\BancadaPendenciaAlert;
use App\Services\Bancada\PendenciaAdministrativaService;
use App\Services\Jira\JiraTicketOpener;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Verifica as pendências administrativas da bancada e cobra: abre chamado no
 * Jira para cada pendência nova, envia um resumo diário por e-mail e mantém o
 * registro para não duplicar cobrança nem perder o momento em que foi resolvida.
 *
 * Feito para rodar agendado (dias úteis, de manhã). Em --dry-run só mostra o
 * que faria, sem gravar, sem Jira e sem e-mail.
 */
class VerificarPendenciasBancada extends Command
{
    protected $signature = 'bancada:verificar-pendencias
        {--dry-run : Só mostra as pendências, sem gravar/Jira/e-mail}
        {--sem-jira : Não abre chamados no Jira}
        {--sem-email : Não envia o e-mail de resumo}';

    protected $description = 'Detecta pendências administrativas da bancada e cobra (Jira + e-mail diário).';

    public function handle(PendenciaAdministrativaService $servico, JiraTicketOpener $jira): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $pendencias = $servico->detectar();

        $this->tabelaResumo($servico->resumo(), count($pendencias));

        if ($pendencias !== []) {
            $this->table(
                ['Identificador', 'Tipo', 'Unidade', 'Status', 'Pendência', 'Dias'],
                array_map(fn ($p) => [
                    $servico->identificador($p), $p['tipo_equipamento'], $p['unidade'] ?? '—',
                    $p['status'], $p['rotulo'], $p['dias'],
                ], array_slice($pendencias, 0, 20))
            );
            if (count($pendencias) > 20) {
                $this->line('  ... e mais '.(count($pendencias) - 20).' pendência(s).');
            }
        }

        if ($dryRun) {
            $this->warn('DRY-RUN: nada foi gravado, nenhum chamado aberto, nenhum e-mail enviado.');

            return self::SUCCESS;
        }

        $this->persistirECobrar($servico, $jira, $pendencias);

        if (! $this->option('sem-email')) {
            $this->enviarEmail($servico, $pendencias);
        }

        return self::SUCCESS;
    }

    /** @param  array<int,array<string,mixed>>  $pendencias */
    private function persistirECobrar(PendenciaAdministrativaService $servico, JiraTicketOpener $jira, array $pendencias): void
    {
        $agora = Carbon::now();
        $vistos = [];
        $abrirJira = ! $this->option('sem-jira') && $jira->configurado();
        $tetoJira = (int) config('bancada.pendencias.jira_max_por_execucao');
        $abertos = 0;

        foreach ($pendencias as $p) {
            $vistos[] = $p['equipment_id'].':'.$p['pendencia'];

            $alerta = BancadaPendenciaAlert::firstOrNew([
                'bancada_equipment_id' => $p['equipment_id'],
                'tipo' => $p['pendencia'],
            ]);
            $novo = ! $alerta->exists || $alerta->situacao === 'resolvida';

            if ($novo) {
                $alerta->primeiro_detectado_em = $agora;
                $alerta->resolvido_em = null;
                // Pendência reabriu: esquece o chamado anterior, que já foi tratado.
                if ($alerta->situacao === 'resolvida') {
                    $alerta->jira_issue_key = null;
                    $alerta->jira_issue_url = null;
                }
            }

            $alerta->situacao = 'aberta';
            $alerta->dias = $p['dias'];
            $alerta->ultimo_alerta_em = $agora;

            if ($abrirJira && $novo && ! $alerta->jira_issue_key && $abertos < $tetoJira) {
                $chave = $this->abrirChamado($jira, $servico, $p);
                if ($chave !== null) {
                    $alerta->jira_issue_key = $chave;
                    $alerta->jira_issue_url = $jira->urlDoChamado($chave);
                    $abertos++;
                }
            }

            $alerta->save();
        }

        // Pendências que saíram da condição: marca como resolvidas.
        $resolvidas = BancadaPendenciaAlert::query()->where('situacao', 'aberta')->get()
            ->filter(fn ($a) => ! in_array($a->bancada_equipment_id.':'.$a->tipo, $vistos, true));
        foreach ($resolvidas as $a) {
            $a->update(['situacao' => 'resolvida', 'resolvido_em' => $agora]);
        }

        $this->info("Pendências registradas: ".count($pendencias)." | chamados abertos agora: {$abertos} | resolvidas: ".$resolvidas->count());
        if ($abrirJira && $abertos >= $tetoJira) {
            $this->warn("Teto de {$tetoJira} chamados/execução atingido — as demais pendências novas entram nas próximas execuções.");
        }
    }

    private function abrirChamado(JiraTicketOpener $jira, PendenciaAdministrativaService $servico, array $p): ?string
    {
        $id = $servico->identificador($p);
        $titulo = "[Bancada] {$p['rotulo']} — {$id} ({$p['dias']} dias)";
        $descricao = "Pendência administrativa detectada pelo CoreTI.\n\n"
            ."Equipamento: {$id} ({$p['tipo_equipamento']})\n"
            ."Unidade: ".($p['unidade'] ?? '—')."\n"
            ."Status atual: {$p['status']}\n"
            ."Pendência: {$p['rotulo']}\n"
            ."Parado há: {$p['dias']} dias (limiar {$p['limiar']} dias)\n";

        // Terceiros vai para a Bancada e já atribuído; os demais ficam no padrão.
        $ehTerceiros = ($p['pendencia'] ?? '') === 'terceiros';
        $equipe = $ehTerceiros
            ? (string) config('bancada.pendencias.terceiros_jira_equipe')
            : (string) config('bancada.pendencias.jira_equipe');
        $responsavel = $ehTerceiros
            ? (string) config('bancada.pendencias.terceiros_jira_responsavel')
            : null;

        // Catálogo e tipo de issue por pendência: entrada_fiscal/terceiros são
        // INCIDENTE (1701), aguardando_peca é REQUISIÇÃO (catálogo de requisição,
        // issue type 10010). Quem não estiver mapeado cai no catálogo padrão.
        $tipoCfg = (array) config("bancada.pendencias.tipos.{$p['pendencia']}", []);
        $requestType = (string) ($tipoCfg['request_type'] ?? config('bancada.pendencias.jira_request_type'));
        $issueType = (string) ($tipoCfg['issue_type'] ?? '');
        $extra = $issueType !== '' ? ['issuetype' => ['id' => $issueType]] : [];

        try {
            return $jira->abrir(
                $titulo,
                $descricao,
                'bancada-pendencia',
                $extra,
                $equipe,
                $requestType,
                semEquipe: false,
                assigneeEmail: $responsavel,
            );
        } catch (Throwable $e) {
            $this->warn("Falha ao abrir chamado para {$id}: ".$e->getMessage());

            return null;
        }
    }

    /** @param  array<int,array<string,mixed>>  $pendencias */
    private function enviarEmail(PendenciaAdministrativaService $servico, array $pendencias): void
    {
        $destinatarios = config('bancada.pendencias.email_destinatarios');
        if ($destinatarios === [] || $pendencias === []) {
            return;
        }

        $resumo = $servico->resumo();
        $linhasResumo = [];
        foreach ($resumo as $tipo => $qtd) {
            if ($qtd > 0) {
                $linhasResumo[] = ucfirst(str_replace('_', ' ', $tipo)).": {$qtd}";
            }
        }

        $top = array_slice($pendencias, 0, 15);
        $orientacao = "Mais antigas:\n";
        foreach ($top as $p) {
            $orientacao .= "• {$servico->identificador($p)} — {$p['rotulo']} — ".($p['unidade'] ?? '—')." — {$p['dias']} dias\n";
        }
        if (count($pendencias) > 15) {
            $orientacao .= "... e mais ".(count($pendencias) - 15)." pendência(s).\n";
        }

        try {
            Mail::to($destinatarios)->send(new CoreTiNotificationMail([
                'tipo_mensagem' => 'AVISO',
                'titulo' => 'Pendências administrativas da bancada',
                'destinatario' => 'equipe de Tecnologia da Informação',
                'mensagem_principal' => count($pendencias).' equipamento(s) com pendência administrativa. '.implode(' · ', $linhasResumo),
                'servico' => 'Bancada de Serviços',
                'status' => 'PENDÊNCIAS',
                'orientacao' => $orientacao,
                'texto_botao' => 'Abrir bancada',
                'url_acao' => rtrim((string) config('app.url'), '/').'/bancada-servicos/equipamentos',
            ]));
            $this->info('E-mail de resumo enviado para: '.implode(', ', $destinatarios));
        } catch (Throwable $e) {
            $this->warn('Falha ao enviar e-mail de resumo: '.$e->getMessage());
        }
    }

    /** @param  array<string,int>  $resumo */
    private function tabelaResumo(array $resumo, int $total): void
    {
        $this->table(
            ['Entrada fiscal', 'Terceiros', 'Aguardando peça', 'Total'],
            [[
                $resumo['entrada_fiscal'] ?? 0,
                $resumo['terceiros'] ?? 0,
                $resumo['aguardando_peca'] ?? 0,
                $total,
            ]]
        );
    }
}
