<?php

namespace App\Services;

use App\Models\CmdbDiscoveredAsset;
use Illuminate\Database\Eloquent\Builder;

class CmdbDiscoveryQueryService
{
    /*
     | Precisa espelhar exatamente o que o sync grava em source_type.
     |
     | O valor de máquina virtual estava como 'vcenter_vm', mas o
     | CmdbVCenterAssetCreationService sempre gravou 'vcenter_virtual_machine'
     | — o resultado é que nenhuma VM aparecia na tela de Descobertas nem
     | entrava nos contadores.
     */
    public const SUPPORTED_SOURCE_TYPES = [
        'snmp', 'vcenter_virtual_machine', 'vcenter_host', 'vcenter_network',
        'vcenter_datastore', 'vcenter_cluster', 'vcenter_datacenter',
    ];

    /**
     * Todas as descobertas, sem restringir por tipo.
     *
     * A consulta filtrava por SUPPORTED_SOURCE_TYPES, uma lista de permissão.
     * Um tipo novo vindo do vSphere simplesmente não aparecia — nem na tela,
     * nem nas contagens —, e ninguém saberia que ele existe. A constante segue
     * servindo para montar o filtro de tipo da tela; deixou de decidir o que
     * pode ser visto.
     */
    public function query(): Builder
    {
        return CmdbDiscoveredAsset::query();
    }

    /**
     * Descobertas que realmente esperam decisão humana.
     *
     * Exclui os tipos de referência do vSphere (rede, datastore, cluster,
     * datacenter): ficariam pendentes para sempre, escondendo os poucos itens
     * que precisam de alguém.
     */
    public function pending(): Builder
    {
        $referencia = self::tiposDeReferencia();

        return $this->query()
            ->whereIn('status', ['pending', 'suggested', 'reopened'])
            ->when($referencia !== [], fn ($q) => $q->whereNotIn('source_type', $referencia));
    }

    /** @return array<int, string> */
    public static function tiposDeReferencia(): array
    {
        return array_values(array_filter((array) config('cmdb.tipos_de_referencia', [])));
    }

    /** Pendentes de qualquer tipo, incluindo os de referência. */
    public function pendingTodosOsTipos(): Builder
    {
        return $this->query()->whereIn('status', ['pending', 'suggested', 'reopened']);
    }

    public function counts(): array
    {
        return [
            'total' => (int) $this->query()->count(),
            'pending' => (int) $this->pending()->count(),
            'linked' => (int) $this->query()->where('status', 'linked')->count(),
            'created' => (int) $this->query()->where('status', 'created')->count(),
            'ignored' => (int) $this->query()->where('status', 'ignored')->count(),
            'errors' => (int) $this->query()->whereIn('status', ['error', 'failed'])->count(),
        ];
    }
}
