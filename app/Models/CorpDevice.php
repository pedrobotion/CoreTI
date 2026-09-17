<?php

namespace App\Models;

use App\Models\Concerns\HasOperationalScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Aparelho celular. A linha (o chip) é a CorpLine — um vínculo pode ter só a
 * linha ("apenas linha corporativa") ou linha + aparelho ("kit completo").
 */
class CorpDevice extends Model
{
    use HasOperationalScope;

    public const STATUS_DISPONIVEL = 'Disponível';
    public const STATUS_EM_USO = 'Em Uso';
    public const STATUS_BAIXADO = 'Baixado';

    public const STATUSES = [
        self::STATUS_DISPONIVEL,
        self::STATUS_EM_USO,
        self::STATUS_BAIXADO,
    ];

    public const ESTADO_NOVO = 'Novo';
    public const ESTADO_USADO = 'Usado';

    public const ESTADOS = [self::ESTADO_NOVO, self::ESTADO_USADO];

    protected $table = 'corp_devices';

    protected $fillable = [
        'origem_gestaoti_id',
        'scope',
        'marca',
        'modelo',
        'imei_1',
        'imei_2',
        'numero_serie',
        'status',
        'estado_aparelho',
        'observacao',
        'created_by',
        'updated_by',
    ];

    public function assignmentItems(): HasMany
    {
        return $this->hasMany(AssetAssignmentItem::class, 'corp_device_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function hasActiveAssignment(?int $ignoreAssignmentId = null): bool
    {
        return AssetAssignmentItem::query()
            ->where('corp_device_id', $this->id)
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

    public function getDescricaoAttribute(): string
    {
        return trim($this->marca.' '.$this->modelo);
    }

    public function getImei1FormatadoAttribute(): ?string
    {
        return self::formatImei($this->imei_1);
    }

    public function getImei2FormatadoAttribute(): ?string
    {
        return self::formatImei($this->imei_2);
    }

    /** Padrão de exibição do IMEI: XX XXXXXX XXXXXX X. */
    public static function formatImei(?string $imei): ?string
    {
        if ($imei === null || $imei === '') {
            return null;
        }

        $digits = preg_replace('/\D/', '', $imei) ?? '';

        if (strlen($digits) !== 15) {
            return $digits !== '' ? $digits : null;
        }

        return implode(' ', [
            substr($digits, 0, 2),
            substr($digits, 2, 6),
            substr($digits, 8, 6),
            substr($digits, 14, 1),
        ]);
    }
}
