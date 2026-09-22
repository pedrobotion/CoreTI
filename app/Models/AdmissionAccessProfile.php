<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Pacote padrão de acessos aplicado a uma admissão.
 *
 * O casamento é por cargo/área/departamento/unidade; campo nulo significa
 * "qualquer valor". O perfil sem nenhum critério funciona como padrão geral.
 */
class AdmissionAccessProfile extends Model
{
    protected $fillable = [
        'nome',
        'descricao',
        'cargo_source_id',
        'area_source_id',
        'departamento_source_id',
        'unidade_source_id',
        'prioridade',
        'cumulativo',
        'ativo',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'cumulativo' => 'boolean',
            'ativo' => 'boolean',
            'prioridade' => 'integer',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(AdmissionProfileItem::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeAtivos(Builder $query): Builder
    {
        return $query->where('ativo', true);
    }

    /**
     * Critérios preenchidos — quanto mais específico, mais forte o casamento.
     */
    public function especificidade(): int
    {
        return collect([
            $this->cargo_source_id,
            $this->area_source_id,
            $this->departamento_source_id,
            $this->unidade_source_id,
        ])->filter(fn ($value) => $value !== null && $value !== '')->count();
    }

    public function isPadraoGeral(): bool
    {
        return $this->especificidade() === 0;
    }
}
