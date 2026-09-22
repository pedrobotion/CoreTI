<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organization_cost_centers', function (Blueprint $table): void {
            $table->id();
            $table->string('source_system', 50)->default('intranet');
            $table->string('source_type', 30)->index();
            $table->string('source_id', 100);
            $table->string('name')->nullable()->index();
            $table->string('unit_source_id', 100)->nullable()->index();
            $table->string('accounting_area_source_id', 100)->nullable()->index();
            $table->boolean('active')->default(true)->index();
            $table->timestamp('source_updated_at')->nullable();
            $table->string('source_hash', 64)->nullable()->index();
            $table->timestamp('last_synced_at')->nullable()->index();
            $table->json('raw_data')->nullable();
            $table->timestamps();

            $table->unique(['source_system', 'source_type', 'source_id'], 'org_cost_center_source_unique');
        });

        Schema::create('hr_collaborators', function (Blueprint $table): void {
            $table->id();
            $table->string('source_system', 50)->default('intranet');
            $table->string('source_id', 100);
            $table->string('employee_number', 100)->nullable()->index();
            $table->string('name')->index();
            $table->string('email')->nullable()->index();
            $table->string('cost_center_source_id', 100)->nullable()->index();
            $table->string('unit_source_id', 100)->nullable()->index();
            $table->string('area_source_id', 100)->nullable()->index();
            $table->string('department_source_id', 100)->nullable()->index();
            $table->string('sector_source_id', 100)->nullable()->index();
            $table->string('position_source_id', 100)->nullable()->index();
            $table->string('company_source_id', 100)->nullable()->index();
            $table->string('manager_source_id', 100)->nullable()->index();
            $table->boolean('active')->default(true)->index();
            $table->boolean('terminated')->default(false)->index();
            $table->date('termination_date')->nullable()->index();
            $table->boolean('excluded_from_protheus')->default(false)->index();
            $table->timestamp('source_updated_at')->nullable();
            $table->string('source_hash', 64)->nullable()->index();
            $table->timestamp('last_synced_at')->nullable()->index();
            $table->json('raw_data')->nullable();
            $table->timestamps();

            $table->unique(['source_system', 'source_id'], 'hr_collaborator_source_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hr_collaborators');
        Schema::dropIfExists('organization_cost_centers');
    }
};
