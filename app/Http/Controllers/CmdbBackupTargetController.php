<?php

namespace App\Http\Controllers;

use App\Http\Requests\CmdbBackupTargetRequest;
use App\Models\CmdbAsset;
use App\Models\CmdbBackupIncident;
use App\Models\CmdbBackupTarget;
use App\Services\Backups\BackupVerificationService;
use App\Services\Backups\SshRunner;
use App\Services\Backups\Verification\StrategyResolver;
use App\Support\Backups\BackupStatus;
use Illuminate\Http\Request;

/**
 * Gerenciamento dos alvos de backup — CMDB → Backups → Alvos.
 *
 * O cadastro aqui é a fonte da verdade da auditoria: o que estiver nesta tela
 * é o que o CoreTI vai verificar, independentemente de qualquer evento externo.
 */
class CmdbBackupTargetController extends Controller
{
    public function __construct(
        private readonly BackupVerificationService $verificacao,
        private readonly SshRunner $ssh,
        private readonly StrategyResolver $estrategias,
    ) {
    }

    public function index(Request $request)
    {
        $situacao = (string) $request->query('situacao', 'todos');
        $busca = trim((string) $request->query('q', ''));

        $alvos = CmdbBackupTarget::query()
            ->with(['asset', 'ultimoCheck', 'incidenteAberto'])
            ->orderBy('host')
            ->orderBy('database_name')
            ->get();

        $resumo = [
            'total' => $alvos->count(),
            'saudaveis' => $alvos->where('last_status', BackupStatus::OK)->count(),
            'atrasados' => $alvos->where('last_status', BackupStatus::ATRASADO)->count(),
            'ausentes' => $alvos->where('last_status', BackupStatus::ARQUIVO_AUSENTE)->count(),
            'tamanho_anormal' => $alvos->where('last_status', BackupStatus::TAMANHO_ANORMAL)->count(),
            'inacessiveis' => $alvos->whereIn('last_status', [
                BackupStatus::SERVIDOR_INACESSIVEL,
                BackupStatus::ERRO_AUTENTICACAO,
                BackupStatus::ERRO_VERIFICACAO,
            ])->count(),
            'nunca_verificados' => $alvos->whereNull('last_status')->count(),
            'inativos' => $alvos->where('ativo', false)->count(),
        ];

        $resumo['com_problema'] = $resumo['atrasados'] + $resumo['ausentes'] + $resumo['tamanho_anormal'];

        $filtrados = match ($situacao) {
            'saudaveis' => $alvos->where('last_status', BackupStatus::OK),
            'problema' => $alvos->filter(fn ($a) => BackupStatus::ehFalhaDeBackup($a->last_status)),
            'auditoria' => $alvos->filter(fn ($a) => BackupStatus::ehFalhaDeAuditoria($a->last_status)),
            'nunca' => $alvos->whereNull('last_status'),
            'inativos' => $alvos->where('ativo', false),
            default => $alvos,
        };

        if ($busca !== '') {
            $termo = mb_strtolower($busca);
            $filtrados = $filtrados->filter(fn (CmdbBackupTarget $a) => str_contains(mb_strtolower($a->host), $termo)
                || str_contains(mb_strtolower($a->database_name), $termo)
                || str_contains(mb_strtolower((string) $a->nome), $termo));
        }

        return view('cmdb.backup-targets.index', [
            'alvos' => $filtrados->values(),
            'resumo' => $resumo,
            'situacao' => $situacao,
            'busca' => $busca,
            'jiraConfigurado' => app(\App\Services\Backups\DatabaseBackupJiraService::class)->habilitado(),
        ]);
    }

    public function create()
    {
        $this->autorizarGestao();

        return view('cmdb.backup-targets.create', [
            'alvo' => new CmdbBackupTarget([
                'tipo_backup' => 'progress',
                'metodo_verificacao' => 'progress_arquivo',
                'periodicidade' => 'diaria',
                'tolerancia_horas' => 6,
                'max_idade_horas' => (int) config('backups.stale_after_hours', 36),
                'estrategia_tamanho' => 'automatico',
                'ativo' => true,
            ]),
            'metodos' => $this->estrategias->opcoes(),
            'ativos' => $this->ativosParaSelecao(),
        ]);
    }

    public function store(CmdbBackupTargetRequest $request)
    {
        $alvo = CmdbBackupTarget::create($request->dadosDoAlvo() + ['origem_cadastro' => 'manual']);

        return redirect()
            ->route('cmdb.backup-targets.show', $alvo)
            ->with('success', 'Alvo cadastrado. Use "Testar conexão" antes de contar com a auditoria automática.');
    }

    public function show(CmdbBackupTarget $target)
    {
        $target->load(['asset', 'incidenteAberto']);

        return view('cmdb.backup-targets.show', [
            'alvo' => $target,
            'checks' => $target->checks()->limit(50)->get(),
            'incidentes' => $target->incidents()->limit(20)->get(),
        ]);
    }

    public function edit(CmdbBackupTarget $target)
    {
        $this->autorizarGestao();

        return view('cmdb.backup-targets.edit', [
            'alvo' => $target,
            'metodos' => $this->estrategias->opcoes(),
            'ativos' => $this->ativosParaSelecao(),
        ]);
    }

    public function update(CmdbBackupTargetRequest $request, CmdbBackupTarget $target)
    {
        $target->update($request->dadosDoAlvo());

        return redirect()
            ->route('cmdb.backup-targets.show', $target)
            ->with('success', 'Alvo atualizado.');
    }

    public function toggle(CmdbBackupTarget $target)
    {
        $this->autorizarGestao();

        $target->update(['ativo' => ! $target->ativo]);

        return back()->with('success', 'Alvo '.($target->ativo ? 'ativado' : 'desativado').'.');
    }

    /**
     * Testa apenas a conexão. Não lê o arquivo nem altera nada no servidor.
     */
    public function testarConexao(CmdbBackupTarget $target)
    {
        $this->autorizarGestao();

        $resultado = $this->ssh->testarConexao($target);

        return back()->with(
            $resultado['ok'] ? 'success' : 'error',
            $resultado['mensagem'].' ('.$resultado['duracao_ms'].'ms)'
        );
    }

    /**
     * Executa a estratégia completa do alvo, sob demanda.
     */
    public function verificarAgora(CmdbBackupTarget $target)
    {
        $this->autorizarGestao();

        $check = $this->verificacao->verificar($target, 'manual');

        if ($check === null) {
            return back()->with('error', 'Já existe uma verificação em andamento para este alvo.');
        }

        return back()->with(
            $check->ok() ? 'success' : 'error',
            BackupStatus::rotulo($check->status).': '.($check->detalhe ?: BackupStatus::descricao($check->status))
        );
    }

    private function autorizarGestao(): void
    {
        abort_unless(
            (bool) request()->user()?->canAnyModule(['cmdb.manage', 'network_datacenter.manage']),
            403,
            'Você não tem permissão para gerenciar alvos de backup.'
        );
    }

    private function ativosParaSelecao()
    {
        return CmdbAsset::query()
            ->select('id', 'name', 'hostname')
            ->orderBy('name')
            ->limit(500)
            ->get();
    }
}
