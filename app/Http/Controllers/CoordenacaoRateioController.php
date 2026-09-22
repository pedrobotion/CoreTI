<?php

namespace App\Http\Controllers;

use App\Models\CoordenacaoMonthlyClosing;
use App\Services\CoordenacaoClosingService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CoordenacaoRateioController extends Controller
{
    public function index(Request $request, CoordenacaoClosingService $service): View
    {
        $month = $request->date('month')?->startOfMonth() ?? now()->startOfMonth();
        $closing = CoordenacaoMonthlyClosing::query()->firstOrCreate(['reference_month' => $month->toDateString()], ['created_by' => auth()->id()]);
        if ($request->boolean('calculate')) $closing = $service->calculate($closing, auth()->id());
        $allocations = $closing->allocations()->with('product')->orderBy('unit_key')->orderBy('product_id')->get();
        return view('coordenacao.rateio.index', compact('closing', 'allocations', 'month'));
    }

    public function unit(Request $request, string $unitKey): View
    {
        $month = $request->date('month')?->startOfMonth() ?? now()->startOfMonth();
        $closing = CoordenacaoMonthlyClosing::query()->whereDate('reference_month', $month)->with('allocations.product')->first();
        $allocations = $closing?->allocations->where('unit_key', $unitKey) ?? collect();
        return view('coordenacao.rateio.unit', compact('closing', 'allocations', 'unitKey', 'month'));
    }
}
