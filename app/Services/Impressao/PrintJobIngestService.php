<?php

namespace App\Services\Impressao;

use App\Models\PrintJob;
use App\Models\Printer;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Recebe os eventos do PrintService e grava sem duplicar.
 *
 * O script de coleta pode reenviar a mesma janela — por retomada após falha,
 * por sobreposição entre execuções ou por alguém conferindo. Reenviar precisa
 * ser inofensivo, senão ninguém roda de novo com segurança e o buraco na
 * estatística vira permanente.
 */
class PrintJobIngestService
{
    /** @var array<string, int>|null */
    private ?array $impressorasPorHostname = null;

    /**
     * @param  array<int, array<string, mixed>>  $eventos
     * @return array{recebidos:int, gravados:int, repetidos:int, ignorados:int, motivos:array<string,int>}
     */
    public function registrar(string $servidor, array $eventos): array
    {
        $resumo = ['recebidos' => count($eventos), 'gravados' => 0, 'repetidos' => 0, 'ignorados' => 0, 'motivos' => []];
        $linhas = [];
        $chaves = [];

        foreach ($eventos as $evento) {
            $linha = $this->normalizar($servidor, $evento, $resumo);

            if ($linha === null) {
                continue;
            }

            /*
             | Repetido dentro do próprio lote.
             |
             | insertOrIgnore não protege contra chave duplicada NA MESMA
             | inserção: o banco recusa o lote inteiro. Filtrar aqui é o que
             | permite o lote passar.
             */
            if (isset($chaves[$linha['chave_evento']])) {
                $resumo['repetidos']++;

                continue;
            }

            $chaves[$linha['chave_evento']] = true;
            $linhas[] = $linha;
        }

        if ($linhas === []) {
            return $resumo;
        }

        $jaExistem = PrintJob::query()
            ->whereIn('chave_evento', array_keys($chaves))
            ->pluck('chave_evento')
            ->flip();

        $novas = array_values(array_filter(
            $linhas,
            fn (array $l): bool => ! $jaExistem->has($l['chave_evento'])
        ));

        $resumo['repetidos'] += count($linhas) - count($novas);

        foreach (array_chunk($novas, 500) as $pedaco) {
            // insertOrIgnore por causa de envios simultâneos: dois servidores
            // podem postar o mesmo instante e a corrida perderia o lote.
            $resumo['gravados'] += DB::table('print_jobs')->insertOrIgnore($pedaco);
        }

        return $resumo;
    }

    /**
     * @param  array<string, mixed>  $evento
     * @param  array<string, mixed>  $resumo
     * @return array<string, mixed>|null
     */
    private function normalizar(string $servidor, array $evento, array &$resumo): ?array
    {
        $usuario = trim((string) ($evento['usuario'] ?? ''));
        $impressora = trim((string) ($evento['impressora'] ?? ''));
        $quando = $this->data($evento['impresso_em'] ?? null);

        foreach (['usuário' => $usuario, 'impressora' => $impressora] as $campo => $valor) {
            if ($valor === '') {
                $resumo['ignorados']++;
                $resumo['motivos']['sem '.$campo] = ($resumo['motivos']['sem '.$campo] ?? 0) + 1;

                return null;
            }
        }

        if ($quando === null) {
            $resumo['ignorados']++;
            $resumo['motivos']['data inválida'] = ($resumo['motivos']['data inválida'] ?? 0) + 1;

            return null;
        }

        $docNumero = trim((string) ($evento['doc_numero'] ?? ''));
        $agora = now();

        return [
            'servidor' => mb_strtoupper($servidor),
            'chave_evento' => PrintJob::chaveDe($servidor, $docNumero, $quando->toDateTimeString(), $usuario),
            'impresso_em' => $quando,
            'usuario' => mb_strtolower($usuario),
            'estacao' => ($e = trim((string) ($evento['estacao'] ?? ''))) !== '' ? mb_strtoupper($e) : null,
            'impressora' => mb_strtoupper($impressora),
            'porta' => ($p = trim((string) ($evento['porta'] ?? ''))) !== '' ? $p : null,
            'doc_numero' => $docNumero !== '' ? $docNumero : null,
            'paginas' => max((int) ($evento['paginas'] ?? 0), 0),
            'tamanho_bytes' => ($t = (int) ($evento['tamanho_bytes'] ?? 0)) > 0 ? $t : null,
            'printer_id' => $this->impressoraDoInsight($impressora),
            'raw_data' => json_encode($evento, JSON_UNESCAPED_UNICODE),
            'created_at' => $agora,
            'updated_at' => $agora,
        ];
    }

    /**
     * Data do evento, sempre em UTC.
     *
     * O Windows manda o horário local com offset. Sem converter, o gráfico
     * "por horário" sairia três horas deslocado — que é exatamente a pergunta
     * que ele existe para responder.
     */
    private function data(mixed $valor): ?Carbon
    {
        $texto = trim((string) $valor);

        if ($texto === '') {
            return null;
        }

        try {
            return Carbon::parse($texto)->setTimezone(config('app.timezone', 'UTC'));
        } catch (\Throwable) {
            return null;
        }
    }

    /** O nome da fila casa com o hostname da impressora monitorada? */
    private function impressoraDoInsight(string $nome): ?int
    {
        $this->impressorasPorHostname ??= Printer::query()
            ->whereNotNull('hostname')
            ->pluck('id', 'hostname')
            ->mapWithKeys(fn (int $id, string $host): array => [mb_strtoupper(trim($host)) => $id])
            ->all();

        return $this->impressorasPorHostname[mb_strtoupper(trim($nome))] ?? null;
    }
}
