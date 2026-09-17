<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\PaginacaoConfiguravel;
use App\Http\Controllers\Concerns\ResolvesOperationalScope;
use App\Models\AssetAssignment;
use App\Models\CorpLine;
use App\Services\Telefonia\RelatorioExportService;
use App\Support\ScopeRegistry;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CorpLineController extends Controller
{
    use PaginacaoConfiguravel;
    use ResolvesOperationalScope;

    public function index(Request $request): View
    {
        $user = $request->user();
        $filters = $this->filters($request);

        $lines = $this->aplicarFiltros(CorpLine::query()->visibleTo($user), $filters)
            ->withCount(['assignmentItems as vinculos_ativos_count' => fn ($q) => $q
                ->whereHas('assignment', fn ($a) => $a->where('ativo', true))])
            ->orderBy('ddd')
            ->orderBy('numero')
            ->paginate($this->porPagina($request))
            ->withQueryString();

        $base = CorpLine::query()->visibleTo($user)->inScope($filters['scope']);

        $resumo = [
            'total' => (clone $base)->count(),
            'disponiveis' => (clone $base)->where('status', CorpLine::STATUS_DISPONIVEL)->count(),
            'em_uso' => (clone $base)->where('status', CorpLine::STATUS_EM_USO)->count(),
            'canceladas' => (clone $base)->where('status', CorpLine::STATUS_CANCELADA)->count(),
            // Linha marcada 'Em Uso' sem nenhum vínculo ativo fica invisível
            // para novo vínculo. Na base do gestaoTI são 41 hoje; aqui o número
            // fica à vista em vez de esperar alguém reclamar.
            'em_uso_sem_vinculo' => (clone $base)
                ->where('status', CorpLine::STATUS_EM_USO)
                ->whereDoesntHave('assignmentItems.assignment', fn ($q) => $q->where('ativo', true))
                ->count(),
            // Só linhas vigentes entram no custo: contar linha cancelada
            // inflaria a conta do mês com o que já não é cobrado.
            'custo_mensal' => (float) (clone $base)
                ->where('status', '!=', CorpLine::STATUS_CANCELADA)
                ->sum('valor_mensal'),
        ];

        return view('telefonia.lines.index', [
            'lines' => $lines,
            'filters' => $filters,
            'resumo' => $resumo,
            'scopeOptions' => $this->scopeOptions($user),
            'operadoras' => CorpLine::OPERADORAS,
            'statuses' => CorpLine::STATUSES,
        ]);
    }

    public function create(Request $request): View
    {
        return view('telefonia.lines.create', [
            'line' => new CorpLine(['status' => CorpLine::STATUS_DISPONIVEL]),
            'scopeOptions' => $this->scopeOptions($request->user()),
            'operadoras' => CorpLine::OPERADORAS,
            'statuses' => CorpLine::STATUSES,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $data['scope'] = $this->resolveScope($request->user(), $request->input('scope'));
        $data['created_by'] = $request->user()?->id;

        $line = CorpLine::query()->create($data);

        return redirect()
            ->route('administrativo.telefonia.lines.index')
            ->with('success', "Linha {$line->numero_formatado} cadastrada.");
    }

    public function edit(Request $request, CorpLine $line): View
    {
        $this->ensureScopeIsVisible($request->user(), $line->scope);

        return view('telefonia.lines.edit', [
            'line' => $line,
            'scopeOptions' => $this->scopeOptions($request->user()),
            'operadoras' => CorpLine::OPERADORAS,
            'statuses' => CorpLine::STATUSES,
            'temVinculoAtivo' => $line->hasActiveAssignment(),
            'temHistorico' => $line->assignmentItems()->exists(),
        ]);
    }

    public function update(Request $request, CorpLine $line): RedirectResponse
    {
        $this->ensureScopeIsVisible($request->user(), $line->scope);

        $data = $this->validated($request, $line);
        $data['scope'] = $this->resolveScope($request->user(), $request->input('scope'));
        $data['updated_by'] = $request->user()?->id;

        $temVinculoAtivo = $line->hasActiveAssignment();

        // A linha entregue a alguém não pode ser declarada livre nem cancelada
        // por engano: o vínculo continuaria apontando para ela.
        if ($temVinculoAtivo && $data['status'] !== CorpLine::STATUS_EM_USO) {
            throw ValidationException::withMessages([
                'status' => 'Esta linha tem vínculo ativo. Desvincule antes de mudar a situação dela.',
            ]);
        }

        // Mudar de operação com vínculo ativo deixaria o vínculo num escopo e
        // a linha em outro.
        if ($temVinculoAtivo && $data['scope'] !== $line->scope) {
            throw ValidationException::withMessages([
                'scope' => 'Esta linha tem vínculo ativo e não pode mudar de operação. Desvincule antes.',
            ]);
        }

        $line->update($data);

        return redirect()
            ->route('administrativo.telefonia.lines.index')
            ->with('success', "Linha {$line->numero_formatado} atualizada.");
    }

    public function destroy(Request $request, CorpLine $line): RedirectResponse
    {
        $this->ensureScopeIsVisible($request->user(), $line->scope);

        // O banco recusaria com restrictOnDelete, mas erro de FK na tela não
        // diz o que fazer. No gestaoTI a FK era CASCADE e apagar a linha
        // levava o histórico de vínculo junto, sem aviso.
        if ($line->assignmentItems()->exists()) {
            return back()->with('error', 'Esta linha tem histórico de vínculo e não pode ser excluída. Se ela saiu do contrato, marque a situação como Cancelada.');
        }

        $numero = $line->numero_formatado;
        $line->delete();

        return redirect()
            ->route('administrativo.telefonia.lines.index')
            ->with('success', "Linha {$numero} excluída.");
    }

    public function show(Request $request, CorpLine $line): View
    {
        $this->ensureScopeIsVisible($request->user(), $line->scope);

        $line->load(['createdBy:id,name', 'updatedBy:id,name']);

        // Histórico completo: quem teve a linha, quando e por quê devolveu.
        $vinculos = AssetAssignment::query()
            ->whereHas('items', fn (Builder $q) => $q->where('corp_line_id', $line->id))
            ->with(['collaborator:id,name,employee_number', 'items'])
            ->orderByDesc('data_vinculo')
            ->get();

        return view('telefonia.lines.show', [
            'line' => $line,
            'vinculos' => $vinculos,
            'vinculoAtivo' => $vinculos->firstWhere('ativo', true),
            'faturas' => $this->historicoDeFaturas($line),
        ]);
    }

    public function exportExcel(Request $request, RelatorioExportService $export): StreamedResponse
    {
        [$cabecalhos, $linhas, $rodape] = $this->dadosParaExport($request);

        return $export->excel(
            'Linhas corporativas',
            $cabecalhos,
            $linhas,
            $rodape,
            textoLiteral: [2, 3],
            subtitulo: $this->descricaoDosFiltros($request),
        );
    }

    public function exportPdf(Request $request, RelatorioExportService $export): Response
    {
        [$cabecalhos, $linhas, $rodape] = $this->dadosParaExport($request);

        return $export->pdf(
            'Linhas corporativas',
            $cabecalhos,
            $linhas,
            $rodape,
            subtitulo: $this->descricaoDosFiltros($request),
        );
    }

    /**
     * Linhas e totais do relatório, com os mesmos filtros da tela.
     *
     * @return array{0: array<int, string>, 1: array<int, array<int, mixed>>, 2: array<int, string|null>}
     */
    private function dadosParaExport(Request $request): array
    {
        $filters = $this->filters($request);

        $lines = $this->aplicarFiltros(CorpLine::query()->visibleTo($request->user()), $filters)
            ->orderBy('ddd')
            ->orderBy('numero')
            ->get();

        $cabecalhos = ['Operação', 'Operadora', 'DDD', 'Número', 'Plano', 'Valor mensal', 'Situação', 'Cancelada em', 'Observação'];

        $registros = $lines->map(fn (CorpLine $line) => [
            $line->scope_label,
            $line->operadora,
            $line->ddd,
            $line->numero,
            $line->nome_plano ?: '—',
            $line->valor_mensal !== null ? (float) $line->valor_mensal : null,
            $line->status,
            $line->data_cancelamento?->format('d/m/Y') ?: '—',
            $line->observacao ?: '—',
        ])->all();

        $total = $lines
            ->where('status', '!=', CorpLine::STATUS_CANCELADA)
            ->sum(fn (CorpLine $line) => (float) $line->valor_mensal);

        $rodape = [null, null, null, null, 'TOTAL MENSAL', round($total, 2), null, null, null];

        return [$cabecalhos, $registros, $rodape];
    }

    /** Deixa registrado no relatório o recorte que o gerou. */
    private function descricaoDosFiltros(Request $request): ?string
    {
        $filters = $this->filters($request);
        $partes = [];

        foreach ([
            'scope' => 'Operação',
            'operadora' => 'Operadora',
            'status' => 'Situação',
            'ddd' => 'DDD',
            'busca' => 'Busca',
        ] as $chave => $rotulo) {
            if (($filters[$chave] ?? '') !== '') {
                $valor = $chave === 'scope' ? ScopeRegistry::label($filters[$chave]) : $filters[$chave];
                $partes[] = "{$rotulo}: {$valor}";
            }
        }

        return $partes === [] ? 'Todas as linhas' : implode(' · ', $partes);
    }

    /**
     * Histórico de cobrança da linha.
     *
     * Devolve vazio enquanto o módulo de faturas não estiver instalado, para
     * a tela de detalhe funcionar de qualquer forma.
     */
    private function historicoDeFaturas(CorpLine $line): \Illuminate\Support\Collection
    {
        if (! class_exists(\App\Models\TelefoniaFaturaLinha::class)) {
            return collect();
        }

        return \App\Models\TelefoniaFaturaLinha::query()
            ->where('corp_line_id', $line->id)
            ->with('fatura')
            ->orderByDesc('competencia')
            ->limit(24)
            ->get();
    }

    /**
     * Filtros da listagem, reaproveitados pelas exportações — se ficassem só
     * no index, o relatório exportado traria um recorte diferente do que a
     * pessoa está vendo na tela.
     *
     * @param  Builder<CorpLine>  $query
     * @param  array<string, string>  $filters
     * @return Builder<CorpLine>
     */
    private function aplicarFiltros(Builder $query, array $filters): Builder
    {
        return $query
            ->inScope($filters['scope'])
            ->when($filters['operadora'] !== '', fn (Builder $q) => $q->where('operadora', $filters['operadora']))
            ->when($filters['status'] !== '', fn (Builder $q) => $q->where('status', $filters['status']))
            ->when($filters['ddd'] !== '', fn (Builder $q) => $q->where('ddd', $filters['ddd']))
            ->when($filters['busca'] !== '', function (Builder $q) use ($filters): void {
                // Digitar "(44) 99754-0714" tem de encontrar 997540714.
                $digitos = preg_replace('/\D/', '', $filters['busca']) ?? '';
                $termo = $digitos !== '' ? $digitos : $filters['busca'];

                $q->where(fn (Builder $inner) => $inner
                    ->where('numero', 'like', "%{$termo}%")
                    ->orWhere('nome_plano', 'like', "%{$filters['busca']}%"));
            });
    }

    /**
     * @return array<string, string>
     */
    private function filters(Request $request): array
    {
        return [
            'scope' => trim((string) $request->string('scope')),
            'operadora' => trim((string) $request->string('operadora')),
            'status' => trim((string) $request->string('status')),
            'ddd' => trim((string) $request->string('ddd')),
            'busca' => trim((string) $request->string('busca')),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?CorpLine $line = null): array
    {
        $data = $request->validate([
            'scope' => ['nullable', 'string', Rule::in(ScopeRegistry::keys())],
            'operadora' => ['required', 'string', Rule::in(CorpLine::OPERADORAS)],
            'ddd' => ['required', 'digits:2'],
            // Único global: um número de telefone não existe duas vezes. É o
            // que impede a mesma linha ser cadastrada na Sede e no Cerrado.
            'numero' => [
                'required', 'digits_between:8,9',
                Rule::unique('corp_lines', 'numero')
                    ->where(fn ($query) => $query->where('ddd', $request->input('ddd')))
                    ->ignore($line?->id),
            ],
            'status' => ['required', 'string', Rule::in(CorpLine::STATUSES)],
            'data_cancelamento' => ['nullable', 'date', 'required_if:status,'.CorpLine::STATUS_CANCELADA],
            'nome_plano' => ['nullable', 'string', 'max:255'],
            'valor_mensal' => ['nullable', 'numeric', 'min:0'],
            'observacao' => ['nullable', 'string', 'max:2000'],
        ], [
            'numero.unique' => 'Já existe uma linha cadastrada com este DDD e número.',
            'numero.digits_between' => 'O número deve ter 8 dígitos (fixo) ou 9 (celular), sem o DDD.',
            'ddd.digits' => 'O DDD deve ter exatamente 2 dígitos.',
            'data_cancelamento.required_if' => 'Informe a data em que a linha foi cancelada.',
        ], [
            'scope' => 'operação',
            'ddd' => 'DDD',
            'numero' => 'número',
            'nome_plano' => 'plano',
            'valor_mensal' => 'valor mensal',
            'data_cancelamento' => 'data de cancelamento',
        ]);

        // Data de cancelamento só faz sentido em linha cancelada.
        if ($data['status'] !== CorpLine::STATUS_CANCELADA) {
            $data['data_cancelamento'] = null;
        }

        return $data;
    }
}
