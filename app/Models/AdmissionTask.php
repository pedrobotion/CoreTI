<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdmissionTask extends Model
{
    public const STATUS_PENDENTE = 'pendente';
    public const STATUS_EM_ANDAMENTO = 'em_andamento';
    public const STATUS_CONCLUIDO = 'concluido';
    public const STATUS_BLOQUEADO = 'bloqueado';
    public const STATUS_NAO_APLICAVEL = 'nao_aplicavel';

    /** Status que não impedem o caso de ficar pronto. */
    public const STATUS_FINALIZADOS = [self::STATUS_CONCLUIDO, self::STATUS_NAO_APLICAVEL];

    protected $fillable = [
        'admission_case_id',
        'task_type',
        'area',
        'status',
        'due_at',
        'obrigatorio',
        'observacao',
        'config',
        'jira_issue_key',
        'jira_issue_url',
        'dispatched_at',
        'resultado',
        'concluded_by',
        'concluded_at',
        'bloqueio_motivo',
    ];

    protected function casts(): array
    {
        return [
            'due_at' => 'date',
            'obrigatorio' => 'boolean',
            'config' => 'array',
            'resultado' => 'array',
            'dispatched_at' => 'datetime',
            'concluded_at' => 'datetime',
        ];
    }

    public function admissionCase(): BelongsTo
    {
        return $this->belongsTo(AdmissionCase::class);
    }

    public function concludedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'concluded_by');
    }

    public function scopePendentes(Builder $query): Builder
    {
        return $query->whereNotIn('status', self::STATUS_FINALIZADOS);
    }

    public function scopeDaArea(Builder $query, string $area): Builder
    {
        return $query->where('area', $area);
    }

    public function isFinalizado(): bool
    {
        return in_array($this->status, self::STATUS_FINALIZADOS, true);
    }

    public function estaAtrasado(): bool
    {
        return ! $this->isFinalizado()
            && $this->due_at !== null
            && $this->due_at->isPast();
    }

    public function label(): string
    {
        return (string) config("admissao.task_types.{$this->task_type}.label", $this->task_type);
    }

    public function areaLabel(): string
    {
        return (string) config("admissao.areas.{$this->area}.label", $this->area);
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            self::STATUS_PENDENTE => 'Pendente',
            self::STATUS_EM_ANDAMENTO => 'Em andamento',
            self::STATUS_CONCLUIDO => 'Concluído',
            self::STATUS_BLOQUEADO => 'Bloqueado',
            self::STATUS_NAO_APLICAVEL => 'Não aplicável',
            default => $this->status,
        };
    }
}
