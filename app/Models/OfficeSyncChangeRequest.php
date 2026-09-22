<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OfficeSyncChangeRequest extends Model
{
    protected $fillable = [
        'office_license_id', 'hr_collaborator_id', 'field_name', 'current_value', 'current_label',
        'proposed_value', 'proposed_label', 'current_source', 'status', 'detected_at', 'reviewed_at',
        'reviewed_by', 'review_notes', 'sync_run_id', 'proposal_hash',
    ];

    protected $casts = ['detected_at' => 'datetime', 'reviewed_at' => 'datetime'];

    public function officeLicense(): BelongsTo
    {
        return $this->belongsTo(OfficeLicense::class);
    }

    public function collaborator(): BelongsTo
    {
        return $this->belongsTo(HrCollaborator::class, 'hr_collaborator_id');
    }
}
