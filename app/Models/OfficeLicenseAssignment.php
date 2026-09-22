<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OfficeLicenseAssignment extends Model
{
    protected $fillable = [
        'office_license_id',
        'microsoft_user_id',
        'microsoft_upn',
        'user_name',
        'user_email',
        'sku_id',
        'sku_part_number',
        'sku_name',
        'license_category',
        'included_in_report',
        'exclusion_reason',
        'account_enabled',
        'license_source',
        'metadata',
        'synced_at',
    ];

    protected $casts = [
        'account_enabled' => 'boolean',
        'included_in_report' => 'boolean',
        'metadata' => 'array',
        'synced_at' => 'datetime',
    ];

    public function officeLicense(): BelongsTo
    {
        return $this->belongsTo(OfficeLicense::class);
    }
}
