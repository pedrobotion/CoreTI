<?php

namespace App\Services\Cmdb\Veeam;

use App\Models\CmdbAsset;

class VeeamProtectionStatusService
{
    public function calculate(array $data, CmdbAsset $asset): array
    {
        if (($data['api_status'] ?? null) === 'unavailable' || ($data['match_status'] ?? null) === 'ambiguous') {
            return ['status' => 'unknown', 'is_protected' => null, 'reason' => 'Dados indisponíveis ou vínculo ambíguo.'];
        }

        $hasJob = (bool) ($data['is_in_backup_job'] ?? false);
        $lastSuccess = $data['last_successful_backup_at'] ?? null;
        $restorePoint = $data['latest_restore_point_at'] ?? null;
        $hours = match ($asset->criticality) {
            'critical', 'high' => 24,
            'medium' => 48,
            default => 72,
        };

        if (! $hasJob || (! $lastSuccess && ! $restorePoint)) {
            return ['status' => 'unprotected', 'is_protected' => false, 'reason' => 'Sem job ou restore point válido.'];
        }

        if (in_array(strtolower((string) ($data['last_session_status'] ?? '')), ['failed', 'error'], true)) {
            return ['status' => 'failed', 'is_protected' => false, 'reason' => 'Última sessão sem sucesso.'];
        }

        $latest = $lastSuccess ?: $restorePoint;
        if (is_string($latest) && now()->parse($latest)->lt(now()->subHours($hours))) {
            return ['status' => 'overdue', 'is_protected' => false, 'reason' => 'Último sucesso ultrapassou o SLA.'];
        }

        return ['status' => 'protected', 'is_protected' => true, 'reason' => null];
    }
}
