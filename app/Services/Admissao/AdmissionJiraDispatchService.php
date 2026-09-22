<?php

namespace App\Services\Admissao;

use App\Models\AdmissionCase;
use App\Models\AdmissionTask;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Abre os chamados da admissão — um por área responsável, com TODAS as tarefas
 * daquela área no mesmo ticket. É o que substitui os "pedidos picados": a TI
 * recebe um chamado com a lista completa em vez de cinco solicitações soltas.
 *
 * Reusa a configuração de services.jira já existente no CoreTI. Quando o Jira
 * está desabilitado, roda em modo simulado: as tarefas são marcadas como
 * despachadas com uma chave fictícia, permitindo validar o fluxo inteiro sem
 * credencial — útil no piloto.
 */
class AdmissionJiraDispatchService
{
    public function __construct(private readonly AdmissionEventLogger $events)
    {
    }

    public function isEnabled(): bool
    {
        return (bool) config('services.jira.enabled', false);
    }

    /**
     * Despacha as tarefas ainda não enviadas do caso.
     *
     * @return array<int, array{area: string, status: string, issue_key: ?string, issue_url: ?string, tarefas: int, message?: string}>
     */
    public function dispatch(AdmissionCase $case): array
    {
        $pendentes = $case->tasks()
            ->whereNull('dispatched_at')
            ->pendentes()
            ->get();

        if ($pendentes->isEmpty()) {
            return [];
        }

        $grupos = config('admissao.chamado_por_area', true)
            ? $pendentes->groupBy('area')
            : $pendentes->groupBy(fn (AdmissionTask $task) => $task->area.':'.$task->task_type);

        $resultados = [];

        foreach ($grupos as $chave => $tarefas) {
            $area = $tarefas->first()->area;
            $resultado = $this->abrirChamado($case, $area, $tarefas);

            if (in_array($resultado['status'], ['created', 'simulated'], true)) {
                foreach ($tarefas as $task) {
                    $task->update([
                        'jira_issue_key' => $resultado['issue_key'],
                        'jira_issue_url' => $resultado['issue_url'],
                        'dispatched_at' => now(),
                        'status' => AdmissionTask::STATUS_EM_ANDAMENTO,
                    ]);
                }
            }

            $this->events->log(
                $case,
                'chamado.despachado',
                sprintf(
                    'Chamado para %s: %s',
                    (string) config("admissao.areas.{$area}.label", $area),
                    $resultado['issue_key'] ?? $resultado['status']
                ),
                $resultado + ['tarefas' => $tarefas->pluck('task_type')->all()],
            );

            $resultados[] = $resultado + ['area' => $area, 'tarefas' => $tarefas->count()];
        }

        return $resultados;
    }

