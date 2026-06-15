<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class JiraAutomationService
{
    public function dispatch(string $event, array $payload): void
    {
        $webhookUrl = trim((string) env('JIRA_AUTOMATION_WEBHOOK_URL', ''));
        $data = [
            'event' => $event,
            'source' => 'coreti',
            'sent_at' => now()->toIso8601String(),
            'payload' => $payload,
        ];

        if ($webhookUrl === '') {
            Log::info('jira_automation.skipped_no_webhook', $data);

            return;
        }

        try {
            Http::timeout(8)->post($webhookUrl, $data)->throw();
            Log::info('jira_automation.sent', ['event' => $event] + $payload);
        } catch (\Throwable $e) {
            Log::error('jira_automation.failed', [
                'event' => $event,
                'error' => $e->getMessage(),
                'payload' => $payload,
            ]);
        }
    }
}

