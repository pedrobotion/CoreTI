<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Alinha 'equipments' à convenção do CoreTI para vínculos organizacionais.
 *
 * A migration original guardava a unidade como 'organization_unit_source_id'
 * (o id da intranet, em texto). Todas as outras tabelas do projeto —
 * cmdb_assets, cmdb_racks, office_licenses, circuitos_unidades,
 * network_servicos_conectividade — usam 'organization_unit_id' e
 * 'organization_department_id', que são as chaves locais, e é isso que o
 * OrganizationLookupService devolve pronto para um <select>.
 *
 * Também troca o campo livre 'departamento' pelo departamento do catálogo: o
 * texto livre foi o que produziu, no gestaoTI, grafias concorrentes para a
 * mesma unidade ("Cristalina I" x "Cristalina").
 *
 * Feita agora porque a tabela está vazia — nenhum dado é convertido nem
 * perdido. Depois de povoada, isso viraria migração de dados.
 *
 * unit_scopes continua chaveada por source_id de propósito: ela precisa
 * classificar até unidade que a sincronização ainda não trouxe.
 */
return new class extends Migration
{
    public function up(): void
    {
        $existentes = (int) DB::table('equipments')->count();

        if ($existentes > 0) {
            throw new RuntimeException(
                "A tabela equipments tem {$existentes} registro(s). Esta migration só é segura com a tabela vazia — "
                .'converta os dados antes de aplicá-la.'
            );
        }

        Schema::table('equipments', function (Blueprint $table): void {
            $table->unsignedBigInteger('organization_unit_id')->nullable()->after('endereco_ip')->index();
            $table->unsignedBigInteger('organization_department_id')->nullable()->after('organization_unit_id')->index();
        });

        Schema::table('equipments', function (Blueprint $table): void {
            $table->dropIndex('equipments_organization_unit_source_id_index');
            $table->dropColumn(['organization_unit_source_id', 'departamento']);
        });
    }

    public function down(): void
    {
        Schema::table('equipments', function (Blueprint $table): void {
            $table->string('organization_unit_source_id', 100)->nullable()->after('endereco_ip')->index();
            $table->string('departamento', 150)->nullable()->after('organization_unit_source_id');
        });

        Schema::table('equipments', function (Blueprint $table): void {
            $table->dropIndex('equipments_organization_unit_id_index');
            $table->dropIndex('equipments_organization_department_id_index');
            $table->dropColumn(['organization_unit_id', 'organization_department_id']);
        });
    }
};
