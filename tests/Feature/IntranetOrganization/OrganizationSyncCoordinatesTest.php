<?php

namespace Tests\Feature\IntranetOrganization;

use App\Models\OrganizationUnit;
use App\Services\IntranetOrganization\IntranetDepartmentSourceRepository;
use App\Services\IntranetOrganization\IntranetOrganizationSyncService;
use App\Services\IntranetOrganization\IntranetUnitSourceRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Mockery;
use Tests\TestCase;

/**
 * A origem é texto livre: um espaço não pode derrubar a sincronização inteira.
 *
 * Foi o que aconteceu em 22/09/2026 — a longitude de uma unidade veio com um
 * espaço à esquerda, o cast decimal recusou, e nenhuma unidade nem
 * departamento entrou naquela noite.
 */
class OrganizationSyncCoordinatesTest extends TestCase
{
    use RefreshDatabase;

    private function unidade(array $overrides = []): object
    {
        return (object) array_merge([
            'IdUnicoop' => 79,
            'Nome' => 'Patos de Minas',
            'IdTipoUnicoop' => 1,
            'CNPJ' => null,
            'InscricaoEstadual' => null,
            'InscricaoMunicipal' => null,
            'Endereco' => null,
            'Complemento' => null,
            'IdMunicipio' => null,
            'CEP' => null,
            'Fone' => null,
            'FoneSecundario' => null,
            'Fax' => null,
            'RamalDireto' => null,
            'Email' => null,
            'EmailNFE' => null,
            'IdRegional' => null,
            'Ativo' => true,
            'IdUnicoopOld' => null,
            'DataAbertura' => null,
            'DataBaixa' => null,
            'NIRE' => null,
            'Latitude' => '-18.621048976209742',
            'Longitude' => '-46.501164462182196',
        ], $overrides);
    }

    private function sincroniza(object ...$unidades): array
    {
        $units = Mockery::mock(IntranetUnitSourceRepository::class);
        $units->shouldReceive('all')->andReturn(new Collection($unidades));

        $departments = Mockery::mock(IntranetDepartmentSourceRepository::class);
        $departments->shouldReceive('all')->andReturn(new Collection());
        $departments->shouldReceive('metadata')->andReturn([]);

        return (new IntranetOrganizationSyncService($units, $departments))->sync();
    }

    public function test_longitude_com_espaco_a_esquerda_nao_derruba_a_sincronizacao(): void
    {
        /*
         | Precisa ser uma ATUALIZAÇÃO, não um cadastro novo.
         |
         | O cast decimal só entra em cena quando o Eloquent compara o valor
         | novo com o original para saber se o campo mudou — coisa que não
         | existe num insert. Foi exatamente o caso em produção: a unidade já
         | estava lá e teve outro campo alterado na origem.
         */
        $this->sincroniza($this->unidade());

        $resumo = $this->sincroniza($this->unidade([
            'Nome' => 'Patos de Minas II',
            'Longitude' => ' -46.501164462182196',
        ]));

        $this->assertSame('success', $resumo['status']);

        $unidade = OrganizationUnit::where('source_id', '79')->firstOrFail();
        $this->assertSame('Patos de Minas II', $unidade->name);
        $this->assertSame('-46.50116446', (string) $unidade->longitude);
    }

    public function test_coordenada_que_nao_e_numero_vira_nulo_em_vez_de_erro(): void
    {
        $this->sincroniza($this->unidade());

        $resumo = $this->sincroniza($this->unidade(['Latitude' => 'n/d', 'Longitude' => '']));

        $this->assertSame('success', $resumo['status']);

        $unidade = OrganizationUnit::where('source_id', '79')->firstOrFail();
        $this->assertNull($unidade->latitude);
        $this->assertNull($unidade->longitude);
    }

    public function test_uma_unidade_com_coordenada_ruim_nao_impede_as_demais(): void
    {
        // O prejuízo real não foi a coordenada: foi o lote inteiro parar.
        $this->sincroniza(
            $this->unidade(['IdUnicoop' => 79]),
            $this->unidade(['IdUnicoop' => 80, 'Nome' => 'Prudentópolis']),
        );

        $resumo = $this->sincroniza(
            $this->unidade(['IdUnicoop' => 79, 'Nome' => 'Patos de Minas II', 'Longitude' => ' -46.5011644']),
            $this->unidade(['IdUnicoop' => 80, 'Nome' => 'Prudentópolis II']),
        );

        $this->assertSame('success', $resumo['status']);
        $this->assertSame(2, OrganizationUnit::count());
        $this->assertSame('Prudentópolis II', OrganizationUnit::where('source_id', '80')->value('name'));
    }

    public function test_coordenada_normal_continua_sendo_gravada(): void
    {
        $this->sincroniza($this->unidade());

        $unidade = OrganizationUnit::where('source_id', '79')->firstOrFail();
        $this->assertSame('-18.62104898', (string) $unidade->latitude);
        $this->assertSame('-46.50116446', (string) $unidade->longitude);
    }
}
