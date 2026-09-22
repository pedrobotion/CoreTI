<?php

namespace App\Services\IntranetOrganization;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class IntranetCostCenterSourceRepository
{
    public function accounting(): Collection
    {
        return DB::connection('intranet')
            ->table('cadCentroCustos')
            ->select([
                'IdCCusto',
                'Nome',
                'IdUnicoop',
                'IdAreaContabil',
                'Ativo',
            ])
            ->orderBy('IdCCusto')
            ->get();
    }

    public function rh(): Collection
    {
        return DB::connection('intranet')
            ->table('cadCentroCustoRh')
            ->select([
                'IdCentroCusto',
                'Nome',
                'IdUnicoop',
            ])
            ->orderBy('IdCentroCusto')
            ->get();
    }
}
