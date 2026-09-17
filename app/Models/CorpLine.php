<?php

namespace App\Models;

use App\Models\Concerns\HasOperationalScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CorpLine extends Model
{
    use HasOperationalScope;

    public const STATUS_DISPONIVEL = 'Disponível';
    public const STATUS_EM_USO = 'Em Uso';
    public const STATUS_CANCELADA = 'Cancelada';

    /** Lista em constante, não em ENUM: incluir status novo não pede ALTER TABLE. */
    public const STATUSES = [
        self::STATUS_DISPONIVEL,
        self::STATUS_EM_USO,
        self::STATUS_CANCELADA,
    ];

    public const OPERADORAS = ['VIVO', 'TIM', 'Claro', 'Algar Telecom'];

    protected $table = 'corp_lines';

    protected $fillable = [
        'origem_gestaoti_id',
        'scope',
        'operadora',
        'ddd',
        'numero',
        'status',
        'data_cancelamento',
        'nome_plano',
        'valor_mensal',
        'observacao',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'data_cancelamento' => 'date',
            'valor_mensal' => 'decimal:2',
        ];
    }

    public function assignmentItems(): HasMany
    {
        return $this->hasMany(AssetAssignmentItem::class, 'corp_line_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Ocupação real da linha, conferida no vínculo e não no status gravado.
     *
     * O gestaoTI já fazia essa dupla checagem, e a base mostra por quê: hoje
     * há 41 linhas marcadas 'Em Uso' sem nenhum vínculo ativo. Confiar só na
     * coluna deixaria essas linhas invisíveis para sempre.
     */
    public function hasActiveAssignment(?int $ignoreAssignmentId = null): bool
    {
        return AssetAssignmentItem::query()
            ->where('corp_line_id', $this->id)
            ->whereHas('assignment', fn (Builder $query) => $query
                ->where('ativo', true)
                ->when($ignoreAssignmentId, fn (Builder $q) => $q->where('id', '!=', $ignoreAssignmentId)))
            ->exists();
    }

    public function isAvailable(): bool
    {
        return $this->status === self::STATUS_DISPONIVEL && ! $this->hasActiveAssignment();
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeAvailable(Builder $query): Builder
    {
        return $query
            ->where('status', self::STATUS_DISPONIVEL)
            ->whereDoesntHave('assignmentItems.assignment', fn (Builder $q) => $q->where('ativo', true));
    }

    public function getNumeroCompletoAttribute(): string
    {
        return $this->ddd.$this->numero;
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
