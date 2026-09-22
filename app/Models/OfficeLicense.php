<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OfficeLicense extends Model
{
    protected $fillable = [
        'matricula',
        'nome',
        'email',
        'departamento_unidade',
        'unicoop_office',
        'area_office',
        'unidade_id',
        'organization_unit_id',
        'unidade_nome',
        'area_nome',
        'departamento_id',
        'organization_department_id',
        'organization_cost_center_id',
        'organization_position_id',
        'organization_assignment_source',
        'organization_assignment_locked_at',
        'organization_assignment_locked_by',
        'departamento_nome',
        'setor_nome',
        'cargo_nome',
        'empresa_nome',
        'empresa_id_cocari',
        'centro_custo',
        'centro_custo_nome',
        'colaborador_nome',
        'colaborador_source_view',
        'colaborador_match_score',
        'colaborador_match_type',
        'colaborador_matched_at',
        'colaborador_match_status',
        'colaborador_match_message',
        'office_apps',
        'office_business',
        'powerbi_pro',
        'powerbi_premium',
        'visio_plan',
        'ativo',
        'microsoft_user_id', 'microsoft_upn', 'account_enabled', 'license_skus', 'license_source',
        'organization_unit_source', 'organization_department_source', 'organization_cost_center_source',
        'organization_position_source', 'organization_assignment_updated_at', 'organization_assignment_updated_by',
        'account_classification',
        'microsoft_last_sign_in_at',
        'microsoft_last_non_interactive_sign_in_at',
        'microsoft_activity_source',
        'microsoft_activity_checked_at',
    ];

    protected $casts = [
        'office_apps' => 'boolean',
        'office_business' => 'boolean',
        'powerbi_pro' => 'boolean',
        'powerbi_premium' => 'boolean',
        'visio_plan' => 'boolean',
        'ativo' => 'boolean',
        'unidade_id' => 'integer',
        'departamento_id' => 'integer',
        'organization_unit_id' => 'integer',
        'organization_department_id' => 'integer',
        'organization_cost_center_id' => 'integer',
        'organization_position_id' => 'integer',
        'organization_assignment_locked_at' => 'datetime',
        'organization_assignment_locked_by' => 'integer',
        'colaborador_match_score' => 'decimal:2',
        'colaborador_matched_at' => 'datetime',
        'account_enabled' => 'boolean',
        'license_skus' => 'array',
        'organization_assignment_updated_at' => 'datetime',
        'organization_assignment_updated_by' => 'integer',
        'microsoft_last_sign_in_at' => 'datetime',
        'microsoft_last_non_interactive_sign_in_at' => 'datetime',
        'microsoft_activity_checked_at' => 'datetime',
    ];

    public function organizationUnit(): BelongsTo
    {
        return $this->belongsTo(OrganizationUnit::class, 'organization_unit_id');
    }

    public function organizationDepartment(): BelongsTo
    {
        return $this->belongsTo(OrganizationDepartment::class, 'organization_department_id');
    }

    public function organizationCostCenter(): BelongsTo
    {
        return $this->belongsTo(OrganizationCostCenter::class, 'organization_cost_center_id');
    }

    public function syncChangeRequests(): HasMany
    {
        return $this->hasMany(OfficeSyncChangeRequest::class);
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(OfficeLicenseAssignment::class);
    }
}
