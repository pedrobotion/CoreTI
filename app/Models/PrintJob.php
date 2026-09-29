<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Um trabalho de impressão, como o Windows registrou.
 *
 * O log não traz o nome do arquivo — o evento 307 guarda o número do trabalho
 * na fila, não o documento. Quem precisar saber "o que foi impresso" não vai
 * achar aqui; o que esta tabela responde é quanto, quem, onde e quando.
 */
class PrintJob extends Model
{
    protected $table = 'print_jobs';

    protected $fillable = [
        'servidor', 'chave_evento', 'impresso_em', 'usuario', 'estacao',
        'impressora', 'porta', 'doc_numero', 'paginas', 'tamanho_bytes',
        'printer_id', 'raw_data',
    ];

    protected function casts(): array
    {
        return [
            'impresso_em' => 'datetime',
            'paginas' => 'integer',
            'tamanho_bytes' => 'integer',
            'raw_data' => 'array',
        ];
    }

    public function printer(): BelongsTo
    {
        return $this->belongsTo(Printer::class);
    }

    /**
     * Identidade do evento.
     *
     * O número do trabalho recicla dentro de cada servidor, então sozinho não
     * serve. Somado ao servidor, ao instante e ao usuário, identifica — e é o
     * que permite reenviar a mesma janela sem duplicar.
     */
    public static function chaveDe(string $servidor, ?string $docNumero, string $impressoEm, string $usuario): string
    {
        return hash('sha256', implode('|', [
            mb_strtolower(trim($servidor)),
            trim((string) $docNumero),
            trim($impressoEm),
            mb_strtolower(trim($usuario)),
        ]));
    }

    /** @param  Builder<static>  $query */
    public function scopeNoPeriodo(Builder $query, \DateTimeInterface $de, \DateTimeInterface $ate): Builder
    {
        return $query->whereBetween('impresso_em', [$de, $ate]);
    }

    /**
     * Trabalho que não rendeu folha.
     *
     * Geralmente cancelamento na fila. Vale medir: é papel que quase saiu e
     * tempo de quem mandou imprimir duas vezes.
     *
     * @param  Builder<static>  $query
     */
    public function scopeSemPagina(Builder $query): Builder
    {
        return $query->where('paginas', 0);
    }
}
