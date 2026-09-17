<?php

namespace App\Console\Commands;

use App\Services\GestaoTi\GestaoTiImportService;
use App\Services\GestaoTi\GestaoTiReadOnlyConnection;
use Illuminate\Console\Command;

/**
 * Traz linhas, aparelhos, equipamentos e vínculos do gestaoTI.
 *
 * Roda em simulação por padrão: para gravar é preciso pedir --aplicar. Assim o
 * primeiro contato com o comando é sempre um relatório, não uma carga.
 */
class ImportarGestaoTi extends Command
{
    protected $signature = 'gestaoti:importar
                            {--aplicar : Grava de verdade. Sem esta opção, apenas simula e desfaz.}
                            {--etapa=* : Limita a etapas: linhas, aparelhos, equipamentos, vinculos.}
                            {--divergencias= : Grava o relatório de divergências neste arquivo CSV.}';

    protected $description = 'Importa dados do COCARI Gestão TI (somente leitura na origem)';

    public function handle(GestaoTiImportService $servico): int
    {
        $aplicar = (bool) $this->option('aplicar');
        $etapas = array_values(array_filter((array) $this->option('etapa')));

        $this->components->info('Conferindo a origem…');
        $conexao = GestaoTiReadOnlyConnection::verificar();

        if (! $conexao['ok']) {
            $this->components->error('Não foi possível ler a base do gestaoTI: '.$conexao['mensagem']);

            return self::FAILURE;
        }

        $this->components->twoColumnDetail('Origem', $conexao['mensagem']);
        $this->components->twoColumnDetail('Modo', $aplicar
            ? '<fg=yellow>APLICANDO — grava no CoreTI</>'
            : '<fg=green>simulação — nada é gravado</>');
        $this->components->twoColumnDetail('Etapas', $etapas === [] ? implode(', ', GestaoTiImportService::ETAPAS) : implode(', ', $etapas));
        $this->newLine();

        if ($aplicar && $this->input->isInteractive()
            && ! $this->confirm('Isto grava no banco do CoreTI. O gestaoTI não é alterado. Continuar?', false)) {
            $this->components->warn('Cancelado.');

            return self::SUCCESS;
        }

        $inicio = microtime(true);
        $relatorio = $servico->importar(! $aplicar, $etapas);
        $segundos = round(microtime(true) - $inicio, 1);

        $this->mostrarContadores($relatorio['contadores']);
        $this->mostrarDivergencias($relatorio['divergencias']);

        if ($caminho = $this->option('divergencias')) {
            $this->gravarCsv($caminho, $relatorio['divergencias']);
            $this->components->info("Divergências gravadas em {$caminho}");
        }

        $this->newLine();
        $this->components->twoColumnDetail('Tempo', "{$segundos}s");

        if (! $aplicar) {
            $this->components->warn('Simulação: tudo foi desfeito. Os números acima são os reais — rode com --aplicar para gravar.');
        }

        return self::SUCCESS;
    }

    /** @param  array<string, array<string, int>>  $contadores */
    private function mostrarContadores(array $contadores): void
    {
        $linhas = [];

        foreach ($contadores as $etapa => $valores) {
            $linhas[] = [
                ucfirst($etapa),
                $valores['lidos'] ?? 0,
                $valores['criados'] ?? 0,
                $valores['atualizados'] ?? 0,
                $valores['nao_importados'] ?? 0,
                $valores['avisos'] ?? 0,
            ];
        }

        $this->table(['Etapa', 'Lidos', 'Criados', 'Atualizados', 'Não importados', 'Avisos'], $linhas);
    }

    /** @param  array<int, array<string, mixed>>  $divergencias */
    private function mostrarDivergencias(array $divergencias): void
    {
        if ($divergencias === []) {
            $this->components->info('Nenhuma divergência.');

            return;
        }

        $erros = array_values(array_filter($divergencias, fn ($d) => $d['nivel'] === 'erro'));
        $avisos = array_values(array_filter($divergencias, fn ($d) => $d['nivel'] === 'aviso'));

        $this->newLine();
        $this->components->error(count($erros).' registro(s) não importado(s)');

        foreach (array_slice($erros, 0, 40) as $d) {
            $this->line(sprintf('  <fg=red>✗</> [%s #%s] %s', $d['etapa'], $d['origem_id'], $d['identificacao']));
            $this->line('    '.$d['motivo']);
        }

        if (count($erros) > 40) {
            $this->line('  … e mais '.(count($erros) - 40).'. Use --divergencias=arquivo.csv para a lista completa.');
        }

        if ($avisos !== []) {
            $this->newLine();
            $this->components->warn(count($avisos).' aviso(s) — importado, mas confira');

            foreach (array_slice($avisos, 0, 40) as $d) {
                $this->line(sprintf('  <fg=yellow>!</> [%s #%s] %s', $d['etapa'], $d['origem_id'], $d['identificacao']));
                $this->line('    '.$d['motivo']);
            }

            if (count($avisos) > 40) {
                $this->line('  … e mais '.(count($avisos) - 40).'.');
            }
        }
    }

    /** @param  array<int, array<string, mixed>>  $divergencias */
    private function gravarCsv(string $caminho, array $divergencias): void
    {
        $arquivo = fopen($caminho, 'wb');
        fwrite($arquivo, "\xEF\xBB\xBF");
        fputcsv($arquivo, ['Etapa', 'Nível', 'ID na origem', 'Identificação', 'Motivo'], ';');

        foreach ($divergencias as $d) {
            fputcsv($arquivo, [$d['etapa'], $d['nivel'], $d['origem_id'], $d['identificacao'], $d['motivo']], ';');
        }

        fclose($arquivo);
    }
}
