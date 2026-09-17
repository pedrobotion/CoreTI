<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\PaginacaoConfiguravel;
use App\Http\Controllers\Concerns\ResolvesOperationalScope;
use App\Models\CorpLine;
use App\Models\TelefoniaFatura;
use App\Services\Telefonia\Faturas\FaturaConciliadorService;
use App\Services\Telefonia\Faturas\FaturaParserFactory;
use App\Support\ScopeRegistry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Importação de faturas de telefonia a partir do PDF da operadora.
 *
 * A importação tem duas etapas de propósito: ler e revisar, depois gravar.
 * O PDF é um documento externo cujo layout muda sem aviso — gravar direto o
 * que o leitor entendeu significaria sobrescrever o valor de 377 linhas com
 * base numa leitura que ninguém conferiu.
 */
class TelefoniaFaturaController extends Controller
{
    use PaginacaoConfiguravel;
    use ResolvesOperationalScope;

    /** Onde a revisão em andamento fica guardada entre as duas etapas. */
    private const CHAVE_SESSAO = 'telefonia.fatura_em_revisao';

    public function index(Request $request): View
    {
        $user = $request->user();

        $filtros = [
            'scope' => trim((string) $request->string('scope')),
            'operadora' => trim((string) $request->string('operadora')),
            'competencia' => trim((string) $request->string('competencia')),
        ];

        $faturas = TelefoniaFatura::query()
            ->visibleTo($user)
            ->inScope($filtros['scope'])
            ->when($filtros['operadora'] !== '', fn ($q) => $q->where('operadora', $filtros['operadora']))
            ->when($filtros['competencia'] !== '', fn ($q) => $q
                ->whereYear('competencia', substr($filtros['competencia'], 0, 4))
                ->whereMonth('competencia', substr($filtros['competencia'], 5, 2)))
            ->with('importadoPor:id,name')
            ->withCount('itens')
            ->orderByDesc('competencia')
            ->orderByDesc('id')
            ->paginate($this->porPagina($request))
            ->withQueryString();

        return view('telefonia.faturas.index', [
            'faturas' => $faturas,
            'filtros' => $filtros,
            'scopeOptions' => $this->scopeOptions($user),
            'operadoras' => CorpLine::OPERADORAS,
        ]);
    }

    public function create(Request $request): View
    {
        return view('telefonia.faturas.create', [
            'scopeOptions' => $this->scopeOptions($request->user()),
            'operadoras' => FaturaParserFactory::disponiveis(),
        ]);
    }

    /** Etapa 1: lê o PDF e mostra o que será gravado. */
    public function preview(Request $request, FaturaConciliadorService $conciliador): View|RedirectResponse
    {
        $dados = $request->validate([
            'scope' => ['nullable', 'string', Rule::in(ScopeRegistry::keys())],
            'operadora' => ['required', 'string', Rule::in(FaturaParserFactory::disponiveis())],
            'arquivo' => ['required', 'file', 'mimes:pdf', 'max:51200'],
        ], [
            'arquivo.required' => 'Selecione o PDF da fatura.',
            'arquivo.mimes' => 'O arquivo precisa ser um PDF.',
            'arquivo.max' => 'O arquivo não pode passar de 50 MB.',
            'operadora.in' => 'Ainda não há leitor de fatura para essa operadora.',
        ]);

        $scope = $this->resolveScope($request->user(), $dados['scope'] ?? null);
        $arquivo = $request->file('arquivo');
        $hash = hash_file('sha256', $arquivo->getRealPath());

        if ($jaImportada = TelefoniaFatura::query()->where('hash_arquivo', $hash)->first()) {
            return back()->with('error', sprintf(
                'Este PDF já foi importado em %s (fatura #%d, competência %s).',
                $jaImportada->created_at?->format('d/m/Y'),
                $jaImportada->id,
                $jaImportada->competencia_label
            ))->withInput();
        }

        $this->descartarRevisaoPendente();

        $caminhoTemporario = $arquivo->store('faturas/em-revisao', 'local');

        try {
            $resultado = FaturaParserFactory::make($dados['operadora'])
                ->parse(Storage::disk('local')->path($caminhoTemporario));
        } catch (\Throwable $e) {
            Storage::disk('local')->delete($caminhoTemporario);

            return back()->with('error', 'Não foi possível ler a fatura: '.$e->getMessage())->withInput();
        }

        $revisao = $conciliador->revisar($resultado, $scope, $dados['operadora']);

        session([self::CHAVE_SESSAO => [
            'caminho' => $caminhoTemporario,
            'nome_original' => $arquivo->getClientOriginalName(),
            'hash' => $hash,
            'scope' => $scope,
            'operadora' => $dados['operadora'],
            'resultado' => serialize($resultado),
        ]]);

        return view('telefonia.faturas.preview', [
            'revisao' => $revisao,
            'scope' => $scope,
            'operadora' => $dados['operadora'],
            'nomeArquivo' => $arquivo->getClientOriginalName(),
        ]);
    }

