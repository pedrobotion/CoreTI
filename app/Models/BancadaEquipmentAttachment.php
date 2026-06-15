<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BancadaEquipmentAttachment extends Model
{
    protected $fillable = [
        'bancada_equipment_id',
        'bancada_equipment_event_id',
        'attachment_type',
        'original_name',
        'storage_disk',
        'storage_path',
        'mime_type',
        'size_bytes',
        'uploaded_by',
        'uploaded_at',
    ];

    protected function casts(): array
    {
        return [
            'size_bytes' => 'integer',
            'uploaded_at' => 'datetime',
        ];
    }

    public function equipment(): BelongsTo
    {
        return $this->belongsTo(BancadaEquipment::class, 'bancada_equipment_id');
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(BancadaEquipmentEvent::class, 'bancada_equipment_event_id');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
