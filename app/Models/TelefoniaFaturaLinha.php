<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Um item da fatura: o que a operadora cobrou por uma linha na competência.
 *
 * DDD e número ficam copiados da fatura porque o item pode existir sem linha
 * cadastrada — número novo que apareceu na conta — e porque o histórico
 * precisa continuar legível se a linha for excluída depois.
 */
class TelefoniaFaturaLinha extends Model
{
    protected $table = 'telefonia_fatura_linhas';

    protected $fillable = [
        'telefonia_fatura_id',
        'corp_line_id',
        'competencia',
        'ddd',
        'numero',
        'nome_plano',
        'valor',
        'valor_anterior',
        'variacao_percentual',
        'linha_nova',
    ];

    protected function casts(): array
    {
        return [
            'competencia' => 'date',
            'valor' => 'decimal:2',
            'valor_anterior' => 'decimal:2',
            'variacao_percentual' => 'decimal:2',
            'linha_nova' => 'boolean',
        ];
    }

    public function fatura(): BelongsTo
    {
        return $this->belongsTo(TelefoniaFatura::class, 'telefonia_fatura_id');
    }

    public function linha(): BelongsTo
    {
        return $this->belongsTo(CorpLine::class, 'corp_line_id');
    }

    public function getNumeroFormatadoAttribute(): string
    {
        $numero = (string) $this->numero;

        return match (strlen($numero)) {
            9 => sprintf('(%s) %s %s-%s', $this->ddd, substr($numero, 0, 1), substr($numero, 1, 4), substr($numero, 5)),
            8 => sprintf('(%s) %s-%s', $this->ddd, substr($numero, 0, 4), substr($numero, 4)),
            default => sprintf('(%s) %s', $this->ddd, $numero),
        };
    }
}
