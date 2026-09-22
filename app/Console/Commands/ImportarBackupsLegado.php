<?php

namespace App\Console\Commands;

use App\Models\CmdbDatabaseBackup;
use App\Services\Backups\DatabaseBackupIngestService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Traz o histórico da base auditoria_backups (alimentada pelo n8n) para o CoreTI.
 *
 * Idempotente: reexecutar não duplica, porque o fingerprint é o mesmo do
 * webhook. Assim dá para rodar antes de virar a chave e de novo depois, para
 * capturar o que entrou no intervalo.
 */
class ImportarBackupsLegado extends Command
{
    protected $signature = 'backups:importar-legado
        {--csv= : Importa de um arquivo CSV exportado da base legada}
        {--delimitador=; : Separador de campos do CSV}
        {--connection= : Conexão do banco legado (padrão: config backups.legacy_connection)}
        {--table= : Tabela legada (padrão: backup_auditoria)}
        {--desde= : Importar apenas a partir desta data (Y-m-d)}
        {--chunk=500 : Registros por lote}
        {--dry-run : Apenas relata, sem gravar}';

    protected $description = 'Importa o histórico de backups da base legada auditoria_backups (banco ou CSV).';

    public function handle(DatabaseBackupIngestService $ingest): int
    {
        if ($this->option('csv')) {
            return $this->importarDeCsv($ingest);
        }

        $conexao = (string) ($this->option('connection') ?: config('backups.legacy_connection'));
        $tabela = (string) ($this->option('table') ?: config('backups.legacy_table'));
        $dryRun = (bool) $this->option('dry-run');

        if (! array_key_exists($conexao, (array) config('database.connections'))) {
            $this->error("Conexão '{$conexao}' não existe em config/database.php.");
            $this->line('Configure-a e informe as credenciais do servidor legado no .env.');

            return self::FAILURE;
        }

        try {
            $existe = Schema::connection($conexao)->hasTable($tabela);
        } catch (\Throwable $e) {
            $this->error("Não foi possível conectar em '{$conexao}': ".$e->getMessage());

            return self::FAILURE;
        }

        if (! $existe) {
            $this->error("Tabela '{$tabela}' não encontrada na conexão '{$conexao}'.");

            return self::FAILURE;
        }

        $query = DB::connection($conexao)->table($tabela)->orderBy('id');

        if ($desde = $this->option('desde')) {
            $query->where('data_backup', '>=', $desde);
        }

        $total = (clone $query)->count();
        $this->info("Encontrados {$total} registro(s) em {$conexao}.{$tabela}.");

        if ($total === 0) {
            return self::SUCCESS;
        }

        $importados = 0;
        $duplicados = 0;
        $falhas = 0;

        $barra = $this->output->createProgressBar($total);
        $barra->start();

        $query->chunk((int) $this->option('chunk'), function ($linhas) use ($ingest, $dryRun, &$importados, &$duplicados, &$falhas, $barra): void {
            foreach ($linhas as $linha) {
                try {
                    $payload = $this->paraPayload((array) $linha);

                    if ($dryRun) {
                        $importados++;
                    } else {
                        $resultado = $ingest->ingest($payload);
                        $resultado['duplicado'] ? $duplicados++ : $importados++;
                    }
                } catch (\Throwable $e) {
                    $falhas++;
                    $this->newLine();
                    $this->warn('Registro '.($linha->id ?? '?').' ignorado: '.$e->getMessage());
                }

                $barra->advance();
            }
        });

        $barra->finish();
        $this->newLine(2);

        $this->table(
            ['Importados', 'Já existentes', 'Falhas', 'Total na base CoreTI'],
            [[$importados, $duplicados, $falhas, CmdbDatabaseBackup::count()]]
        );

        if ($dryRun) {
            $this->comment('Execução em --dry-run: nada foi gravado.');
        }

        return self::SUCCESS;
    }

    /**
     * O export usa vírgula como separador decimal ("0,709000"); sem converter,
     * o valor não passaria por is_numeric e o tamanho viraria nulo.
     */
    private function paraNumero(mixed $valor): ?float
    {
        if ($valor === null || $valor === '') {
            return null;
        }

        $normalizado = str_replace(',', '.', (string) $valor);

        return is_numeric($normalizado) ? (float) $normalizado : null;
    }

