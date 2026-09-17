<?php

namespace App\Models;

use App\Models\Concerns\HasOperationalScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Equipamento de TI do parque distribuído — o que o ServiceDesk entrega,
 * recolhe e inventaria. Servidores, VMs e storage continuam no CMDB.
 */
class Equipment extends Model
{
    use HasOperationalScope;

    public const STATUS_NOVO = 'Novo';
    public const STATUS_DISPONIVEL = 'Disponível';
    public const STATUS_EM_USO = 'Em Uso';
    public const STATUS_EM_MANUTENCAO = 'Em Manutenção';
    public const STATUS_EM_CONSERTO = 'Em Conserto';
    public const STATUS_EMPRESTADO = 'Emprestado';
    public const STATUS_BAIXADO = 'Baixado';

    public const STATUSES = [
        self::STATUS_NOVO,
        self::STATUS_DISPONIVEL,
        self::STATUS_EM_USO,
        self::STATUS_EM_MANUTENCAO,
        self::STATUS_EM_CONSERTO,
        self::STATUS_EMPRESTADO,
        self::STATUS_BAIXADO,
    ];

    /** Só estes podem ser vinculados a um colaborador; o resto é de unidade. */
    public const TIPOS_VINCULAVEIS = ['Notebook', 'Computador', 'Monitor'];

    public const TIPOS = [
        'Computador',
        'Notebook',
        'Impressora',
        'Nobreak',
        'Switch',
        'Firewall',
        'TV',
        'Central Telefônica',
        'Storage',
        'Monitor',
        'Repetidor de Sinal Celular',
        'Roteador',
        'Relógio Ponto',
        'Projetor de Imagem',
        'Áudio',
        'Outros',
    ];

    protected $table = 'equipments';

    protected $fillable = [
        'origem_gestaoti_id',
        'scope',
        'tipo',
        'nome',
        'patrimonio',
        'numero_serie',
        'endereco_ip',
        'organization_unit_id',
        'organization_department_id',
        'centro_custo_codigo',
        'centro_custo_nome',
        'localizacao',
        'status',
        'observacoes',
        'created_by',
        'updated_by',
    ];

    public function unit(): BelongsTo
    {
        return $this->belongsTo(OrganizationUnit::class, 'organization_unit_id');
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(OrganizationDepartment::class, 'organization_department_id');
    }

    /**
     * Onde o equipamento está, para exibição.
     *
     * A unidade é a resposta normal; 'localizacao' cobre o que não é unidade —
     * bancada, empréstimo, remessa em trânsito.
     */
    public function getLocalAttribute(): string
    {
        return $this->unit?->name ?: ($this->localizacao ?: '—');
    }

    public function assignmentItems(): HasMany
    {
        return $this->hasMany(AssetAssignmentItem::class, 'equipment_id');
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
            ->where('equipment_id', $this->id)
            ->whereHas('assignment', fn (Builder $query) => $query
                ->where('ativo', true)
                ->when($ignoreAssignmentId, fn (Builder $q) => $q->where('id', '!=', $ignoreAssignmentId)))
            ->exists();
    }

    public function isAssignable(): bool
    {
        return in_array($this->tipo, self::TIPOS_VINCULAVEIS, true);
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
}