    /**
     * @param  Collection<int, AdmissionTask>  $tarefas
     * @return array{status: string, issue_key: ?string, issue_url: ?string, message?: string}
     */
    private function abrirChamado(AdmissionCase $case, string $area, Collection $tarefas): array
    {
        $summary = sprintf(
            '[Admissão %s] %s — início em %s',
            $case->codigo,
            $case->nome,
            optional($case->data_inicio)->format('d/m/Y') ?? 'data a definir'
        );

        $description = $this->montarDescricao($case, $area, $tarefas);

        if (! $this->isEnabled()) {
            Log::info('admissao.jira.simulado', [
                'case' => $case->codigo,
                'area' => $area,
                'summary' => $summary,
            ]);

            return [
                'status' => 'simulated',
                'issue_key' => 'SIMULADO-'.$case->codigo.'-'.strtoupper($area),
                'issue_url' => null,
            ];
        }

        $baseUrl = rtrim((string) config('services.jira.base_url', ''), '/');
        $email = (string) config('services.jira.email', '');
        $token = (string) config('services.jira.api_token', '');
        $issueType = (string) config('services.jira.issue_type', '');
        $projectKey = (string) (config("admissao.areas.{$area}.jira_project_key")
            ?: config('services.jira.project_key', ''));

        if ($baseUrl === '' || $email === '' || $token === '' || $projectKey === '' || $issueType === '') {
            Log::warning('admissao.jira.config_incompleta', compact('baseUrl', 'projectKey', 'issueType', 'area'));

            return [
                'status' => 'error',
                'issue_key' => null,
                'issue_url' => null,
                'message' => 'Configuração Jira incompleta para a área '.$area.'.',
            ];
        }

        $response = Http::withBasicAuth($email, $token)
            ->acceptJson()
            ->post("{$baseUrl}/rest/api/3/issue", [
                'fields' => [
                    'project' => ['key' => $projectKey],
                    'summary' => $summary,
                    'description' => [
                        'type' => 'doc',
                        'version' => 1,
                        'content' => [[
                            'type' => 'paragraph',
                            'content' => [['type' => 'text', 'text' => $description]],
                        ]],
                    ],
                    'issuetype' => ['id' => $issueType],
                ],
            ]);

        if (! $response->successful()) {
            Log::error('admissao.jira.falha', [
                'case' => $case->codigo,
                'area' => $area,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return [
                'status' => 'error',
                'issue_key' => null,
                'issue_url' => null,
                'message' => 'Falha ao abrir chamado no Jira ('.$response->status().').',
            ];
        }

        $key = $response->json('key');

        return [
            'status' => 'created',
            'issue_key' => $key,
            'issue_url' => $key ? "{$baseUrl}/browse/{$key}" : null,
        ];
    }

    /**
     * @param  Collection<int, AdmissionTask>  $tarefas
     */
    private function montarDescricao(AdmissionCase $case, string $area, Collection $tarefas): string
    {
        $linhas = [
            'Solicitação gerada automaticamente pelo módulo de Admissão do CoreTI.',
            '',
            '== Colaborador ==',
            'Nome: '.$case->nome,
            'Matrícula: '.($case->matricula ?: 'a definir'),
            'CPF: '.($case->cpf ?: '-'),
            'Cargo: '.($case->cargo_nome ?: '-'),
            'Área: '.($case->area_nome ?: '-'),
            'Unidade: '.($case->unidade_nome ?: '-'),
            'Centro de custo: '.($case->centro_custo ?: '-'),
            'Gestor: '.($case->gestor_nome ?: '-').($case->gestor_email ? ' <'.$case->gestor_email.'>' : ''),
            'Data de admissão: '.(optional($case->data_admissao)->format('d/m/Y') ?: '-'),
            'Data de início: '.(optional($case->data_inicio)->format('d/m/Y') ?: '-'),
        ];

        if ($case->substitui_nome) {
            $linhas[] = 'Substitui: '.$case->substitui_nome.' (matrícula '.($case->substitui_matricula ?: '-').')';
            $linhas[] = 'Espelhar os acessos do colaborador substituído quando aplicável.';
        }

        if ($area === 'patrimonio') {
            $linhas[] = 'Tamanho de uniforme: '.($case->tamanho_uniforme ?: '-');
            $linhas[] = 'Tamanho de calçado: '.($case->tamanho_calcado ?: '-');
            $linhas[] = 'EPI: '.(empty($case->epi) ? '-' : implode(', ', (array) $case->epi));
        }

        $linhas[] = '';
        $linhas[] = '== Itens desta solicitação ==';

        foreach ($tarefas as $task) {
            $linhas[] = sprintf(
                '- %s%s | prazo: %s',
                $task->label(),
                $task->obrigatorio ? '' : ' (opcional)',
                optional($task->due_at)->format('d/m/Y') ?: 'a definir'
            );

            if ($task->observacao) {
                $linhas[] = '    Obs.: '.$task->observacao;
            }
        }

        if ($case->observacoes) {
            $linhas[] = '';
            $linhas[] = '== Observações do RH ==';
            $linhas[] = $case->observacoes;
        }

        $linhas[] = '';
        $linhas[] = 'Acompanhar em: '.route('admissao.cases.show', $case);
        $linhas[] = 'Ao concluir, registre o resultado no CoreTI para fechar o caso.';

        return implode("\n", $linhas);
    }
}
