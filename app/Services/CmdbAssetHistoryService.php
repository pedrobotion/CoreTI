<?php

namespace App\Services;

use App\Models\CmdbAsset;
use App\Models\CmdbAssetHistory;
use App\Models\User;

class CmdbAssetHistoryService
{
    public const LABELS = [
        'created' => 'Criado',
        'updated' => 'Atualizado',
        'deleted' => 'Excluído',
        'ip_added' => 'IP adicionado',
        'ip_updated' => 'IP alterado',
        'ip_deleted' => 'IP removido',
        'relationship_added' => 'Relacionamento criado',
        'relationship_updated' => 'Relacionamento alterado',
        'relationship_deleted' => 'Relacionamento removido',
        'expiration_created' => 'Vencimento criado',
        'expiration_updated' => 'Vencimento alterado',
        'jira_issue_created' => 'Chamado Jira criado',
        'document_uploaded' => 'Documento enviado',
        'document_deleted' => 'Documento removido',
        'photo_uploaded' => 'Foto enviada',
        'photo_deleted' => 'Foto removida',
        'manual_note' => 'Nota manual',
        'sync_started' => 'Sincronização iniciada',
        'sync_completed' => 'Sincronização concluída',
        'sync_failed' => 'Sincronização falhou',
        'sync_updated_asset' => 'Ativo atualizado por sincronização',
        'source_changed' => 'Origem alterada',
        'sync_snmp_discovered' => 'Sincronização SNMP',
        'discovery_linked' => 'Descoberta vinculada',
        'discovery_created' => 'Descoberta convertida em ativo',
        'discovery_ignored' => 'Descoberta ignorada',
        'discovery_reopened' => 'Descoberta reaberta',
    ];

    public function record(
        CmdbAsset $asset,
        ?User $user,
        string $eventType,
        string $title,
        ?string $description = null,
        array $oldValues = [],
        array $newValues = [],
    ): CmdbAssetHistory {
        return CmdbAssetHistory::create([
            'asset_id' => $asset->id,
            'user_id' => $user?->id,
            'event_type' => $eventType,
            'title' => $title,
            'description' => $description,
            'old_values' => $oldValues ?: null,
            'new_values' => $newValues ?: null,
        ]);
    }
}
