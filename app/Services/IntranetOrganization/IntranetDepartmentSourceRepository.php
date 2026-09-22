<?php

namespace App\Services\IntranetOrganization;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class IntranetDepartmentSourceRepository
{
    public function all(): Collection
    {
        return DB::connection('intranet')
            ->table('cadDepartamento')
            ->select([
                'IdDepartamento',
                'Nome',
                'IdArea',
                'Ativo',
                'IdDepartamentoOld',
            ])
            ->orderBy('IdDepartamento')
            ->get();
    }

    public function metadata(): array
    {
        return [
            'table' => 'cadDepartamento',
            'primary_key' => 'IdDepartamento',
            'name_field' => 'Nome',
            'unit_field' => null,
            'area_field' => 'IdArea',
            'active_field' => 'Ativo',
        ];
    }
}
