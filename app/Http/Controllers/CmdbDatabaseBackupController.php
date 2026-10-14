<?php

namespace App\Http\Controllers;

use App\Models\CmdbBackupCheck;
use App\Models\CmdbBackupTarget;
use App\Models\CmdbDatabaseBackup;
use App\Services\Backups\DatabaseBackupJiraService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Visão de backups de banco dentro do CMDB.
 *
 * A tela principal é o painel por host+banco: o que interessa ao time não é a
 * lista de eventos, e sim "quais pares estão saudáveis, falhando ou mudos".
 */
class CmdbDatabaseBackupController extends Controller
{
    public function __construct(private readonly DatabaseBackupJiraService $jira)
    {
    }

    public function index(Request $request)
    {
        $situacao = (string) $request->query('situacao', 'todos');
        $busca = trim((string) $request->query('q', ''));
        $horasParaAtraso = (int) config('backups.stale_after_hours', 36);

        // Último evento de cada par host+banco.
        $ultimos = CmdbDatabaseBackup::query()
            ->select('cmdb_database_backups.*')
            ->joinSub(
                CmdbDatabaseBackup::query()
                    ->selectRaw('host, database_name, MAX(COALESCE(data_backup, received_at)) as ultimo')
                    ->groupBy('host', 'database_name'),
                'u',
                fn ($join) => $join
                    ->on('cmdb_database_backups.host', '=', 'u.host')
                    ->on('cmdb_database_backups.database_name', '=', 'u.database_name')
                    ->whereRaw('COALESCE(cmdb_database_backups.data_backup, cmdb_database_backups.received_at) = u.ultimo')
            )
            ->with('asset')
            ->orderBy('cmdb_database_backups.host')
            ->orderBy('cmdb_database_backups.database_name')
            ->get()
            ->unique(fn (CmdbDatabaseBackup $b) => $b->host.'|'.$b->database_name)
            ->values();

        $limite = now()->subHours($horasParaAtraso);

        /*
         | Atrasado e inativo são coisas diferentes.
         |
         | Um par que parou de reportar ontem é incidente: alguém precisa
         | olhar hoje. Um que parou há um ano é registro que deveria ter sido
         | aposentado — no nosso caso, identificadores antigos de bancos que
         | hoje reportam com outro nome (/banco/trr/base/trr virou trr).
         |
         | Misturar os dois enche a tela de alarme que ninguém vai resolver, e
         | o alarme legítimo se perde no meio.
         */
        $diasParaInativo = (int) config('backups.inactive_after_days', 30);
        $limiteInativo = now()->subDays($diasParaInativo);

        $ultimos = $ultimos->map(function (CmdbDatabaseBackup $b) use ($limite, $limiteInativo) {
            $referencia = $b->data_backup ?? $b->received_at;

            $b->inativo = $referencia !== null && $referencia->lessThan($limiteInativo);
            $b->atrasado = ! $b->inativo
                && ($referencia === null || $referencia->lessThan($limite));

            return $b;
        });

        // Inativo sai da lista por padrão: continua consultável pelo filtro.
        $ativos = $ultimos->where('inativo', false);

        $resumo = [
            'pares' => $ativos->count(),
            'ok' => $ativos->where('sucesso', true)->where('atrasado', false)->count(),
            'falha' => $ativos->where('sucesso', false)->count(),
            'atrasados' => $ativos->where('atrasado', true)->count(),
            'sem_ativo' => $ativos->whereNull('asset_id')->count(),
            'inativos' => $ultimos->where('inativo', true)->count(),
        ];

        $ultimos = $situacao === 'inativos' ? $ultimos->where('inativo', true) : $ativos;

        if ($situacao === 'falha') {
            $ultimos = $ultimos->where('sucesso', false);
        } elseif ($situacao === 'atrasados') {
            $ultimos = $ultimos->where('atrasado', true);
        } elseif ($situacao === 'ok') {
            $ultimos = $ultimos->where('sucesso', true)->where('atrasado', false);
        } elseif ($situacao === 'sem_ativo') {
            $ultimos = $ultimos->whereNull('asset_id');
        }

        if ($busca !== '') {
            $termo = mb_strtolower($busca);
            $ultimos = $ultimos->filter(fn (CmdbDatabaseBackup $b) => str_contains(mb_strtolower($b->host), $termo)
                || str_contains(mb_strtolower($b->database_name), $termo));
        }

        // Verificação ativa: indexada por host|banco para cruzar com o que o
        // agente reportou. Divergência entre as duas fontes é o alerta forte.
        $verificacoes = CmdbBackupTarget::query()
            ->get()
            ->keyBy(fn (CmdbBackupTarget $t) => $t->host.'|'.$t->database_name);

        $divergentes = CmdbBackupCheck::where('divergente', true)
            ->where('executado_em', '>=', now()->subDays(7))
            ->count();

        $resumo['divergentes'] = $divergentes;

        return view('cmdb.database-backups.index', [
            'verificacoes' => $verificacoes,
            'pares' => $ultimos->values(),
            'resumo' => $resumo,
            'situacao' => $situacao,
            'busca' => $busca,
            'horasParaAtraso' => $horasParaAtraso,
            'diasParaInativo' => $diasParaInativo,
            'jiraAtivo' => $this->jira->habilitado(),
            'jiraConfigurado' => $this->jira->configurado(),
            'jiraMotivo' => $this->jira->motivoDesligado(),
        ]);
    }

