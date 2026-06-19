<?php

namespace App\Http\Controllers;

use App\Services\NetworkLocationResolverService;
use App\Models\NetworkServicoConectividade;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class NetworkDatacenterController extends Controller
{
    public function dashboard(): View
    {
        if (! Schema::hasTable('network_servicos_conectividade')) {
            return view('network-datacenter.dashboard', [
                'total' => 0,
                'ativos' => 0,
                'semFicha' => 0,
                'latest' => collect(),
                'byProduto' => collect(),
                'byContratante' => collect(),
                'setupPending' => true,
            ]);
        }

        $total = (int) DB::table('network_servicos_conectividade')->count();
        $ativos = (int) DB::table('network_servicos_conectividade')->where('status', 'ativo')->count();
        $semFicha = (int) DB::table('network_servicos_conectividade')
            ->where(function ($query): void {
                $query->whereNull('ficha_storage_path')
                    ->orWhere('ficha_storage_path', '');
            })
            ->count();
        $latest = NetworkServicoConectividade::query()
            ->with(['contratante', 'produto'])
            ->orderByDesc('created_at')
            ->limit(6)
            ->get();

        $byProduto = DB::table('network_servicos_conectividade')
            ->leftJoin('network_produtos', 'network_produtos.id', '=', 'network_servicos_conectividade.produto_id')
            ->selectRaw("COALESCE(NULLIF(network_produtos.nome, ''), 'Sem produto') as nome")
            ->selectRaw('COUNT(*) as total')
            ->groupBy('nome')
            ->orderByDesc('total')
            ->limit(6)
            ->get();

        $byContratante = DB::table('network_servicos_conectividade')
            ->leftJoin('network_contratantes', 'network_contratantes.id', '=', 'network_servicos_conectividade.contratante_id')
            ->selectRaw("COALESCE(NULLIF(network_contratantes.razao_social, ''), 'Sem contratante') as nome")
            ->selectRaw('COUNT(*) as total')
            ->groupBy('nome')
            ->orderByDesc('total')
            ->limit(6)
            ->get();

        return view('network-datacenter.dashboard', compact('total', 'ativos', 'semFicha', 'latest', 'byProduto', 'byContratante'));
    }

    public function lookupLocation(Request $request, NetworkLocationResolverService $resolver): JsonResponse
    {
        $data = $request->validate([
            'q' => ['required', 'string', 'max:255'],
        ]);

        $location = $resolver->resolve($data['q']);

        return response()->json([
            'found' => $location !== null,
            'location' => $location,
        ]);
    }
}
