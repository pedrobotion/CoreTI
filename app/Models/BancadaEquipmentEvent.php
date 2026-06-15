<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BancadaEquipmentEvent extends Model
{
    protected $fillable = [
        'bancada_equipment_id',
        'previous_status',
        'new_status',
        'action',
        'module',
        'performed_by',
        'observation',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
        ];
    }

    public function equipment(): BelongsTo
    {
        return $this->belongsTo(BancadaEquipment::class, 'bancada_equipment_id');
    }

    public function performer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'performed_by');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(BancadaEquipmentAttachment::class, 'bancada_equipment_event_id');
    }
}
