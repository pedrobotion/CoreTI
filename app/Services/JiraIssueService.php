<?php

namespace App\Services;

use App\Models\CmdbExpiration;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class JiraIssueService
{
    public function isEnabled(): bool
    {
        return (bool) config('services.jira.enabled', false);
    }

    public function createIssueForExpiration(CmdbExpiration $expiration): array
    {
        $asset = $expiration->asset;
        $payload = [
            'summary' => sprintf(
                'Renovação necessária - %s - %s',
                $expiration->expiration_type,
                $asset?->name ?? $expiration->title
            ),
            'description' => implode("\n", [
                'O item abaixo está próximo do vencimento.',
                '',
                'Ativo: ' . ($asset?->name ?? '-'),
                'Tipo de ativo: ' . ($asset?->asset_type ?? '-'),
                'Tipo de vencimento: ' . $expiration->expiration_type,
                'Data de vencimento: ' . optional($expiration->expiration_date)->format('d/m/Y'),
                'Dias restantes: ' . (optional($expiration->expiration_date) ? now()->diffInDays($expiration->expiration_date, false) : '-'),
                'Localização: ' . ($asset?->location ?? '-'),
                'Responsável: ' . ($asset?->responsibleUser?->name ?? '-'),
                'Descrição: ' . ($expiration->description ?? '-'),
                'Link para o item no CMDB: ' . route('cmdb.assets.show', $asset),
                '',
                'Ação necessária:',
                'Avaliar renovação, troca, compra ou regularização antes do vencimento.',
            ]),
        ];

        if (! $this->isEnabled()) {
            Log::info('Jira desabilitado - simulação de abertura de chamado CMDB.', $payload);

            return [
                'status' => 'simulated',
                'issue_key' => null,
                'issue_url' => null,
            ];
        }

        $baseUrl = rtrim((string) config('services.jira.base_url', ''), '/');
        $email = (string) config('services.jira.email', '');
        $token = (string) config('services.jira.api_token', '');
        $projectKey = (string) config('services.jira.project_key', '');
        $issueType = (string) config('services.jira.issue_type', '');

        if ($baseUrl === '' || $email === '' || $token === '' || $projectKey === '' || $issueType === '') {
            Log::warning('Configuração Jira incompleta para CMDB.', compact('baseUrl', 'email', 'projectKey', 'issueType'));

            return [
                'status' => 'error',
                'issue_key' => null,
                'issue_url' => null,
                'message' => 'Configuração Jira incompleta.',
            ];
        }

        $response = Http::withBasicAuth($email, $token)
            ->acceptJson()
            ->post("{$baseUrl}/rest/api/3/issue", [
                'fields' => [
                    'project' => ['key' => $projectKey],
                    'summary' => $payload['summary'],
                    'description' => [
                        'type' => 'doc',
                        'version' => 1,
                        'content' => [
                            [
                                'type' => 'paragraph',
                                'content' => [
                                    ['type' => 'text', 'text' => $payload['description']],
                                ],
                            ],
                        ],
                    ],
                    'issuetype' => ['id' => $issueType],
                ],
            ]);

        if (! $response->successful()) {
            Log::error('Falha ao abrir chamado Jira para CMDB.', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return [
                'status' => 'error',
                'issue_key' => null,
                'issue_url' => null,
                'message' => 'Falha ao abrir chamado Jira.',
            ];
        }

        $key = $response->json('key');

        return [
            'status' => 'created',
            'issue_key' => $key,
            'issue_url' => $key ? "{$baseUrl}/browse/{$key}" : null,
        ];
    }
}
