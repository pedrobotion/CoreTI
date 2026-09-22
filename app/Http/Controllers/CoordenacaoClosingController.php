<?php

namespace App\Http\Controllers;

use App\Models\CoordenacaoMonthlyClosing;
use App\Services\CoordenacaoClosingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CoordenacaoClosingController extends Controller
{
    public function index(): View { return view('coordenacao.closings.index', ['closings' => CoordenacaoMonthlyClosing::query()->latest('reference_month')->paginate(20)]); }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate(['reference_month' => ['required', 'date']]);
        CoordenacaoMonthlyClosing::firstOrCreate(['reference_month' => date('Y-m-01', strtotime($data['reference_month']))], ['created_by' => auth()->id()]);
        return back()->with('success', 'Competência criada.');
    }

    public function show(CoordenacaoMonthlyClosing $closing): View { return view('coordenacao.closings.show', compact('closing')); }

    public function calculate(CoordenacaoMonthlyClosing $closing, CoordenacaoClosingService $service): RedirectResponse
    {
        $service->calculate($closing, auth()->id());
        return back()->with('success', 'Rateio mensal calculado.');
    }

    public function review(CoordenacaoMonthlyClosing $closing): RedirectResponse { $closing->update(['status' => 'reviewed', 'reviewed_at' => now(), 'reviewed_by' => auth()->id()]); return back()->with('success', 'Competência marcada como revisada.'); }
    public function close(CoordenacaoMonthlyClosing $closing): RedirectResponse { abort_unless($closing->status === 'reviewed', 422, 'A competência precisa ser revisada antes do fechamento.'); $closing->update(['status' => 'closed', 'closed_at' => now(), 'closed_by' => auth()->id()]); return back()->with('success', 'Competência fechada e protegida contra recálculo.'); }
    public function reopen(CoordenacaoMonthlyClosing $closing): RedirectResponse { abort_unless($closing->status !== 'exported', 422, 'Competência exportada não pode ser reaberta.'); $closing->update(['status' => 'draft', 'closed_at' => null]); return back()->with('success', 'Competência reaberta.'); }
}
