<?php

namespace App\Http\Controllers;

use App\Models\BancadaEquipment;
use App\Models\InternalStockMovement;
use App\Models\InternalStockProduct;
use App\Models\InternalStockUnit;
use App\Services\BancadaStatusFlowService;
use App\Services\Estoque\InternalStockService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse as HttpStreamedResponse;

/**
 * Estoque interno da TI: catálogo de produtos (monitores/computadores
 * serializados e consumíveis por quantidade, como SSD), com entrada, baixa,
 * histórico de movimentações e alerta de estoque baixo.
 */
class InternalStockController extends Controller
{
    public function __construct(private readonly InternalStockService $estoque) {}

    /** Nome da rota "index" do estoque conforme o contexto (Bancada ou Administrativo). */
    private function indexRoute(): string
    {
        return request()->routeIs('bancada-servicos.*') ? 'bancada-servicos.estoque' : 'administrativo.estoque-interno';
    }

    public function index(Request $request): View
    {
        $categoria = trim((string) $request->string('categoria'));
        $situacao = (string) $request->string('situacao', 'todos');
        $busca = trim((string) $request->string('q'));

        $produtos = InternalStockProduct::query()
            ->with(['units' => fn ($q) => $q->where('status', InternalStockUnit::STATUS_EM_ESTOQUE)])
            ->when($categoria !== '', fn ($q) => $q->where('categoria', $categoria))
            ->when($busca !== '', fn ($q) => $q->where(fn ($s) => $s
                ->where('nome', 'like', "%{$busca}%")
                ->orWhereHas('units', fn ($u) => $u
                    ->where('serial_number', 'like', "%{$busca}%")
                    ->orWhere('plaqueta', 'like', "%{$busca}%"))))
            ->orderBy('categoria')
            ->orderBy('nome')
            ->get()
            ->when($situacao === 'abaixo', fn ($c) => $c->filter->abaixo_do_minimo->values())
            ->when($situacao === 'zerado', fn ($c) => $c->filter(fn ($p) => $p->saldo === 0)->values())
            ->when($situacao === 'disponivel', fn ($c) => $c->filter(fn ($p) => $p->saldo > 0)->values());

        $todos = InternalStockProduct::query()->ativos()
            ->with(['units' => fn ($q) => $q->where('status', InternalStockUnit::STATUS_EM_ESTOQUE)])
            ->get();

        $cards = collect(InternalStockProduct::CATEGORIAS)
            ->map(fn ($cat) => (object) [
                'categoria' => $cat,
                'saldo' => $todos->where('categoria', $cat)->sum(fn ($p) => $p->saldo),
                'tipos' => $todos->where('categoria', $cat)->count(),
            ])
            ->filter(fn ($c) => $c->tipos > 0)
            ->values();

        $movimentos = InternalStockMovement::query()
            ->with(['product', 'unit', 'colaborador', 'autor'])
            ->latest()
            ->limit(15)
            ->get();

        $reposicao = BancadaEquipment::query()
            ->where('status', BancadaStatusFlowService::STATUS_AGUARDANDO_PECA)
            ->where('peca_origem', 'estoque_ti')
            ->latest('updated_at')
            ->get();

        return view('panels.administrativo.estoque-interno', [
            'produtos' => $produtos,
            'cards' => $cards,
            'abaixoDoMinimo' => $todos->filter->abaixo_do_minimo->count(),
            'movimentos' => $movimentos,
            'reposicao' => $reposicao,
            'filtros' => ['categoria' => $categoria, 'situacao' => $situacao, 'q' => $busca],
            'categorias' => InternalStockProduct::CATEGORIAS,
        ]);
    }

