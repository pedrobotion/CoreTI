<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AdPurgeBatch extends Model
{
    public const STATUS_AGUARDANDO = 'aguardando_aprovacao';
    public const STATUS_APROVADO = 'aprovado';
    public const STATUS_REJEITADO = 'rejeitado';
    public const STATUS_EXECUTADO = 'executado';
    public const STATUS_EXPIRADO = 'expirado';

    protected $guarded = [];

    protected $casts = [
        'aprovado_em' => 'datetime',
        'rejeitado_em' => 'datetime',
        'expira_em' => 'datetime',
        'executado_em' => 'datetime',
    ];

    public function itens(): HasMany
    {
        return $this->hasMany(AdPurgeBatchItem::class, 'batch_id');
    }

    public function aprovador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'aprovado_por');
    }

    public function rejeitador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rejeitado_por');
    }

    /**
     * Só um lote aprovado, dentro da validade e ainda não executado pode ser
     * entregue ao servidor do AD.
     *
     * A checagem de validade fica aqui e não só no comando de expiração: se o
     * agendador falhar, um lote vencido continuaria marcado como aprovado e
     * seria executado com uma lista velha.
     */
    public function liberadoParaExecucao(): bool
    {
        if ($this->status !== self::STATUS_APROVADO) {
            return false;
        }

        return ! $this->venceu();
    }

    public function venceu(): bool
    {
        return $this->expira_em !== null && $this->expira_em->isPast();
    }

    public function pendente(): bool
    {
        return $this->status === self::STATUS_AGUARDANDO;
    }

    public function rotulo(): string
    {
        return match ($this->status) {
            self::STATUS_AGUARDANDO => 'Aguardando aprovação',
            self::STATUS_APROVADO => 'Aprovado',
            self::STATUS_REJEITADO => 'Rejeitado',
            self::STATUS_EXECUTADO => 'Executado',
            self::STATUS_EXPIRADO => 'Expirado',
            default => $this->status,
        };
    }

    /** Classes literais: o Tailwind só enxerga nomes de classe completos. */
    public function classesBadge(): string
    {
        return match ($this->status) {
            self::STATUS_AGUARDANDO => 'bg-amber-100 text-amber-800 ring-amber-600/20',
            self::STATUS_APROVADO => 'bg-blue-100 text-blue-800 ring-blue-600/20',
            self::STATUS_REJEITADO => 'bg-slate-100 text-slate-700 ring-slate-600/20',
            self::STATUS_EXECUTADO => 'bg-emerald-100 text-emerald-800 ring-emerald-600/20',
            self::STATUS_EXPIRADO => 'bg-red-100 text-red-800 ring-red-600/20',
            default => 'bg-slate-100 text-slate-700 ring-slate-600/20',
        };
    }
}