    /**
     * Converte a linha legada no mesmo formato que o webhook recebe, para
     * reaproveitar normalização, fingerprint e vínculo com o ativo.
     *
     * @param  array<string, mixed>  $linha
     * @return array<string, mixed>
     */
    private function paraPayload(array $linha): array
    {
        return [
            'host' => $linha['host'] ?? '',
            'database' => $linha['database'] ?? '',
            'status' => $linha['status'] ?? '',
            'backup_type' => $linha['backup_type'] ?? null,
            'ip' => $linha['ip_address'] ?? null,
            'arquivo' => $linha['arquivo'] ?? null,
            'tamanho' => $this->paraNumero($linha['tamanho'] ?? null),
            'observacao' => $linha['observacao'] ?? null,
            // A base legada já guarda datetime; o serviço aceita ambos formatos.
            'inicio' => $linha['inicio_backup'] ?? null,
            'fim' => $linha['fim_backup'] ?? null,
            'data_geracao' => $linha['data_backup'] ?? null,
            'data_verificacao' => $linha['data_verificacao'] ?? null,
        ];
    }

    /**
     * Importa de um CSV exportado da base legada.
     *
     * Caminho usado quando não há credencial para o MySQL antigo — só o dump.
     * Reaproveita o mesmo serviço de ingestão do webhook, então normalização,
     * fingerprint e vínculo com o ativo do CMDB são idênticos: reimportar não
     * duplica, e um registro que o webhook já trouxe é reconhecido.
     */
    private function importarDeCsv(DatabaseBackupIngestService $ingest): int
    {
        $caminho = (string) $this->option('csv');

        if (! is_readable($caminho)) {
            $this->error("Arquivo não encontrado ou sem permissão de leitura: {$caminho}");

            return self::FAILURE;
        }

        $delimitador = (string) $this->option('delimitador') ?: ';';
        $dryRun = (bool) $this->option('dry-run');

        $handle = fopen($caminho, 'r');

        if ($handle === false) {
            $this->error('Não foi possível abrir o arquivo.');

            return self::FAILURE;
        }

        $cabecalho = fgetcsv($handle, 0, $delimitador);

        if ($cabecalho === false || $cabecalho === null) {
            fclose($handle);
            $this->error('CSV vazio ou sem cabeçalho.');

            return self::FAILURE;
        }

        // Remove BOM da primeira coluna, comum em exports do Windows/Excel.
        $cabecalho[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $cabecalho[0]);
        $cabecalho = array_map(fn ($c) => trim((string) $c), $cabecalho);

        $obrigatorias = ['host', 'database', 'status'];
        $faltando = array_diff($obrigatorias, $cabecalho);

        if ($faltando !== []) {
            fclose($handle);
            $this->error('Colunas obrigatórias ausentes no CSV: '.implode(', ', $faltando));
            $this->line('Encontradas: '.implode(', ', $cabecalho));

            return self::FAILURE;
        }

        $this->info('Importando de '.$caminho.' (delimitador "'.$delimitador.'")');

        $importados = 0;
        $duplicados = 0;
        $falhas = 0;
        $linha = 1;

        while (($valores = fgetcsv($handle, 0, $delimitador)) !== false) {
            $linha++;

            // Linha em branco no fim do arquivo.
            if ($valores === [null] || $valores === []) {
                continue;
            }

            if (count($valores) !== count($cabecalho)) {
                $falhas++;
                $this->warn("Linha {$linha}: número de colunas diferente do cabeçalho; ignorada.");

                continue;
            }

            try {
                $registro = array_combine($cabecalho, $valores);
                $resultado = $dryRun
                    ? ['duplicado' => false]
                    : $ingest->ingest($this->paraPayload($registro));

                $resultado['duplicado'] ? $duplicados++ : $importados++;
            } catch (\Throwable $e) {
                $falhas++;
                $this->warn("Linha {$linha}: ".$e->getMessage());
            }

            if (($importados + $duplicados) % 200 === 0) {
                $this->line('  ... '.($importados + $duplicados).' processados');
            }
        }

        fclose($handle);

        $this->newLine();
        $this->table(
            ['Importados', 'Já existentes', 'Linhas com erro', 'Total na base CoreTI'],
            [[$importados, $duplicados, $falhas, CmdbDatabaseBackup::count()]]
        );

        if ($dryRun) {
            $this->comment('Execução em --dry-run: nada foi gravado.');
        }

        return $falhas > 0 ? self::FAILURE : self::SUCCESS;
    }
}