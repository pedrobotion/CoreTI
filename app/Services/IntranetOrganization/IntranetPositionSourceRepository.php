<?php

namespace App\Services\IntranetOrganization;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class IntranetPositionSourceRepository
{
    public function all(): Collection
    {
        try {
            return DB::connection('intranet')->table('cadCargo')->select([
                'IdCargo', 'Nome', 'CodigoRH', 'CodigoNivelRH', 'IdCategoriaCargo', 'Ativo',
                'IdCargoRH', 'Sistema', 'CBO', 'Detalhes', 'DataIntegracao',
            ])->get();
        } catch (\Throwable) {
            return collect();
        }
    }
}