    public function storeProduct(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'categoria' => ['required', 'string', 'in:'.implode(',', InternalStockProduct::CATEGORIAS)],
            'nome' => ['required', 'string', 'max:255'],
            'controle' => ['required', 'string', 'in:'.implode(',', InternalStockProduct::CONTROLES)],
            'quantidade_minima' => ['nullable', 'integer', 'min:0'],
        ]);

        InternalStockProduct::create([
            'categoria' => $data['categoria'],
            'nome' => $data['nome'],
            'controle' => $data['controle'],
            'quantidade_minima' => $data['quantidade_minima'] ?? 0,
            'created_by' => $request->user()?->id,
        ]);

        return back()->with('success', 'Produto cadastrado no estoque.');
    }

    public function entradaForm(InternalStockProduct $product): View
    {
        return view('panels.administrativo.estoque-entrada', ['product' => $product]);
    }

    public function entrada(Request $request, InternalStockProduct $product): RedirectResponse
    {
        $data = $this->validarEntrada($request, $product);
        $this->estoque->entrada($product, $data, $request->user()?->id);

        return redirect()->route($this->indexRoute())->with('success', 'Entrada registrada.');
    }

    public function baixaForm(InternalStockProduct $product): View
    {
        $product->load(['units' => fn ($q) => $q->where('status', InternalStockUnit::STATUS_EM_ESTOQUE)->orderBy('serial_number')]);

        return view('panels.administrativo.estoque-baixa', ['product' => $product]);
    }

    public function baixa(Request $request, InternalStockProduct $product): RedirectResponse
    {
        $data = $this->validarBaixa($request, $product);

        try {
            $this->estoque->baixa($product, $data, $request->user()?->id);
        } catch (\InvalidArgumentException $e) {
            return back()->withInput()->withErrors(['quantidade' => $e->getMessage()]);
        }

        return redirect()->route($this->indexRoute())->with('success', 'Baixa registrada.');
    }

    public function movimentos(Request $request): View
    {
        $movimentos = InternalStockMovement::query()
            ->with(['product', 'unit', 'colaborador', 'autor'])
            ->latest()
            ->paginate(40)
            ->withQueryString();

        return view('panels.administrativo.estoque-movimentos', ['movimentos' => $movimentos]);
    }

    public function export(): HttpStreamedResponse
    {
        $produtos = InternalStockProduct::query()
            ->with(['units' => fn ($q) => $q->where('status', InternalStockUnit::STATUS_EM_ESTOQUE)])
            ->orderBy('categoria')->orderBy('nome')->get();

        $nome = 'estoque-interno-'.now()->format('Y-m-d').'.csv';

        return response()->streamDownload(function () use ($produtos) {
            $out = fopen('php://output', 'w');
            fprintf($out, "\xEF\xBB\xBF"); // BOM p/ Excel abrir UTF-8
            fputcsv($out, ['Categoria', 'Produto', 'Controle', 'Saldo', 'Mínimo', 'Abaixo do mínimo'], ';');
            foreach ($produtos as $p) {
                fputcsv($out, [
                    $p->categoria, $p->nome, $p->controle, $p->saldo, $p->quantidade_minima,
                    $p->abaixo_do_minimo ? 'SIM' : 'não',
                ], ';');
            }
            fclose($out);
        }, $nome, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** @return array<string, mixed> */
    private function validarEntrada(Request $request, InternalStockProduct $product): array
    {
        if ($product->ehSerial()) {
            return $request->validate([
                'serial_number' => ['nullable', 'string', 'max:120'],
                'plaqueta' => ['nullable', 'string', 'max:100'],
                'modelo' => ['nullable', 'string', 'max:160'],
                'estado' => ['required', 'string', 'in:'.implode(',', InternalStockUnit::ESTADOS)],
                'motivo' => ['nullable', 'string', 'max:60'],
                'observacao' => ['nullable', 'string', 'max:1000'],
            ]);
        }

        return $request->validate([
            'quantidade' => ['required', 'integer', 'min:1'],
            'motivo' => ['nullable', 'string', 'max:60'],
            'observacao' => ['nullable', 'string', 'max:1000'],
        ]);
    }

    /** @return array<string, mixed> */
    private function validarBaixa(Request $request, InternalStockProduct $product): array
    {
        $regras = [
            'motivo' => ['required', 'string', 'max:60'],
            'destino_tipo' => ['nullable', 'string', 'in:colaborador,unidade,bancada,outro'],
            'destino_descricao' => ['nullable', 'string', 'max:255'],
            'observacao' => ['nullable', 'string', 'max:1000'],
        ];

        if ($product->ehSerial()) {
            $regras['internal_stock_unit_id'] = ['required', 'integer',
                'exists:internal_stock_units,id'];
        } else {
            $regras['quantidade'] = ['required', 'integer', 'min:1'];
        }

        return $request->validate($regras);
    }
}