    /** Etapa 2: grava o que foi revisado. */
    public function confirm(Request $request, FaturaConciliadorService $conciliador): RedirectResponse
    {
        $pendente = session(self::CHAVE_SESSAO);

        if (! is_array($pendente) || ! Storage::disk('local')->exists($pendente['caminho'])) {
            return redirect()
                ->route('administrativo.telefonia.faturas.create')
                ->with('error', 'A revisão expirou. Envie o PDF novamente.');
        }

        $this->ensureScopeIsVisible($request->user(), $pendente['scope']);

        $paraCancelar = array_map('intval', (array) $request->input('cancelar', []));

        $destino = sprintf(
            'faturas/%s/%s',
            $pendente['scope'],
            basename($pendente['caminho'])
        );

        Storage::disk('local')->move($pendente['caminho'], $destino);

        try {
            $fatura = $conciliador->salvar(
                unserialize($pendente['resultado'], ['allowed_classes' => true]),
                $pendente['scope'],
                $pendente['operadora'],
                $request->user()?->id,
                $destino,
                $pendente['nome_original'],
                $pendente['hash'],
                $paraCancelar,
            );
        } catch (\Throwable $e) {
            Storage::disk('local')->delete($destino);
            session()->forget(self::CHAVE_SESSAO);

            return redirect()
                ->route('administrativo.telefonia.faturas.create')
                ->with('error', 'Falha ao gravar a fatura: '.$e->getMessage());
        }

        session()->forget(self::CHAVE_SESSAO);

        $aviso = $paraCancelar === [] ? '' : ' '.count($paraCancelar).' linha(s) marcada(s) como cancelada(s).';

        return redirect()
            ->route('administrativo.telefonia.faturas.show', $fatura)
            ->with('success', "Fatura de {$fatura->competencia_label} importada com {$fatura->quantidade_linhas} linha(s).".$aviso);
    }

    public function show(Request $request, TelefoniaFatura $fatura): View
    {
        $this->ensureScopeIsVisible($request->user(), $fatura->scope);

        $fatura->load('importadoPor:id,name');

        return view('telefonia.faturas.show', [
            'fatura' => $fatura,
            'itens' => $fatura->itens()->with('linha:id,status')->orderByDesc('valor')->get(),
        ]);
    }

    public function download(Request $request, TelefoniaFatura $fatura): StreamedResponse
    {
        $this->ensureScopeIsVisible($request->user(), $fatura->scope);

        abort_unless(
            $fatura->arquivo_pdf && Storage::disk('local')->exists($fatura->arquivo_pdf),
            404,
            'O PDF desta fatura não está mais disponível.'
        );

        return Storage::disk('local')->download(
            $fatura->arquivo_pdf,
            $fatura->arquivo_nome_original ?: "fatura-{$fatura->id}.pdf"
        );
    }

    public function destroy(Request $request, TelefoniaFatura $fatura): RedirectResponse
    {
        $this->ensureScopeIsVisible($request->user(), $fatura->scope);

        $competencia = $fatura->competencia_label;

        if ($fatura->arquivo_pdf && Storage::disk('local')->exists($fatura->arquivo_pdf)) {
            Storage::disk('local')->delete($fatura->arquivo_pdf);
        }

        // Os itens caem junto (cascade). O valor_mensal que a fatura gravou nas
        // linhas permanece: é o último valor conhecido de cobrança, e zerá-lo
        // deixaria o custo mensal mentindo.
        $fatura->delete();

        return redirect()
            ->route('administrativo.telefonia.faturas.index')
            ->with('success', "Fatura de {$competencia} removida.");
    }

    private function descartarRevisaoPendente(): void
    {
        $pendente = session(self::CHAVE_SESSAO);

        if (is_array($pendente) && Storage::disk('local')->exists($pendente['caminho'] ?? '')) {
            Storage::disk('local')->delete($pendente['caminho']);
        }

        session()->forget(self::CHAVE_SESSAO);
    }
}
