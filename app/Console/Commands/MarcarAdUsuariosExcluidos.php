<?php

namespace App\Console\Commands;

use App\Models\AdUser;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Marca no CoreTI as contas removidas do Active Directory.
 *
 * A linha NÃO é apagada: depois de excluída do AD a conta some do inventário,
 * e com ela o histórico de quem tinha acesso, a qual centro de custo pertencia
 * e quando foi desativada. Marcar preserva a rastreabilidade.
 *
 * Recebe o CSV gerado por scripts/ad/expurgar_usuarios_desativados.ps1.
 */
class MarcarAdUsuariosExcluidos extends Command
{
    protected $signature = 'ad:marcar-excluidos
        {--csv= : CSV de excluídos gerado pelo script PowerShell}
        {--delimitador=; : Separador de campos}
        {--dry-run : Apenas relata}';

    protected $description = 'Marca como excluídas as contas do AD removidas pelo expurgo, preservando o histórico.';

    public function handle(): int
    {
        $caminho = (string) $this->option('csv');

        if ($caminho === '' || ! is_readable($caminho)) {
            $this->error('Informe um CSV legível em --csv.');

            return self::FAILURE;
        }

        $handle = fopen($caminho, 'r');
        $delimitador = (string) $this->option('delimitador') ?: ';';
        $dryRun = (bool) $this->option('dry-run');

        // O PowerShell grava UTF-8 com BOM. O BOM fica ANTES da aspa de
        // abertura, então o fgetcsv não enxerga o primeiro campo como
        // delimitado por aspas e o devolve com as aspas literais. Descartar os
        // três bytes aqui resolve na origem, em vez de limpar o sintoma depois.
        if (fread($handle, 3) !== "\xEF\xBB\xBF") {
            rewind($handle);
        }

        $cabecalho = fgetcsv($handle, 0, $delimitador);

        if ($cabecalho === false) {
            fclose($handle);
            $this->error('CSV vazio.');

            return self::FAILURE;
        }

        $cabecalho = array_map(fn ($c) => trim((string) $c, " \t\n\r\0\x0B\"'"), $cabecalho);

        if (! in_array('nome_usuario', $cabecalho, true)) {
            fclose($handle);
            $this->error('O CSV precisa ter a coluna nome_usuario. Encontradas: '.implode(', ', $cabecalho));

            return self::FAILURE;
        }

        $marcados = 0;
        $naoEncontrados = [];
        $jaMarcados = 0;
        $linha = 1;

        while (($valores = fgetcsv($handle, 0, $delimitador)) !== false) {
            $linha++;

            if ($valores === [null] || count($valores) !== count($cabecalho)) {
                continue;
            }

            $registro = array_combine($cabecalho, $valores);
            $usuario = trim((string) ($registro['nome_usuario'] ?? ''));

            if ($usuario === '') {
                continue;
            }

            $conta = AdUser::whereRaw('LOWER(nome_usuario) = ?', [mb_strtolower($usuario)])->first();

            if (! $conta) {
                $naoEncontrados[] = $usuario;

                continue;
            }

            if ($conta->excluido) {
                $jaMarcados++;

                continue;
            }

            if (! $dryRun) {
                $conta->update([
                    'excluido' => true,
                    'excluido_em' => $this->paraData($registro['excluida_em'] ?? null) ?? now(),
                    'excluido_por' => trim((string) ($registro['excluida_por'] ?? '')) ?: null,
                    // A conta não existe mais no AD; não pode seguir como ativa.
                    'e_ativo' => false,
                ]);
            }

            $marcados++;
        }

        fclose($handle);

        $this->table(
            ['Marcados', 'Já marcados', 'Não encontrados no CoreTI'],
            [[$marcados, $jaMarcados, count($naoEncontrados)]]
        );

        if ($naoEncontrados !== []) {
            $this->newLine();
            $this->warn('Contas do CSV que não existem em ad_users (nunca sincronizadas?):');
            foreach (array_slice($naoEncontrados, 0, 15) as $u) {
                $this->line('  - '.$u);
            }
            if (count($naoEncontrados) > 15) {
                $this->line('  ... e mais '.(count($naoEncontrados) - 15));
            }
        }

        if ($dryRun) {
            $this->newLine();
            $this->comment('Execução em --dry-run: nada foi gravado.');
        }

        return self::SUCCESS;
    }

    private function paraData(mixed $valor): ?Carbon
    {
        if (blank($valor)) {
            return null;
        }

        try {
            // Mesmo motivo do AdPurgeService: o CSV do PowerShell traz hora
            // local, e sem converter ela é gravada como se fosse UTC.
            return Carbon::parse((string) $valor)->setTimezone(config('app.timezone', 'UTC'));
        } catch (\Throwable) {
            return null;
        }
    }
}
