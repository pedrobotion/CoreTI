<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdPurgeBatchItem extends Model
{
    public const STATUS_PENDENTE = 'pendente';
    public const STATUS_APROVADO = 'aprovado';
    public const STATUS_RECUSADO = 'recusado';
    public const STATUS_EXCLUIDO = 'excluido';
    public const STATUS_PULADO = 'pulado';
    public const STATUS_FALHOU = 'falhou';

    protected $guarded = [];

    protected $casts = [
        'desativada_em' => 'datetime',
        'ultimo_logon' => 'datetime',
        'excluido_em' => 'datetime',
        'entregue_em' => 'datetime',
        'parece_servico' => 'boolean',
        'ja_privilegiada' => 'boolean',
    ];

    public function batch(): BelongsTo
    {
        return $this->belongsTo(AdPurgeBatch::class, 'batch_id');
    }

    /**
     * Chega desmarcada na tela: conta de serviço costuma ficar desativada de
     * propósito, e marca histórica de privilégio pede conferência.
     */
    public function pedeConferencia(): bool
    {
        return $this->parece_servico || $this->ja_privilegiada;
    }

    public function scopeAprovados($query)
    {
        return $query->where('status', self::STATUS_APROVADO);
    }

    /**
     * Aprovadas e disponíveis para entrega.
     *
     * Fica de fora o que já foi entregue e ainda está dentro do prazo: duas
     * execuções simultâneas do executor receberiam a mesma lista e tentariam
     * apagar as mesmas contas.
     */
    public function scopeEntregaveis($query)
    {
        $limite = now()->subMinutes(max(1, (int) config('ad.expurgo.minutos_de_entrega', 30)));

        return $query->where('status', self::STATUS_APROVADO)
            ->where(function ($q) use ($limite) {
                $q->whereNull('entregue_em')->orWhere('entregue_em', '<', $limite);
            });
    }

    public function rotulo(): string
    {
        return match ($this->status) {
            self::STATUS_PENDENTE => 'Pendente',
            self::STATUS_APROVADO => 'Aprovado',
            self::STATUS_RECUSADO => 'Não excluir',
            self::STATUS_EXCLUIDO => 'Excluído do AD',
            self::STATUS_PULADO => 'Pulado',
            self::STATUS_FALHOU => 'Falhou',
            default => $this->status,
        };
    }

    public function classesBadge(): string
    {
        return match ($this->status) {
            self::STATUS_PENDENTE => 'bg-slate-100 text-slate-700',
            self::STATUS_APROVADO => 'bg-blue-100 text-blue-800',
            self::STATUS_RECUSADO => 'bg-slate-100 text-slate-500',
            self::STATUS_EXCLUIDO => 'bg-emerald-100 text-emerald-800',
            self::STATUS_PULADO => 'bg-amber-100 text-amber-800',
            self::STATUS_FALHOU => 'bg-red-100 text-red-800',
            default => 'bg-slate-100 text-slate-700',
        };
    }
}