    /**
     * Histórico de eventos, com filtros — equivale a consultar a tabela antiga.
     */
    public function events(Request $request)
    {
        $query = CmdbDatabaseBackup::query()->with('asset')->latest('data_backup')->latest('id');

        if ($host = trim((string) $request->query('host', ''))) {
            $query->where('host', $host);
        }

        if ($database = trim((string) $request->query('database', ''))) {
            $query->where('database_name', $database);
        }

        if ($request->query('situacao') === 'falha') {
            $query->falhas();
        } elseif ($request->query('situacao') === 'ok') {
            $query->sucessos();
        }

        if ($dias = (int) $request->query('dias', 30)) {
            $query->where(fn ($q) => $q
                ->where('data_backup', '>=', now()->subDays($dias))
                ->orWhere('received_at', '>=', now()->subDays($dias)));
        }

        return view('cmdb.database-backups.events', [
            'eventos' => $query->paginate(50)->withQueryString(),
            'hosts' => CmdbDatabaseBackup::query()->distinct()->orderBy('host')->pluck('host'),
            'filtros' => $request->only(['host', 'database', 'situacao', 'dias']),
        ]);
    }

    public function show(CmdbDatabaseBackup $backup)
    {
        $backup->load('asset');

        $historico = CmdbDatabaseBackup::doPar($backup->host, $backup->database_name)
            ->latest('data_backup')
            ->limit(30)
            ->get();

        return view('cmdb.database-backups.show', compact('backup', 'historico'));
    }

    /**
     * Reabertura manual do chamado — útil quando o Jira estava fora no momento
     * do evento e a falha ficou registrada sem ticket.
     */
    public function reenviarChamado(CmdbDatabaseBackup $backup)
    {
        if ($backup->sucesso) {
            return back()->with('error', 'Este backup não falhou.');
        }

        $resultado = $this->jira->abrirChamado($backup);

        return back()->with(
            in_array($resultado['status'], ['created', 'simulated', 'suppressed'], true) ? 'success' : 'error',
            match ($resultado['status']) {
                'created' => 'Chamado aberto: '.$resultado['issue_key'],
                'simulated' => 'Jira não configurado — chamado apenas simulado.',
                'suppressed' => 'Já existe chamado recente: '.$resultado['issue_key'],
                default => $resultado['message'] ?? 'Falha ao abrir chamado.',
            }
        );
    }
}
