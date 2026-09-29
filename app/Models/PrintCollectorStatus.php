<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Quando cada servidor de impressão falou com o CoreTI pela última vez.
 *
 * Diferente do último trabalho impresso: um domingo sem impressão nenhuma é
 * normal, um coletor parado não é. Só este registro separa os dois.
 */
class PrintCollectorStatus extends Model
{
    protected $table = 'print_collector_status';

    protected $fillable = [
        'servidor', 'ultimo_contato_em', 'ultimo_lote_recebidos', 'ultimo_lote_gravados',
    ];

    protected function casts(): array
    {
        return ['ultimo_contato_em' => 'datetime'];
    }

    public static function registrarContato(string $servidor, int $recebidos, int $gravados): void
    {
        static::updateOrCreate(
            ['servidor' => mb_strtoupper(trim($servidor))],
            [
                'ultimo_contato_em' => now(),
                'ultimo_lote_recebidos' => $recebidos,
                'ultimo_lote_gravados' => $gravados,
            ]
        );
    }
}
