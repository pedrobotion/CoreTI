<?php

namespace App\Http\Controllers;

use App\Models\BancadaEquipmentEvent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Webhook do Jira para os chamados de MANUTENÇÃO da bancada. Quando o chamado é
 * finalizado (categoria de status "done"), traz a descrição que a equipe
 * escreveu de volta para a OBSERVAÇÃO do equipamento no CoreTI.
 *
 * O payload do webhook só precisa trazer a chave do chamado; os dados
 * autoritativos (status e descrição) são lidos do próprio Jira.
 */
class JiraManutencaoWebhookController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $chave = $this->chaveDoPayload($request);
        if ($chave === '') {
            return response()->json(['message' => 'Chave do chamado ausente.'], 422);
        }

        // Só nos interessa se foi um chamado de manutenção aberto pelo CoreTI.
        $evento = BancadaEquipmentEvent::query()
            ->where('action', 'manutencao_chamado')
            ->where('metadata->jira_issue_key', $chave)
            ->latest('id')
            ->first();

        if (! $evento || ! $evento->equipment) {
            return response()->json(['status' => 'ignorado', 'message' => 'Chamado não é de manutenção da bancada.'], 200);
        }

        // Idempotência: já sincronizado para esta chave?
        $jaSincronizado = BancadaEquipmentEvent::query()
            ->where('action', 'manutencao_chamado_finalizado')
            ->where('metadata->jira_issue_key', $chave)
            ->exists();
        if ($jaSincronizado) {
            return response()->json(['status' => 'duplicado'], 200);
        }

        $issue = $this->buscarIssue($chave);
        if ($issue === null) {
            return response()->json(['message' => 'Não foi possível consultar o chamado no Jira.'], 502);
        }

        // Só sincroniza quando o chamado está finalizado (categoria "done").
        if ((string) data_get($issue, 'fields.status.statusCategory.key') !== 'done') {
            return response()->json(['status' => 'pendente', 'message' => 'Chamado ainda não finalizado.'], 200);
        }

        $descricao = trim($this->adfParaTexto(data_get($issue, 'fields.description')));

        $equipment = $evento->equipment;
        $atual = trim((string) $equipment->observacao);
        $linha = "[Manutenção {$chave}] " . ($descricao !== '' ? $descricao : '(sem descrição)');
        $nova = ($atual !== '' ? $atual . "\n" : '') . $linha;
        // Guarda de tamanho, para não estourar a coluna.
        $equipment->observacao = mb_substr($nova, 0, 2000);
        $equipment->save();

        BancadaEquipmentEvent::create([
            'bancada_equipment_id' => $equipment->id,
            'previous_status' => $equipment->status,
            'new_status' => $equipment->status,
            'action' => 'manutencao_chamado_finalizado',
            'module' => 'Bancada',
            'performed_by' => null,
            'observation' => "Descrição do chamado {$chave} copiada para a observação.",
            'metadata' => ['jira_issue_key' => $chave, 'descricao' => $descricao],
        ]);

        Log::info('manutencao.webhook.sincronizado', ['chave' => $chave, 'equipment_id' => $equipment->id]);

        return response()->json(['status' => 'ok', 'equipment_id' => $equipment->id], 200);
    }

    private function chaveDoPayload(Request $request): string
    {
        foreach (['issue.key', 'key', 'issueKey', 'issue_key'] as $caminho) {
            $valor = trim((string) $request->input($caminho, ''));
            if ($valor !== '') {
                return $valor;
            }
        }

        return '';
    }

    /** @return array<string, mixed>|null */
    private function buscarIssue(string $chave): ?array
    {
        $base = rtrim((string) config('services.jira.base_url'), '/');
        if ($base === '') {
            return null;
        }

        $resposta = Http::withBasicAuth(
            (string) config('services.jira.email'),
            (string) config('services.jira.api_token')
        )->acceptJson()->timeout(20)
            ->get("{$base}/rest/api/3/issue/{$chave}", ['fields' => 'status,description,resolution']);

        return $resposta->successful() ? (array) $resposta->json() : null;
    }

    /** Extrai o texto de um documento ADF (Atlassian Document Format). */
    private function adfParaTexto(mixed $node): string
    {
        if (is_string($node)) {
            return $node;
        }
        if (! is_array($node)) {
            return '';
        }

        if (($node['type'] ?? '') === 'text') {
            return (string) ($node['text'] ?? '');
        }

        $texto = '';
        foreach ((array) ($node['content'] ?? []) as $filho) {
            $texto .= $this->adfParaTexto($filho);
        }

        // Quebra de linha após blocos de texto.
        if (in_array($node['type'] ?? '', ['paragraph', 'heading', 'listItem', 'blockquote'], true)) {
            $texto .= "\n";
        }

        return $texto;
    }
}
