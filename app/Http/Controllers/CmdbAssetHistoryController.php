<?php

namespace App\Http\Controllers;

use App\Models\CmdbAsset;
use App\Services\CmdbAssetHistoryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class CmdbAssetHistoryController extends Controller
{
    public function store(Request $request, CmdbAsset $asset, CmdbAssetHistoryService $history): RedirectResponse
    {
        $data = $request->validate([
            'note' => ['required', 'string', 'max:4000'],
        ]);

        $history->record(
            $asset,
            $request->user(),
            'manual_note',
            'Nota manual',
            $data['note'],
            [],
            ['note' => $data['note']]
        );

        return back()->with('success', 'Nota adicionada ao histórico.');
    }
}
