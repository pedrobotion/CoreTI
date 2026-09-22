<?php

namespace App\Services;

use App\Mail\CoreTiNotificationMail;
use App\Models\CoreTiNotificationRule;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Throwable;

class CoreTiSyncNotificationService
{
    /**
     * Sends only warnings and failures. Successful synchronization runs are
     * intentionally silent to avoid operational e-mail noise.
     */
    public function notify(
        string $source,
        array $summary = [],
        string $status = 'success',
        ?string $message = null,
        array $context = [],
    ): bool {
        if (! (bool) config('coreti.sync_notifications.enabled', true)) {
            return false;
        }

        $status = in_array($status, ['success', 'warning', 'failed'], true) ? $status : 'success';
        if ($status === 'success') {
            return false;
        }

        $recipients = $this->resolveRecipients($source);
        if ($recipients === []) {
            Log::warning('CoreTI sync notification skipped: no recipients configured.', ['source' => $source]);
            return false;
        }

        $type = $status === 'failed' ? 'INCIDENTE' : ($status === 'warning' ? 'AVISO' : 'INFORMAÇÃO');
        $title = 'Sincronização ' . $source . ' ' . ($status === 'failed' ? 'com falha' : 'concluída');
        $message = $message ?: $this->defaultMessage($source, $summary, $status);

        try {
            Mail::to($recipients)->send(new CoreTiNotificationMail([
                'tipo_mensagem' => $type,
                'titulo' => $title,
                'destinatario' => 'equipe de Tecnologia da Informação',
                'mensagem_principal' => $message,
                'servico' => 'Sincronização ' . $source,
                'status' => strtoupper($status),
                'orientacao' => $this->formatDetails($summary, $context),
                'url_acao' => $context['url'] ?? null,
                'texto_botao' => $context['button'] ?? 'Abrir CoreTI',
            ]));

            Log::info('CoreTI sync notification sent.', [
                'source' => $source,
                'status' => $status,
                'recipients_count' => count($recipients),
            ]);

            return true;
        } catch (Throwable $throwable) {
            Log::error('CoreTI sync notification failed.', [
                'source' => $source,
                'status' => $status,
                'error' => $throwable->getMessage(),
            ]);

            return false;
        }
    }

    private function resolveRecipients(string $source): array
    {
        $eventKey = $this->eventKey($source);
        try {
            if (Schema::hasTable('coreti_notification_rules')) {
                $rule = CoreTiNotificationRule::query()
                    ->where('event_key', $eventKey)
                    ->where('active', true)
                    ->with(['recipients' => fn ($query) => $query->where('active', true)])
                    ->first();

                if ($rule) {
                    return $rule->recipients
                        ->pluck('email')
                        ->filter(fn ($email) => filter_var($email, FILTER_VALIDATE_EMAIL))
                        ->unique()
                        ->values()
                        ->all();
                }
            }
        } catch (Throwable $throwable) {
            Log::warning('CoreTI notification routing table could not be read; using environment fallback.', [
                'source' => $source,
                'error' => $throwable->getMessage(),
            ]);
        }

        return (array) config('coreti.sync_notifications.recipients', []);
    }

    private function eventKey(string $source): string
    {
        $source = mb_strtolower($source);

        return match (true) {
            str_contains($source, 'office') || str_contains($source, 'microsoft') => 'office_sync',
            str_contains($source, 'google') || str_contains($source, 'workspace') => 'google_workspace_sync',
            str_contains($source, 'veeam') => 'veeam_sync',
            str_contains($source, 'cmdb') || str_contains($source, 'vcenter') || str_contains($source, 'snmp') => 'cmdb_sync',
            // Depois de 'cmdb' de propósito: um conector CMDB chamado
            // "Active Directory" deve continuar caindo em cmdb_sync.
            str_contains($source, 'expurgo') || str_contains($source, 'active directory') => 'ad_purge',
            str_contains($source, 'vencimento') || str_contains($source, 'renova') => 'expiration_alert',
            default => 'system_alert',
        };
    }

    private function defaultMessage(string $source, array $summary, string $status): string
    {
        if ($status === 'failed') {
            return 'A sincronização ' . $source . ' terminou com falha. Consulte os detalhes no CoreTI.';
        }

        return 'A sincronização ' . $source . ' terminou com alterações, pendências ou avisos que merecem atenção.';
    }

    private function formatDetails(array $summary, array $context): ?string
    {
        $lines = [];
        foreach ($summary as $key => $value) {
            if (is_scalar($value) && $value !== '' && $value !== null) {
                $lines[] = ucfirst(str_replace('_', ' ', (string) $key)) . ': ' . $value;
            }
        }

        foreach ((array) ($context['details'] ?? []) as $label => $value) {
            if (is_scalar($value) && $value !== '' && $value !== null) {
                $lines[] = ucfirst(str_replace('_', ' ', (string) $label)) . ': ' . $value;
            }
        }

        return $lines === [] ? null : implode(' | ', array_slice($lines, 0, 20));
    }
}
