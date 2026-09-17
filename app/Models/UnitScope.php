<?php

namespace App\Models;

use App\Support\ScopeRegistry;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * De/para unidade da intranet → escopo operacional (Sede ou Cerrado).
 *
 * Unidade ausente desta tabela é unidade pendente de classificação, não
 * unidade da Sede: a ausência nunca é lida como resposta.
 */
class UnitScope extends Model
{
    protected $table = 'unit_scopes';

    protected $fillable = [
        'organization_unit_source_id',
        'scope',
        'notes',
        'assigned_by',
    ];

    protected static function booted(): void
    {
        // O mapa é lido em toda resolução de escopo e muda muito pouco.
        $forget = static fn () => ScopeRegistry::forgetUnitMap();

        static::saved($forget);
        static::deleted($forget);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(OrganizationUnit::class, 'organization_unit_source_id', 'source_id');
    }

    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }
}
