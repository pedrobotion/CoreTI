<?php

namespace App\Services;

use App\Models\NetworkServicoConectividade;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class NetworkFichaUploadService
{
    public function store(NetworkServicoConectividade $servico, UploadedFile $file): array
    {
        $directory = 'network-datacenter/fichas';
        $safeName = Str::slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME));
        $extension = strtolower($file->getClientOriginalExtension() ?: 'pdf');
        $filename = trim($safeName, '-') . '-' . Str::uuid() . '.' . $extension;

        if ($servico->ficha_storage_path && Storage::disk('local')->exists($servico->ficha_storage_path)) {
            Storage::disk('local')->delete($servico->ficha_storage_path);
        }

        $storedPath = $file->storeAs($directory, $filename, 'local');

        $payload = [
            'ficha_original_name' => $file->getClientOriginalName(),
            'ficha_storage_path' => $storedPath,
            'ficha_mime_type' => $file->getClientMimeType(),
            'ficha_uploaded_at' => now(),
            'ficha_uploaded_by' => Auth::id(),
        ];

        $servico->update($payload);

        return $payload;
    }
}
