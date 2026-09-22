<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->addLinks('office_licenses');
        $this->addLinks('cmdb_assets');
        $this->addLinks('cmdb_racks');
        $this->addLinks('network_servicos_conectividade');
        $this->addLinks('circuitos_unidades');
    }

    public function down(): void
    {
        $this->dropLinks('circuitos_unidades');
        $this->dropLinks('network_servicos_conectividade');
        $this->dropLinks('cmdb_racks');
        $this->dropLinks('cmdb_assets');
        $this->dropLinks('office_licenses');
    }

    private function addLinks(string $table): void
    {
        if (! Schema::hasTable($table)) {
            return;
        }

        Schema::table($table, function (Blueprint $tableBlueprint) use ($table): void {
            if (! Schema::hasColumn($table, 'organization_unit_id')) {
                $tableBlueprint->unsignedBigInteger('organization_unit_id')->nullable()->index();
            }

            if (! Schema::hasColumn($table, 'organization_department_id')) {
                $tableBlueprint->unsignedBigInteger('organization_department_id')->nullable()->index();
            }
        });
    }

    private function dropLinks(string $table): void
    {
        if (! Schema::hasTable($table)) {
            return;
        }

        Schema::table($table, function (Blueprint $tableBlueprint) use ($table): void {
            if (Schema::hasColumn($table, 'organization_department_id')) {
                $tableBlueprint->dropIndex($table . '_organization_department_id_index');
                $tableBlueprint->dropColumn('organization_department_id');
            }

            if (Schema::hasColumn($table, 'organization_unit_id')) {
                $tableBlueprint->dropIndex($table . '_organization_unit_id_index');
                $tableBlueprint->dropColumn('organization_unit_id');
            }
        });
    }
};
