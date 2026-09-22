<?php

namespace App\Services\IntranetOrganization;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class IntranetUnitSourceRepository
{
    public function all(): Collection
    {
        return DB::connection('intranet')
            ->table('cadUnicoop')
            ->select([
                'IdUnicoop',
                'Nome',
                'IdTipoUnicoop',
                'CNPJ',
                'InscricaoEstadual',
                'InscricaoMunicipal',
                'Endereco',
                'Complemento',
                'IdMunicipio',
                'CEP',
                'Fone',
                'FoneSecundario',
                'Fax',
                'RamalDireto',
                'Email',
                'EmailNFE',
                'IdRegional',
                'Ativo',
                'IdUnicoopOld',
                'DataAbertura',
                'DataBaixa',
                'NIRE',
                'Latitude',
                'Longitude',
            ])
            ->orderBy('IdUnicoop')
            ->get();
    }
}
