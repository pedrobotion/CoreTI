<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('office_licenses', function (Blueprint $table): void {
            if (! Schema::hasColumn('office_licenses', 'organization_cost_center_id')) {
                $table->unsignedBigInteger('organization_cost_center_id')->nullable()->after('organization_department_id')->index();
            }

            if (! Schema::hasColumn('office_licenses', 'organization_assignment_source')) {
                $table->string('organization_assignment_source', 20)->nullable()->after('organization_cost_center_id')->index();
            }

            if (! Schema::hasColumn('office_licenses', 'organization_assignment_locked_at')) {
                $table->timestamp('organization_assignment_locked_at')->nullable()->after('organization_assignment_source');
            }

            if (! Schema::hasColumn('office_licenses', 'organization_assignment_locked_by')) {
                $table->unsignedBigInteger('organization_assignment_locked_by')->nullable()->after('organization_assignment_locked_at')->index();
            }
        });
    }

    public function down(): void
    {
        Schema::table('office_licenses', function (Blueprint $table): void {
            foreach ([
                'organization_assignment_locked_by',
                'organization_assignment_locked_at',
                'organization_assignment_source',
                'organization_cost_center_id',
            ] as $column) {
                if (Schema::hasColumn('office_licenses', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
