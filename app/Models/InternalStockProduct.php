<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Tipo de item do estoque interno da TI.
 *
 * @property-read int $saldo  Saldo atual: unidades em_estoque (serial) ou quantidade_atual.
 */
class InternalStockProduct extends Model
{
    public const CATEGORIAS = ['Nobreak', 'Computador', 'SSD', 'Outro'];

    public const CONTROLE_SERIAL = 'serial';

    public const CONTROLE_QUANTIDADE = 'quantidade';

    public const CONTROLES = [self::CONTROLE_SERIAL, self::CONTROLE_QUANTIDADE];

    protected $fillable = [
        'categoria', 'nome', 'controle', 'quantidade_minima', 'quantidade_atual',
        'ativo', 'observacao', 'created_by', 'updated_by',
        'reposicao_pendente_em', 'reposicao_jira_key', 'reposicao_jira_url',
    ];

    protected $casts = [
        'ativo' => 'boolean',
        'quantidade_minima' => 'integer',
        'quantidade_atual' => 'integer',
        'reposicao_pendente_em' => 'datetime',
    ];

    public function units(): HasMany
    {
        return $this->hasMany(InternalStockUnit::class);
    }

    public function movements(): HasMany
    {
        return $this->hasMany(InternalStockMovement::class);
    }

    public function ehSerial(): bool
    {
        return $this->controle === self::CONTROLE_SERIAL;
    }

    /**
     * Saldo em estoque. Para serial, conta as unidades disponíveis; para
     * quantidade, é o próprio contador. Usa a relação já carregada quando houver,
     * para não disparar uma query por linha na listagem.
     */
    public function getSaldoAttribute(): int
    {
        if (! $this->ehSerial()) {
            return (int) $this->quantidade_atual;
        }

        if ($this->relationLoaded('units')) {
            return $this->units->where('status', InternalStockUnit::STATUS_EM_ESTOQUE)->count();
        }

        return (int) $this->units()->where('status', InternalStockUnit::STATUS_EM_ESTOQUE)->count();
    }

    public function getAbaixoDoMinimoAttribute(): bool
    {
        return $this->saldo <= $this->quantidade_minima;
    }

    public function scopeAtivos(Builder $query): Builder
    {
        return $query->where('ativo', true);
    }
}
