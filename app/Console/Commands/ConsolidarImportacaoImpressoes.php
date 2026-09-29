<?php

namespace App\Console\Commands;

use App\Models\PrintJob;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Remove da importação manual o que os servidores já entregaram.
 *
 * A planilha e o log trazem os MESMOS trabalhos: ela é uma exportação deles.
 * Mas a chave que evita duplicata inclui a origem — de propósito, porque o
 * número do trabalho recicla dentro de cada fila e sem a origem dois
 * servidores colidiriam entre si.
 *
 * A consequência é que a linha vinda da planilha e a mesma linha vinda do
 * IMP01 convivem, e o total dobra no período em que as duas se cruzam. Este
 * comando desfaz esse encontro, mantendo a versão que veio do servidor: ela
 * tem a origem correta e é a que o painel por servidor precisa.
 *
 * O que só existe na planilha — o trecho que o log do Windows já girou e
 * apagou — permanece. É justamente o histórico que se perderia.
 */
class ConsolidarImportacaoImpressoes extends Command
{
    protected $signature = 'impressao:consolidar
        {--origem=PLANILHA : Origem importada a consolidar}
        {--simular : Mostra o que seria removido, sem apagar}';

    protected $description = 'Remove os trabalhos importados de planilha que os servidores já enviaram.';

    public function handle(): int
    {
        $origem = mb_strtoupper((string) $this->option('origem'));

        $importados = PrintJob::query()->where('servidor', $origem)->count();

        if ($importados === 0) {
            $this->info("Nenhum trabalho com origem {$origem}. Nada a consolidar.");

            return self::SUCCESS;
        }

        /*
         | Mesmo trabalho = mesmo instante, usuário, impressora e número na
         | fila. O documento não entra porque o log não o registra, e a origem
         | é justamente o que difere entre as duas cópias.
         */
        $duplicados = DB::table('print_jobs as importado')
            ->join('print_jobs as real', function ($join) use ($origem): void {
                $join->on('importado.impresso_em', '=', 'real.impresso_em')
                    ->on('importado.usuario', '=', 'real.usuario')
                    ->on('importado.impressora', '=', 'real.impressora')
                    ->whereColumn('importado.doc_numero', 'real.doc_numero')
                    ->where('real.servidor', '!=', $origem);
            })
            ->where('importado.servidor', $origem)
            ->distinct()
            ->pluck('importado.id');

        $this->components->twoColumnDetail("Trabalhos com origem {$origem}", (string) $importados);
        $this->components->twoColumnDetail('Já entregues pelos servidores', (string) $duplicados->count());
        $this->components->twoColumnDetail(
            'Só existem na planilha',
            (string) ($importados - $duplicados->count())
        );

        if ($duplicados->isEmpty()) {
            $this->newLine();
            $this->info('Nenhuma sobreposição. Os servidores ainda não enviaram este período.');

            return self::SUCCESS;
        }

        if ($this->option('simular')) {
            $this->newLine();
            $this->comment('Simulação: nada foi removido.');

            return self::SUCCESS;
        }

        $removidos = 0;

        foreach ($duplicados->chunk(500) as $pedaco) {
            $removidos += PrintJob::query()->whereIn('id', $pedaco)->delete();
        }

        $this->newLine();
        $this->info("{$removidos} trabalho(s) duplicado(s) removido(s) da importação.");
        $this->line('O que sobrou da planilha é o histórico que o log do Windows já não tem.');

        return self::SUCCESS;
    }
}
