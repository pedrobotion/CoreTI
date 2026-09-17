<?php

namespace App\Models;

use App\Models\Concerns\HasOperationalScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Vínculo entre colaborador e os itens que ele recebeu.
 *
 * O vínculo é a unidade de assinatura: um termo, um desvínculo, N itens.
 */
class AssetAssignment extends Model
{
    use HasOperationalScope;

    /** Prazo do termo, herdado do gestaoTI. */
    public const VIGENCIA_ANOS = 3;

    /** Janela de alerta de vencimento, em dias. */
    public const ALERTA_VENCIMENTO_DIAS = 30;

    public const TIPO_COMPLETO = 'completo';
    public const TIPO_APENAS_LINHA = 'apenas_linha';
    public const TIPO_EQUIPAMENTO = 'equipamento';
    public const TIPO_MISTO = 'misto';

    protected $table = 'asset_assignments';

    protected $fillable = [
        'origem_gestaoti_tabela',
        'origem_gestaoti_id',
        'scope',
        'hr_collaborator_id',
        'collaborator_matricula',
        'data_vinculo',
        'data_vencimento',
        'observacoes',
        'ativo',
        'termo_assinado_path',
        'termo_assinado_em',
        'motivo_desvinculo',
        'observacoes_desvinculo',
        'data_desvinculo',
        'desvinculado_por',
        'criado_por',
    ];

    protected function casts(): array
    {
        return [
            'data_vinculo' => 'datetime',
            'data_vencimento' => 'date',
            'termo_assinado_em' => 'datetime',
            'data_desvinculo' => 'datetime',
            'ativo' => 'boolean',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(AssetAssignmentItem::class);
    }

    public function collaborator(): BelongsTo
    {
        return $this->belongsTo(HrCollaborator::class, 'hr_collaborator_id');
    }

    public function criadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'criado_por');
    }

    public function desvinculadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'desvinculado_por');
    }

    /**
     * Tipo lido do conteúdo, não de uma coluna.
     *
     * No gestaoTI 'tipo_vinculo' era digitado no formulário e podia divergir
     * dos itens efetivamente vinculados. Derivando, não há divergência
     * possível.
     */
    public function getTipoAttribute(): string
    {
        $items = $this->relationLoaded('items') ? $this->items : $this->items()->get();

        $temLinha = $items->contains(fn (AssetAssignmentItem $item) => $item->corp_line_id !== null);
        $temAparelho = $items->contains(fn (AssetAssignmentItem $item) => $item->corp_device_id !== null);
        $temEquipamento = $items->contains(fn (AssetAssignmentItem $item) => $item->equipment_id !== null);

        return match (true) {
            $temEquipamento && ! $temLinha && ! $temAparelho => self::TIPO_EQUIPAMENTO,
            $temLinha && $temAparelho && ! $temEquipamento => self::TIPO_COMPLETO,
            $temLinha && ! $temAparelho && ! $temEquipamento => self::TIPO_APENAS_LINHA,
            default => self::TIPO_MISTO,
        };
    }

    public function getTipoDescricaoAttribute(): string
    {
        return match ($this->tipo) {
            self::TIPO_COMPLETO => 'Kit completo (aparelho + linha)',
            self::TIPO_APENAS_LINHA => 'Apenas linha corporativa',
            self::TIPO_EQUIPAMENTO => 'Equipamento',
            default => 'Itens diversos',
        };
    }

    public function isVencido(): bool
    {
        return $this->ativo && $this->data_vencimento?->isPast();
    }

    public function isProximoVencimento(): bool
    {
        if (! $this->ativo || $this->data_vencimento === null || $this->data_vencimento->isPast()) {
            return false;
        }

        return $this->data_vencimento->lessThanOrEqualTo(now()->addDays(self::ALERTA_VENCIMENTO_DIAS));
    }

    public function temTermoAssinado(): bool
    {
        return $this->termo_assinado_path !== null;
    }

    /**
     * Trocar item, colaborador ou data invalida a assinatura: o papel
     * assinado deixa de descrever o que está vinculado.
     */
    public function invalidarTermo(): void
    {
        $this->forceFill([
            'termo_assinado_path' => null,
            'termo_assinado_em' => null,
        ]);
    }

    /** @param  Builder<static>  $query */
    public function scopeAtivos(Builder $query): Builder
    {
        return $query->where('ativo', true);
    }

    /** @param  Builder<static>  $query */
    public function scopeInativos(Builder $query): Builder
    {
        return $query->where('ativo', false);
    }

    /** @param  Builder<static>  $query */
    public function scopeVencidos(Builder $query): Builder
    {
        return $query->where('ativo', true)->whereDate('data_vencimento', '<', now());
    }

    /** @param  Builder<static>  $query */
    public function scopeProximosVencimento(Builder $query): Builder
    {
        return $query
            ->where('ativo', true)
            ->whereDate('data_vencimento', '>=', now())
            ->whereDate('data_vencimento', '<=', now()->addDays(self::ALERTA_VENCIMENTO_DIAS));
    }

    /** @param  Builder<static>  $query */
    public function scopePendenteTermo(Builder $query): Builder
    {
        return $query->where('ativo', true)->whereNull('termo_assinado_path');
    }

    /**
     * Vínculo ativo de colaborador que já não está ativo no RH — o caso que
     * mais gera aparelho perdido: a pessoa sai e o item não volta.
     *
     * @param  Builder<static>  $query
     */
    public function scopeInconsistentes(Builder $query): Builder
    {
        return $query
            ->where('ativo', true)
            ->whereHas('collaborator', fn (Builder $q) => $q
                ->where(fn (Builder $inner) => $inner
                    ->where('active', false)
                    ->orWhere('terminated', true)));
    }
}
