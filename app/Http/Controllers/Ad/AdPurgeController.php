<?php

namespace App\Http\Controllers\Ad;

use App\Http\Controllers\Controller;
use App\Models\AdPurgeBatch;
use App\Services\Ad\AdPurgeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AdPurgeController extends Controller
{
    public function __construct(private readonly AdPurgeService $expurgo)
    {
    }

    public function index(): View
    {
        $lotes = AdPurgeBatch::withCount([
            'itens as excluidos_count' => fn ($q) => $q->where('status', 'excluido'),
        ])->orderByDesc('referencia')->paginate(20);

        return view('ad.exclusao.index', ['lotes' => $lotes]);
    }

    public function show(AdPurgeBatch $lote): View
    {
        $itens = $lote->itens()
            ->orderByDesc('dias_desativada')
            ->orderBy('nome_usuario')
            ->get();

        return view('ad.exclusao.show', [
            'lote' => $lote,
            'itens' => $itens,
            'podeDecidir' => $lote->pendente() && Auth::user()?->canModule('ad', 'execute'),
        ]);
    }

    public function aprovar(Request $request, AdPurgeBatch $lote): RedirectResponse
    {
        $dados = $request->validate([
            'itens' => ['present', 'array'],
            'itens.*' => ['integer'],
        ]);

        if (! $lote->pendente()) {
            return redirect()
                ->route('ad.exclusao.show', $lote)
                ->with('error', 'Este lote já foi decidido.');
        }

        $lote = $this->expurgo->aprovar($lote, $dados['itens'], Auth::user());

        $mensagem = $lote->total_aprovados > 0
            ? "Aprovadas {$lote->total_aprovados} contas. O servidor do AD executa na próxima verificação."
            : 'Nenhuma conta foi marcada; o lote foi registrado como rejeitado.';

        return redirect()->route('ad.exclusao.show', $lote)->with('success', $mensagem);
    }

    public function rejeitar(Request $request, AdPurgeBatch $lote): RedirectResponse
    {
        $dados = $request->validate([
            'motivo' => ['nullable', 'string', 'max:1000'],
        ]);

        if (! $lote->pendente()) {
            return redirect()
                ->route('ad.exclusao.show', $lote)
                ->with('error', 'Este lote já foi decidido.');
        }

        $this->expurgo->rejeitar($lote, Auth::user(), $dados['motivo'] ?? null);

        return redirect()
            ->route('ad.exclusao.show', $lote)
            ->with('success', 'Lote rejeitado. Nenhuma conta será excluída.');
    }
}
