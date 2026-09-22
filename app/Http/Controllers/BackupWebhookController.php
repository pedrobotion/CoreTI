<?php

namespace App\Http\Controllers;

use App\Services\Backups\DatabaseBackupIngestService;
use App\Services\Backups\DatabaseBackupJiraService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Endpoint que os agentes de backup chamam — substitui o webhook do n8n.
 *
 * Mantém o mesmo contrato de payload, para que o script nos servidores mude
 * apenas a URL e ganhe o header de token.
 */
class BackupWebhookController extends Controller
{
    public function __construct(
        private readonly DatabaseBackupIngestService $ingest,
        private readonly DatabaseBackupJiraService $jira,
    ) {
    }

    public function store(Request $request): JsonResponse
    {
        $dados = $request->validate([
            'host' => ['required', 'string', 'max:191'],
            'database' => ['required', 'string', 'max:191'],
            'status' => ['required', 'string', 'max:40'],
            'backup_type' => ['nullable', 'string', 'max:60'],
            'ip' => ['nullable', 'string', 'max:45'],
            'arquivo' => ['nullable', 'string', 'max:512'],
            'tamanho' => ['nullable', 'numeric'],
            'observacao' => ['nullable', 'string', 'max:2000'],
            'inicio' => ['nullable'],
            'fim' => ['nullable'],
            'data_geracao' => ['nullable'],
            'data_verificacao' => ['nullable'],
        ]);

        $resultado = $this->ingest->ingest($dados);
        $backup = $resultado['registro'];

        if ($resultado['duplicado']) {
            return response()->json([
                'status' => 'duplicado',
                'id' => $backup->id,
                'message' => 'Evento já registrado anteriormente.',
            ], 200);
        }

        Log::info('backup.webhook.recebido', [
            'id' => $backup->id,
            'host' => $backup->host,
            'database' => $backup->database_name,
            'status' => $backup->status,
            'sucesso' => $backup->sucesso,
        ]);

        $chamado = null;

        if (! $backup->sucesso) {
            // Falha no Jira não pode perder o registro de auditoria, que já
            // está gravado — por isso a exceção é contida aqui.
            try {
                $chamado = $this->jira->abrirChamado($backup);
            } catch (\Throwable $e) {
                Log::error('backup.webhook.jira_excecao', [
                    'id' => $backup->id,
                    'erro' => $e->getMessage(),
                ]);

                $backup->update(['jira_error' => mb_substr($e->getMessage(), 0, 500)]);
                $chamado = ['status' => 'error', 'message' => $e->getMessage()];
            }
        }

        return response()->json([
            'status' => 'registrado',
            'id' => $backup->id,
            'sucesso' => $backup->sucesso,
            'asset_id' => $backup->asset_id,
            'chamado' => $chamado,
        ], 201);
    }
}
