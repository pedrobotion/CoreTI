<?php

namespace App\Http\Controllers;

use App\Models\CoordenacaoPendingItem;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class CoordenacaoPendingController extends Controller
{
    public function index(): View { return view('coordenacao.pending.index', ['items' => CoordenacaoPendingItem::query()->where('status', 'open')->latest()->paginate(30)]); }
    public function resolve(CoordenacaoPendingItem $item): RedirectResponse { $item->update(['status' => 'resolved']); return back()->with('success', 'Pendência resolvida.'); }
}
