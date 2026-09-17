<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\PaginacaoConfiguravel;
use App\Http\Controllers\Concerns\ResolvesOperationalScope;
use App\Models\AssetAssignment;
use App\Models\AssetAssignmentItem;
use App\Models\Equipment;
use App\Models\HrCollaborator;
use App\Models\OrganizationPosition;
use App\Models\OrganizationUnit;
use App\Services\Telefonia\RelatorioExportService;
use App\Services\Telefonia\TermoResponsabilidadeService;
use App\Support\AssignmentContext;
use App\Support\ScopeRegistry;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Vínculos entre colaborador e itens (linha, aparelho, equipamento).
 *
 * Atende os dois contextos — Telefonia e Equipamentos — com o mesmo motor.
 * Ver App\Support\AssignmentContext.
 */
class AssetAssignmentController extends Controller
{
    use PaginacaoConfiguravel;
    use ResolvesOperationalScope;

    /** Motivos de desvínculo, herdados da operação do gestaoTI. */
    public const MOTIVOS_DESVINCULO = [
        'Desligamento do colaborador',
        'Troca de aparelho',
        'Troca de linha',
        'Troca de equipamento',
        'Devolução voluntária',
        'Mudança de função',
        'Perda ou roubo',
        'Defeito',
        'Outro',
    ];

    public function index(Request $request): View
    {
        $ctx = $this->contexto($request);
        $user = $request->user();
        $filters = $this->filters($request);

        $assignments = $this->aplicarFiltros($this->baseQuery($ctx, $user), $ctx, $filters)
            ->with(['collaborator:id,name,employee_number,unit_source_id,position_source_id,active,terminated', 'items'])
            ->orderByDesc('data_vinculo')
            ->paginate($this->porPagina($request))
            ->withQueryString();

        $base = fn () => $this->baseQuery($ctx, $user)->inScope($filters['scope']);

        $resumo = [
            'total' => $base()->count(),
            'ativos' => $base()->ativos()->count(),
            'a_vencer' => $base()->proximosVencimento()->count(),
            'vencidos' => $base()->vencidos()->count(),
            'sem_termo' => $base()->pendenteTermo()->count(),
            'inconsistentes' => $base()->inconsistentes()->count(),
        ];

        return view('vinculos.index', [
            'ctx' => $ctx,
            'assignments' => $assignments,
            'filters' => $filters,
            'resumo' => $resumo,
            'scopeOptions' => $this->scopeOptions($user),
            'unidadesPorSourceId' => $this->unidadesPorSourceId(),
            'cargos' => $this->cargosComVinculo($ctx, $user),
            'unidades' => $this->unidadesComVinculo($ctx, $user),
            'tipos' => $this->tiposDoContexto($ctx),
        ]);
    }

