<?php

namespace App\Http\Controllers\Ad;

use App\Http\Controllers\Controller;
use App\Models\AdOfficeAccountException;
use App\Models\AdOfficeRule;
use App\Models\CoretiCostCenter;
use App\Services\Ad\AdOfficeRuleService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class AdOfficeRuleController extends Controller
{
    public function __construct(private readonly AdOfficeRuleService $servico)
    {
    }

    public function index(Request $request): View
    {
        $filtros = $request->validate([
            'situacao' => ['nullable', 'string', 'in:todas,confirmada,sugerida,sem_definicao,ignorada'],
            'q' => ['nullable', 'string', 'max:120'],
        ]);

        $situacao = $filtros['situacao'] ?? 'todas';
        $busca = trim((string) ($filtros['q'] ?? ''));

        $regras = AdOfficeRule::with('centroCusto', 'confirmador')
            ->when($busca !== '', fn ($q) => $q->where('ou_caminho', 'like', "%{$busca}%"))
            ->orderBy('ou_caminho')
            ->get()
            ->when($situacao !== 'todas', fn ($c) => $c->filter(fn ($r) => $r->situacao() === $situacao));

        $previa = $this->servico->previa();

        return view('service-desk.ad.centro-custo.index', [
            'excecoes' => AdOfficeAccountException::with('autor')->orderBy('nome_usuario')->get(),
            'regras' => $regras->values(),
            'centros' => CoretiCostCenter::query()->active()->orderBy('code')->get(['code', 'name']),
            'situacao' => $situacao,
            'busca' => $busca,
            'escopo' => $this->servico->escopo(),
            'resumo' => [
                'total' => AdOfficeRule::count(),
                'confirmadas' => AdOfficeRule::whereNotNull('confirmado_em')->where('ignorar', false)->count(),
                'sem_definicao' => AdOfficeRule::whereNull('centro_custo_code')->where('ignorar', false)->count(),
                'ignoradas' => AdOfficeRule::where('ignorar', true)->count(),
                'contas_afetadas' => $previa->count(),
                'preenchendo' => $previa->whereNull('de')->count(),
                'trocando' => $previa->whereNotNull('de')->count(),
            ],
            'trocas' => $previa->whereNotNull('de')->values(),
        ]);
    }

    public function update(Request $request, AdOfficeRule $regra): RedirectResponse
    {
        $dados = $request->validate([
            'tipo_valor' => ['nullable', 'string', 'in:centro_custo,cnpj'],
            'centro_custo_code' => ['nullable', 'string', 'exists:coreti_cost_centers,code'],
            // Empresa terceira recebe CNPJ no lugar do código.
            'valor_livre' => ['nullable', 'string', 'max:60'],
            'ignorar' => ['nullable', 'boolean'],
            'motivo_ignorar' => ['nullable', 'string', 'max:255'],
        ]);

        $ignorar = (bool) ($dados['ignorar'] ?? false);
        $tipo = $dados['tipo_valor'] ?? AdOfficeRule::TIPO_CENTRO_CUSTO;

        $regra->update([
            'ignorar' => $ignorar,
            'motivo_ignorar' => $ignorar ? ($dados['motivo_ignorar'] ?? null) : null,
            'tipo_valor' => $tipo,
            // Marcada para não mexer não guarda valor: "vazio" e "não mexer"
            // são estados diferentes e não podem se confundir.
            'centro_custo_code' => $ignorar || $tipo !== AdOfficeRule::TIPO_CENTRO_CUSTO
                ? null
                : ($dados['centro_custo_code'] ?? null),
            'valor_livre' => $ignorar || $tipo !== AdOfficeRule::TIPO_CNPJ
                ? null
                : ($dados['valor_livre'] ?? null),
            'confirmado_por' => Auth::id(),
            'confirmado_em' => now(),
        ]);

        return back()->with('success', "Regra de {$regra->ou_caminho} atualizada.");
    }

    /**
     * Salva várias regras de uma vez.
     *
     * Revisar 80 sugestões salvando linha a linha recarrega a página a cada
     * clique e joga a rolagem para o topo. Aqui vai tudo num envio só.
     *
     * Só as linhas que realmente mudaram são gravadas: marcar as 127 como
     * confirmadas porque passaram pelo formulário transformaria "ninguém
     * olhou ainda" em "alguém decidiu", que é a informação que a tela existe
     * para dar.
     */
    public function atualizarLote(Request $request): RedirectResponse
    {
        $dados = $request->validate([
            'total_enviado' => ['required', 'integer', 'min:0'],
            'regras' => ['present', 'array'],
            'regras.*.tipo_valor' => ['nullable', 'string', 'in:centro_custo,cnpj'],
            'regras.*.centro_custo_code' => ['nullable', 'string', 'exists:coreti_cost_centers,code'],
            'regras.*.valor_livre' => ['nullable', 'string', 'max:60'],
            'regras.*.ignorar' => ['nullable', 'boolean'],
            'regras.*.motivo_ignorar' => ['nullable', 'string', 'max:255'],
        ]);

        /*
         | O PHP corta campos em silêncio ao passar de max_input_vars — sem
         | erro, sem aviso. Numa tela de 127 linhas isso gravaria parte das
         | alterações e descartaria o resto sem ninguém perceber.
         |
         | O formulário informa quantas linhas mandou; se chegou menos, foi
         | truncado, e é melhor não gravar nada.
         */
        $recebidas = count($dados['regras'] ?? []);

        if ($recebidas < (int) $dados['total_enviado']) {
            Log::error('service-desk.ad.centro-custo.formulario_truncado', [
                'enviadas' => (int) $dados['total_enviado'],
                'recebidas' => $recebidas,
                'max_input_vars' => ini_get('max_input_vars'),
            ]);

            return back()->with('error', sprintf(
                'O servidor recebeu %d de %d linhas (limite max_input_vars = %s). Nada foi gravado. Filtre a lista e salve em partes.',
                $recebidas,
                (int) $dados['total_enviado'],
                ini_get('max_input_vars')
            ));
        }

        $regras = AdOfficeRule::whereIn('id', array_keys($dados['regras'] ?? []))->get()->keyBy('id');
        $alteradas = 0;

        foreach ($dados['regras'] ?? [] as $id => $campos) {
            $regra = $regras->get((int) $id);

            if ($regra === null) {
                continue;
            }

            $ignorar = (bool) ($campos['ignorar'] ?? false);
            $tipo = $campos['tipo_valor'] ?? AdOfficeRule::TIPO_CENTRO_CUSTO;

            $novos = [
                'ignorar' => $ignorar,
                'motivo_ignorar' => $ignorar ? ($campos['motivo_ignorar'] ?? $regra->motivo_ignorar) : null,
                'tipo_valor' => $tipo,
                'centro_custo_code' => $ignorar || $tipo !== AdOfficeRule::TIPO_CENTRO_CUSTO
                    ? null
                    : ($campos['centro_custo_code'] ?: null),
                'valor_livre' => $ignorar || $tipo !== AdOfficeRule::TIPO_CNPJ
                    ? null
                    : ($campos['valor_livre'] ?: null),
            ];

            $mudou = false;
            foreach ($novos as $campo => $valor) {
                if ($regra->{$campo} !== $valor) {
                    $mudou = true;

                    break;
                }
            }

            if (! $mudou) {
                continue;
            }

            $regra->update($novos + ['confirmado_por' => Auth::id(), 'confirmado_em' => now()]);
            $alteradas++;
        }

        return back()->with(
            'success',
            $alteradas === 0
                ? 'Nada mudou; nenhuma regra foi gravada.'
                : "{$alteradas} regra(s) atualizada(s)."
        );
    }

    public function adicionarExcecao(Request $request): RedirectResponse
    {
        $dados = $request->validate([
            'nome_usuario' => ['required', 'string', 'max:255'],
            'motivo' => ['required', 'string', 'max:255'],
        ]);

        // Guardado em minúsculo: cadastrar "Jose.Candido" não pode deixar
        // jose.candido de fora da proteção por causa da caixa das letras.
        $usuario = mb_strtolower(trim($dados['nome_usuario']));

        AdOfficeAccountException::updateOrCreate(
            ['nome_usuario' => $usuario],
            ['motivo' => $dados['motivo'], 'criado_por' => Auth::id()]
        );

        return back()->with('success', "{$usuario} não será mais alterada pela regra.");
    }

    public function removerExcecao(AdOfficeAccountException $excecao): RedirectResponse
    {
        $usuario = $excecao->nome_usuario;
        $excecao->delete();

        return back()->with('success', "{$usuario} voltou a seguir a regra da OU.");
    }

    /** Aceita a sugestão como decisão, sem alterar o código. */
    public function confirmar(AdOfficeRule $regra): RedirectResponse
    {
        if ($regra->valorParaAd() === null) {
            return back()->with('error', 'Defina o valor do campo Escritório antes de confirmar.');
        }

        $regra->update(['confirmado_por' => Auth::id(), 'confirmado_em' => now()]);

        return back()->with('success', "Regra de {$regra->ou_caminho} confirmada.");
    }
}
