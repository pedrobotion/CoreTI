<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('office_licenses', function (Blueprint $table): void {
            $columns = [
                'microsoft_user_id' => fn () => $table->string('microsoft_user_id', 191)->nullable()->index(),
                'microsoft_upn' => fn () => $table->string('microsoft_upn', 255)->nullable()->index(),
                'account_enabled' => fn () => $table->boolean('account_enabled')->nullable()->index(),
                'license_skus' => fn () => $table->json('license_skus')->nullable(),
                'license_source' => fn () => $table->string('license_source', 40)->nullable(),
                'organization_unit_source' => fn () => $table->string('organization_unit_source', 20)->nullable(),
                'organization_department_source' => fn () => $table->string('organization_department_source', 20)->nullable(),
                'organization_cost_center_source' => fn () => $table->string('organization_cost_center_source', 20)->nullable(),
                'organization_position_source' => fn () => $table->string('organization_position_source', 20)->nullable(),
                'organization_assignment_updated_at' => fn () => $table->timestamp('organization_assignment_updated_at')->nullable(),
                'organization_assignment_updated_by' => fn () => $table->unsignedBigInteger('organization_assignment_updated_by')->nullable()->index(),
                'account_classification' => fn () => $table->string('account_classification', 30)->nullable()->index(),
            ];

            foreach ($columns as $name => $definition) {
                if (! Schema::hasColumn('office_licenses', $name)) {
                    $definition();
                }
            }
        });
    }

    public function down(): void
    {
        Schema::table('office_licenses', function (Blueprint $table): void {
            foreach ([
                'microsoft_user_id', 'microsoft_upn', 'account_enabled', 'license_skus', 'license_source',
                'organization_unit_source', 'organization_department_source', 'organization_cost_center_source',
                'organization_position_source', 'organization_assignment_updated_at', 'organization_assignment_updated_by',
                'account_classification',
            ] as $column) {
                if (Schema::hasColumn('office_licenses', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