    public function create(Request $request): View
    {
        $ctx = $this->contexto($request);

        return view('vinculos.create', [
            'ctx' => $ctx,
            'assignment' => new AssetAssignment(['data_vinculo' => now()]),
            'itensDisponiveis' => $this->itensDisponiveis($ctx, $request->user()),
            'itensSelecionados' => [],
            'colaborador' => null,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $ctx = $this->contexto($request);
        $user = $request->user();

        $dados = $this->validated($request);
        $colaborador = $this->resolverColaborador($dados['hr_collaborator_id']);
        $itens = $this->resolverItens($request, $ctx, $user, null);
        $scope = $this->escopoDosItens($itens);

        $assignment = DB::transaction(function () use ($dados, $colaborador, $itens, $scope, $user) {
            $dataVinculo = Carbon::parse($dados['data_vinculo'])->setTimeFrom(now());

            $assignment = AssetAssignment::query()->create([
                'scope' => $scope,
                'hr_collaborator_id' => $colaborador->id,
                'collaborator_matricula' => $colaborador->employee_number,
                'data_vinculo' => $dataVinculo,
                'data_vencimento' => $dataVinculo->copy()->addYears(AssetAssignment::VIGENCIA_ANOS),
                'observacoes' => $dados['observacoes'] ?? null,
                'ativo' => true,
                'criado_por' => $user?->id,
            ]);

            foreach ($itens as $kind => $item) {
                AssetAssignmentItem::query()->create([
                    'asset_assignment_id' => $assignment->id,
                    AssignmentContext::coluna($kind) => $item->id,
                ]);

                $item->update(['status' => $item::STATUS_EM_USO]);
            }

            return $assignment;
        });

        return redirect()
            ->route($ctx->routePrefix.'show', $assignment)
            ->with('success', $ctx->itemLabel.' registrado para '.$colaborador->name.'. Gere o termo e anexe a via assinada.');
    }

    public function show(Request $request, AssetAssignment $assignment): View
    {
        $ctx = $this->contexto($request);
        $this->ensureScopeIsVisible($request->user(), $assignment->scope);

        $assignment->load(['collaborator', 'items.line', 'items.device', 'items.equipment', 'criadoPor:id,name', 'desvinculadoPor:id,name']);

        return view('vinculos.show', [
            'ctx' => $ctx,
            'assignment' => $assignment,
            'unidadeColaborador' => $this->unidadesPorSourceId()[$assignment->collaborator?->unit_source_id] ?? null,
        ]);
    }

    public function edit(Request $request, AssetAssignment $assignment): View
    {
        $ctx = $this->contexto($request);
        $this->ensureScopeIsVisible($request->user(), $assignment->scope);
        $this->recusarSeEncerrado($assignment, $ctx);

        $assignment->load(['collaborator', 'items']);

        return view('vinculos.edit', [
            'ctx' => $ctx,
            'assignment' => $assignment,
            // O item já vinculado precisa continuar na lista, senão sumiria do
            // próprio formulário que o mostra.
            'itensDisponiveis' => $this->itensDisponiveis($ctx, $request->user(), $assignment),
            'itensSelecionados' => $this->itensAtuais($assignment, $ctx),
            'colaborador' => $assignment->collaborator,
        ]);
    }

    public function update(Request $request, AssetAssignment $assignment): RedirectResponse
    {
        $ctx = $this->contexto($request);
        $this->ensureScopeIsVisible($request->user(), $assignment->scope);
        $this->recusarSeEncerrado($assignment, $ctx);

        $dados = $this->validated($request);
        $colaborador = $this->resolverColaborador($dados['hr_collaborator_id']);
        $novosItens = $this->resolverItens($request, $ctx, $request->user(), $assignment);
        $scope = $this->escopoDosItens($novosItens);

        $antes = $this->itensAtuais($assignment, $ctx);
        $dataVinculo = Carbon::parse($dados['data_vinculo'])->setTimeFrom($assignment->data_vinculo);

        // Trocar item, colaborador ou data faz o papel assinado deixar de
        // descrever o que está entregue — a assinatura perde validade.
        $mudouAlgoRelevante = $colaborador->id !== $assignment->hr_collaborator_id
            || ! $dataVinculo->equalTo($assignment->data_vinculo)
            || $this->idsDosItens($novosItens) !== $this->idsDosItens($antes);

        DB::transaction(function () use ($assignment, $ctx, $dados, $colaborador, $novosItens, $antes, $scope, $dataVinculo, $mudouAlgoRelevante) {
            $assignment->fill([
                'scope' => $scope,
                'hr_collaborator_id' => $colaborador->id,
                'collaborator_matricula' => $colaborador->employee_number,
                'data_vinculo' => $dataVinculo,
                'data_vencimento' => $dataVinculo->copy()->addYears(AssetAssignment::VIGENCIA_ANOS),
                'observacoes' => $dados['observacoes'] ?? null,
            ]);

            if ($mudouAlgoRelevante && $assignment->temTermoAssinado()) {
                $this->apagarArquivoTermo($assignment);
                $assignment->invalidarTermo();
            }

            $assignment->save();

            // Remove os itens deste contexto e regrava; os do outro contexto
            // (um termo que cobre linha e notebook) ficam intactos.
            $assignment->items()
                ->where(fn (Builder $q) => $this->apenasColunasDoContexto($q, $ctx))
                ->delete();

            foreach ($novosItens as $kind => $item) {
                AssetAssignmentItem::query()->create([
                    'asset_assignment_id' => $assignment->id,
                    AssignmentContext::coluna($kind) => $item->id,
                ]);

                $item->update(['status' => $item::STATUS_EM_USO]);
            }

            // Item que saiu do vínculo volta a ficar livre — mas só se nenhum
            // outro vínculo ativo ainda depender dele.
            $novosIds = $this->idsDosItens($novosItens);
            foreach ($antes as $kind => $item) {
                if (($novosIds[$kind] ?? null) !== $item->id) {
                    $this->liberarItem($item, $assignment->id);
                }
            }
        });

        $aviso = $mudouAlgoRelevante && $assignment->wasChanged('termo_assinado_path')
            ? ' O termo assinado foi invalidado: gere e anexe a nova via.'
            : '';

        return redirect()
            ->route($ctx->routePrefix.'show', $assignment)
            ->with('success', $ctx->itemLabel.' atualizado.'.$aviso);
    }

    public function destroy(Request $request, AssetAssignment $assignment): RedirectResponse
    {
        $ctx = $this->contexto($request);
        $this->ensureScopeIsVisible($request->user(), $assignment->scope);

        DB::transaction(function () use ($assignment, $ctx) {
            foreach ($this->itensAtuais($assignment, $ctx) as $item) {
                $this->liberarItem($item, $assignment->id);
            }

            $this->apagarArquivoTermo($assignment);
            $assignment->items()->delete();
            $assignment->delete();
        });

        return redirect()
            ->route($ctx->routePrefix.'index')
            ->with('success', $ctx->itemLabel.' excluído e itens liberados.');
    }

    public function desvinculoForm(Request $request, AssetAssignment $assignment): View|RedirectResponse
    {
        $ctx = $this->contexto($request);
        $this->ensureScopeIsVisible($request->user(), $assignment->scope);

        if (! $assignment->ativo) {
            return redirect()->route($ctx->routePrefix.'index')->with('error', 'Este registro já está encerrado.');
        }

        $assignment->load(['collaborator', 'items.line', 'items.device', 'items.equipment']);

        return view('vinculos.desvinculo', [
            'ctx' => $ctx,
            'assignment' => $assignment,
            'motivos' => self::MOTIVOS_DESVINCULO,
        ]);
    }

    public function desvincular(Request $request, AssetAssignment $assignment): RedirectResponse
    {
        $ctx = $this->contexto($request);
        $this->ensureScopeIsVisible($request->user(), $assignment->scope);

        if (! $assignment->ativo) {
            return redirect()->route($ctx->routePrefix.'index')->with('error', 'Este registro já está encerrado.');
        }

        $dados = $request->validate([
            'motivo_desvinculo' => ['required', 'string', 'max:255'],
            'observacoes_desvinculo' => ['nullable', 'string', 'max:1000'],
        ], [
            'motivo_desvinculo.required' => 'Informe o motivo da devolução.',
        ], [
            'motivo_desvinculo' => 'motivo',
            'observacoes_desvinculo' => 'observações',
        ]);

        DB::transaction(function () use ($assignment, $dados, $request) {
            $assignment->update([
                'ativo' => false,
                'motivo_desvinculo' => $dados['motivo_desvinculo'],
                'observacoes_desvinculo' => $dados['observacoes_desvinculo'] ?? null,
                'data_desvinculo' => now(),
                'desvinculado_por' => $request->user()?->id,
            ]);

            // Encerrar o vínculo libera TODOS os itens dele, dos dois
            // contextos: o termo era um só e foi devolvido por inteiro.
            foreach ($assignment->items()->with(['line', 'device', 'equipment'])->get() as $item) {
                if ($modelo = $item->item()) {
                    $this->liberarItem($modelo, $assignment->id);
                }
            }
        });

        return redirect()
            ->route($ctx->routePrefix.'index')
            ->with('success', 'Devolução registrada. Os itens voltaram a ficar disponíveis.');
    }

    public function termo(Request $request, AssetAssignment $assignment, TermoResponsabilidadeService $termos): Response
    {
        $ctx = $this->contexto($request);
        $this->ensureScopeIsVisible($request->user(), $assignment->scope);

        $assignment->load(['collaborator', 'items.line', 'items.device', 'items.equipment']);

        /*
         | Telefonia usa o modelo oficial da COCARI, com numeração própria e as
         | cláusulas aprovadas. Equipamentos segue no termo genérico até
         | chegar o documento equivalente — não cabe a este código inventar
         | redação jurídica para equipamento.
         */
        if ($ctx->key === AssignmentContext::TELEFONIA) {
            $dados = $termos->dados($assignment);

            $pdf = Pdf::loadView('vinculos.termo-celular-pdf', ['termo' => $dados])
                ->setPaper('a4', 'portrait');

            return $pdf->download(sprintf('termo-%d-%s.pdf', $dados['numero'], Str::slug($dados['colaborador'])));
        }

        $pdf = Pdf::loadView('vinculos.termo-pdf', [
            'ctx' => $ctx,
            'assignment' => $assignment,
            'unidadeColaborador' => $this->unidadesPorSourceId()[$assignment->collaborator?->unit_source_id] ?? null,
        ])->setPaper('a4', 'portrait');

        return $pdf->download(sprintf(
            'termo-%s-%s-%s.pdf',
            $ctx->key,
            $assignment->id,
            now()->format('Y-m-d')
        ));
    }

    public function uploadTermo(Request $request, AssetAssignment $assignment): RedirectResponse
    {
        $ctx = $this->contexto($request);
        $this->ensureScopeIsVisible($request->user(), $assignment->scope);

        $request->validate([
            'termo_assinado' => ['required', 'file', 'mimes:pdf', 'max:51200'],
        ], [
            'termo_assinado.required' => 'Selecione o PDF do termo assinado.',
            'termo_assinado.mimes' => 'O arquivo precisa ser um PDF.',
            'termo_assinado.max' => 'O arquivo não pode passar de 50 MB.',
        ]);

        $this->apagarArquivoTermo($assignment);

        /*
         | Disco privado, não o público.
         |
         | O termo assinado traz nome, matrícula e assinatura do colaborador.
         | No gestaoTI ele é gravado em storage/app/public, o que o deixa
         | acessível por URL direta a quem souber o caminho, sem login. Aqui
         | o arquivo só sai por verTermo(), que confere escopo e permissão.
         */
        $caminho = $request->file('termo_assinado')->storeAs(
            'termos-vinculo',
            sprintf('termo-%d-%s.pdf', $assignment->id, now()->format('YmdHis')),
            'local'
        );

        $assignment->update([
            'termo_assinado_path' => $caminho,
            'termo_assinado_em' => now(),
        ]);

        return back()->with('success', 'Termo assinado anexado.');
    }

    public function verTermo(Request $request, AssetAssignment $assignment): StreamedResponse
    {
        $this->contexto($request);
        $this->ensureScopeIsVisible($request->user(), $assignment->scope);

        abort_unless(
            $assignment->termo_assinado_path && Storage::disk('local')->exists($assignment->termo_assinado_path),
            404,
            'Termo assinado não encontrado.'
        );

        return Storage::disk('local')->response(
            $assignment->termo_assinado_path,
            "termo-assinado-{$assignment->id}.pdf",
            ['Content-Type' => 'application/pdf']
        );
    }

    public function excluirTermo(Request $request, AssetAssignment $assignment): RedirectResponse
    {
        $this->contexto($request);
        $this->ensureScopeIsVisible($request->user(), $assignment->scope);

        $this->apagarArquivoTermo($assignment);
        $assignment->update(['termo_assinado_path' => null, 'termo_assinado_em' => null]);

        return back()->with('success', 'Termo assinado removido.');
    }

    /**
     * Busca de colaborador para o formulário.
     *
     * São 2.267 ativos: jogar todos num <select> pesaria mais que a própria
     * página, então a busca é sob demanda.
     */
    public function lookupColaboradores(Request $request): JsonResponse
    {
        $this->contexto($request);

        $termo = trim((string) $request->string('termo'));

        if (mb_strlen($termo) < 2) {
            return response()->json([]);
        }

        $unidades = $this->unidadesPorSourceId();

        $colaboradores = HrCollaborator::query()
            ->active()
            ->where(fn (Builder $q) => $q
                ->where('name', 'like', "%{$termo}%")
                ->orWhere('employee_number', 'like', "%{$termo}%"))
            ->orderBy('name')
            ->limit(20)
            ->get(['id', 'name', 'employee_number', 'unit_source_id']);

        return response()->json($colaboradores->map(fn (HrCollaborator $c) => [
            'id' => $c->id,
            'nome' => $c->name,
            'matricula' => $c->employee_number,
            'unidade' => $unidades[$c->unit_source_id] ?? null,
        ]));
    }

    public function exportExcel(Request $request, RelatorioExportService $export): StreamedResponse
    {
        $ctx = $this->contexto($request);
        [$cabecalhos, $registros] = $this->dadosParaExport($request, $ctx);

        return $export->excel(
            $ctx->itemLabel.'s de '.$ctx->label,
            $cabecalhos,
            $registros,
            textoLiteral: [1],
            subtitulo: $this->descricaoDosFiltros($request, $ctx),
        );
    }

    public function exportPdf(Request $request, RelatorioExportService $export): Response
    {
        $ctx = $this->contexto($request);
        [$cabecalhos, $registros] = $this->dadosParaExport($request, $ctx);

        return $export->pdf(
            $ctx->itemLabel.'s de '.$ctx->label,
            $cabecalhos,
            $registros,
            subtitulo: $this->descricaoDosFiltros($request, $ctx),
        );
    }

    /**
     * @return array{0: array<int, string>, 1: array<int, array<int, mixed>>}
     */
    private function dadosParaExport(Request $request, AssignmentContext $ctx): array
    {
        $filters = $this->filters($request);
        $unidades = $this->unidadesPorSourceId();
        $cargos = OrganizationPosition::query()->pluck('name', 'source_id')->all();

        $assignments = $this->aplicarFiltros($this->baseQuery($ctx, $request->user()), $ctx, $filters)
            ->with(['collaborator', 'items.line', 'items.device', 'items.equipment'])
            ->orderByDesc('data_vinculo')
            ->get();

        $cabecalhos = [
            'Operação', 'Matrícula', 'Colaborador', 'Cargo', 'Unidade',
            'Tipo', 'Itens', 'Entrega', 'Vencimento', 'Termo assinado',
            'Situação', 'Devolvido em', 'Motivo da devolução',
        ];

        $registros = $assignments->map(function (AssetAssignment $a) use ($unidades, $cargos) {
            return [
                $a->scope_label,
                $a->collaborator_matricula ?: '—',
                $a->collaborator?->name ?: '—',
                $cargos[$a->collaborator?->position_source_id] ?? '—',
                $unidades[$a->collaborator?->unit_source_id] ?? '—',
                $a->tipo_descricao,
                // Um vínculo pode cobrir mais de um item; o relatório mostra
                // todos, senão o kit completo apareceria como se fosse só a linha.
                $a->items->map(fn ($item) => $item->descricao())->implode(' | '),
                $a->data_vinculo?->format('d/m/Y'),
                $a->data_vencimento?->format('d/m/Y'),
                $a->temTermoAssinado() ? 'Sim' : 'Não',
                $a->ativo ? 'Ativo' : 'Encerrado',
                $a->data_desvinculo?->format('d/m/Y') ?: '—',
                $a->motivo_desvinculo ?: '—',
            ];
        })->all();

        return [$cabecalhos, $registros];
    }

    private function descricaoDosFiltros(Request $request, AssignmentContext $ctx): ?string
    {
        $filters = $this->filters($request);
        $partes = [];

        if ($filters['scope'] !== '') {
            $partes[] = 'Operação: '.ScopeRegistry::label($filters['scope']);
        }

        if ($filters['situacao'] !== '') {
            $partes[] = 'Situação: '.ucfirst($filters['situacao']);
        }

        if ($filters['cargo'] !== '') {
            $partes[] = 'Cargo: '.(OrganizationPosition::query()->where('source_id', $filters['cargo'])->value('name') ?? $filters['cargo']);
        }

        if ($filters['unidade'] !== '') {
            $partes[] = 'Unidade: '.($this->unidadesPorSourceId()[$filters['unidade']] ?? $filters['unidade']);
        }

        if ($filters['tipo'] !== '') {
            $partes[] = 'Tipo: '.($this->tiposDoContexto($ctx)[$filters['tipo']] ?? $filters['tipo']);
        }

        foreach (['colaborador' => 'Colaborador', 'busca' => 'Busca', 'termo' => 'Termo'] as $chave => $rotulo) {
            if (($filters[$chave] ?? '') !== '') {
                $partes[] = "{$rotulo}: {$filters[$chave]}";
            }
        }

        if ($filters['inconsistentes'] === '1') {
            $partes[] = 'Somente colaborador inativo';
        }

        return $partes === [] ? 'Todos os registros' : implode(' · ', $partes);
    }

    /**
     * O contexto vem do default da rota, não de um parâmetro do método.
     *
     * Como argumento tipado ele quebrava: 'contexto' não está na URI, então o
     * Laravel o acrescenta DEPOIS dos parâmetros da URL ao montar a lista, e a
     * resolução de argumentos do controller é posicional. Nas rotas sem {id} a
     * ordem casava por acaso; nas com {id} o método recebia o id no lugar do
     * contexto e o model no lugar do id.
     */
    private function contexto(Request $request): AssignmentContext
    {
        return AssignmentContext::make((string) $request->route('contexto'));
    }

    // ------------------------------------------------------------------
    // Apoio
    // ------------------------------------------------------------------

    /**
     * Filtros da listagem, reaproveitados pelas exportações — se ficassem só
     * no index, o relatório traria um recorte diferente do que se está vendo.
     *
     * @param  Builder<AssetAssignment>  $query
     * @param  array<string, string>  $filters
     * @return Builder<AssetAssignment>
     */
    private function aplicarFiltros(Builder $query, AssignmentContext $ctx, array $filters): Builder
    {
        return $query
            ->inScope($filters['scope'])
            ->when($filters['colaborador'] !== '', fn (Builder $q) => $q->whereHas('collaborator', fn (Builder $c) => $c
                ->where('name', 'like', "%{$filters['colaborador']}%")
                ->orWhere('employee_number', 'like', "%{$filters['colaborador']}%")))
            ->when($filters['cargo'] !== '', fn (Builder $q) => $q->whereHas('collaborator',
                fn (Builder $c) => $c->where('position_source_id', $filters['cargo'])))
            ->when($filters['unidade'] !== '', fn (Builder $q) => $q->whereHas('collaborator',
                fn (Builder $c) => $c->where('unit_source_id', $filters['unidade'])))
            ->when($filters['tipo'] !== '', fn (Builder $q) => $this->filtrarPorTipo($q, $filters['tipo']))
            ->when($filters['situacao'] === 'ativos', fn (Builder $q) => $q->ativos())
            ->when($filters['situacao'] === 'encerrados', fn (Builder $q) => $q->inativos())
            ->when($filters['vencimento'] === 'vencidos', fn (Builder $q) => $q->vencidos())
            ->when($filters['vencimento'] !== '' && $filters['vencimento'] !== 'vencidos', fn (Builder $q) => $q
                ->where('ativo', true)
                ->whereDate('data_vencimento', '>=', now())
                ->whereDate('data_vencimento', '<=', now()->addDays((int) $filters['vencimento'])))
            ->when($filters['termo'] === 'pendente', fn (Builder $q) => $q->pendenteTermo())
            ->when($filters['termo'] === 'assinado', fn (Builder $q) => $q->whereNotNull('termo_assinado_path'))
            ->when($filters['inconsistentes'] === '1', fn (Builder $q) => $q->inconsistentes())
            ->when($filters['busca'] !== '', fn (Builder $q) => $this->filtrarPorItem($q, $ctx, $filters['busca']));
    }

    /**
     * Tipo é derivado do conteúdo, então o filtro também: pergunta pelos itens
     * que o vínculo tem, não por uma coluna.
     *
     * @param  Builder<AssetAssignment>  $query
     * @return Builder<AssetAssignment>
     */
    private function filtrarPorTipo(Builder $query, string $tipo): Builder
    {
        return match ($tipo) {
            AssetAssignment::TIPO_COMPLETO => $query
                ->whereHas('items', fn (Builder $q) => $q->whereNotNull('corp_line_id'))
                ->whereHas('items', fn (Builder $q) => $q->whereNotNull('corp_device_id')),
            AssetAssignment::TIPO_APENAS_LINHA => $query
                ->whereHas('items', fn (Builder $q) => $q->whereNotNull('corp_line_id'))
                ->whereDoesntHave('items', fn (Builder $q) => $q->whereNotNull('corp_device_id')),
            AssetAssignment::TIPO_EQUIPAMENTO => $query
                ->whereHas('items', fn (Builder $q) => $q->whereNotNull('equipment_id')),
            default => $query,
        };
    }

    /**
     * Só os cargos que aparecem nesta tela — oferecer os 2.107 do catálogo
     * inteiro num <select> daria uma lista intransitável e quase toda vazia.
     *
     * @return \Illuminate\Support\Collection<int, object>
     */
    private function cargosComVinculo(AssignmentContext $ctx, ?object $user): \Illuminate\Support\Collection
    {
        $ids = $this->baseQuery($ctx, $user)
            ->join('hr_collaborators', 'hr_collaborators.id', '=', 'asset_assignments.hr_collaborator_id')
            ->whereNotNull('hr_collaborators.position_source_id')
            ->distinct()
            ->pluck('hr_collaborators.position_source_id');

        return OrganizationPosition::query()
            ->whereIn('source_id', $ids)
            ->orderBy('name')
            ->get(['source_id', 'name']);
    }

    /** @return \Illuminate\Support\Collection<int, object> */
    private function unidadesComVinculo(AssignmentContext $ctx, ?object $user): \Illuminate\Support\Collection
    {
        $ids = $this->baseQuery($ctx, $user)
            ->join('hr_collaborators', 'hr_collaborators.id', '=', 'asset_assignments.hr_collaborator_id')
            ->whereNotNull('hr_collaborators.unit_source_id')
            ->distinct()
            ->pluck('hr_collaborators.unit_source_id');

        return OrganizationUnit::query()
            ->whereIn('source_id', $ids)
            ->orderBy('name')
            ->get(['source_id', 'name']);
    }

    /**
     * Tipos que fazem sentido oferecer neste contexto.
     *
     * @return array<string, string>
     */
    private function tiposDoContexto(AssignmentContext $ctx): array
    {
        if ($ctx->key === AssignmentContext::EQUIPAMENTOS) {
            return [AssetAssignment::TIPO_EQUIPAMENTO => 'Equipamento'];
        }

        return [
            AssetAssignment::TIPO_COMPLETO => 'Kit completo (aparelho + linha)',
            AssetAssignment::TIPO_APENAS_LINHA => 'Apenas linha corporativa',
        ];
    }

    /** @param  Builder<AssetAssignment>  $query */
    private function baseQuery(AssignmentContext $ctx, ?object $user): Builder
    {
        // Só vínculos que contenham item deste contexto. Um termo que cobre
        // linha e notebook aparece nas duas telas — e deve mesmo.
        return AssetAssignment::query()
            ->visibleTo($user)
            ->whereHas('items', fn (Builder $q) => $this->apenasColunasDoContexto($q, $ctx));
    }

    /** @param  Builder<AssetAssignmentItem>  $query */
    private function apenasColunasDoContexto(Builder $query, AssignmentContext $ctx): Builder
    {
        return $query->where(function (Builder $q) use ($ctx): void {
            foreach ($ctx->colunas() as $coluna) {
                $q->orWhereNotNull($coluna);
            }
        });
    }

    /** @param  Builder<AssetAssignment>  $query */
    private function filtrarPorItem(Builder $query, AssignmentContext $ctx, string $termo): Builder
    {
        $digitos = preg_replace('/\D/', '', $termo) ?: $termo;

        return $query->whereHas('items', function (Builder $q) use ($ctx, $termo, $digitos): void {
            $q->where(function (Builder $inner) use ($ctx, $termo, $digitos): void {
                if ($ctx->aceita('linha')) {
                    $inner->orWhereHas('line', fn (Builder $l) => $l->where('numero', 'like', "%{$digitos}%"));
                }

                if ($ctx->aceita('aparelho')) {
                    $inner->orWhereHas('device', fn (Builder $d) => $d
                        ->where('imei_1', 'like', "%{$digitos}%")
                        ->orWhere('imei_2', 'like', "%{$digitos}%")
                        ->orWhere('modelo', 'like', "%{$termo}%"));
                }

                if ($ctx->aceita('equipamento')) {
                    $inner->orWhereHas('equipment', fn (Builder $e) => $e
                        ->where('patrimonio', 'like', "%{$termo}%")
                        ->orWhere('nome', 'like', "%{$termo}%"));
                }
            });
        });
    }

    /**
     * Itens que o usuário pode escolher: disponíveis, no escopo dele, mais o
     * que o próprio vínculo já usa.
     *
     * @return array<string, \Illuminate\Support\Collection>
     */
    private function itensDisponiveis(AssignmentContext $ctx, ?object $user, ?AssetAssignment $assignment = null): array
    {
        $atuais = $assignment ? $this->itensAtuais($assignment, $ctx) : [];
        $disponiveis = [];

        foreach ($ctx->kinds as $kind) {
            $modelo = AssignmentContext::modelo($kind);
            $idAtual = ($atuais[$kind] ?? null)?->id;

            $disponiveis[$kind] = $modelo::query()
                ->visibleTo($user)
                ->where(fn (Builder $q) => $q
                    ->where(fn (Builder $inner) => $inner->available())
                    ->when($idAtual, fn (Builder $inner) => $inner->orWhere('id', $idAtual)))
                ->when($kind === 'equipamento', fn (Builder $q) => $q->whereIn('tipo', Equipment::TIPOS_VINCULAVEIS))
                ->orderBy($kind === 'linha' ? 'numero' : ($kind === 'aparelho' ? 'marca' : 'nome'))
                ->get();
        }

        return $disponiveis;
    }

    /**
     * Itens deste contexto que o vínculo usa hoje.
     *
     * @return array<string, Model>
     */
    private function itensAtuais(AssetAssignment $assignment, AssignmentContext $ctx): array
    {
        $atuais = [];

        foreach ($assignment->items()->with(['line', 'device', 'equipment'])->get() as $item) {
            foreach ($ctx->kinds as $kind) {
                $id = $item->{AssignmentContext::coluna($kind)};

                if ($id !== null) {
                    $atuais[$kind] = match ($kind) {
                        'linha' => $item->line,
                        'aparelho' => $item->device,
                        'equipamento' => $item->equipment,
                    };
                }
            }
        }

        return array_filter($atuais);
    }

    /**
     * @param  array<string, Model>  $itens
     * @return array<string, int>
     */
    private function idsDosItens(array $itens): array
    {
        $ids = array_map(fn (Model $item) => (int) $item->id, $itens);
        ksort($ids);

        return $ids;
    }

    /**
     * Valida e devolve os itens escolhidos.
     *
     * @return array<string, Model>
     */
    private function resolverItens(Request $request, AssignmentContext $ctx, ?object $user, ?AssetAssignment $assignment): array
    {
        $itens = [];

        foreach ($ctx->kinds as $kind) {
            $id = $request->input("itens.{$kind}");

            if ($id === null || $id === '') {
                continue;
            }

            $modelo = AssignmentContext::modelo($kind);
            $item = $modelo::query()->find($id);

            if ($item === null) {
                throw ValidationException::withMessages([
                    "itens.{$kind}" => AssignmentContext::ROTULOS[$kind].' não encontrado.',
                ]);
            }

            if (! $user?->canScope($item->scope)) {
                throw ValidationException::withMessages([
                    "itens.{$kind}" => AssignmentContext::ROTULOS[$kind].' pertence a uma operação fora do seu acesso.',
                ]);
            }

            if ($kind === 'equipamento' && ! $item->isAssignable()) {
                throw ValidationException::withMessages([
                    "itens.{$kind}" => sprintf(
                        'Equipamento do tipo "%s" não é entregue a colaborador. Só %s.',
                        $item->tipo,
                        implode(', ', Equipment::TIPOS_VINCULAVEIS)
                    ),
                ]);
            }

            // Disponibilidade conferida no vínculo, não só no status gravado:
            // é o que impede entregar duas vezes o mesmo item.
            if ($item->hasActiveAssignment($assignment?->id)) {
                throw ValidationException::withMessages([
                    "itens.{$kind}" => AssignmentContext::ROTULOS[$kind].' já está vinculado a outro colaborador.',
                ]);
            }

            $jaEraDoVinculo = $assignment !== null
                && ($this->itensAtuais($assignment, $ctx)[$kind] ?? null)?->id === $item->id;

            if (! $jaEraDoVinculo && $item->status !== $item::STATUS_DISPONIVEL) {
                throw ValidationException::withMessages([
                    "itens.{$kind}" => sprintf(
                        '%s está com a situação "%s" e não pode ser entregue.',
                        AssignmentContext::ROTULOS[$kind],
                        $item->status
                    ),
                ]);
            }

            $itens[$kind] = $item;
        }

        if ($itens === []) {
            throw ValidationException::withMessages([
                'itens' => 'Selecione ao menos um item para entregar.',
            ]);
        }

        // "Apenas linha corporativa" é válido; aparelho sozinho, não — o
        // aparelho sem chip não é entregue a ninguém.
        if ($ctx->aceita('aparelho') && isset($itens['aparelho']) && ! isset($itens['linha'])) {
            throw ValidationException::withMessages([
                'itens.aparelho' => 'Aparelho só é entregue junto com uma linha. Selecione a linha ou remova o aparelho.',
            ]);
        }

        return $itens;
    }

    /**
     * Todos os itens de um vínculo têm de ser da mesma operação — senão o
     * termo misturaria Sede e Cerrado num documento só.
     *
     * @param  array<string, Model>  $itens
     */
    private function escopoDosItens(array $itens): string
    {
        $escopos = array_unique(array_map(fn (Model $item) => $item->scope, $itens));

        if (count($escopos) > 1) {
            throw ValidationException::withMessages([
                'itens' => 'Os itens selecionados são de operações diferentes ('
                    .implode(' e ', array_map(fn ($s) => ScopeRegistry::label($s), $escopos))
                    .'). Um vínculo cobre uma operação só.',
            ]);
        }

        return (string) reset($escopos);
    }

    private function resolverColaborador(int|string $id): HrCollaborator
    {
        $colaborador = HrCollaborator::query()->find($id);

        if ($colaborador === null) {
            throw ValidationException::withMessages(['hr_collaborator_id' => 'Colaborador não encontrado.']);
        }

        if (! $colaborador->active || $colaborador->terminated) {
            throw ValidationException::withMessages([
                'hr_collaborator_id' => "{$colaborador->name} não está ativo no RH e não pode receber itens.",
            ]);
        }

        return $colaborador;
    }

    /**
     * Devolve o item ao estoque — mas só se nenhum outro vínculo ativo ainda
     * depender dele. Uma linha pode aparecer em mais de um registro.
     */
    private function liberarItem(Model $item, int $ignorarAssignmentId): void
    {
        if (! $item->hasActiveAssignment($ignorarAssignmentId)) {
            $item->update(['status' => $item::STATUS_DISPONIVEL]);
        }
    }

    private function apagarArquivoTermo(AssetAssignment $assignment): void
    {
        if ($assignment->termo_assinado_path && Storage::disk('local')->exists($assignment->termo_assinado_path)) {
            Storage::disk('local')->delete($assignment->termo_assinado_path);
        }
    }

    private function recusarSeEncerrado(AssetAssignment $assignment, AssignmentContext $ctx): void
    {
        abort_if(! $assignment->ativo, 403, 'Registro encerrado não pode ser alterado. Consulte o histórico.');
    }

    /** @return array<string, string> */
    private function unidadesPorSourceId(): array
    {
        return OrganizationUnit::query()->pluck('name', 'source_id')->all();
    }

    /** @return array<string, string> */
    private function filters(Request $request): array
    {
        return [
            'scope' => trim((string) $request->string('scope')),
            'colaborador' => trim((string) $request->string('colaborador')),
            'cargo' => trim((string) $request->string('cargo')),
            'unidade' => trim((string) $request->string('unidade')),
            'tipo' => trim((string) $request->string('tipo')),
            'situacao' => trim((string) $request->string('situacao', 'ativos')),
            'vencimento' => trim((string) $request->string('vencimento')),
            'termo' => trim((string) $request->string('termo')),
            'inconsistentes' => trim((string) $request->string('inconsistentes')),
            'busca' => trim((string) $request->string('busca')),
        ];
    }

    /** @return array<string, mixed> */
    private function validated(Request $request): array
    {
        return $request->validate([
            'hr_collaborator_id' => ['required', 'integer'],
            'data_vinculo' => ['required', 'date'],
            'observacoes' => ['nullable', 'string', 'max:1000'],
        ], [
            'hr_collaborator_id.required' => 'Selecione o colaborador.',
        ], [
            'data_vinculo' => 'data de entrega',
            'observacoes' => 'observações',
        ]);
    }
}
