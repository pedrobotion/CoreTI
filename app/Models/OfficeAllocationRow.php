<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OfficeAllocationRow extends Model
{
    protected $fillable = [
        'batch_id',
        'office_license_id',
        'office_user_external_id',
        'user_name',
        'user_email',
        'employee_source_id',
        'employee_number',
        'sku_id',
        'sku_name',
        'organization_unit_id',
        'organization_department_id',
        'organization_cost_center_id',
        'cost_center_source_id',
        'unit_name_snapshot',
        'department_name_snapshot',
        'cost_center_code_snapshot',
        'cost_center_name_snapshot',
        'classification_type',
        'classification_status',
        'classification_message',
        'unit_price',
        'quantity',
        'total_amount',
        'metadata',
    ];

    protected $casts = [
        'batch_id' => 'integer',
        'office_license_id' => 'integer',
        'organization_unit_id' => 'integer',
        'organization_department_id' => 'integer',
        'organization_cost_center_id' => 'integer',
        'unit_price' => 'decimal:2',
        'quantity' => 'integer',
        'total_amount' => 'decimal:2',
        'metadata' => 'array',
    ];

    public function batch(): BelongsTo
    {
        return $this->belongsTo(OfficeAllocationBatch::class, 'batch_id');
    }

    public function officeLicense(): BelongsTo
    {
        return $this->belongsTo(OfficeLicense::class, 'office_license_id');
    }
}
