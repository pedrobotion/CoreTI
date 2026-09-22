<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Schema;

class AdmissionCase extends Model
{
    public const STATUS_RASCUNHO = 'rascunho';
    public const STATUS_ENVIADO = 'enviado';
    public const STATUS_PROVISIONANDO = 'provisionando';
    public const STATUS_PRONTO = 'pronto';
    public const STATUS_CONCLUIDO = 'concluido';
    public const STATUS_CANCELADO = 'cancelado';

    public const TIPO_ADMISSAO = 'admissao';
    public const TIPO_TRANSFERENCIA = 'transferencia';
    public const TIPO_PROMOCAO = 'promocao';

    /** Status em que o caso ainda pode ser editado pelo RH. */
    public const STATUS_EDITAVEIS = [self::STATUS_RASCUNHO];

    /** Status que contam como "em andamento" para o painel. */
    public const STATUS_ABERTOS = [
        self::STATUS_ENVIADO,
        self::STATUS_PROVISIONANDO,
        self::STATUS_PRONTO,
    ];

    private static ?bool $estruturaDisponivel = null;

    protected $fillable = [
        'codigo',
        'tipo',
        'status',
        'hr_collaborator_id',
        'nome',
        'matricula',
        'cpf',
        'email_pessoal',
        'telefone',
        'cargo_source_id',
        'cargo_nome',
        'area_source_id',
        'area_nome',
        'departamento_source_id',
        'unidade_source_id',
        'unidade_nome',
        'centro_custo',
        'gestor_source_id',
        'gestor_nome',
        'gestor_email',
        'data_admissao',
        'data_inicio',
        'tamanho_uniforme',
        'tamanho_calcado',
        'epi',
        'substitui_matricula',
        'substitui_nome',
        'observacoes',
        'dados_extras',
        'created_by',
        'submitted_by',
        'submitted_at',
        'concluded_at',
        'cancelled_at',
        'cancel_reason',
    ];

    protected function casts(): array
    {
        return [
            'data_admissao' => 'date',
            'data_inicio' => 'date',
            'epi' => 'array',
            'dados_extras' => 'array',
            'submitted_at' => 'datetime',
            'concluded_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    /**
     * As migrations do módulo já rodaram? Memoizado — usado pelo menu lateral
     * para não oferecer um link que resultaria em erro de SQL.
     */
    public static function estruturaDisponivel(): bool
    {
        return self::$estruturaDisponivel ??= Schema::hasTable('admission_cases');
    }

    /**
     * Usado apenas por testes que recriam o schema entre casos.
     */
    public static function esquecerEstrutura(): void
    {
        self::$estruturaDisponivel = null;
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(AdmissionTask::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(AdmissionCaseEvent::class)->latest();
    }

    public function collaborator(): BelongsTo
    {
        return $this->belongsTo(HrCollaborator::class, 'hr_collaborator_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function scopeAbertos(Builder $query): Builder
    {
        return $query->whereIn('status', self::STATUS_ABERTOS);
    }

    public function isEditavel(): bool
    {
        return in_array($this->status, self::STATUS_EDITAVEIS, true);
    }

    public function isCancelado(): bool
    {
        return $this->status === self::STATUS_CANCELADO;
    }

    /**
     * Dias corridos até a data de início. Negativo = colaborador já começou.
     */
    public function diasParaInicio(): ?int
    {
        if (! $this->data_inicio) {
            return null;
        }

        return (int) now()->startOfDay()->diffInDays($this->data_inicio->startOfDay(), false);
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            self::STATUS_RASCUNHO => 'Rascunho',
            self::STATUS_ENVIADO => 'Enviado',
            self::STATUS_PROVISIONANDO => 'Em provisionamento',
            self::STATUS_PRONTO => 'Pronto',
            self::STATUS_CONCLUIDO => 'Concluído',
            self::STATUS_CANCELADO => 'Cancelado',
            default => $this->status,
        };
    }

    public function tipoLabel(): string
    {
        return match ($this->tipo) {
            self::TIPO_ADMISSAO => 'Admissão',
            self::TIPO_TRANSFERENCIA => 'Transferência',
            self::TIPO_PROMOCAO => 'Promoção',
            default => $this->tipo,
        };
    }
}
