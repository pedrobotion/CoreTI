<?php

namespace App\Http\Controllers;

use App\Models\CmdbAsset;
use App\Models\CmdbAssetDocument;
use App\Services\CmdbAssetHistoryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class CmdbAssetDocumentController extends Controller
{
    private const TYPES = ['document', 'photo', 'manual', 'invoice', 'contract', 'certificate', 'network_diagram', 'backup_evidence', 'other'];

    public function store(Request $request, CmdbAsset $asset, CmdbAssetHistoryService $history): RedirectResponse
    {
        $data = $request->validate([
            'document_type' => ['required', 'string', Rule::in(self::TYPES)],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'file' => ['required', 'file', 'max:10240', 'mimes:pdf,jpg,jpeg,png,gif,bmp,webp,txt,doc,docx,xls,xlsx'],
        ]);

        $file = $request->file('file');
        $path = $file->store("cmdb/assets/{$asset->id}/documents", 'local');

        $document = CmdbAssetDocument::create([
            'asset_id' => $asset->id,
            'user_id' => $request->user()?->id,
            'document_type' => $data['document_type'],
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'file_path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getClientMimeType(),
            'file_size' => $file->getSize(),
        ]);

        $history->record(
            $asset,
            $request->user(),
            in_array($document->document_type, ['photo', 'network_diagram'], true) ? 'photo_uploaded' : 'document_uploaded',
            $document->title,
            $document->description,
            [],
            $document->only(['document_type', 'original_name', 'mime_type', 'file_size'])
        );

        return back()->with('success', 'Arquivo enviado com sucesso.');
    }

    public function destroy(Request $request, CmdbAsset $asset, CmdbAssetDocument $document, CmdbAssetHistoryService $history): RedirectResponse
    {
        abort_unless($document->asset_id === $asset->id, 404);

        $history->record(
            $asset,
            $request->user(),
            in_array($document->document_type, ['photo', 'network_diagram'], true) ? 'photo_deleted' : 'document_deleted',
            'Arquivo removido',
            $document->title,
            $document->only(['document_type', 'original_name', 'mime_type', 'file_size']),
            []
        );

        Storage::disk('local')->delete($document->file_path);
        $document->delete();

        return back()->with('success', 'Arquivo removido com sucesso.');
    }

    public function download(Request $request, CmdbAsset $asset, CmdbAssetDocument $document)
    {
        abort_unless($document->asset_id === $asset->id, 404);

        $disk = Storage::disk('local');
        abort_unless($disk->exists($document->file_path), 404);

        $path = $disk->path($document->file_path);
        $inline = $request->boolean('inline') && Str::startsWith((string) $document->mime_type, 'image/');

        return $inline
            ? response()->file($path, ['Content-Type' => $document->mime_type ?? 'application/octet-stream'])
            : response()->download($path, $document->original_name, ['Content-Type' => $document->mime_type ?? 'application/octet-stream']);
    }
}
