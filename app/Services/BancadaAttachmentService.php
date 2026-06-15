<?php

namespace App\Services;

use App\Models\BancadaEquipment;
use App\Models\BancadaEquipmentAttachment;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;

class BancadaAttachmentService
{
    public function store(
        BancadaEquipment $equipment,
        UploadedFile $file,
        string $attachmentType,
        ?int $eventId = null,
        string $disk = 'local'
    ): BancadaEquipmentAttachment {
        $path = $file->store('bancada/attachments', $disk);

        return BancadaEquipmentAttachment::create([
            'bancada_equipment_id' => $equipment->id,
            'bancada_equipment_event_id' => $eventId,
            'attachment_type' => $attachmentType,
            'original_name' => $file->getClientOriginalName(),
            'storage_disk' => $disk,
            'storage_path' => $path,
            'mime_type' => $file->getClientMimeType(),
            'size_bytes' => $file->getSize(),
            'uploaded_by' => Auth::id(),
            'uploaded_at' => now(),
        ]);
    }
}
