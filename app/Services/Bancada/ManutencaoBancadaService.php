<?php

namespace App\Services\Bancada;

use App\Models\BancadaEquipment;
use App\Services\BancadaStatusFlowService;
use Illuminate\Support\Collection;

/**
 * Equipamentos prontos para manutenção: os que já passaram pela entrada e estão
 * na bancada (status "Em bancada"). É nesse momento que a equipe liga para a
 * unidade e confirma o defeito — por isso o chamado de manutenção é aberto aqui.
 */
class ManutencaoBancadaService
{
    public function __construct(private readonly BancadaStatusFlowService $statusFlow)
    {
    }

    /** @return Collection<int, BancadaEquipment> */
    public function detectar(): Collection
    {
        return BancadaEquipment::query()
            ->where('status', BancadaStatusFlowService::STATUS_EM_BANCADA)
            ->orderBy('id')
            ->get();
    }

    /** O equipamento é da Sede (matriz)? Define se a Unidade no Jira é "Sede". */
    public function ehSede(BancadaEquipment $equipment): bool
    {
        return ($equipment->origem_tipo ?? null) === 'sede'
            || str_contains(mb_strtolower((string) $equipment->unidade_setor), 'sede');
    }
}
