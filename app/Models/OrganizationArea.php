<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Área organizacional, espelhada de cadArea na intranet.
 *
 * Completa a estrutura que o CoreTI já mantinha (unidades, departamentos,
 * cargos, centros de custo): hr_collaborators guarda area_source_id, mas até
 * aqui não havia de onde tirar o nome da área.
 */
class OrganizationArea extends Model
{
    protected $table = 'organization_areas';

    protected $fillable = [
        'source_system',
        'source_id',
        'name',
        'division_source_id',
        'active',
        'source_hash',
        'last_synced_at',
        'raw_data',
    ];

    protected function casts(): array
    {
        return [
            'active' => 'boolean',
            'last_synced_at' => 'datetime',
            'raw_data' => 'array',
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('active', true);
    }
}
