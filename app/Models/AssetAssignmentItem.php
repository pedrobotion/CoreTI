<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use InvalidArgumentException;

/**
 * Um item dentro de um vínculo: uma linha, um aparelho ou um equipamento.
 *
 * Exatamente uma das três colunas é preenchida. O banco garante que o item
 * vinculado não seja apagado (restrictOnDelete); esta classe garante que a
 * linha da tabela não fique vazia nem aponte para dois itens ao mesmo tempo.
 */
class AssetAssignmentItem extends Model
{
    public const COLUNAS_ITEM = ['corp_line_id', 'corp_device_id', 'equipment_id'];

    protected $table = 'asset_assignment_items';

    protected $fillable = [
        'asset_assignment_id',
        'corp_line_id',
        'corp_device_id',
        'equipment_id',
    ];

    protected static function booted(): void
    {
        $guard = static function (self $item): void {
            $preenchidas = array_filter(
                self::COLUNAS_ITEM,
                static fn (string $coluna) => $item->{$coluna} !== null
            );

            if (count($preenchidas) !== 1) {
                throw new InvalidArgumentException(
                    'Um item de vínculo aponta para exatamente uma linha, um aparelho ou um equipamento; recebido: '
                    .(count($preenchidas) === 0 ? 'nenhum' : implode(', ', $preenchidas)).'.'
                );
            }
        };

        static::saving($guard);
    }

    public function assignment(): BelongsTo
    {
        return $this->belongsTo(AssetAssignment::class, 'asset_assignment_id');
    }

    public function line(): BelongsTo
    {
        return $this->belongsTo(CorpLine::class, 'corp_line_id');
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(CorpDevice::class, 'corp_device_id');
    }

    public function equipment(): BelongsTo
    {
        return $this->belongsTo(Equipment::class, 'equipment_id');
    }

    /**
     * O item em si, seja ele qual for.
     */
    public function item(): CorpLine|CorpDevice|Equipment|null
    {
        return $this->line ?? $this->device ?? $this->equipment;
    }

    public function kind(): ?string
    {
        return match (true) {
            $this->corp_line_id !== null => 'linha',
            $this->corp_device_id !== null => 'aparelho',
            $this->equipment_id !== null => 'equipamento',
            default => null,
        };
    }

    public function tipoLabel(): string
    {
        return match ($this->kind()) {
            'linha' => 'Linha corporativa',
            'aparelho' => 'Aparelho celular',
            'equipamento' => 'Equipamento',
            default => 'Item',
        };
    }

    /**
     * Como o item aparece na tela e no termo assinado.
     *
     * O termo é documento: precisa identificar o bem sem ambiguidade, por isso
     * traz número, IMEI ou patrimônio, e não só o nome comercial.
     */
    public function descricao(): string
    {
        return match ($this->kind()) {
            'linha' => trim(sprintf(
                '%s — %s%s',
                $this->line?->numero_formatado,
                $this->line?->operadora,
                $this->line?->nome_plano ? ' · '.$this->line->nome_plano : ''
            )),
            'aparelho' => trim(sprintf(
                '%s — IMEI %s%s',
                $this->device?->descricao,
                $this->device?->imei_1_formatado,
                $this->device?->numero_serie ? ' · Série '.$this->device->numero_serie : ''
            )),
            'equipamento' => trim(sprintf(
                '%s — Patrimônio %s%s',
                $this->equipment?->nome,
                $this->equipment?->patrimonio,
                $this->equipment?->numero_serie ? ' · Série '.$this->equipment->numero_serie : ''
            )),
            default => '—',
        };
    }

    /**
     * Existe vínculo ativo para este item?
     *
     * Ponto único da regra "um item em um só vínculo ativo" — a que o MySQL
     * não consegue expressar num índice porque depende da coluna 'ativo'.
     *
     * @param  'corp_line_id'|'corp_device_id'|'equipment_id'  $coluna
     */
    public static function itemEstaVinculado(string $coluna, int $itemId, ?int $ignorarAssignmentId = null): bool
    {
        if (! in_array($coluna, self::COLUNAS_ITEM, true)) {
            throw new InvalidArgumentException("Coluna de item desconhecida: {$coluna}.");
        }

        return self::query()
            ->where($coluna, $itemId)
            ->whereHas('assignment', fn (Builder $query) => $query
                ->where('ativo', true)
                ->when($ignorarAssignmentId, fn (Builder $q) => $q->where('id', '!=', $ignorarAssignmentId)))
            ->exists();
    }
}
