<?php

namespace App\Http\Controllers\Ad;

use App\Http\Controllers\Controller;
use App\Models\AdNonCollaboratorAccount;
use App\Services\Ad\AdCadastroIncompletoService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdCadastroIncompletoController extends Controller
{
    public function __construct(private readonly AdCadastroIncompletoService $servico)
    {
    }

    public function index(Request $request): View
    {
        [$campos, $incluirForaDeEscopo, $busca] = $this->filtros($request);

        $consulta = $this->servico->consulta($campos, $incluirForaDeEscopo);

        if ($busca !== '') {
            $consulta->where(function ($q) use ($busca): void {
                $q->where('nome_usuario', 'like', "%{$busca}%")
                    ->orWhere('nome_completo', 'like', "%{$busca}%");
            });
        }

        return view('service-desk.ad.cadastro-incompleto.index', [
            'contas' => $consulta->paginate(50)->withQueryString(),
            'campos' => AdCadastroIncompletoService::CAMPOS,
            'selecionados' => $campos,
            'incluirForaDeEscopo' => $incluirForaDeEscopo,
            'busca' => $busca,
            'contagens' => $this->servico->contagens($incluirForaDeEscopo),
            'naoColaboradores' => AdNonCollaboratorAccount::with('autor')->orderBy('nome_usuario')->get(),
            'servico' => $this->servico,
        ]);
    }

    public function exportar(Request $request): StreamedResponse
    {
        [$campos, $incluirForaDeEscopo] = $this->filtros($request);

        $consulta = $this->servico->consulta($campos, $incluirForaDeEscopo);
        $nome = 'ad_cadastro_incompleto_'.now()->local()->format('Y-m-d').'.csv';

        return response()->streamDownload(function () use ($consulta): void {
            $saida = fopen('php://output', 'w');

            // Sem BOM: o Import-Csv do PowerShell 5.1 não o descarta e a
            // primeira coluna viria com lixo no nome.
            fputcsv($saida, ['nome_usuario', 'nome_completo', 'unidade', 'setor', 'cargo', 'matricula', 'centro_custo', 'faltando'], ';');

            $consulta->chunk(500, function ($contas) use ($saida): void {
                foreach ($contas as $conta) {
                    fputcsv($saida, [
                        $conta->nome_usuario,
                        $conta->nome_completo,
                        $conta->unidade_principal,
                        $conta->unidade_setor,
                        $conta->Cargo,
                        $conta->matricula,
                        $conta->centro_custo,
                        $this->servico->faltando($conta)->implode(', '),
                    ], ';');
                }
            });

            fclose($saida);
        }, $nome, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function marcarNaoColaborador(Request $request): RedirectResponse
    {
        $dados = $request->validate([
            'nome_usuario' => ['required', 'string', 'max:255'],
            'motivo' => ['nullable', 'string', 'max:255'],
        ]);

        // Minúsculo: a comparação não pode depender da caixa das letras.
        AdNonCollaboratorAccount::updateOrCreate(
            ['nome_usuario' => mb_strtolower(trim($dados['nome_usuario']))],
            // 'nullable' permite a chave nem existir: o formulário da linha
            // manda só o nome_usuario.
            ['motivo' => ($dados['motivo'] ?? null) ?: 'Marcada na tela', 'criado_por' => Auth::id()]
        );

        return back()->with('success', $dados['nome_usuario'].' saiu do relatório de colaborador.');
    }

    public function reverterNaoColaborador(AdNonCollaboratorAccount $conta): RedirectResponse
    {
        $nome = $conta->nome_usuario;
        $conta->delete();

        return back()->with('success', $nome.' voltou ao relatório.');
    }

    /** @return array{0: array<int, string>, 1: bool, 2: string} */
    private function filtros(Request $request): array
    {
        $dados = $request->validate([
            'campos' => ['nullable', 'array'],
            'campos.*' => ['string', 'in:'.implode(',', array_keys(AdCadastroIncompletoService::CAMPOS))],
            'servico' => ['nullable', 'boolean'],
            'q' => ['nullable', 'string', 'max:120'],
        ]);

        return [
            array_values($dados['campos'] ?? []),
            (bool) ($dados['servico'] ?? false),
            trim((string) ($dados['q'] ?? '')),
        ];
    }
}
