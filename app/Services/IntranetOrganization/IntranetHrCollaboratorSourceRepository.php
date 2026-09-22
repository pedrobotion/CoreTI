<?php

namespace App\Services\IntranetOrganization;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class IntranetHrCollaboratorSourceRepository
{
    public function all(): Collection
    {
        return DB::connection('intranet')
            ->table('cadColaborador')
            ->select([
                'IdPessoa',
                'IdPessoaSuperior',
                'CentroCusto',
                'Matricula',
                'Nome',
                'Email',
                'DataDesligamento',
                'IdUnicoop',
                'IdArea',
                'IdDepartamento',
                'IdSetor',
                'IdCargo',
                'IdEmpresaCocari',
                'Desligado',
                'Ativo',
                'ExcluidoProtheus',
                'DataUltimaAtualizacao',
            ])
            ->orderBy('Nome')
            ->get();
    }
}
