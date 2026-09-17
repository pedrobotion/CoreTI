<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Apoio ao Termo de Responsabilidade no modelo oficial da COCARI.
 *
 * Três coisas que o documento pede e o CoreTI ainda não tinha:
 *
 *  - numeração própria e contínua ("Termo nº 1617"), que dá sequência à série
 *    em papel e é referenciada pelo campo "Número do Termo anterior";
 *  - a área organizacional do colaborador, que existe na intranet (cadArea) e
 *    ainda não era espelhada aqui;
 *  - a cidade da unidade, para o termo do Cerrado não sair assinado em
 *    Mandaguari.
 */
return new class extends Migration
{
    /**
     * Município de cada unidade, por código IBGE.
     *
     * O nome veio do cadastro de unidades do gestaoTI, conferido um a um; a UF
     * é derivada do próprio código IBGE, que é a fonte autoritativa — e foi
     * assim que apareceu uma divergência: Piracanjuba está como SP no
     * gestaoTI, mas o código 52 é de Goiás.
     */
    private const MUNICIPIOS = [
        ['3119302', 'Coromandel', 'MG'],
        ['3128600', 'Guarda-Mor', 'MG'],
        ['3147006', 'Paracatu', 'MG'],
        ['3148004', 'Patos de Minas', 'MG'],
        ['4100806', 'Alvorada do Sul', 'PR'],
        ['4101408', 'Apucarana', 'PR'],
        ['4103206', 'Bom Sucesso', 'PR'],
        ['4103305', 'Borrazópolis', 'PR'],
        ['4103800', 'Cambira', 'PR'],
        ['4106852', 'Cruzmaltina', 'PR'],
        ['4107603', 'Faxinal', 'PR'],
        ['4108551', 'Godoy Moreira', 'PR'],
        ['4110102', 'Imbituva', 'PR'],
        ['4111100', 'Itambé', 'PR'],
        ['4111407', 'Ivaí', 'PR'],
        ['4112108', 'Jandaia do Sul', 'PR'],
        ['4112504', 'Jardim Alegre', 'PR'],
        ['4113106', 'Kaloré', 'PR'],
        ['4113759', 'Lunardelli', 'PR'],
        ['4114203', 'Mandaguari', 'PR'],
        ['4114807', 'Marialva', 'PR'],
        ['4114906', 'Marilândia do Sul', 'PR'],
        ['4115507', 'Marumbi', 'PR'],
        ['4117297', 'Novo Itacolomi', 'PR'],
        ['4117305', 'Ortigueira', 'PR'],
        ['4118204', 'Paranaguá', 'PR'],
        ['4119905', 'Ponta Grossa', 'PR'],
        ['4120606', 'Prudentópolis', 'PR'],
        ['4122172', 'Rio Branco do Ivaí', 'PR'],
        ['4122651', 'Rosário do Ivaí', 'PR'],
        ['4125803', 'São Pedro do Ivaí', 'PR'],
        ['4127007', 'Teixeira Soares', 'PR'],
        ['5204805', 'Campo Alegre de Goiás', 'GO'],
        ['5205109', 'Catalão', 'GO'],
        ['5206206', 'Cristalina', 'GO'],
        ['5210109', 'Ipameri', 'GO'],
        ['5215306', 'Orizona', 'GO'],
        ['5217104', 'Piracanjuba', 'GO'],
        ['5217609', 'Planaltina', 'GO'],
        ['5220009', "São João d'Aliança", 'GO'],
        ['5220603', 'Silvânia', 'GO'],
    ];

    public function up(): void
    {
        Schema::create('municipios', function (Blueprint $table): void {
            $table->id();
            $table->string('codigo_ibge', 7)->unique();
            $table->string('nome', 120);
            $table->string('uf', 2)->index();
            $table->timestamps();
        });

        Schema::create('organization_areas', function (Blueprint $table): void {
            $table->id();
            $table->string('source_system', 50)->default('intranet');
            $table->string('source_id', 100);
            $table->string('name')->index();
            $table->string('division_source_id', 100)->nullable()->index();
            $table->boolean('active')->default(true)->index();
            $table->string('source_hash', 64)->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->json('raw_data')->nullable();
            $table->timestamps();

            $table->unique(['source_system', 'source_id'], 'organization_area_source_unique');
        });

        /*
         | Sequência em tabela, não MAX()+1.
         |
         | Dois termos gerados ao mesmo tempo leriam o mesmo MAX e receberiam o
         | mesmo número. Aqui o UPDATE toma o lock da linha e serializa a
         | emissão — o número do termo é referência de documento e não pode
         | repetir.
         */
        Schema::create('termo_sequencias', function (Blueprint $table): void {
            $table->id();
            $table->string('chave', 40)->unique();
            $table->unsignedInteger('proximo');
            $table->timestamps();
        });

        Schema::table('asset_assignments', function (Blueprint $table): void {
            $table->unsignedInteger('numero_termo')->nullable()->unique()->after('id');
        });

        $agora = now();

        DB::table('municipios')->insert(array_map(
            fn (array $m) => ['codigo_ibge' => $m[0], 'nome' => $m[1], 'uf' => $m[2], 'created_at' => $agora, 'updated_at' => $agora],
            self::MUNICIPIOS
        ));

        DB::table('termo_sequencias')->insert([
            'chave' => 'celular',
            'proximo' => (int) env('TELEFONIA_TERMO_NUMERO_INICIAL', 1617),
            'created_at' => $agora,
            'updated_at' => $agora,
        ]);

        $this->semearAreas($agora);
    }

    public function down(): void
    {
        Schema::table('asset_assignments', function (Blueprint $table): void {
            $table->dropUnique(['numero_termo']);
            $table->dropColumn('numero_termo');
        });

        Schema::dropIfExists('termo_sequencias');
        Schema::dropIfExists('organization_areas');
        Schema::dropIfExists('municipios');
    }

    /**
     * Carga inicial das áreas a partir da intranet.
     *
     * Falha em silêncio de propósito: a migration não pode depender da intranet
     * estar no ar. Sem as áreas, o termo sai com o campo em branco em vez de
     * não sair.
     */
    private function semearAreas(\Illuminate\Support\Carbon $agora): void
    {
        try {
            $linhas = DB::connection('intranet')->table('cadArea')->get(['IdArea', 'Nome', 'IdDivisao', 'Ativo']);
        } catch (\Throwable) {
            return;
        }

        $registros = $linhas->map(fn ($linha) => [
            'source_system' => 'intranet',
            'source_id' => (string) $linha->IdArea,
            'name' => trim((string) $linha->Nome),
            'division_source_id' => $linha->IdDivisao !== null ? (string) $linha->IdDivisao : null,
            'active' => (bool) $linha->Ativo,
            'source_hash' => hash('sha256', json_encode($linha)),
            'last_synced_at' => $agora,
            'raw_data' => json_encode($linha, JSON_UNESCAPED_UNICODE),
            'created_at' => $agora,
            'updated_at' => $agora,
        ])->all();

        foreach (array_chunk($registros, 200) as $lote) {
            DB::table('organization_areas')->insert($lote);
        }
    }
};
