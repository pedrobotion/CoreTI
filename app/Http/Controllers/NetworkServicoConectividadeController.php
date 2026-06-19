<?php

namespace App\Http\Controllers;

use App\Models\NetworkContratante;
use App\Models\NetworkProduto;
use App\Models\NetworkServicoConectividade;
use App\Services\NetworkFichaIaExtractionService;
use App\Services\NetworkFichaUploadService;
use App\Services\NetworkLocationResolverService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Illuminate\Pagination\LengthAwarePaginator;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class NetworkServicoConectividadeController extends Controller
{
    private const VELOCIDADES = [
        '0 Mbps',
        '10 Mbps',
        '20 Mbps',
        '30 Mbps',
        '50 Mbps',
        '100 Mbps',
        '150 Mbps',
        '200 Mbps',
        '300 Mbps',
        '400 Mbps',
        '500 Mbps',
        '600 Mbps',
        '700 Mbps',
        '800 Mbps',
        '900 Mbps',
        '1 Gbps',
    ];

    public function index(Request $request): View
    {
        if (! Schema::hasTable('network_servicos_conectividade')) {
            $records = new LengthAwarePaginator(
                [],
                0,
                15,
                1,
                [
                    'path' => $request->url(),
                    'query' => $request->query(),
                ]
            );

            return view('network-datacenter.servicos.index', [
                'records' => $records,
                'filters' => [],
                'search' => '',
                'perPage' => 15,
                'contratantes' => collect(),
                'produtos' => collect(),
                'velocidades' => self::VELOCIDADES,
                'setupPending' => true,
            ]);
        }

        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
            'contrato' => ['nullable', 'string', 'max:120'],
            'contratante_id' => ['nullable', 'integer', 'exists:network_contratantes,id'],
            'produto_id' => ['nullable', 'integer', 'exists:network_produtos,id'],
            'local' => ['nullable', 'string', 'max:120'],
            'municipio' => ['nullable', 'string', 'max:120'],
            'status' => ['nullable', 'string', 'max:20'],
            'per_page' => ['nullable', 'integer', 'in:10,15,25,50'],
        ]);

        $search = trim((string) ($filters['q'] ?? ''));
        $contrato = trim((string) ($filters['contrato'] ?? ''));
        $local = trim((string) ($filters['local'] ?? ''));
        $municipio = trim((string) ($filters['municipio'] ?? ''));
        $status = trim((string) ($filters['status'] ?? ''));
        $perPage = (int) ($filters['per_page'] ?? 15);

        $query = NetworkServicoConectividade::query()->with(['contratante', 'produto']);

        if ($search !== '') {
            $query->where(function ($q) use ($search): void {
                $q->where('contrato', 'like', "%{$search}%")
                    ->orWhere('razao_social', 'like', "%{$search}%")
                    ->orWhere('endereco_instalacao', 'like', "%{$search}%")
                    ->orWhere('local_nome', 'like', "%{$search}%")
                    ->orWhere('observacao', 'like', "%{$search}%");
            });
        }

        if ($contrato !== '') {
            $query->where('contrato', 'like', "%{$contrato}%");
        }

        if (! empty($filters['contratante_id'])) {
            $query->where('contratante_id', (int) $filters['contratante_id']);
        }

        if (! empty($filters['produto_id'])) {
            $query->where('produto_id', (int) $filters['produto_id']);
        }

        if ($local !== '') {
            $query->where(function ($q) use ($local): void {
                $q->where('local_nome', 'like', "%{$local}%")
                    ->orWhere('local_tipo', 'like', "%{$local}%")
                    ->orWhere('endereco_instalacao', 'like', "%{$local}%");
            });
        }

        if ($municipio !== '') {
            $query->where('municipio', 'like', "%{$municipio}%");
        }

        if ($status !== '') {
            $query->where('status', $status);
        }

        $records = $query
            ->orderByDesc('created_at')
            ->paginate($perPage)
            ->withQueryString();

        return view('network-datacenter.servicos.index', [
            'records' => $records,
            'filters' => $filters,
            'search' => $search,
            'perPage' => $perPage,
            'contratantes' => NetworkContratante::query()->where('ativo', true)->orderBy('razao_social')->get(),
            'produtos' => NetworkProduto::query()->where('ativo', true)->orderBy('nome')->get(),
            'velocidades' => self::VELOCIDADES,
        ]);
    }

    public function create(NetworkLocationResolverService $locationResolver): View
    {
        return view('network-datacenter.servicos.create', $this->formData(null, $locationResolver));
    }

    public function store(Request $request, NetworkLocationResolverService $locationResolver): RedirectResponse
    {
        $data = $this->validateData($request);
        $payload = $this->buildPayload($request, $data, $locationResolver);
        $payload['created_by'] = Auth::id();
        $payload['updated_by'] = Auth::id();

        $servico = NetworkServicoConectividade::create($payload);

        if ($request->hasFile('ficha_pdf')) {
            app(NetworkFichaUploadService::class)->store($servico, $request->file('ficha_pdf'));
        }

        return redirect()->route('network-datacenter.servicos.show', $servico)->with('success', 'Serviço cadastrado com sucesso.');
    }

    public function show(NetworkServicoConectividade $servico, NetworkLocationResolverService $locationResolver): View
    {
        $servico->load(['contratante', 'produto', 'fichaUploader', 'creator', 'updater']);

        return view('network-datacenter.servicos.show', array_merge($this->formData($servico, $locationResolver), [
            'servico' => $servico,
        ]));
    }

    public function edit(NetworkServicoConectividade $servico, NetworkLocationResolverService $locationResolver): View
    {
        $servico->load(['contratante', 'produto']);

        return view('network-datacenter.servicos.edit', array_merge($this->formData($servico, $locationResolver), [
            'servico' => $servico,
        ]));
    }

    public function update(Request $request, NetworkServicoConectividade $servico, NetworkLocationResolverService $locationResolver): RedirectResponse
    {
        $data = $this->validateData($request, $servico);
        $payload = $this->buildPayload($request, $data, $locationResolver);
        $payload['updated_by'] = Auth::id();

        $servico->update($payload);

        if ($request->hasFile('ficha_pdf')) {
            app(NetworkFichaUploadService::class)->store($servico, $request->file('ficha_pdf'));
        }

        return redirect()->route('network-datacenter.servicos.show', $servico)->with('success', 'Serviço atualizado com sucesso.');
    }

    public function uploadFicha(Request $request, NetworkServicoConectividade $servico, NetworkFichaUploadService $uploadService): RedirectResponse
    {
        $data = $request->validate([
            'ficha_pdf' => ['required', 'file', 'mimetypes:application/pdf', 'max:20480'],
        ]);

        $uploadService->store($servico, $request->file('ficha_pdf'));

        return back()->with('success', 'Ficha anexada com sucesso.');
    }

    public function downloadFicha(NetworkServicoConectividade $servico): BinaryFileResponse
    {
        abort_unless($servico->ficha_storage_path && Storage::disk('local')->exists($servico->ficha_storage_path), 404, 'Ficha não encontrada.');

        return response()->download(
            Storage::disk('local')->path($servico->ficha_storage_path),
            $servico->ficha_original_name ?: basename($servico->ficha_storage_path)
        );
    }

    public function extractAi(NetworkServicoConectividade $servico, NetworkFichaIaExtractionService $aiService): RedirectResponse
    {
        $result = $aiService->extract($servico);
        $servico->update([
            'ia_status' => $result['status'],
            'ia_resultado' => $result['resultado'],
            'ia_processado_em' => in_array($result['status'], ['processado', 'nao_configurada'], true) ? now() : null,
        ]);

        $message = $result['message'] ?? 'Processamento concluído.';

        return back()->with($result['status'] === 'erro' ? 'error' : 'success', $message);
    }

    public function lookupLocation(Request $request, NetworkLocationResolverService $locationResolver): \Illuminate\Http\JsonResponse
    {
        $data = $request->validate([
            'q' => ['required', 'string', 'max:255'],
        ]);

        return response()->json([
            'found' => ($location = $locationResolver->resolve($data['q'])) !== null,
            'location' => $location ?? null,
        ]);
    }

    private function validateData(Request $request, ?NetworkServicoConectividade $servico = null): array
    {
        return $request->validate([
            'contrato' => ['nullable', 'string', 'max:255'],
            'contratante_id' => ['nullable', 'integer', 'exists:network_contratantes,id'],
            'razao_social' => ['nullable', 'string', 'max:255'],
            'velocidade_nominal' => ['nullable', 'string', Rule::in(self::VELOCIDADES)],
            'produto_id' => ['nullable', 'integer', 'exists:network_produtos,id'],
            'rede' => ['nullable', 'string', 'max:255'],
            'mascara' => ['nullable', 'string', 'max:255'],
            'gateway' => ['nullable', 'string', 'max:255'],
            'broadcast' => ['nullable', 'string', 'max:255'],
            'faixa' => ['nullable', 'string', 'max:255'],
            'qtd' => ['nullable', 'string', 'max:50'],
            'endereco_instalacao' => ['nullable', 'string'],
            'estacao_a' => ['nullable', 'string', 'max:255'],
            'endereco' => ['nullable', 'string', 'max:255'],
            'numero' => ['nullable', 'string', 'max:50'],
            'bairro' => ['nullable', 'string', 'max:255'],
            'municipio' => ['nullable', 'string', 'max:255'],
            'uf' => ['nullable', 'string', 'max:10'],
            'contato' => ['nullable', 'string', 'max:255'],
            'outros_contatos' => ['nullable', 'string'],
            'local_busca' => ['nullable', 'string', 'max:255'],
            'local_tipo' => ['nullable', 'string', 'max:50'],
            'local_nome' => ['nullable', 'string', 'max:255'],
            'unidade_id' => ['nullable', 'integer'],
            'departamento_id' => ['nullable', 'integer'],
            'observacao' => ['nullable', 'string'],
            'status' => ['nullable', 'string', 'max:20'],
            'ficha_pdf' => ['nullable', 'file', 'mimetypes:application/pdf', 'max:20480'],
        ]);
    }

    private function buildPayload(Request $request, array $data, NetworkLocationResolverService $locationResolver): array
    {
        $location = null;
        $locationTerm = trim((string) ($data['local_busca'] ?? ''));
        if ($locationTerm !== '') {
            $location = $locationResolver->resolve($locationTerm);
        }

        $payload = [
            'contrato' => trim((string) ($data['contrato'] ?? '')) ?: null,
            'contratante_id' => $data['contratante_id'] ?? null,
            'razao_social' => trim((string) ($data['razao_social'] ?? '')) ?: null,
            'velocidade_nominal' => trim((string) ($data['velocidade_nominal'] ?? '')) ?: null,
            'produto_id' => $data['produto_id'] ?? null,
            'rede' => trim((string) ($data['rede'] ?? '')) ?: null,
            'mascara' => trim((string) ($data['mascara'] ?? '')) ?: null,
            'gateway' => trim((string) ($data['gateway'] ?? '')) ?: null,
            'broadcast' => trim((string) ($data['broadcast'] ?? '')) ?: null,
            'faixa' => trim((string) ($data['faixa'] ?? '')) ?: null,
            'qtd' => trim((string) ($data['qtd'] ?? '')) ?: null,
            'endereco_instalacao' => trim((string) ($data['endereco_instalacao'] ?? '')) ?: null,
            'estacao_a' => trim((string) ($data['estacao_a'] ?? '')) ?: null,
            'endereco' => trim((string) ($data['endereco'] ?? '')) ?: null,
            'numero' => trim((string) ($data['numero'] ?? '')) ?: null,
            'bairro' => trim((string) ($data['bairro'] ?? '')) ?: null,
            'municipio' => trim((string) ($data['municipio'] ?? '')) ?: null,
            'uf' => trim((string) ($data['uf'] ?? '')) ?: null,
            'contato' => trim((string) ($data['contato'] ?? '')) ?: null,
            'outros_contatos' => trim((string) ($data['outros_contatos'] ?? '')) ?: null,
            'observacao' => trim((string) ($data['observacao'] ?? '')) ?: null,
            'status' => trim((string) ($data['status'] ?? 'ativo')) ?: 'ativo',
            'unidade_id' => $data['unidade_id'] ?? null,
            'departamento_id' => $data['departamento_id'] ?? null,
            'local_tipo' => trim((string) ($data['local_tipo'] ?? '')) ?: null,
            'local_nome' => trim((string) ($data['local_nome'] ?? '')) ?: null,
        ];

        if ($location !== null) {
            $payload['local_tipo'] = $location['type'];
            $payload['local_nome'] = $location['name'];
            $payload['unidade_id'] = $location['type'] === 'unidade' ? $location['id_unidade'] : null;
            $payload['departamento_id'] = $location['type'] === 'departamento' ? $location['id_departamento'] : null;
            if (! empty($location['endereco'])) {
                $payload['endereco_instalacao'] = $location['endereco'];
            }
        }

        if ($payload['razao_social'] === null && $payload['contratante_id']) {
            $payload['razao_social'] = NetworkContratante::query()
                ->whereKey($payload['contratante_id'])
                ->value('razao_social');
        }

        return $payload;
    }

    private function formData(?NetworkServicoConectividade $servico, NetworkLocationResolverService $locationResolver): array
    {
        return [
            'servico' => $servico,
            'contratantes' => NetworkContratante::query()->where('ativo', true)->orderBy('razao_social')->get(),
            'produtos' => NetworkProduto::query()->where('ativo', true)->orderBy('nome')->get(),
            'velocidades' => self::VELOCIDADES,
            'locationOptions' => $locationResolver->allLocations()->values()->all(),
        ];
    }
}
