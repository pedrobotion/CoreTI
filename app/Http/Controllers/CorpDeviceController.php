<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\PaginacaoConfiguravel;
use App\Http\Controllers\Concerns\ResolvesOperationalScope;
use App\Models\AssetAssignment;
use App\Models\CorpDevice;
use App\Services\Telefonia\RelatorioExportService;
use App\Support\ScopeRegistry;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CorpDeviceController extends Controller
{
    use PaginacaoConfiguravel;
    use ResolvesOperationalScope;

    public function index(Request $request): View
    {
        $user = $request->user();
        $filters = $this->filters($request);

        $devices = $this->aplicarFiltros(CorpDevice::query()->visibleTo($user), $filters)
            ->withCount(['assignmentItems as vinculos_ativos_count' => fn ($q) => $q
                ->whereHas('assignment', fn ($a) => $a->where('ativo', true))])
            ->orderBy('marca')
            ->orderBy('modelo')
            ->paginate($this->porPagina($request))
            ->withQueryString();

        $base = CorpDevice::query()->visibleTo($user)->inScope($filters['scope']);

        $resumo = [
            'total' => (clone $base)->count(),
            'disponiveis' => (clone $base)->where('status', CorpDevice::STATUS_DISPONIVEL)->count(),
            'em_uso' => (clone $base)->where('status', CorpDevice::STATUS_EM_USO)->count(),
            'baixados' => (clone $base)->where('status', CorpDevice::STATUS_BAIXADO)->count(),
            'novos' => (clone $base)->where('estado_aparelho', CorpDevice::ESTADO_NOVO)->count(),
            // Mesmo problema das linhas: 11 aparelhos assim na base do gestaoTI.
            'em_uso_sem_vinculo' => (clone $base)
                ->where('status', CorpDevice::STATUS_EM_USO)
                ->whereDoesntHave('assignmentItems.assignment', fn ($q) => $q->where('ativo', true))
                ->count(),
        ];

        return view('telefonia.devices.index', [
            'devices' => $devices,
            'filters' => $filters,
            'resumo' => $resumo,
            'scopeOptions' => $this->scopeOptions($user),
            'marcas' => CorpDevice::query()->visibleTo($user)->distinct()->orderBy('marca')->pluck('marca'),
            'statuses' => CorpDevice::STATUSES,
            'estados' => CorpDevice::ESTADOS,
        ]);
    }

    public function create(Request $request): View
    {
        return view('telefonia.devices.create', [
            'device' => new CorpDevice([
                'status' => CorpDevice::STATUS_DISPONIVEL,
                'estado_aparelho' => CorpDevice::ESTADO_USADO,
            ]),
            'scopeOptions' => $this->scopeOptions($request->user()),
            'statuses' => CorpDevice::STATUSES,
            'estados' => CorpDevice::ESTADOS,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $data['scope'] = $this->resolveScope($request->user(), $request->input('scope'));
        $data['created_by'] = $request->user()?->id;

        $device = CorpDevice::query()->create($data);

        return redirect()
            ->route('administrativo.telefonia.devices.index')
            ->with('success', "Aparelho {$device->descricao} cadastrado.");
    }

    public function edit(Request $request, CorpDevice $device): View
    {
        $this->ensureScopeIsVisible($request->user(), $device->scope);

        return view('telefonia.devices.edit', [
            'device' => $device,
            'scopeOptions' => $this->scopeOptions($request->user()),
            'statuses' => CorpDevice::STATUSES,
            'estados' => CorpDevice::ESTADOS,
            'temVinculoAtivo' => $device->hasActiveAssignment(),
            'temHistorico' => $device->assignmentItems()->exists(),
        ]);
    }

    public function update(Request $request, CorpDevice $device): RedirectResponse
    {
        $this->ensureScopeIsVisible($request->user(), $device->scope);

        $data = $this->validated($request, $device);
        $data['scope'] = $this->resolveScope($request->user(), $request->input('scope'));
        $data['updated_by'] = $request->user()?->id;

        $temVinculoAtivo = $device->hasActiveAssignment();

        // Dar baixa num aparelho que está com alguém é o caminho mais curto
        // para o aparelho sumir do controle sem nunca ter voltado.
        if ($temVinculoAtivo && $data['status'] !== CorpDevice::STATUS_EM_USO) {
            throw ValidationException::withMessages([
                'status' => 'Este aparelho está vinculado a um colaborador. Desvincule antes de mudar a situação dele.',
            ]);
        }

        if ($temVinculoAtivo && $data['scope'] !== $device->scope) {
            throw ValidationException::withMessages([
                'scope' => 'Este aparelho tem vínculo ativo e não pode mudar de operação. Desvincule antes.',
            ]);
        }

        $device->update($data);

        return redirect()
            ->route('administrativo.telefonia.devices.index')
            ->with('success', "Aparelho {$device->descricao} atualizado.");
    }

    public function destroy(Request $request, CorpDevice $device): RedirectResponse
    {
        $this->ensureScopeIsVisible($request->user(), $device->scope);

        if ($device->assignmentItems()->exists()) {
            return back()->with('error', 'Este aparelho tem histórico de vínculo e não pode ser excluído. Se ele saiu do parque, marque a situação como Baixado.');
        }

        $descricao = $device->descricao;
        $device->delete();

        return redirect()
            ->route('administrativo.telefonia.devices.index')
            ->with('success', "Aparelho {$descricao} excluído.");
    }

    public function show(Request $request, CorpDevice $device): View
    {
        $this->ensureScopeIsVisible($request->user(), $device->scope);

        $device->load(['createdBy:id,name', 'updatedBy:id,name']);

        $vinculos = AssetAssignment::query()
            ->whereHas('items', fn (Builder $q) => $q->where('corp_device_id', $device->id))
            ->with(['collaborator:id,name,employee_number', 'items'])
            ->orderByDesc('data_vinculo')
            ->get();

        return view('telefonia.devices.show', [
            'device' => $device,
            'vinculos' => $vinculos,
            'vinculoAtivo' => $vinculos->firstWhere('ativo', true),
        ]);
    }

    public function exportExcel(Request $request, RelatorioExportService $export): StreamedResponse
    {
        [$cabecalhos, $registros] = $this->dadosParaExport($request);

        return $export->excel(
            'Aparelhos celulares',
            $cabecalhos,
            $registros,
            textoLiteral: [3, 4, 5],
            subtitulo: $this->descricaoDosFiltros($request),
        );
    }

    public function exportPdf(Request $request, RelatorioExportService $export): Response
    {
        [$cabecalhos, $registros] = $this->dadosParaExport($request);

        return $export->pdf(
            'Aparelhos celulares',
            $cabecalhos,
            $registros,
            subtitulo: $this->descricaoDosFiltros($request),
        );
    }

    /**
     * @return array{0: array<int, string>, 1: array<int, array<int, mixed>>}
     */
    private function dadosParaExport(Request $request): array
    {
        $filters = $this->filters($request);

        $devices = $this->aplicarFiltros(CorpDevice::query()->visibleTo($request->user()), $filters)
            ->orderBy('marca')
            ->orderBy('modelo')
            ->get();

        $cabecalhos = ['Operação', 'Marca', 'Modelo', 'IMEI 1', 'IMEI 2', 'Número de série', 'Estado', 'Situação', 'Observação'];

        $registros = $devices->map(fn (CorpDevice $device) => [
            $device->scope_label,
            $device->marca,
            $device->modelo,
            $device->imei_1,
            $device->imei_2 ?: '—',
            $device->numero_serie ?: '—',
            $device->estado_aparelho,
            $device->status,
            $device->observacao ?: '—',
        ])->all();

        return [$cabecalhos, $registros];
    }

    private function descricaoDosFiltros(Request $request): ?string
    {
        $filters = $this->filters($request);
        $partes = [];

        foreach ([
            'scope' => 'Operação',
            'marca' => 'Marca',
            'status' => 'Situação',
            'estado' => 'Estado',
            'busca' => 'Busca',
        ] as $chave => $rotulo) {
            if (($filters[$chave] ?? '') !== '') {
                $valor = $chave === 'scope' ? ScopeRegistry::label($filters[$chave]) : $filters[$chave];
                $partes[] = "{$rotulo}: {$valor}";
            }
        }

        return $partes === [] ? 'Todos os aparelhos' : implode(' · ', $partes);
    }

    /**
     * @param  Builder<CorpDevice>  $query
     * @param  array<string, string>  $filters
     * @return Builder<CorpDevice>
     */
    private function aplicarFiltros(Builder $query, array $filters): Builder
    {
        return $query
            ->inScope($filters['scope'])
            ->when($filters['marca'] !== '', fn (Builder $q) => $q->where('marca', $filters['marca']))
            ->when($filters['status'] !== '', fn (Builder $q) => $q->where('status', $filters['status']))
            ->when($filters['estado'] !== '', fn (Builder $q) => $q->where('estado_aparelho', $filters['estado']))
            ->when($filters['busca'] !== '', function (Builder $q) use ($filters): void {
                // IMEI é exibido com espaços; a busca precisa achar do mesmo jeito.
                $digitos = preg_replace('/\D/', '', $filters['busca']) ?? '';

                $q->where(function (Builder $inner) use ($filters, $digitos): void {
                    $inner->where('modelo', 'like', "%{$filters['busca']}%")
                        ->orWhere('numero_serie', 'like', "%{$filters['busca']}%");

                    if ($digitos !== '') {
                        $inner->orWhere('imei_1', 'like', "%{$digitos}%")
                            ->orWhere('imei_2', 'like', "%{$digitos}%");
                    }
                });
            });
    }

    /**
     * @return array<string, string>
     */
    private function filters(Request $request): array
    {
        return [
            'scope' => trim((string) $request->string('scope')),
            'marca' => trim((string) $request->string('marca')),
            'status' => trim((string) $request->string('status')),
            'estado' => trim((string) $request->string('estado')),
            'busca' => trim((string) $request->string('busca')),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?CorpDevice $device = null): array
    {
        // O usuário digita o IMEI como quiser; guardamos só os dígitos, para
        // que "35 483376 414055 0" e "354833764140550" não virem dois cadastros.
        // Campo em branco vira null, não string vazia: '' colidiria com o
        // índice único no segundo aparelho sem IMEI.
        foreach (['imei_1', 'imei_2'] as $campo) {
            $digitos = preg_replace('/\D/', '', (string) $request->input($campo)) ?: null;
            $request->merge([$campo => $digitos]);
        }

        return $request->validate([
            'scope' => ['nullable', 'string', Rule::in(ScopeRegistry::keys())],
            'marca' => ['required', 'string', 'max:100'],
            'modelo' => ['required', 'string', 'max:100'],
            /*
             | IMEI é opcional: aparelhos antigos entraram no parque antes de
             | o número ser anotado e não há de onde tirá-lo. Quando informado,
             | continua único e dentro da faixa — a base real do gestaoTI traz
             | 14 e 16 dígitos além dos 15 do padrão.
             */
            'imei_1' => [
                'nullable', 'digits_between:14,16',
                Rule::unique('corp_devices', 'imei_1')->ignore($device?->id),
            ],
            'imei_2' => [
                'nullable', 'digits_between:14,16', 'different:imei_1',
            ],
            'numero_serie' => ['nullable', 'string', 'max:50'],
            'status' => ['required', 'string', Rule::in(CorpDevice::STATUSES)],
            'estado_aparelho' => ['required', 'string', Rule::in(CorpDevice::ESTADOS)],
            'observacao' => ['nullable', 'string', 'max:2000'],
        ], [
            'imei_1.unique' => 'Já existe um aparelho cadastrado com este IMEI.',
            'imei_1.digits_between' => 'O IMEI deve ter entre 14 e 16 dígitos.',
            'imei_2.digits_between' => 'O IMEI 2 deve ter entre 14 e 16 dígitos.',
            'imei_2.different' => 'O IMEI 2 não pode ser igual ao IMEI 1.',
        ], [
            'scope' => 'operação',
            'imei_1' => 'IMEI 1',
            'imei_2' => 'IMEI 2',
            'numero_serie' => 'número de série',
            'estado_aparelho' => 'estado do aparelho',
        ]);
    }
}
