<?php

namespace App\Console\Commands;

use App\Models\CmdbDiscoveredAsset;
use App\Services\CmdbDiscoveredAssetService;
use App\Services\CmdbVCenterAssetCreationService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Aprova automaticamente as descobertas do vCenter que passam pelas regras.
 *
 * Não reimplementa a criação de ativo: aplica as exclusões (marcando o que
 * não deve entrar como "ignorado", com motivo) e delega o resto para
 * cmdb:vcenter-create-servers, que já converte VM e host em ativo.
 *
 * Sem as regras, a aprovação cega traria para o CMDB templates, cópias de
 * backup, restaurações temporárias e objetos que já sumiram do vCenter.
 */
class AprovarDescobertasVCenter extends Command
{
    protected $signature = 'cmdb:aprovar-descobertas
        {--connector= : Restringe a um conector}
        {--dry-run : Mostra o que faria, sem aprovar nem ignorar}
        {--sem-regras : Aprova tudo que for VM/host, ignorando as exclusões}';

    protected $description = 'Aprova descobertas do vCenter conforme as regras de config/cmdb.php.';

    public function handle(
        CmdbDiscoveredAssetService $descobertas,
        CmdbVCenterAssetCreationService $criacao,
    ): int {
        if (! config('cmdb.auto_aprovar.habilitado', true)) {
            $this->warn('Aprovação automática desabilitada em config/cmdb.php.');

            return self::SUCCESS;
        }

        $dryRun = (bool) $this->option('dry-run');
        $semRegras = (bool) $this->option('sem-regras');

        $pendentes = CmdbDiscoveredAsset::query()
            ->whereIn('source_type', (array) config('cmdb.auto_aprovar.tipos', []))
            ->whereNull('asset_id')
            ->whereIn('status', ['pending', 'linked', 'created'])
            ->when($this->option('connector'), fn ($q) => $q->where('connector_id', (int) $this->option('connector')))
            ->orderBy('id')
            ->get();

        if ($pendentes->isEmpty()) {
            $this->info('Nenhuma descoberta pendente de VM ou host.');

            return self::SUCCESS;
        }

        $this->info("{$pendentes->count()} descoberta(s) na fila.");

        $aprovar = [];
        $excluir = [];   // motivo permanente: marcados como ignorados
        $adiar = [];     // motivo temporário: continuam pendentes

        foreach ($pendentes as $descoberta) {
            [$motivo, $permanente] = $semRegras
                ? [null, false]
                : $this->motivoParaNaoAprovar($descoberta);

            if ($motivo === null) {
                $aprovar[] = $descoberta;
            } elseif ($permanente) {
                $excluir[] = [$descoberta, $motivo];
            } else {
                $adiar[] = [$descoberta, $motivo];
            }
        }

        if ($adiar !== []) {
            $this->newLine();
            $this->line('<comment>Adiadas (seguem pendentes, serão reavaliadas):</comment>');
            $this->table(
                ['#', 'Nome', 'Motivo'],
                array_map(fn ($par) => [
                    $par[0]->id,
                    mb_strimwidth((string) $par[0]->hostname, 0, 44, '…'),
                    $par[1],
                ], $adiar)
            );
        }

        if ($excluir !== []) {
            $this->newLine();
            $this->line('<comment>Ignoradas (motivo permanente):</comment>');
            $this->table(
                ['#', 'Nome', 'Motivo'],
                array_map(fn ($par) => [
                    $par[0]->id,
                    mb_strimwidth((string) $par[0]->hostname, 0, 44, '…'),
                    $par[1],
                ], $excluir)
            );

            if (! $dryRun && config('cmdb.auto_aprovar.marcar_excluidos_como_ignorados', true)) {
                foreach ($excluir as [$descoberta, $motivo]) {
                    // Fica visível na tela e pode ser reaberto pela revisão.
                    $descobertas->ignore($descoberta, null, 'Ignorado pela aprovação automática: '.$motivo);
                }
            }
        }

        if ($aprovar === []) {
            $this->newLine();
            $this->info('Nada a aprovar automaticamente.');

            return self::SUCCESS;
        }

        $this->newLine();
        $this->line('<info>Serão aprovadas:</info>');
        foreach ($aprovar as $descoberta) {
            $this->line('  + '.$descoberta->hostname);
        }

        if ($dryRun) {
            $this->newLine();
            $this->comment('Execução em --dry-run: nada foi aprovado nem ignorado.');

            return self::SUCCESS;
        }

        // Delega a criação: as excluídas já saíram da fila como ignoradas.
        $resumo = $criacao->createPendingServers(
            $this->option('connector') !== null ? (int) $this->option('connector') : null
        );

        $this->newLine();
        $this->table(
            ['Encontradas', 'Criadas', 'Ignoradas', 'Adiadas', 'Falhas'],
            [[$resumo['found'], $resumo['created'], count($excluir), count($adiar), $resumo['failed']]]
        );

        foreach ($resumo['issues'] ?? [] as $problema) {
            $this->error(sprintf('Descoberta #%s (%s): %s',
                $problema['discovery_id'] ?? '?', $problema['name'] ?? '?', $problema['message'] ?? ''));
        }

        return ($resumo['failed'] ?? 0) > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * Avalia a descoberta.
     *
     * Devolve [motivo, permanente]. Motivo null significa "pode aprovar".
     *
     * A distinção importa: nome de template é julgamento definitivo e vira
     * "ignorado"; carência e ausência temporária no vCenter apenas ADIAM, e o
     * item continua pendente para ser reavaliado no próximo ciclo. Marcar um
     * motivo temporário como ignorado tiraria a descoberta da fila para
     * sempre — ela nunca mais seria aprovada sozinha.
     *
     * @return array{0: ?string, 1: bool}
     */
    private function motivoParaNaoAprovar(CmdbDiscoveredAsset $descoberta): array
    {
        $nome = mb_strtolower(trim((string) $descoberta->hostname));

        foreach ((array) config('cmdb.auto_aprovar.ignorar_nomes_contendo', []) as $padrao) {
            if ($padrao !== '' && str_contains($nome, mb_strtolower($padrao))) {
                return ['nome contém "'.$padrao.'"', true];
            }
        }

        $maxSemVer = (int) config('cmdb.auto_aprovar.max_horas_sem_ver', 24);
        $visto = $descoberta->last_seen_at ?? $descoberta->discovered_at;

        if ($maxSemVer > 0 && $visto && Carbon::parse($visto)->lessThan(now()->subHours($maxSemVer))) {
            // Temporário: a VM pode voltar a aparecer no próximo inventário.
            return [sprintf('não é vista pelo vCenter desde %s', Carbon::parse($visto)->format('d/m/Y H:i')), false];
        }

        $carencia = (int) config('cmdb.auto_aprovar.horas_de_carencia', 24);

        if ($carencia > 0 && $descoberta->discovered_at
            && Carbon::parse($descoberta->discovered_at)->greaterThan(now()->subHours($carencia))) {
            return [sprintf('descoberta há menos de %dh (carência)', $carencia), false];
        }

        return [null, false];
    }
}
